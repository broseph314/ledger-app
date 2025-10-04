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
        $asAt   = $request->filled('as_at') ? Carbon::parse($request->query('as_at'))->endOfDay() : now();
        $until  = $request->filled('until') ? Carbon::parse($request->query('until'))->endOfDay() : $asAt->copy()->addDays(90);
        $withSchedule = $request->boolean('include_schedule', true);

        // Load business → entities → ledgers, plus base balance sums and active recurrings
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

                    // Load recurrings; keep only those that could affect the window
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

        $payload['data'] = $businesses->map(function ($business) use ($asAt, $until, $withSchedule) {
            return [
                'business_id' => $business->id,
                'business'    => $business->name,
                'entities'    => $business->entities->map(function ($entity) use ($asAt, $until, $withSchedule) {
                    return [
                        'entity_id' => $entity->id,
                        'entity'    => $entity->name,
                        'ledgers'   => $entity->ledgers->map(function ($ledger) use ($asAt, $until, $withSchedule) {
                            // Base balance = starting_balance + (credits - debits) up to as_at
                            $debits   = (float) ($ledger->debit_sum ?? 0);
                            $credits  = (float) ($ledger->credit_sum ?? 0);
                            $opening  = (float) $ledger->starting_balance + ($credits - $debits);

                            // Project recurring events within window
                            $events = [];
                            $projected = $opening;

                            foreach ($ledger->recurrings as $rec) {
                                // Determine sign from type (handles if stored amount is already signed too)
                                $amt = (float) $rec->amount;
                                $signedAmount = strtolower($rec->type) === 'debit' ? -abs($amt) : abs($amt);

                                // Find the first event >= as_at
                                $firstCandidate = $rec->next_occurrence
                                    ? Carbon::parse($rec->next_occurrence)
                                    : ($rec->last_payment_date
                                        ? $this->nextFromFrequency(Carbon::parse($rec->last_payment_date), $rec->frequency)
                                        : $asAt->copy());

                                $cursor = $this->advanceToOrEqual($firstCandidate, $rec->frequency, $asAt);

                                // Iterate occurrences until horizon end or end_date
                                $endDate = $rec->end_date ? Carbon::parse($rec->end_date)->endOfDay() : null;
                                while ($cursor->lte($until) && (is_null($endDate) || $cursor->lte($endDate))) {
                                    $projected += $signedAmount;

                                    if ($withSchedule) {
                                        $events[] = [
                                            'recurring_id' => $rec->id,
                                            'date'         => $cursor->toDateString(),
                                            'description'  => $rec->description,
                                            'type'         => strtolower($rec->type),
                                            'amount'       => $signedAmount,
                                        ];
                                    }

                                    $cursor = $this->nextFromFrequency($cursor, $rec->frequency);
                                }
                            }

                            return [
                                'ledger_id'                => $ledger->id,
                                'ledger'                   => $ledger->name,
                                'opening_balance_at_as_at' => $opening,
                                'projected_balance_at_until' => $projected,
                                'projected_change'         => $projected - $opening,
                                'events'                   => $withSchedule ? $events : null,
                            ];
                        })->values(),
                    ];
                })->values(),
            ];
        })->values();

        return response()->json($payload);
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
