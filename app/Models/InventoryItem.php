<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryItem extends Model
{
    protected $fillable = [
        'sku',
        'mobile_inventaris_id',
        'name',
        'category',
        'stock',
        'unit',
        'daily_usage',
        'minimum_stock',
        'reorder_point',
        'lead_time_days',
        'safety_stock_days',
        'reorder_point_override',
        'supplier_id',
        'unit_budidaya_id',
        'photo_path',
        'notes',
        'last_restock_at',
        'synced_from_mobile_at',
        'is_active',
    ];

    protected $casts = [
        'stock' => 'float',
        'daily_usage' => 'float',
        'minimum_stock' => 'float',
        'reorder_point' => 'float',
        'lead_time_days' => 'integer',
        'safety_stock_days' => 'integer',
        'reorder_point_override' => 'float',
        'last_restock_at' => 'datetime',
        'synced_from_mobile_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(MasterSupplier::class, 'supplier_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id');
    }

    public function supplierProductLinks(): HasMany
    {
        return $this->hasMany(InventorySupplierProductLink::class, 'inventory_item_id');
    }

    public function preferredSupplierProductLink(): HasOne
    {
        return $this->hasOne(InventorySupplierProductLink::class, 'inventory_item_id')
            ->where('is_preferred', true);
    }
}
