<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOrderDetail extends Model
{
    protected $table = 'pesananDetail';

    protected $keyType = 'string';

    public $incrementing = false;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'pesananId',
        'produkId',
        'jumlah',
        'isDeleted',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'isDeleted' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class, 'pesananId');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class, 'produkId');
    }
}
