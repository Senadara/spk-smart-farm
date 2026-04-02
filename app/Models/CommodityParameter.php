<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommodityParameter extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'commodity_parameter';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'commodityId',
        'parameterId',
        'minValue',
        'maxValue',
    ];

    public function commodity()
    {
        // Asumsi relasi ke Komoditas (komoditas.id)
        return $this->belongsTo(Komoditas::class, 'commodityId');
    }

    public function parameter()
    {
        return $this->belongsTo(IotParameter::class, 'parameterId');
    }
}
