<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SpkActionTask extends Model
{
    use HasUuids;

    protected $table = 'spk_action_tasks';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'spk_fuzzy_log_id',
        'unit_budidaya_id',
        'assigned_to',
        'assigned_by',
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'completed_at',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'datetime',
    ];

    /* ── Relationships ─────────────────────────────────── */

    public function fuzzyLog()
    {
        return $this->belongsTo(SpkFuzzyLog::class, 'spk_fuzzy_log_id');
    }

    public function unitBudidaya()
    {
        return $this->belongsTo(UnitBudidaya::class, 'unit_budidaya_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function reports()
    {
        return $this->hasMany(SpkActionReport::class, 'task_id')->orderBy('createdAt', 'desc');
    }

    /* ── Helpers ────────────────────────────────────────── */

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => 'red',
            'high'   => 'amber',
            'medium' => 'blue',
            'low'    => 'gray',
            default  => 'gray',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'todo'        => 'gray',
            'in_progress' => 'blue',
            'done'        => 'emerald',
            'cancelled'   => 'red',
            default       => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'todo'        => 'To Do',
            'in_progress' => 'Dikerjakan',
            'done'        => 'Selesai',
            'cancelled'   => 'Dibatalkan',
            default       => $this->status,
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => 'Urgent',
            'high'   => 'Tinggi',
            'medium' => 'Sedang',
            'low'    => 'Rendah',
            default  => $this->priority,
        };
    }
}
