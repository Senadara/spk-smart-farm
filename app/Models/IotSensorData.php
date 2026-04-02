<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotSensorData extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'iot_sensor_data';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'deviceId',
        'parameterId',
        'value',
        'sensorTimestamp',
        'isDeleted',
    ];

    protected $casts = [
        'sensorTimestamp' => 'datetime',
        'isDeleted' => 'boolean',
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
