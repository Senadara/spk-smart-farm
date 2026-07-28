<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProductStockMovement extends Model
{
    protected $table = 'supplier_product_stock_movements';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'supplier_product_id',
        'supplier_store_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'actor_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class, 'supplier_product_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(SupplierStore::class, 'supplier_store_id');
    }
}
