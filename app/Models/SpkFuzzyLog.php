<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SpkFuzzyLog extends Model
{
    use HasUuids;

    protected $table = 'spk_fuzzy_logs';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'unit_budidaya_id',
        'input_json',
        'fuzzified_json',
        'rule_result_json',
        'status_lingkungan',
        'status_kesehatan',
        'diagnosis_kausalitas',
        'output_value',
        'output_label',
        'narrative',
        'recommendation',
    ];

    protected $casts = [
        'input_json'      => 'array',
        'fuzzified_json'  => 'array',
        'rule_result_json'=> 'array',
        'output_value'    => 'float',
    ];

    /**
     * Unit budidaya (kandang) terkait log ini.
     * Bisa null jika proses berjalan secara global.
     */
    public function unitBudidaya(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(UnitBudidaya::class, 'unit_budidaya_id');
    }
}
