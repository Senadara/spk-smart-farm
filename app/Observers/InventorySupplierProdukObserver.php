<?php

namespace App\Observers;

use App\Models\InventorySupplierProduk;
use App\Models\SpkRanking;

class InventorySupplierProdukObserver
{
    public function saved(InventorySupplierProduk $pivot)
    {
        SpkRanking::where('produk_id', $pivot->produk_id)->update(['is_valid' => false]);
    }

    public function deleted(InventorySupplierProduk $pivot)
    {
        SpkRanking::where('produk_id', $pivot->produk_id)->update(['is_valid' => false]);
    }
}
