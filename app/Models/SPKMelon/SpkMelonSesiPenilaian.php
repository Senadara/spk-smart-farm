<?php

namespace App\Models\SPKMelon;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\User;

/**
 * Model untuk tabel spk_melon_sesi_penilaian.
 *
 * Tabel dibuat oleh Sequelize migration (DDL).
 * Data dikelola oleh Laravel (DML) via SesiPenilaianController.
 *
 * @property string      $id               UUID v4
 * @property string      $namaSesi          Nama sesi evaluasi
 * @property string      $tipeEvaluasi      'produktivitas' | 'kualitas'
 * @property \Carbon\Carbon $periodeMulai   Tanggal mulai periode
 * @property \Carbon\Carbon $periodeSelesai Tanggal selesai periode
 * @property string      $dinilaiOleh       UUID user penilai (FK ke users.id)
 * @property float|null  $rasioKonsistensi  Consistency Ratio, DECIMAL(10,6)
 * @property string      $status            'draft' | 'proses' | 'selesai' | 'gagal'
 * @property string|null $catatanSesi       Catatan opsional
 * @property bool        $isDeleted         Soft delete flag
 */
class SpkMelonSesiPenilaian extends Model
{
    // Tabel dibuat oleh Sequelize. Timestamps menggunakan camelCase sesuai Sequelize convention.
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $table = 'spk_melon_sesi_penilaian';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Kolom yang dapat diisi melalui mass assignment.
     * Naming sesuai database-schema.md.
     */
    protected $fillable = [
        'namaSesi',
        'tipeEvaluasi',
        'periodeMulai',
        'periodeSelesai',
        'dinilaiOleh',
        'rasioKonsistensi',
        'status',
        'catatanSesi',
        'isDeleted',
    ];

    protected $casts = [
        'periodeMulai' => 'date',
        'periodeSelesai' => 'date',
        'rasioKonsistensi' => 'decimal:6',
        'isDeleted' => 'boolean',
    ];

    /**
     * Auto-generate UUID v4 saat record baru dibuat.
     * Mengikuti pola SPK-01 (SpkMelonKriteria) - manual boot(), bukan HasUuids trait.
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
     * Default value handler: status = 'draft' jika tidak diisi eksplisit.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('active', function ($query) {
            $query->where('isDeleted', 0);
        });

        static::creating(function ($model) {
            $model->status = $model->status ?? 'draft';
            $model->isDeleted = $model->isDeleted ?? 0;
        });
    }

    /**
     * Relasi ke user yang membuat sesi.
     */
    public function penilai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dinilaiOleh', 'id');
    }

    /**
     * Helper: hitung durasi periode dalam hari.
     */
    public function getDurasiHariAttribute(): int
    {
        return $this->periodeMulai->diffInDays($this->periodeSelesai) + 1;
    }

    /**
     * Helper: cek apakah periode sesuai rentang tipikal.
     * Digunakan untuk soft warning, BUKAN validasi.
     */
    public function getPeriodeTipikalAttribute(): bool
    {
        $durasi = $this->durasiHari;

        return match ($this->tipeEvaluasi) {
            'produktivitas' => $durasi >= 60 && $durasi <= 90,
            'kualitas' => $durasi >= 1 && $durasi <= 7,
            default => true,
        };
    }

    // ─── RELASI SPK-03 ──────────────────────────────

    /**
     * Relasi ke perbandingan berpasangan milik sesi ini.
     */
    public function perbandingan(): HasMany
    {
        return $this->hasMany(SpkMelonPerbandingan::class, 'sesiId', 'id');
    }

    /**
     * Relasi ke bobot kriteria milik sesi ini (SPK-05).
     */
    public function bobot(): HasMany
    {
        return $this->hasMany(SpkMelonBobot::class, 'sesiId', 'id');
    }

    // ─── HELPER SPK-03 ──────────────────────────────

    /**
     * Cek apakah matriks perbandingan masih bisa di-edit.
     * Hanya status 'draft' atau 'proses' yang boleh edit.
     */
    public function isPerbandinganEditable(): bool
    {
        return in_array($this->status, ['draft', 'proses'], true);
    }
}
