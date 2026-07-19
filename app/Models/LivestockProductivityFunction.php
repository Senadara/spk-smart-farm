<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LivestockProductivityFunction extends Model
{
    use HasUuids;

    protected $table = 'livestock_productivity_functions';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'code',
        'name',
        'service_class',
        'output_unit',
        'description',
        'required_inputs',
        'is_active',
    ];

    protected $casts = [
        'required_inputs' => 'array',
        'is_active' => 'boolean',
    ];
}
