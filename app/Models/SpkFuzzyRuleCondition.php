<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SpkFuzzyRuleCondition extends Model
{
    use HasUuids;

    protected $table = 'spk_fuzzy_rule_conditions';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'rule_id',
        'variable_id',
        'set_id',
    ];

    public function rule(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SpkFuzzyRule::class, 'rule_id');
    }

    public function variable(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SpkFuzzyVariable::class, 'variable_id');
    }

    public function set(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SpkFuzzySet::class, 'set_id');
    }
}
