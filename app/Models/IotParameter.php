<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotParameter extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'iot_parameter';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'parameterCode',
        'parameterName',
        'unit',
        'description',
    ];

    public function commodityParameters()
    {
        return $this->hasMany(CommodityParameter::class, 'parameterId');
    }

    public function parameterMappings()
    {
        return $this->hasMany(IotParameterMapping::class, 'parameterId');
    }
}
