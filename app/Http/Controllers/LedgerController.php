<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\Transaction;

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

                            // Monthly buckets YYYY-MM => recurring-only net change
                            $monthlyRecurring = $this->initMonthlyBuckets($asAt, $until);

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
                                    if (array_key_exists($key, $monthlyRecurring)) {
                                        $monthlyRecurring[$key] += $signedAmount;
                                    }
                                    $cursor = $this->nextFromFrequency($cursor, $rec->frequency);
                                }
                            }
                            $historical = $this->historicalMonthlyProjection($ledger, $asAt, $until, 12); // 12-month lookback
                            $monthlyCombined = $monthlyRecurring;
                            foreach ($monthlyCombined as $ym => $val) {
                                $monthlyCombined[$ym] = round(($monthlyRecurring[$ym] ?? 0) + ($historical[$ym] ?? 0), 2);
                            }

                            $projectedChange = array_sum($monthlyCombined);
                            $projectedEnd    = $opening + $projectedChange;

                            // Shape monthly array (ascending by month)
                            $monthly = collect($monthlyCombined)->map(function ($delta, $ym) use ($monthlyRecurring, $historical) {
                                return [
                                    'month'            => $ym,
                                    'recurring_total'  => round($monthlyRecurring[$ym] ?? 0, 2),
                                    'historical_total' => round($historical[$ym] ?? 0, 2),
                                    'projected_change' => round($delta, 2),
                                ];
                            })->values()->all();

                            return [
                                'ledger_id'                    => $ledger->id,
                                'ledger'                       => $ledger->name,
                                'opening_balance_at_as_at'     => round($opening, 2),
                                'projected_balance_at_until'   => round($projectedEnd, 2),
                                'projected_change'             => round($projectedChange, 2),
                                'monthly'                      => $monthly,
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

    /**
     * Forecast non-recurring net change per month in [asAt..until] using a seasonal
     * month-of-year average built from the last $lookbackMonths before $asAt.
     *
     * Returns: ['YYYY-MM' => float delta, ...] for the forecast window.
     */
    private function historicalMonthlyProjection(
        Ledger $ledger,
        Carbon $asAt,
        Carbon $until,
        int $lookbackMonths = 12
    ): array {
        // Build keys for the forecast window
        $forecastKeys = array_keys($this->initMonthlyBuckets($asAt, $until));

        // History window (e.g., 12 full months ending at asAt)
        $histStart = $asAt->copy()->startOfMonth()->subMonthsNoOverflow($lookbackMonths)->startOfMonth();
        $histEnd   = $asAt->copy()->endOfDay();

        // Initialize all history months to 0 so averages use consistent denominators
        $histMonths = [];
        $cursor = $histStart->copy();
        while ($cursor->lte($histEnd)) {
            $histMonths[$cursor->format('Y-m')] = 0.0;
            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        // 1) Sum ALL historical transactions
        $txns = Transaction::query()
            ->where('ledger_id', $ledger->id)
            ->whereBetween('occurred_at', [$histStart, $histEnd])
            ->get(['occurred_at', 'amount']);

        foreach ($txns as $t) {
            $ym = Carbon::parse($t->occurred_at)->format('Y-m');
            if (isset($histMonths[$ym])) {
                $histMonths[$ym] += (float) $t->amount;
            }
        }

        // 2) Subtract recurring occurrences within the same history window
        $recHist = $this->recurringMonthlyTotals($ledger->recurrings ?? collect(), $histStart, $histEnd);
        foreach ($histMonths as $ym => $total) {
            $histMonths[$ym] = $total - ($recHist[$ym] ?? 0.0);
        }

        // 3) Build month-of-year seasonal averages for non-recurring component
        $moTotals = array_fill(1, 12, 0.0);
        $moCounts = array_fill(1, 12, 0);
        foreach ($histMonths as $ym => $val) {
            [$y, $m] = explode('-', $ym);
            $mo = (int) $m;
            $moTotals[$mo] += $val;
            $moCounts[$mo] += 1;
        }

        $overallAvg = 0.0;
        if (count($histMonths) > 0) {
            $overallAvg = array_sum($histMonths) / count($histMonths);
        }

        $moAvg = [];
        for ($m = 1; $m <= 12; $m++) {
            $moAvg[$m] = $moCounts[$m] > 0 ? ($moTotals[$m] / $moCounts[$m]) : $overallAvg;
        }

        // 4) Project forward: pick the avg for that calendar month-of-year
        $out = [];
        foreach ($forecastKeys as $ym) {
            [$y, $m] = explode('-', $ym);
            $mo = (int) $m;
            $out[$ym] = $moAvg[$mo] ?? $overallAvg; // may be positive or negative
        }

        return $out;
    }

    /**
     * Aggregate recurring amounts per month between [$start..$end].
     * Returns ['YYYY-MM' => float].
     */
    private function recurringMonthlyTotals($recurrings, Carbon $start, Carbon $end): array
    {
        $totals = [];

        foreach ($recurrings as $rec) {
            $amt = (float) $rec->amount;
            $signed = strtolower($rec->type) === 'debit' ? -abs($amt) : abs($amt);

            // Pick an anchor to start stepping from
            if ($rec->last_payment_date) {
                $anchor = $this->nextFromFrequency(Carbon::parse($rec->last_payment_date), $rec->frequency);
            } elseif ($rec->next_occurrence) {
                $anchor = Carbon::parse($rec->next_occurrence);
            } else {
                continue; // no basis to generate occurrences
            }

            // Move to >= $start
            $cursor = $this->advanceToOrEqual($anchor, $rec->frequency, $start);
            $endDate = $rec->end_date ? Carbon::parse($rec->end_date)->endOfDay() : null;

            $guard = 0;
            while ($cursor->lte($end) && (is_null($endDate) || $cursor->lte($endDate))) {
                $ym = $cursor->format('Y-m');
                $totals[$ym] = ($totals[$ym] ?? 0.0) + $signed;

                $cursor = $this->nextFromFrequency($cursor, $rec->frequency);
                if (++$guard > 1000) break; // safety
            }
        }

        // Ensure all months in [start..end] exist (for clean subtraction)
        $cur = $start->copy()->startOfMonth();
        $endKey = $end->copy()->startOfMonth();
        while ($cur->lte($endKey)) {
            $totals[$cur->format('Y-m')] = $totals[$cur->format('Y-m')] ?? 0.0;
            $cur->addMonthNoOverflow()->startOfMonth();
        }

        return $totals;
    }
}
