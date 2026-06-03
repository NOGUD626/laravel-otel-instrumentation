<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Race extends Model
{
    protected $fillable = ['name', 'prefecture', 'held_on', 'distance_m'];

    protected $casts = [
        'held_on' => 'date',
    ];
}
