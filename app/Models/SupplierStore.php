<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierStore extends Model
{
    protected $table = 'toko';

    protected $keyType = 'string';

    public $incrementing = false;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'userId',
        'nama',
        'phone',
        'alamat',
        'latitude',
        'longitude',
        'logoToko',
        'deskripsi',
        'isDeleted',
        'tokoStatus',
        'TypeToko',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'isDeleted' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class, 'tokoId');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SupplierOrder::class, 'tokoId');
    }
}
