<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $fillable = [
        'name',
        'type',
    ];

    public function entities()
    {
        return $this->hasMany(Entity::class);
    }
}
