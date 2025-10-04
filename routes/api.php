<?php

use Illuminate\Support\Facades\Route;

Route::get('/transactions', function () {
    return response()->json(['message' => 'Transactions endpoint']);
})->name('api.transactions');

Route::get('/forecast', function () {
    return response()->json(['message' => 'Forecast endpoint']);
})->name('api.forecast');

Route::get('/balance', function () {
    return response()->json(['message' => 'Balance endpoint']);
})->name('api.balance');
