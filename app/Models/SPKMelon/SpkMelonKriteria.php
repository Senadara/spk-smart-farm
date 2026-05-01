<?php

namespace App\Models\SPKMelon;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Model untuk tabel spk_melon_kriteria.
 *
 * Tabel dibuat oleh Sequelize migration (DDL).
 * Seed data dikelola oleh Laravel seeder (DML) - SpkMelonKriteriaSeeder.
 *
 * @property string   $id          UUID v4
 * @property string   $kode        Contoh: C1, C2, ...
 * @property string   $nama        Nama kriteria
 * @property string   $tipe        'benefit' | 'cost'
 * @property string   $kategori    'produktivitas' | 'kualitas' | 'lingkungan'
 * @property string|null $spiHitung   Formula/field reference, contoh: 'harianKebun.tinggiTanaman'
 * @property string|null $spiSumber   Data source, contoh: 'harianKebun', 'panenKebun', 'sensor'
 * @property string|null $keterangan  Keterangan opsional
 * @property bool     $isDeleted   Soft delete flag
 */
class SpkMelonKriteria extends Model
{
    // Tabel dibuat oleh Sequelize. Timestamps menggunakan camelCase sesuai Sequelize convention.
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';
    protected $table = 'spk_melon_kriteria';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kode',
        'nama',
        'tipe',
        'kategori',
        'spiHitung',   // Formula/field reference. Contoh: 'harianKebun.tinggiTanaman'
        'spiSumber',   // Data source identifier. Contoh: 'harianKebun', 'panenKebun', 'sensor'
        'keterangan',
    ];

    protected $casts = [
        'isDeleted' => 'boolean',
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
     * Global scope: selalu filter record yang belum di-soft-delete.
     * Gunakan withoutGlobalScope('active') untuk melihat semua record termasuk yang dihapus.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('active', function ($query) {
            $query->where('isDeleted', 0);
        });
    }

    /**
     * Scope untuk filter berdasarkan kategori.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $kategori  'produktivitas' | 'kualitas' | 'lingkungan'
     */
    public function scopeByKategori($query, string $kategori)
    {
        return $query->where('kategori', $kategori);
    }

    /**
     * Cek apakah kriteria sudah dipakai di sesi SPK yang berstatus 'selesai'.
     * Jika locked, kolom kode, tipe, dan kategori tidak boleh diubah.
     *
     * TODO: [SPK-02] Implementasi setelah model SpkMelonSesiPenilaian tersedia.
     *       Query: SpkMelonPerbandingan yang punya sesi berstatus 'selesai'
     *       dengan kriteriaId === $this->id.
     */
    public function isLocked(): bool
    {
        // Stub: return false sementara hingga SPK-02 selesai.
        return false;
    }
}
