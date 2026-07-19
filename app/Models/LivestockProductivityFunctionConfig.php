<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LivestockProductivityFunctionConfig extends Model
{
    use HasUuids;

    protected $table = 'livestock_productivity_function_configs';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'config_id',
        'function_id',
        'required_for_fuzzy',
        'aggregation_scope',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'required_for_fuzzy' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function function()
    {
        return $this->belongsTo(LivestockProductivityFunction::class, 'function_id');
    }
}
