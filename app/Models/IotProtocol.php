<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotProtocol extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'iot_protocol';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null; // As per your DBML, no updated_at

    protected $fillable = [
        'id',
        'protocolName',
        'description',
    ];

    public function connectionConfigs()
    {
        return $this->hasMany(IotConnectionConfig::class, 'protocolId');
    }
}
