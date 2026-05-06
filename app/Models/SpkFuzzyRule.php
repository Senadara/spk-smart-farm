<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SpkFuzzyRule extends Model
{
    use HasUuids;

    protected $table = 'spk_fuzzy_rules';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'name',
        'operator',
        'output_set_id',
        'group',
        'diagnosis',
    ];

    /**
     * Kondisi-kondisi IF pada rule ini.
     */
    public function conditions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SpkFuzzyRuleCondition::class, 'rule_id');
    }

    /**
     * Himpunan output yang diaktifkan oleh rule ini (bagian THEN).
     */
    public function outputSet(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SpkFuzzySet::class, 'output_set_id');
    }
}
