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
        'mqttPort',
        'mqttTopic',
        'mqttClientId',
        'mqttUsername',
        'mqttPassword',
        'mqttUseTls',
        'mqttQos',
        'mqttKeepAlive',
        'authType',
        'authKey',
        'headers',
    ];

    protected $hidden = [
        'authKey',
        'mqttPassword',
    ];

    protected $casts = [
        'headers' => 'array',
        'mqttPort' => 'integer',
        'mqttUseTls' => 'boolean',
        'mqttQos' => 'integer',
        'mqttKeepAlive' => 'integer',
        'mqttPassword' => 'encrypted',
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
