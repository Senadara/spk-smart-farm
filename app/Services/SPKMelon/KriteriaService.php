<?php

namespace App\Services\SPKMelon;

use App\Models\SPKMelon\SpkMelonKriteria;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk business logic Kriteria SPK.
 *
 * Tanggung jawab:
 * - Auto-generate kode kriteria (C1, C2, ...) tanpa reuse kode yang sudah dipakai.
 * - Validasi integritas historis saat update (isLocked).
 * - Soft delete (set isDeleted = 1).
 * - Menyediakan opsi spiSumber berdasarkan kategori untuk dropdown dinamis.
 */
class KriteriaService
{
    /**
     * Generate kode kriteria berikutnya.
     *
     * Aturan: skip kode yang sudah dipakai (termasuk yang di-soft-delete).
     * Kode tidak pernah di-reuse. Contoh: jika C1, C2, C3 ada (C2 dihapus),
     * maka kode berikutnya adalah C4 (bukan C2).
     */
    public function generateNextKode(): string
    {
        // Tanpa global scope agar record soft-deleted juga termasuk dalam hitungan
        $lastKode = SpkMelonKriteria::withoutGlobalScope('active')
            ->orderByRaw('CAST(SUBSTRING(kode, 2) AS UNSIGNED) DESC')
            ->value('kode');

        if (!$lastKode) {
            return 'C1';
        }

        $lastNumber = (int) substr($lastKode, 1);
        return 'C' . ($lastNumber + 1);
    }

    /**
     * Buat kriteria baru dengan kode auto-generated.
     *
     * @param  array $data  Data tervalidasi dari StoreKriteriaRequest
     * @return SpkMelonKriteria
     */
    public function create(array $data): SpkMelonKriteria
    {
        return DB::transaction(function () use ($data) {
            $data['kode'] = $this->generateNextKode();
            return SpkMelonKriteria::create($data);
        });
    }

    /**
     * Update kriteria dengan pengecekan integritas historis.
     *
     * Jika kriteria sudah dipakai di sesi selesai (isLocked = true),
     * hanya nama, keterangan, spiSumber, dan spiHitung yang boleh diubah.
     * Field kode, tipe, dan kategori akan di-strip secara paksa di sini
     * sebagai lapisan keamanan tambahan (selain di UpdateKriteriaRequest).
     *
     * @param  SpkMelonKriteria $kriteria
     * @param  array $data  Data tervalidasi dari UpdateKriteriaRequest
     * @return SpkMelonKriteria
     */
    public function update(SpkMelonKriteria $kriteria, array $data): SpkMelonKriteria
    {
        if ($kriteria->isLocked()) {
            // Strip field yang tidak boleh diubah jika kriteria locked
            unset($data['kode'], $data['tipe'], $data['kategori']);
        }

        $kriteria->update($data);
        return $kriteria->fresh();
    }

    /**
     * Soft delete kriteria (set isDeleted = 1).
     * Record tetap ada di database untuk menjaga integritas historis perhitungan SPK.
     *
     * @param  SpkMelonKriteria $kriteria
     * @return bool
     */
    public function softDelete(SpkMelonKriteria $kriteria): bool
    {
        return $kriteria->update(['isDeleted' => 1]);
    }

    /**
     * Ambil list opsi spiSumber yang valid berdasarkan kategori.
     * Digunakan untuk mengisi dropdown dinamis di modal form.
     *
     * @param  string $kategori  'produktivitas' | 'kualitas' | 'lingkungan'
     * @return array<string>
     */
    public function getSpiSumberByKategori(string $kategori): array
    {
        return match ($kategori) {
            'produktivitas' => ['harianKebun', 'panenKebun', 'hama', 'manual'],
            'kualitas' => ['penilaianKualitas', 'manual'],
            'lingkungan' => ['sensor', 'manual'],
            default => [],
        };
    }
}
