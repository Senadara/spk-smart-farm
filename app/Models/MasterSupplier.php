<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterSupplier extends Model
{
    protected $fillable = ['nama', 'alamat', 'kontak'];

    public function produks()
    {
        return $this->belongsToMany(MasterProduk::class, 'inventory_supplier_produk', 'supplier_id', 'produk_id');
    }

    public function spkSupplierParameterValues()
    {
        return $this->hasMany(SpkSupplierParameterValue::class, 'supplier_id');
    }

    public function spkRankings()
    {
        return $this->hasMany(SpkRanking::class, 'supplier_id');
    }
}
