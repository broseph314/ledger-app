<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entity extends Model
{
    protected $fillable = [
        'name',
        'type',
        'location',
        'business_id',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function ledgers()
    {
        return $this->hasMany(Ledger::class);
    }
}
