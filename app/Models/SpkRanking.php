<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpkRanking extends Model
{
    protected $fillable = [
        'user_id', 'produk_id', 'supplier_id', 
        'final_score', 'ranking', 'is_valid', 'last_calculated_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function produk()
    {
        return $this->belongsTo(MasterProduk::class, 'produk_id');
    }

    public function supplier()
    {
        return $this->belongsTo(MasterSupplier::class, 'supplier_id');
    }
}
