<?php

namespace App\Models\SPKMelon;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Model untuk tabel spk_melon_bobot.
 *
 * Tabel dibuat oleh Sequelize migration (DDL).
 * Data dikelola oleh Laravel (DML) via BobotController + FuzzyAhpService.
 *
 * @property string $id          UUID v4
 * @property string $sesiId      FK → spk_melon_sesi_penilaian.id
 * @property string $kriteriaId  FK → spk_melon_kriteria.id
 * @property float  $bobotFuzzyL Bobot fuzzy lower bound
 * @property float  $bobotFuzzyM Bobot fuzzy middle value
 * @property float  $bobotFuzzyU Bobot fuzzy upper bound
 * @property float  $bobotAkhir  Bobot crisp (defuzzified + normalized)
 * @property bool   $isDeleted   Soft delete flag
 */
class SpkMelonBobot extends Model
{
    // Konvensi Sequelize: timestamp pakai camelCase
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'spk_melon_bobot';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'sesiId',
        'kriteriaId',
        'bobotFuzzyL',
        'bobotFuzzyM',
        'bobotFuzzyU',
        'bobotAkhir',
        'isDeleted',
    ];

    protected $casts = [
        'bobotFuzzyL' => 'decimal:6',
        'bobotFuzzyM' => 'decimal:6',
        'bobotFuzzyU' => 'decimal:6',
        'bobotAkhir'  => 'decimal:6',
        'isDeleted'   => 'boolean',
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

    /**
     * Relasi ke sesi penilaian.
     */
    public function sesi(): BelongsTo
    {
        return $this->belongsTo(SpkMelonSesiPenilaian::class, 'sesiId', 'id');
    }

    /**
     * Relasi ke kriteria.
     */
    public function kriteria(): BelongsTo
    {
        return $this->belongsTo(SpkMelonKriteria::class, 'kriteriaId', 'id');
    }
}
