<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LivestockEnvironmentParameter extends Model
{
    use HasUuids;

    protected $table = 'livestock_environment_parameters';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'config_id',
        'parameter_id',
        'parameter_code',
        'parameter_name',
        'unit',
        'icon_key',
        'min_value',
        'max_value',
        'fallback_value',
        'stale_minutes',
        'required_for_iot',
        'required_for_fuzzy',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_value' => 'float',
        'max_value' => 'float',
        'fallback_value' => 'float',
        'stale_minutes' => 'integer',
        'required_for_iot' => 'boolean',
        'required_for_fuzzy' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function config()
    {
        return $this->belongsTo(LivestockMasterConfig::class, 'config_id');
    }

    public function iotParameter()
    {
        return $this->belongsTo(IotParameter::class, 'parameter_id');
    }
}
