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
        'completion_requested_at',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'system_validation_status',
        'system_validation_note',
    ];

    protected $casts = [
        'due_date'                => 'date',
        'completed_at'            => 'datetime',
        'completion_requested_at' => 'datetime',
        'reviewed_at'             => 'datetime',
    ];

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

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reports()
    {
        return $this->hasMany(SpkActionReport::class, 'task_id')->orderBy('createdAt', 'desc');
    }

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
        if ($this->is_pending_review) {
            return 'amber';
        }

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
        if ($this->is_pending_review) {
            return 'Menunggu Validasi';
        }

        return match ($this->status) {
            'todo'        => 'To Do',
            'in_progress' => 'Dikerjakan',
            'done'        => 'Selesai',
            'cancelled'   => 'Dibatalkan',
            default       => $this->status,
        };
    }

    public function getIsPendingReviewAttribute(): bool
    {
        return $this->status === 'in_progress' && $this->review_status === 'pending';
    }

    public function getReviewStatusLabelAttribute(): string
    {
        return match ($this->review_status) {
            'pending' => 'Menunggu Validasi',
            'approved' => 'Disetujui',
            'rejected' => 'Perlu Revisi',
            default => 'Belum Direview',
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

    protected static function booted()
    {
        static::addGlobalScope('tenant_isolation', function (\Illuminate\Database\Eloquent\Builder $builder) {
            $user = session('user');
            if ($user && isset($user['role'])) {
                if ($user['role'] === 'pjawab') {
                    $builder->where('assigned_by', $user['id']);
                } elseif ($user['role'] === 'petugas') {
                    $builder->where('assigned_to', $user['id']);
                }
            }
        });
    }
}
