<?php

namespace App\Services\SPKMelon;

use App\Models\SPKMelon\SpkMelonKriteria;
use App\Models\SPKMelon\SpkMelonPerbandingan;
use App\Models\SPKMelon\SpkMelonSesiPenilaian;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service class untuk logika bisnis matriks perbandingan berpasangan (SPK-03).
 *
 * Tanggung jawab:
 * - Ambil matriks lama untuk pre-fill form edit
 * - Simpan matriks baru (delete-then-insert dalam transaction)
 * - Transisi status sesi (draft → proses)
 * - Ambil daftar kriteria untuk sesi tertentu
 */
class PerbandinganService
{
    /**
     * Ambil seluruh perbandingan milik sesi tertentu sebagai array
     * key-value dengan format "kriteria1Id::kriteria2Id" => nilaiSaaty.
     *
     * Digunakan controller untuk pre-fill matriks saat user re-edit.
     */
    public function getExistingMatrix(SpkMelonSesiPenilaian $sesi): array
    {
        $rows = SpkMelonPerbandingan::where('sesiId', $sesi->id)->get();
        $matrix = [];

        foreach ($rows as $row) {
            $key = $row->kriteria1Id . '::' . $row->kriteria2Id;
            $matrix[$key] = (float) $row->nilaiSaaty;
        }

        return $matrix;
    }

    /**
     * Simpan seluruh matriks perbandingan dalam satu transaksi.
     *
     * Alur:
     * 1. Hapus seluruh row lama (jika ada) — hard delete
     * 2. Insert seluruh row baru: upper triangle (user input) + diagonal + lower triangle (reciprocal)
     * 3. Transisi status sesi dari 'draft' ke 'proses' jika sebelumnya draft
     *
     * @param SpkMelonSesiPenilaian $sesi
     * @param array $kriteriaList Array of SpkMelonKriteria objects, ordered
     * @param array $upperTriangle Format: ["kriteria1Id::kriteria2Id" => nilaiSaaty]
     * @return void
     * @throws \Throwable
     */
    public function saveMatrix(
        SpkMelonSesiPenilaian $sesi,
        array $kriteriaList,
        array $upperTriangle
    ): void {
        DB::transaction(function () use ($sesi, $kriteriaList, $upperTriangle) {
            // 1. Hapus row lama (hard delete karena tidak butuh audit history)
            SpkMelonPerbandingan::withoutGlobalScope('active')
                ->where('sesiId', $sesi->id)
                ->delete();

            $now = now();
            $rows = [];

            // 2. Bangun seluruh n × n cells
            foreach ($kriteriaList as $i => $kriteriaRow) {
                foreach ($kriteriaList as $j => $kriteriaCol) {
                    if ($i === $j) {
                        // Diagonal: Saaty = 1, TFN = (1, 1, 1)
                        $saaty = 1.0;
                        $tfn = [1, 1, 1];
                    } elseif ($i < $j) {
                        // Upper triangle: ambil dari user input
                        $key = $kriteriaRow->id . '::' . $kriteriaCol->id;
                        $saaty = (float) ($upperTriangle[$key] ?? 1);
                        $tfn = TfnHelper::saatyToTfn($saaty);
                    } else {
                        // Lower triangle: resiprokal dari upper
                        $upperKey = $kriteriaCol->id . '::' . $kriteriaRow->id;
                        $upperSaaty = (float) ($upperTriangle[$upperKey] ?? 1);
                        $saaty = TfnHelper::saatyReciprocal($upperSaaty);
                        $tfn = TfnHelper::saatyToTfn($saaty);
                    }

                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'sesiId' => $sesi->id,
                        'kriteria1Id' => $kriteriaRow->id,
                        'kriteria2Id' => $kriteriaCol->id,
                        'nilaiSaaty' => $saaty,
                        'tfnL' => $tfn[0],
                        'tfnM' => $tfn[1],
                        'tfnU' => $tfn[2],
                        'isDeleted' => 0,
                        'createdAt' => $now,
                        'updatedAt' => $now,
                    ];
                }
            }

            // 3. Bulk insert
            SpkMelonPerbandingan::insert($rows);

            // 4. Transisi status sesi (hanya jika sebelumnya draft)
            if ($sesi->status === 'draft') {
                $sesi->status = 'proses';
                // Reset CR karena matriks baru
                $sesi->rasioKonsistensi = null;
                $sesi->save();
            } elseif ($sesi->status === 'proses') {
                // Edit ulang: reset CR saja, status tetap proses
                $sesi->rasioKonsistensi = null;
                $sesi->save();
            }
        });
    }

    /**
     * Ambil daftar kriteria untuk sesi tersebut, urutan konsisten.
     *
     * SCOPE SPK-03: Kriteria yang dipakai adalah seluruh kriteria yang
     * sesuai dengan tipeEvaluasi sesi + kategori 'lingkungan'. Urutan
     * mengikuti kode kriteria (C1, C2, C3, …) untuk konsistensi tampilan.
     */
    public function getKriteriaForSesi(SpkMelonSesiPenilaian $sesi): \Illuminate\Database\Eloquent\Collection
    {
        return SpkMelonKriteria::whereIn('kategori', [$sesi->tipeEvaluasi, 'lingkungan'])
            ->orderBy('kode')
            ->get();
    }
}
