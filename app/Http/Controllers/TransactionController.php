<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class TransactionController extends Controller
{
    public function storeExpense(StoreExpenseRequest $request)
    {
        try {
            $v = $request->validated();

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


}
