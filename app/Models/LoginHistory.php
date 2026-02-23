<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'email',
        'name',
        'role',
        'ip_address',
        'user_agent',
        'login_at',
    ];

    protected $casts = [
        'login_at' => 'datetime',
    ];
}
