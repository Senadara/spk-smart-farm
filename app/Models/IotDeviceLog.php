<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotDeviceLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'iot_device_log';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'deviceId',
        'logType',
        'message',
    ];

    public function device()
    {
        return $this->belongsTo(IotDevice::class, 'deviceId');
    }
}
