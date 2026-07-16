<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'inventory_item_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'unit',
        'unit_budidaya_id',
        'user_id',
        'note',
        'source_table',
        'source_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'quantity' => 'float',
        'stock_before' => 'float',
        'stock_after' => 'float',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
