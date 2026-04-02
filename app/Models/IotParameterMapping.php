<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotParameterMapping extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'iot_parameter_mapping';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'deviceId',
        'parameterId',
        'payloadKey',
    ];

    public function device()
    {
        return $this->belongsTo(IotDevice::class, 'deviceId');
    }

    public function parameter()
    {
        return $this->belongsTo(IotParameter::class, 'parameterId');
    }
}
