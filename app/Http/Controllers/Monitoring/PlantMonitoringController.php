<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlantMonitoringController extends Controller
{
    /**
     * DASH-03: Halaman Pemantauan Kondisi Perkebunan (Plant Monitoring — REDESIGN v2.1).
     * Layout 4 section: Header + Filter, Sensor Cards, Chart Tren, Laporan Harian.
     */
    public function index(Request $request)
    {
        // 1. Ambil daftar blok kebun untuk dropdown filter
        $blokKebun = $this->getBlokKebunList();

        // 2. Ambil filter parameters
        $selectedBlokId = $request->input('blok_kebun_id', null);
        $periode = $request->input('periode', '7');

        // 3. Data sections
        $sensorData      = $this->getLatestSensorData($selectedBlokId);
        $sensorHistory   = $this->getSensorHistory($selectedBlokId, (int)$periode);
        $laporanTerbaru  = $this->getLaporanTerbaru($selectedBlokId, $periode);

        return view('plant-monitoring.index', compact(
            'blokKebun',
            'selectedBlokId',
            'periode',
            'sensorData',
            'sensorHistory',
            'laporanTerbaru'
        ));
    }

    /**
     * Ambil daftar blok kebun melon untuk dropdown filter.
     * Sumber: tabel unitBudidaya JOIN jenisBudidaya (filter tipe = tumbuhan).
     * Tabel ini milik Node.js/Sequelize — READ ONLY.
     */
    private function getBlokKebunList(): array
    {
        $units = DB::table('unitBudidaya')
            ->join('jenisBudidaya', 'unitBudidaya.JenisBudidayaId', '=', 'jenisBudidaya.id')
            ->where('jenisBudidaya.tipe', 'tumbuhan')
            ->where('unitBudidaya.isDeleted', 0)
            ->select('unitBudidaya.id', 'unitBudidaya.nama', 'unitBudidaya.lokasi')
            ->orderBy('unitBudidaya.nama')
            ->get()
            ->toArray();

        return $units ?: [];
    }

    /**
     * Ambil data sensor terbaru per blok kebun.
     * Sumber: tabel spk_melon_log_sensor.
     * Status: TABEL KOSONG (IOT-01 BLOCKED). Scaffold query + return dummy.
     */
    private function getLatestSensorData(?string $blokKebunId): array
    {
        // --- SCAFFOLD ELOQUENT QUERY (aktifkan saat data tersedia) ---
        // $query = DB::table('spk_melon_log_sensor as s')
        //     ->join('unitBudidaya as u', 's.unitBudidayaId', '=', 'u.id')
        //     ->where('s.isDeleted', 0)
        //     ->where('u.isDeleted', 0);
        //
        // if ($blokKebunId) {
        //     $query->where('s.unitBudidayaId', $blokKebunId);
        // }
        //
        // $latestIds = DB::table('spk_melon_log_sensor')
        //     ->select(DB::raw('MAX(id) as id'))
        //     ->where('isDeleted', 0)
        //     ->groupBy('unitBudidayaId');
        //
        // $results = $query
        //     ->joinSub($latestIds, 'latest', 's.id', '=', 'latest.id')
        //     ->select(
        //         'u.id as unitId', 'u.nama as namaBlok',
        //         's.ph as ph_tanah', 's.ec', 's.suhu', 's.kelembaban',
        //         's.nitrogen', 's.fosfor', 's.kalium', 's.dicatatPada'
        //     )
        //     ->orderBy('u.nama')
        //     ->get()
        //     ->toArray();
        //
        // if (!empty($results)) return $results;
        // --- END SCAFFOLD ---

        // Helper untuk men-generate data sparkline 7 titik terakhir
        $genSparkline = function($base, $var) {
            return array_map(fn() => round($base + (mt_rand(-$var, $var) / 10), 1), range(1, 7));
        };

        // TODO: [IOT-01] BLOCKED — Replace dummy setelah integrasi Antares
        return [
            [
                'namaBlok'    => 'Greenhouse A',
                'ph_tanah'    => 6.8,
                'ec'          => 2.1,
                'suhu'        => 28.5,
                'kelembaban'  => 75.0,
                'nitrogen'    => 45,
                'fosfor'      => 30,
                'kalium'      => 150,
                'dicatatPada' => now()->subMinutes(15)->toDateTimeString(),
                'trend'       => ['ph_tanah' => 'up', 'ec' => 'stable', 'suhu' => 'down', 'kelembaban' => 'up'],
                'history'     => [
                    'ph_tanah'   => $genSparkline(6.8, 5),
                    'ec'         => $genSparkline(2.1, 3),
                    'suhu'       => $genSparkline(28.5, 10),
                    'kelembaban' => $genSparkline(75.0, 30),
                    'nitrogen'   => $genSparkline(45, 50),
                    'fosfor'     => $genSparkline(30, 30),
                    'kalium'     => $genSparkline(150, 100),
                ]
            ],
            [
                'namaBlok'    => 'Greenhouse B',
                'ph_tanah'    => 5.2,
                'ec'          => 3.8,
                'suhu'        => 33.0,
                'kelembaban'  => 68.0,
                'nitrogen'    => 42,
                'fosfor'      => 28,
                'kalium'      => 140,
                'dicatatPada' => now()->subMinutes(20)->toDateTimeString(),
                'trend'       => ['ph_tanah' => 'down', 'ec' => 'up', 'suhu' => 'up', 'kelembaban' => 'down'],
                'history'     => [
                    'ph_tanah'   => $genSparkline(5.2, 5),
                    'ec'         => $genSparkline(3.8, 3),
                    'suhu'       => $genSparkline(33.0, 10),
                    'kelembaban' => $genSparkline(68.0, 30),
                    'nitrogen'   => $genSparkline(42, 50),
                    'fosfor'     => $genSparkline(28, 30),
                    'kalium'     => $genSparkline(140, 100),
                ]
            ],
            [
                'namaBlok'    => 'Greenhouse C',
                'ph_tanah'    => 4.8,
                'ec'          => 4.5,
                'suhu'        => 35.0,
                'kelembaban'  => 60.0,
                'nitrogen'    => 38,
                'fosfor'      => 22,
                'kalium'      => 110,
                'dicatatPada' => now()->subMinutes(30)->toDateTimeString(),
                'trend'       => ['ph_tanah' => 'down', 'ec' => 'up', 'suhu' => 'up', 'kelembaban' => 'down'],
                'history'     => [
                    'ph_tanah'   => $genSparkline(4.8, 5),
                    'ec'         => $genSparkline(4.5, 3),
                    'suhu'       => $genSparkline(35.0, 10),
                    'kelembaban' => $genSparkline(60.0, 30),
                    'nitrogen'   => $genSparkline(38, 50),
                    'fosfor'     => $genSparkline(22, 30),
                    'kalium'     => $genSparkline(110, 100),
                ]
            ],
        ];
    }

    /**
     * Ambil data historis sensor untuk chart tren.
     * TODO: [IOT-01] BLOCKED — Replace dummy setelah integrasi Antares
     */
    private function getSensorHistory(?string $blokKebunId, int $days = 7): array
    {
        // Dummy data: 7 hari terakhir untuk chart tren
        $labels = [];
        $ph = [];
        $ec = [];
        $suhu = [];
        $kelembaban = [];
        $nitrogen = [];
        $fosfor = [];
        $kalium = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[]     = now()->subDays($i)->format('d/m');
            $ph[]         = round(5.5 + (mt_rand(0, 20) / 10), 1);
            $ec[]         = round(1.8 + (mt_rand(0, 20) / 10), 1);
            $suhu[]       = round(25 + (mt_rand(0, 100) / 10), 1);
            $kelembaban[] = round(60 + (mt_rand(0, 250) / 10), 1);
            $nitrogen[]   = mt_rand(30, 60);
            $fosfor[]     = mt_rand(20, 40);
            $kalium[]     = mt_rand(100, 180);
        }

        return [
            'labels'     => $labels,
            'ph'         => $ph,
            'ec'         => $ec,
            'suhu'       => $suhu,
            'kelembaban' => $kelembaban,
            'nitrogen'   => $nitrogen,
            'fosfor'     => $fosfor,
            'kalium'     => $kalium,
            'thresholds' => [
                'ph_min'   => 5.5,
                'ph_max'   => 7.5,
                'ec_max'   => 3.5,
                'suhu_max' => 30,
            ],
        ];
    }

    /**
     * Ambil laporan terbaru dari berbagai jenis (harian, sakit, hama, panen, nutrisi).
     * Sumber: tabel laporan JOIN harianKebun/hama/panenKebun/sakit/kematian/penggunaanInventaris.
     * Status: TABEL KOSONG. Scaffold query + return dummy.
     * Tabel-tabel ini milik Node.js/Sequelize — READ ONLY.
     */
    private function getLaporanTerbaru(?string $blokKebunId, string $periode): array
    {
        // --- SCAFFOLD ELOQUENT QUERY (aktifkan saat data tersedia) ---
        // TODO: [DASH-03] Replace with actual API call
        // Real: GET /api/farm/laporan/harian-kebun + /api/farm/laporan/hama + /api/farm/laporan/panen-kebun
        // --- END SCAFFOLD ---

        // TODO: [DASH-03] Dummy data — replace with actual API call
        return [
            [
                'tanggal' => now()->subDays(0)->format('Y-m-d'),
                'blok' => 'Greenhouse A',
                'jenis' => 'Laporan Harian',
                'tipe' => 'harian',
                'detail' => [
                    'kode_tanaman' => 'Melon #1',
                    'nama_tanaman' => 'Melon Fujisawa',
                    'penyiraman' => true,
                    'pruning' => false,
                    'nutrisi' => true,
                    'repotting' => false,
                    'tinggi' => 45.5,
                    'kondisi_daun' => 'Sehat',
                    'status_pertumbuhan' => 'Vegetatif',
                    'pelapor' => 'Pak Adi',
                    'catatan' => 'Tanaman sudah dilakukan perawatan harian.',
                    'foto' => null,
                ],
            ],
            [
                'tanggal' => now()->subDays(0)->format('Y-m-d'),
                'blok' => 'Greenhouse B',
                'jenis' => 'Tanaman Sakit',
                'tipe' => 'khusus',
                'detail' => [
                    'kode_tanaman' => 'Melon #2',
                    'nama_tanaman' => 'Melon',
                    'nama_penyakit' => 'Embun Tepung',
                    'pelapor' => 'Adi Santoso',
                    'catatan' => 'Terdapat lapisan putih seperti bedak pada permukaan daun bagian atas.',
                    'foto' => null,
                ],
            ],
            [
                'tanggal' => now()->subDays(1)->format('Y-m-d'),
                'blok' => 'Greenhouse A',
                'jenis' => 'Hama Tanaman',
                'tipe' => 'khusus',
                'detail' => [
                    'nama_hama' => 'Tikus',
                    'jumlah' => 2,
                    'status_hama' => 'Ada',
                    'pelapor' => 'Pak Adi',
                    'catatan' => 'Tikus merupakan hama pengerat yang menyerang tanaman melon.',
                    'foto' => null,
                ],
            ],
            [
                'tanggal' => now()->subDays(1)->format('Y-m-d'),
                'blok' => 'Greenhouse C',
                'jenis' => 'Laporan Harian',
                'tipe' => 'harian',
                'detail' => [
                    'kode_tanaman' => 'Melon #14',
                    'nama_tanaman' => 'Melon',
                    'penyiraman' => true,
                    'pruning' => true,
                    'nutrisi' => false,
                    'repotting' => false,
                    'tinggi' => 38.0,
                    'kondisi_daun' => 'Layu',
                    'status_pertumbuhan' => 'Vegetatif',
                    'pelapor' => 'Adi Santoso',
                    'catatan' => 'Kondisi daun layu, perlu penanganan segera.',
                    'foto' => null,
                ],
            ],
            [
                'tanggal' => now()->subDays(2)->format('Y-m-d'),
                'blok' => 'Greenhouse A',
                'jenis' => 'Hasil Panen',
                'tipe' => 'khusus',
                'detail' => [
                    'nama_komoditas' => 'Buah Melon',
                    'estimasi_panen' => 15,
                    'realisasi_panen' => 14,
                    'gagal_panen' => 1,
                    'umur_tanaman' => 60,
                    'satuan' => 'Kilogram - Kg',
                    'pelapor' => 'Adi Santoso',
                    'catatan' => 'Kebun A berhasil panen 14 buah melon, dengan berat total 15 Kg.',
                    'foto' => null,
                ],
            ],
            [
                'tanggal' => now()->subDays(2)->format('Y-m-d'),
                'blok' => 'Greenhouse B',
                'jenis' => 'Pemberian Nutrisi',
                'tipe' => 'khusus',
                'detail' => [
                    'kategori_inventaris' => 'Pupuk Cair',
                    'nama_inventaris' => 'Nutrisi AB Mix',
                    'jumlah_digunakan' => 2.5,
                    'satuan' => 'Liter',
                    'kode_tanaman' => 'Melon #7',
                    'keperluan' => 'Pemupukan rutin fase generatif',
                    'pelapor' => 'Pak Adi',
                    'catatan' => 'Pemberian nutrisi dilakukan pagi hari sebelum jam 9, konsentrasi EC 2.5 mS/cm.',
                    'foto' => null,
                ],
            ],
            [
                'tanggal' => now()->subDays(3)->format('Y-m-d'),
                'blok' => 'Greenhouse C',
                'jenis' => 'Tanaman Mati',
                'tipe' => 'khusus',
                'detail' => [
                    'kode_tanaman' => 'Melon #9',
                    'nama_tanaman' => 'Melon',
                    'penyebab_kematian' => 'Layu Fusarium',
                    'pelapor' => 'Adi Santoso',
                    'catatan' => 'Tanaman menunjukkan gejala layu total sejak 2 hari sebelumnya. Akar membusuk dengan warna coklat kemerahan khas Fusarium oxysporum.',
                    'foto' => null,
                ],
            ],
        ];
    }
}
