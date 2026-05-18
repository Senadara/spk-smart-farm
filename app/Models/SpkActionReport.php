<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SpkActionReport extends Model
{
    use HasUuids;

    protected $table = 'spk_action_reports';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'task_id',
        'reported_by',
        'description',
        'photo',
        'status_update',
    ];

    /* ── Relationships ─────────────────────────────────── */

    public function task()
    {
        return $this->belongsTo(SpkActionTask::class, 'task_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
