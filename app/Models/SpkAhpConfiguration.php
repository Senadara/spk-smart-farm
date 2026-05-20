<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpkAhpConfiguration extends Model
{
    protected $fillable = [
        'user_id', 'cr', 'is_valid', 'version', 'weights_snapshot',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'weights_snapshot' => 'array',
    ];
}
