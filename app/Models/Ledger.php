<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ledger extends Model
{
    protected $fillable = [
        'entity_id',
        'type',
        'current_balance',
        'starting_balance',
        'current_as_of',
        'last_processed',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
    public function recurrings()
    {
        return $this->hasMany(\App\Models\Recurring::class);
    }
    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
