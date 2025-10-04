<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ledger extends Model
{
    protected $fillable = [
        'entity_id',
        'date',
        'description',
        'amount',
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

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }
}
