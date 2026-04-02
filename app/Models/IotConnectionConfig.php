<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotConnectionConfig extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'iot_connection_config';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'protocolId',
        'baseUrl',
        'endpointPath',
        'mqttBrokerUrl',
        'mqttTopic',
        'authType',
        'authKey',
        'headers',
    ];

    protected $casts = [
        'headers' => 'array',
    ];

    public function protocol()
    {
        return $this->belongsTo(IotProtocol::class, 'protocolId');
    }

    public function devices()
    {
        return $this->hasMany(IotDevice::class, 'connectionConfigId');
    }
}
