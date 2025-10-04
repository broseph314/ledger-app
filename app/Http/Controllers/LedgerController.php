<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
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
}
