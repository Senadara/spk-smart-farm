<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DailyReportMetric extends Model
{
    use HasUuids;

    protected $table = 'daily_report_metrics';

    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'id',
        'laporan_id',
        'metric_code',
        'value',
        'unit',
        'metadata',
        'isDeleted',
    ];

    protected $casts = [
        'value' => 'float',
        'metadata' => 'array',
        'isDeleted' => 'boolean',
    ];
}
