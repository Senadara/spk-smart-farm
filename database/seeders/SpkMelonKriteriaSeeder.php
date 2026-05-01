<?php

namespace Database\Seeders;

use App\Models\SPKMelon\SpkMelonKriteria;
use Illuminate\Database\Seeder;

/**
 * Seeder untuk 10 kriteria SPK default (C1–C10).
 *
 * ATURAN PENTING:
 * - Seeder ini HANYA boleh dijalankan dari repo Laravel (spk-smart-farm), BUKAN dari smart-farming-api.
 * - JANGAN jalankan `php artisan migrate:fresh --seed` — akan menghapus semua tabel Sequelize.
 * - Gunakan: `php artisan db:seed --class=SpkMelonKriteriaSeeder`
 *
 * Kriteria kualitas (C6–C8) berstatus TENTATIVE: menunggu konfirmasi pakar agronomi (SPK-08 blocker).
 * Kriteria lingkungan (C9–C10) menunggu integrasi sensor IoT (IOT-01 BLOCKED).
 */
class SpkMelonKriteriaSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotency check: skip jika data sudah ada (termasuk yang di-soft-delete).
        // Ini mencegah duplikasi saat seeder dijalankan ulang.
        if (SpkMelonKriteria::withoutGlobalScope('active')->count() > 0) {
            $this->command->info('Kriteria SPK sudah ada. Seeder di-skip (idempotent).');
            return;
        }

        $kriteria = [
            // KATEGORI: PRODUKTIVITAS
            [
                'kode'       => 'C1',
                'nama'       => 'Realisasi Panen',
                'tipe'       => 'benefit',
                'kategori'   => 'produktivitas',
                'spiSumber'  => 'panenKebun',
                'spiHitung'  => 'panenKebun.realisasiPanen',
                'keterangan' => 'Total hasil panen aktual per blok kebun (kg). Sumber: panenKebun.realisasiPanen.',
            ],
            [
                'kode'       => 'C2',
                'nama'       => 'Gagal Panen',
                'tipe'       => 'cost',
                'kategori'   => 'produktivitas',
                'spiSumber'  => 'panenKebun',
                'spiHitung'  => 'panenKebun.gagalPanen',
                'keterangan' => 'Jumlah tanaman yang gagal dipanen. Sumber: panenKebun.gagalPanen.',
            ],
            [
                'kode'       => 'C3',
                'nama'       => 'Tinggi Tanaman',
                'tipe'       => 'benefit',
                'kategori'   => 'produktivitas',
                'spiSumber'  => 'harianKebun',
                'spiHitung'  => 'harianKebun.tinggiTanaman',
                'keterangan' => 'Rata-rata tinggi tanaman selama periode evaluasi (cm). Sumber: harianKebun.tinggiTanaman.',
            ],
            [
                'kode'       => 'C4',
                'nama'       => 'Kondisi Daun',
                'tipe'       => 'benefit',
                'kategori'   => 'produktivitas',
                'spiSumber'  => 'harianKebun',
                'spiHitung'  => 'harianKebun.kondisiDaun',
                'keterangan' => 'Skor rata-rata kondisi daun (1-5). Sumber: harianKebun.kondisiDaun (via helper).',
            ],
            [
                'kode'       => 'C5',
                'nama'       => 'Jumlah Serangan Hama',
                'tipe'       => 'cost',
                'kategori'   => 'produktivitas',
                'spiSumber'  => 'hama',
                'spiHitung'  => 'hama.jumlahSerangan',
                'keterangan' => 'Total insiden serangan hama tercatat selama periode evaluasi. Sumber: hama.jumlahSerangan.',
            ],

            // KATEGORI: KUALITAS
            // TENTATIVE: indikator berdasarkan studi literatur, menunggu konfirmasi pakar agronomi (SPK-08 blocker)
            [
                'kode'       => 'C6',
                'nama'       => 'Kadar Brix',
                'tipe'       => 'benefit',
                'kategori'   => 'kualitas',
                'spiSumber'  => 'penilaianKualitas',
                'spiHitung'  => 'penilaianKualitas.kadarBrix',
                // TENTATIVE: pending agronomy expert confirmation (SPK-08 blocker)
                'keterangan' => 'Tingkat kemanisan buah (derajat Brix). TENTATIVE: indikator berdasarkan studi literatur, menunggu konfirmasi pakar agronomi.',
            ],
            [
                'kode'       => 'C7',
                'nama'       => 'Berat Buah Individu',
                'tipe'       => 'benefit',
                'kategori'   => 'kualitas',
                'spiSumber'  => 'penilaianKualitas',
                'spiHitung'  => 'penilaianKualitas.beratBuah',
                // TENTATIVE: pending agronomy expert confirmation (SPK-08 blocker)
                'keterangan' => 'Rata-rata berat per buah melon (gram). TENTATIVE: indikator berdasarkan studi literatur, menunggu konfirmasi pakar agronomi.',
            ],
            [
                'kode'       => 'C8',
                'nama'       => 'Diameter Buah',
                'tipe'       => 'benefit',
                'kategori'   => 'kualitas',
                'spiSumber'  => 'penilaianKualitas',
                'spiHitung'  => 'penilaianKualitas.diameterBuah',
                // TENTATIVE: pending agronomy expert confirmation (SPK-08 blocker)
                'keterangan' => 'Rata-rata diameter buah (cm). TENTATIVE: indikator berdasarkan studi literatur, menunggu konfirmasi pakar agronomi.',
            ],

            // KATEGORI: LINGKUNGAN
            // BLOCKED: data aktual menunggu integrasi sensor IoT (IOT-01 BLOCKED)
            [
                'kode'       => 'C9',
                'nama'       => 'pH Tanah',
                'tipe'       => 'benefit',
                'kategori'   => 'lingkungan',
                'spiSumber'  => 'sensor',
                'spiHitung'  => 'spk_melon_log_sensor.ph',
                'keterangan' => 'Rata-rata pH tanah dari sensor IoT. Sumber: spk_melon_log_sensor.ph. Note: data aktual pending IOT-01.',
            ],
            [
                'kode'       => 'C10',
                'nama'       => 'Electrical Conductivity (EC)',
                'tipe'       => 'benefit',
                'kategori'   => 'lingkungan',
                'spiSumber'  => 'sensor',
                'spiHitung'  => 'spk_melon_log_sensor.ec',
                'keterangan' => 'Rata-rata nilai EC dari sensor IoT (mS/cm). Sumber: spk_melon_log_sensor.ec. Note: data aktual pending IOT-01.',
            ],
        ];

        foreach ($kriteria as $item) {
            SpkMelonKriteria::create($item);
        }

        $this->command->info('Berhasil seed 10 kriteria default (C1-C10).');
    }
}
