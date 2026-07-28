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
        'notificationEmail',
        'alamat',
        'latitude',
        'longitude',
        'logoToko',
        'deskripsi',
        'kategori',
        'isDeleted',
        'tokoStatus',
        'approvalReason',
        'registrationNotifiedAt',
        'approvalNotifiedAt',
        'TypeToko',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'isDeleted' => 'boolean',
            'registrationNotifiedAt' => 'datetime',
            'approvalNotifiedAt' => 'datetime',
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
