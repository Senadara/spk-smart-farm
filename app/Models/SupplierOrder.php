<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierOrder extends Model
{
    protected $table = 'pesanan';

    protected $keyType = 'string';

    public $incrementing = false;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'userId',
        'tokoId',
        'status',
        'totalHarga',
        'isDeleted',
        'MidtransOrderId',
        'buktiDiterimaId',
    ];

    protected function casts(): array
    {
        return [
            'totalHarga' => 'integer',
            'isDeleted' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(SupplierStore::class, 'tokoId');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function details(): HasMany
    {
        return $this->hasMany(SupplierOrderDetail::class, 'pesananId');
    }
}
