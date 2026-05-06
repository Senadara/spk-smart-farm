<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SpkFuzzyVariable extends Model
{
    use HasUuids;

    protected $table = 'spk_fuzzy_variables';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'name',
        'type',
        'group',
        'unit',
        'description',
    ];

    /**
     * Himpunan (membership functions) dari variabel ini.
     */
    public function sets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SpkFuzzySet::class, 'variable_id');
    }

    /**
     * Sumber data untuk variabel input ini.
     */
    public function inputSource(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SpkFuzzyInputSource::class, 'variable_id');
    }

    /**
     * Kondisi-kondisi rule yang melibatkan variabel ini.
     */
    public function ruleConditions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SpkFuzzyRuleCondition::class, 'variable_id');
    }
}
