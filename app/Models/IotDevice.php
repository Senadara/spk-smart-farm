<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotDevice extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'iot_device';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'unitBudidayaId',
        'connectionConfigId',
        'deviceCode',
        'deviceName',
        'pollingInterval',
        'status',
        'installedAt',
    ];

    protected $casts = [
        'installedAt' => 'datetime',
    ];

    public function unitBudidaya()
    {
        // Hubungkan ke UnitBudidaya. Karena model mungkin belum dibikin di spk-smart-farm
        // Maka kita asumsikan namanya UnitBudidaya
        return $this->belongsTo(UnitBudidaya::class, 'unitBudidayaId');
    }

    public function connectionConfig()
    {
        return $this->belongsTo(IotConnectionConfig::class, 'connectionConfigId');
    }

    public function parameterMappings()
    {
        return $this->hasMany(IotParameterMapping::class, 'deviceId');
    }

    public function sensorData()
    {
        return $this->hasMany(IotSensorData::class, 'deviceId');
    }

    public function logs()
    {
        return $this->hasMany(IotDeviceLog::class, 'deviceId');
    }
}
