<?php

namespace App\Observers;

use App\Models\SpkSupplierParameterValue;
use App\Models\SpkRanking;

class SpkSupplierParameterValueObserver
{
    public function saved(SpkSupplierParameterValue $value)
    {
        SpkRanking::where('produk_id', $value->produk_id)->update(['is_valid' => false]);
    }

    public function deleted(SpkSupplierParameterValue $value)
    {
        SpkRanking::where('produk_id', $value->produk_id)->update(['is_valid' => false]);
    }
}
