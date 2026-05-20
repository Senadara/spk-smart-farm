<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpkSupplierSelectionLog extends Model
{
    protected $fillable = [
        'user_id', 'supplier_id', 'produk_id', 'final_score', 'ranking',
    ];

    public function supplier()
    {
        return $this->belongsTo(MasterSupplier::class, 'supplier_id');
    }

    public function produk()
    {
        return $this->belongsTo(MasterProduk::class, 'produk_id');
    }
}
