<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterProduk extends Model
{
    protected $fillable = ['nama', 'deskripsi'];

    public function suppliers()
    {
        return $this->belongsToMany(MasterSupplier::class, 'inventory_supplier_produk', 'produk_id', 'supplier_id');
    }

    public function spkSupplierParameterValues()
    {
        return $this->hasMany(SpkSupplierParameterValue::class, 'produk_id');
    }

    public function spkRankings()
    {
        return $this->hasMany(SpkRanking::class, 'produk_id');
    }
}
