<?php

use App\Http\Controllers\LedgerController;
use App\Http\Controllers\TransactionController;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

Route::get('/transactions', [TransactionController::class, 'index'])->name('api.transactions');

Route::get('/forecast', function () {
    return response()->json(['message' => 'Forecast endpoint']);
})->name('api.forecast');

Route::get('/balance', [LedgerController::class, 'index'])->name('api.balance');

Route::post('/income', [TransactionController::class, 'storeIncome'])->name('api.income');

Route::post('/expense', [TransactionController::class, 'storeExpense'])->name('api.expense');
