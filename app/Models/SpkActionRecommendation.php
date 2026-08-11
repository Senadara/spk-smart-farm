<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SpkActionRecommendation extends Model
{
    use HasUuids;

    protected $table = 'spk_action_recommendations';

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
        'assigned_task_id',
        'status',
        'priority',
        'score',
        'title',
        'description',
        'fingerprint',
        'disable_reason',
        'assigned_at',
        'disabled_at',
    ];

    protected $casts = [
        'score' => 'float',
        'assigned_at' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    public function spkFuzzyLog()
    {
        return $this->belongsTo(SpkFuzzyLog::class, 'spk_fuzzy_log_id');
    }

    public function unitBudidaya()
    {
        return $this->belongsTo(UnitBudidaya::class, 'unit_budidaya_id');
    }

    public function assignedTask()
    {
        return $this->belongsTo(SpkActionTask::class, 'assigned_task_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
