<?php

namespace App\Models\SPKMelon;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Model untuk tabel spk_melon_perbandingan.
 *
 * Tabel dibuat oleh Sequelize migration (DDL).
 * Data dikelola oleh Laravel (DML) via PerbandinganController.
 *
 * @property string $id          UUID v4
 * @property string $sesiId      FK → spk_melon_sesi_penilaian.id
 * @property string $kriteria1Id FK → spk_melon_kriteria.id (row criterion)
 * @property string $kriteria2Id FK → spk_melon_kriteria.id (column criterion)
 * @property float  $nilaiSaaty  Skala Saaty (1-9 atau resiprokal 1/2-1/9)
 * @property float  $tfnL        TFN lower bound
 * @property float  $tfnM        TFN middle value
 * @property float  $tfnU        TFN upper bound
 * @property bool   $isDeleted   Soft delete flag
 */
class SpkMelonPerbandingan extends Model
{
    // Konvensi Sequelize: timestamp pakai camelCase
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'spk_melon_perbandingan';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'sesiId',
        'kriteria1Id',
        'kriteria2Id',
        'nilaiSaaty',
        'tfnL',
        'tfnM',
        'tfnU',
        'isDeleted',
    ];

    protected $casts = [
        'nilaiSaaty' => 'decimal:2',
        'tfnL'       => 'decimal:6',
        'tfnM'       => 'decimal:6',
        'tfnU'       => 'decimal:6',
        'isDeleted'  => 'boolean',
    ];

    /**
     * Auto-generate UUID v4 saat record baru dibuat.
     */
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Global scope: filter record yang belum di-soft-delete.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('active', function ($query) {
            $query->where('isDeleted', 0);
        });
    }

    // ─── RELASI ──────────────────────────────────────

    public function sesi(): BelongsTo
    {
        return $this->belongsTo(SpkMelonSesiPenilaian::class, 'sesiId', 'id');
    }

    public function kriteria1(): BelongsTo
    {
        return $this->belongsTo(SpkMelonKriteria::class, 'kriteria1Id', 'id');
    }

    public function kriteria2(): BelongsTo
    {
        return $this->belongsTo(SpkMelonKriteria::class, 'kriteria2Id', 'id');
    }
}
