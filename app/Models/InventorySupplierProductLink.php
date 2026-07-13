<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySupplierProductLink extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'supplier_product_id',
        'conversion_qty',
        'conversion_unit',
        'is_preferred',
        'notes',
    ];

    protected $casts = [
        'conversion_qty' => 'float',
        'is_preferred' => 'boolean',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class, 'supplier_product_id');
    }
}
