<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOrderRating extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'order_id',
        'user_id',
        'store_id',
        'supplier_id',
        'product_id',
        'master_produk_id',
        'rating',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(SupplierStore::class, 'store_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(MasterSupplier::class, 'supplier_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class, 'product_id');
    }

    public function masterProduk(): BelongsTo
    {
        return $this->belongsTo(MasterProduk::class, 'master_produk_id');
    }
}
