<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recurring extends Model
{
    public $fillable = [
        'ledger_id',
        'description',
        'amount',
        'type',
        'frequency',
        'next_occurrence',
        'end_date',
        'last_processed',
        'last_payment_date',
        'next_payment_date',
    ];
}
