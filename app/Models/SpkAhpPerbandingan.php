<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpkAhpPerbandingan extends Model
{
    protected $fillable = ['user_id', 'parameter_1_id', 'parameter_2_id', 'nilai_skala'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parameter1()
    {
        return $this->belongsTo(SpkParameter::class, 'parameter_1_id');
    }

    public function parameter2()
    {
        return $this->belongsTo(SpkParameter::class, 'parameter_2_id');
    }
}
