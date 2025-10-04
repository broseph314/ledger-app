<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\StoreIncomeRequest;
use App\Models\Recurring;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class TransactionController extends Controller
{
    public function storeExpense(StoreExpenseRequest $request)
    {
        try {
            $v = $request->validated();

            if(isset($v['frequency'])) {
                $this->createRecurringExpense($v);
            }

            $transaction = Transaction::create([
                'ledger_id' => $v['ledger_id'],
                'amount' => -1 * abs($v['amount']),
                'occurred_at' => Carbon::parse($v['date'] ?? now()),
                'description' => $v['description'] ?? 'Expense',
                'type' => 'debit',
                'occurred_on' => now(),
            ]);


            return response()->json([
                'message' => 'Expense recorded.',
                'transaction' => $transaction,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to record expense.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function storeIncome(StoreIncomeRequest $request)
    {
        try {
            $v = $request->validated();

//            return Carbon::parse($v['date']);

            if(isset($v['from_ledger_id'])) {
                $expense = Transaction::create([
                    'ledger_id' => $v['from_ledger_id'],
                    'amount' => abs($v['amount']),
                    'occurred_at' => Carbon::parse($v['date'] ?? now()),
                    'description' => 'Internal transfer from ' . ($v['description'] ?? 'another ledger'),
                    'type' => 'debit',
                ]);
            }

            if(isset($v['frequency'])) {
                $this->createRecurringIncome($v);
            }

            $transaction = Transaction::create([
                'ledger_id' => $v['ledger_id'],
                'amount' => abs($v['amount']),
                'occurred_at' => Carbon::parse($v['date'] ?? now()),
                'description' => $v['description'] ?? 'Income',
                'type' => 'credit',
            ]);

            $data = [
                'transaction' => $transaction,
            ];
            if(isset($expense)) {
                $data['linked_expense'] = $expense;
            }

            return response()->json([
                'message' => 'Income recorded.',
                'data' => $data,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to record income.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    private function createRecurringExpense(array $v): Recurring
    {
        $firstAt = Carbon::parse($v['date'] ?? now());
        $nextAt  = $this->nextFromFrequency($firstAt, $v['frequency']);

        return Recurring::create([
            'ledger_id'         => $v['ledger_id'],
            'description'       => $v['description'] ?? 'Expense',
            'amount'            => -1 * abs($v['amount']),
            'type'              => 'debit',
            'frequency'         => $v['frequency'],
            'end_date'          => $v['end_date'] ?? null,
            'start_date'        => $firstAt,
            'last_payment_date' => $firstAt,
            'next_payment_date' => $nextAt,
        ]);
    }

    private function createRecurringIncome(array $v): Recurring
    {
        $firstAt = Carbon::parse($v['date'] ?? now());
        $nextAt  = $this->nextFromFrequency($firstAt, $v['frequency']);

        return Recurring::create([
            'ledger_id'         => $v['ledger_id'],
            'description'       => $v['description'] ?? 'Income',
            'amount'            => abs($v['amount']),
            'type'              => 'credit',
            'frequency'         => $v['frequency'],
            'start_date'        => $firstAt,
            'end_date'          => $v['end_date'] ?? null,
            'last_payment_date' => $firstAt,
            'next_payment_date' => $nextAt,
        ]);
    }

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


}
