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
        $sensorData    = $this->getLatestSensorData($selectedBlokId);
        $sensorHistory = $this->getSensorHistory($selectedBlokId, (int)$periode);
        $dailyReports  = $this->getLatestDailyReports($selectedBlokId, $periode);

        return view('plant-monitoring.index', compact(
            'blokKebun',
            'selectedBlokId',
            'periode',
            'sensorData',
            'sensorHistory',
            'dailyReports'
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
     * Ambil ringkasan laporan harian terbaru per blok kebun.
     * Sumber: tabel harianKebun JOIN laporan (filter tipe = 'harian').
     * Status: TABEL KOSONG. Scaffold query + return dummy.
     * Tabel harianKebun dan laporan milik Node.js/Sequelize — READ ONLY.
     */
    private function getLatestDailyReports(?string $blokKebunId, string $periode): array
    {
        // --- SCAFFOLD ELOQUENT QUERY (aktifkan saat data tersedia) ---
        // $query = DB::table('harianKebun as hk')
        //     ->join('laporan as l', 'hk.LaporanId', '=', 'l.id')
        //     ->join('unitBudidaya as u', 'l.UnitBudidayaId', '=', 'u.id')
        //     ->where('l.tipe', 'harian')
        //     ->where('hk.isDeleted', 0)
        //     ->where('l.isDeleted', 0)
        //     ->whereBetween('l.createdAt', [
        //         now()->subDays((int)$periode)->toDateString() . ' 00:00:00',
        //         now()->toDateString() . ' 23:59:59'
        //     ]);
        //
        // if ($blokKebunId) {
        //     $query->where('l.UnitBudidayaId', $blokKebunId);
        // }
        //
        // $results = $query->select(/* columns */)->orderBy('l.createdAt', 'desc')->limit(10)->get()->toArray();
        // if (!empty($results)) return $results;
        // --- END SCAFFOLD ---

        // TODO: [IOT-01] Replace dummy setelah data laporan tersedia
        return [
            [
                'tanggal'        => now()->subDays(0)->format('Y-m-d'),
                'namaBlok'       => 'Greenhouse A',
                'tinggiTanaman'  => 45.5,
                'kondisiDaun'    => 'sehat',
                'catatan'        => 'Pertumbuhan normal, tidak ada kelainan pada daun.',
            ],
            [
                'tanggal'        => now()->subDays(0)->format('Y-m-d'),
                'namaBlok'       => 'Greenhouse B',
                'tinggiTanaman'  => 42.0,
                'kondisiDaun'    => 'kuning',
                'catatan'        => 'Beberapa daun menguning, perlu pengecekan nutrisi.',
            ],
            [
                'tanggal'        => now()->subDays(1)->format('Y-m-d'),
                'namaBlok'       => 'Greenhouse A',
                'tinggiTanaman'  => 44.2,
                'kondisiDaun'    => 'sehat',
                'catatan'        => 'Semua tanaman tumbuh normal.',
            ],
            [
                'tanggal'        => now()->subDays(1)->format('Y-m-d'),
                'namaBlok'       => 'Greenhouse C',
                'tinggiTanaman'  => 38.0,
                'kondisiDaun'    => 'layu',
                'catatan'        => 'Tanaman menunjukkan tanda stres panas.',
            ],
        ];
    }
}
