<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LivestockMasterConfig extends Model
{
    use HasUuids;

    protected $table = 'livestock_master_configs';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'jenis_budidaya_id',
        'commodity_id',
        'status',
        'notes',
        'afkir_label',
        'afkir_target_weeks',
        'afkir_warning_weeks',
        'configured_by',
        'configured_at',
    ];

    protected $casts = [
        'configured_at' => 'datetime',
        'afkir_target_weeks' => 'integer',
        'afkir_warning_weeks' => 'integer',
    ];

    public function environmentParameters()
    {
        return $this->hasMany(LivestockEnvironmentParameter::class, 'config_id');
    }

    public function productivityFunctionConfigs()
    {
        return $this->hasMany(LivestockProductivityFunctionConfig::class, 'config_id');
    }
}
