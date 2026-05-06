<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpkSupplierParameterValue extends Model
{
    protected $fillable = ['supplier_id', 'produk_id', 'parameter_id', 'value'];

    public function supplier()
    {
        return $this->belongsTo(MasterSupplier::class, 'supplier_id');
    }

    public function produk()
    {
        return $this->belongsTo(MasterProduk::class, 'produk_id');
    }

    public function parameter()
    {
        return $this->belongsTo(SpkParameter::class, 'parameter_id');
    }
}
