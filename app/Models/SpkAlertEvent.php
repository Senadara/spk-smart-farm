<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SpkAlertEvent extends Model
{
    use HasUuids;

    protected $table = 'spk_alert_events';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'spk_fuzzy_log_id',
        'unit_budidaya_id',
        'commodity_id',
        'owner_id',
        'alert_type',
        'severity',
        'status_lingkungan',
        'status_kesehatan',
        'diagnosis_kausalitas',
        'output_value',
        'title',
        'body',
        'data_json',
        'fingerprint',
        'send_status',
        'response_json',
        'sent_at',
        'read_at',
        'read_by',
    ];

    protected $casts = [
        'data_json' => 'array',
        'response_json' => 'array',
        'output_value' => 'float',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function spkFuzzyLog()
    {
        return $this->belongsTo(SpkFuzzyLog::class, 'spk_fuzzy_log_id');
    }

    public function unitBudidaya()
    {
        return $this->belongsTo(UnitBudidaya::class, 'unit_budidaya_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
