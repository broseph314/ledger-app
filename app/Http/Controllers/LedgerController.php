<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LedgerController extends Controller
{

    public function forecast(Request $request): JsonResponse
    {
        $request->validate([
            'lookahead_months' => ['nullable', 'integer', 'min:1', 'max:36'],
            'as_at'            => ['nullable', 'date'],
            'include_monthly'  => ['nullable', 'boolean'],
        ]);

        $asAt   = $request->filled('as_at') ? Carbon::parse($request->query('as_at'))->endOfDay() : now();
        $lookahead = $request->has('lookahead_months')
            ? max(1, min(36, (int)$request->query('lookahead_months')))
            : 3;

        $until = $asAt->copy()->startOfMonth()
            ->addMonthsNoOverflow($lookahead - 1)
            ->endOfMonth();

        $includeMonthly = $request->boolean('include_monthly', true);

        // Load business → entities → ledgers, base balance up to as_at, and recurrings
        $businesses = Business::query()
            ->with([
                'entities.ledgers' => function ($q) use ($asAt) {
                    // Base balance components up to as_at
                    $q->withSum(['transactions as debit_sum' => function ($t) use ($asAt) {
                        $t->where('type', 'debit')->where('occurred_at', '<=', $asAt);
                    }], 'amount');

                    $q->withSum(['transactions as credit_sum' => function ($t) use ($asAt) {
                        $t->where('type', 'credit')->where('occurred_at', '<=', $asAt);
                    }], 'amount');

                    // Recurrings that could still affect the window
                    $q->with(['recurrings' => function ($r) use ($asAt) {
                        $r->where(function ($w) use ($asAt) {
                            $w->whereNull('end_date')->orWhere('end_date', '>=', $asAt);
                        });
                    }]);
                },
                'entities.ledgers.entity',
                'entities.ledgers.entity.business',
            ])
            ->get();

        $payload = [
            'as_at' => $asAt->toIso8601String(),
            'until' => $until->toIso8601String(),
            'data'  => [],
        ];

        $payload['data'] = $businesses->map(function ($business) use ($asAt, $until, $includeMonthly) {
            return [
                'business_id' => $business->id,
                'business'    => $business->name,
                'entities'    => $business->entities->map(function ($entity) use ($asAt, $until, $includeMonthly) {
                    return [
                        'entity_id' => $entity->id,
                        'entity'    => $entity->name,
                        'ledgers'   => $entity->ledgers->map(function ($ledger) use ($asAt, $until, $includeMonthly) {
                            // Opening = starting_balance + (credits - debits) up to as_at
                            $debits   = (float) ($ledger->debit_sum ?? 0);
                            $credits  = (float) ($ledger->credit_sum ?? 0);
                            $opening  = (float) $ledger->starting_balance + ($credits - $debits);

                            // Monthly buckets YYYY-MM => net change (recurrings)
                            $monthlyBuckets = $this->initMonthlyBuckets($asAt, $until);

                            // Project recurring occurrences into buckets
                            foreach ($ledger->recurrings as $rec) {
                                $amt = (float) $rec->amount;
                                $signedAmount = strtolower($rec->type) === 'debit' ? -abs($amt) : abs($amt);

                                $firstCandidate = $rec->next_occurrence
                                    ? Carbon::parse($rec->next_occurrence)
                                    : ($rec->last_payment_date
                                        ? $this->nextFromFrequency(Carbon::parse($rec->last_payment_date), $rec->frequency)
                                        : $asAt->copy());

                                $cursor = $this->advanceToOrEqual($firstCandidate, $rec->frequency, $asAt);

                                $endDate = $rec->end_date ? Carbon::parse($rec->end_date)->endOfDay() : null;
                                while ($cursor->lte($until) && (is_null($endDate) || $cursor->lte($endDate))) {
                                    $key = $cursor->format('Y-m');
                                    if (array_key_exists($key, $monthlyBuckets)) {
                                        $monthlyBuckets[$key] += $signedAmount;
                                    }
                                    $cursor = $this->nextFromFrequency($cursor, $rec->frequency);
                                }
                            }

                            // Totals
                            $projectedChange = array_sum($monthlyBuckets);
                            $projectedEnd    = $opening + $projectedChange;

                            // Shape monthly array (ascending by month)
                            $monthly = null;
                            if ($includeMonthly) {
                                $monthly = collect($monthlyBuckets)
                                    ->map(fn ($delta, $ym) => [
                                        'month'            => $ym,                 // '2025-02'
                                        'projected_change' => round($delta, 2),   // total delta for that month
                                        'recurring_total'  => round($delta, 2),   // equals projected_change for now
                                    ])
                                    ->values()
                                    ->all();
                            }

                            return [
                                'ledger_id'                    => $ledger->id,
                                'ledger'                       => $ledger->name,
                                'opening_balance_at_as_at'     => round($opening, 2),
                                'projected_balance_at_until'   => round($projectedEnd, 2),
                                'projected_change'             => round($projectedChange, 2),
                                'monthly'                      => $monthly, // replaces verbose 'events'
                            ];
                        })->values(),
                    ];
                })->values(),
            ];
        })->values();

        return response()->json($payload);
    }

    /** Initialize YYYY-MM => 0.0 buckets between asAt and until (inclusive). */
    private function initMonthlyBuckets(Carbon $asAt, Carbon $until): array
    {
        $buckets = [];
        $cursor = $asAt->copy()->startOfMonth();
        $end    = $until->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $buckets[$cursor->format('Y-m')] = 0.0;
            $cursor = $cursor->addMonthNoOverflow()->startOfMonth();
        }
        return $buckets;
    }

    public function index(): JsonResponse
    {
        // starting off I'll just get all businesses, their entities, and their ledgers
        // and calculate the current balance for each ledger
        $businesses = Business::query()
            ->with([
                'entities.ledgers' => function ($q) {
                    $q->withSum('transactions as transactions_sum', 'amount');
                },
            ])
            ->get();

        $data = $businesses->map(function ($business) {
            return [
                'business_id' => $business->id,
                'business'    => $business->name,
                'entities'    => $business->entities->map(function ($entity) {
                    return [
                        'entity_id' => $entity->id,
                        'entity'    => $entity->name,
                        'ledgers'   => $entity->ledgers->map(function ($ledger) {
                            // current_balance = starting_balance + sum(transactions.amount)
                            $transactionsTotal = (float) ($ledger->transactions_sum ?? 0);
                            $starting          = (float) $ledger->starting_balance;

                            return [
                                'ledger_id'       => $ledger->id,
                                'ledger'          => $ledger->name,
                                'current_balance' => $starting + $transactionsTotal,
                            ];
                        })->values(),
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'as_of' => now()->toIso8601String(),
            'data'  => $data,
        ]);
    }

    /** Advance date by frequency (daily, weekly, fortnightly, monthly, quarterly, yearly). */
    private function nextFromFrequency(Carbon $from, string $frequency): Carbon
    {
        return match (strtolower($frequency)) {
            'daily'       => $from->copy()->addDay(),
            'weekly'      => $from->copy()->addWeek(),
            'fortnightly' => $from->copy()->addWeeks(2),
            'monthly'     => $from->copy()->addMonthNoOverflow(),
            'quarterly'   => $from->copy()->addMonthsNoOverflow(3),
            'yearly', 'annually' => $from->copy()->addYearNoOverflow(),
            default       => $from->copy()->addMonthNoOverflow(),
        };
    }

    /** Move the date forward in its recurrence until it is >= target. */
    private function advanceToOrEqual(Carbon $start, string $frequency, Carbon $target): Carbon
    {
        $cursor = $start->copy();
        // Safety bound to avoid infinite loops
        for ($i = 0; $i < 1000 && $cursor->lt($target); $i++) {
            $cursor = $this->nextFromFrequency($cursor, $frequency);
        }
        return $cursor;
    }

     private function historicalProjectionDelta(Ledger $ledger, Carbon $from, Carbon $until): float
     {
         // TODO: Build a model from past transactions (seasonality, moving averages, etc.)
         // Return a delta to add to $projected.
     }

}
