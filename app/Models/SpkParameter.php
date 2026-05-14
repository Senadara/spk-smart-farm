<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpkParameter extends Model
{
    protected $fillable = ['nama_parameter', 'tipe'];

    public function supplierParameterValues()
    {
        return $this->hasMany(SpkSupplierParameterValue::class, 'parameter_id');
    }

    public function ahpPerbandingan1()
    {
        return $this->hasMany(SpkAhpPerbandingan::class, 'parameter_1_id');
    }
    
    public function ahpPerbandingan2()
    {
        return $this->hasMany(SpkAhpPerbandingan::class, 'parameter_2_id');
    }

    public function ahpBobots()
    {
        return $this->hasMany(SpkAhpBobot::class, 'parameter_id');
    }
}
