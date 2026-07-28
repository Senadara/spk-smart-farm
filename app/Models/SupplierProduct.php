<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierProduct extends Model
{
    protected $table = 'produk';

    protected $keyType = 'string';

    public $incrementing = false;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'tokoId',
        'nama',
        'deskripsi',
        'kategori',
        'gambar',
        'stok',
        'minimum_stock',
        'restock_quantity',
        'satuan',
        'harga',
        'isDeleted',
    ];

    protected function casts(): array
    {
        return [
            'stok' => 'integer',
            'minimum_stock' => 'integer',
            'restock_quantity' => 'integer',
            'harga' => 'integer',
            'isDeleted' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(SupplierStore::class, 'tokoId');
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(SupplierOrderDetail::class, 'produkId');
    }

    public function inventoryLinks(): HasMany
    {
        return $this->hasMany(InventorySupplierProductLink::class, 'supplier_product_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(SupplierProductStockMovement::class, 'supplier_product_id');
    }
}
