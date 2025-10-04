<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    public $fillable = [
        'ledger_id',
        'date',
        'description',
        'amount',
        'type',
        'from_ledger_id',
        'recurring_id',
        'occurred_on',
    ];
    public function ledger()
    {
        return $this->belongsTo(Ledger::class);
    }

}
