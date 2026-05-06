<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpkAhpBobot extends Model
{
    protected $fillable = ['user_id', 'parameter_id', 'bobot', 'is_valid'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parameter()
    {
        return $this->belongsTo(SpkParameter::class, 'parameter_id');
    }
}
