<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SpkFuzzyInputSource extends Model
{
    use HasUuids;

    protected $table = 'spk_fuzzy_input_sources';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'variable_id',
        'source_type',
        'source_name',
        'field_name',
        'function_name',
        'extra_config',
    ];

    protected $casts = [
        'extra_config' => 'array',
    ];

    public function variable(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SpkFuzzyVariable::class, 'variable_id');
    }
}
