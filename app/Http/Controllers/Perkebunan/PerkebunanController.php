<?php

namespace App\Http\Controllers\Perkebunan;

use App\Http\Controllers\Controller;
use App\Services\WeatherService;

class PerkebunanController extends Controller
{
    public function __construct(
        private WeatherService $weatherService
    ) {}

    /**
     * Dashboard monitoring utama perkebunan melon.
     */
    public function index()
    {
        return view('perkebunan.index', [
            'user'            => session('user', []),
            'weather'         => $this->weatherService->getForecast(),
            'kebunStats'      => $this->getKebunStats(),
            'evaluasiTerbaru' => $this->getEvaluasiTerbaru(),
            'rankingTerbaru'  => $this->getRankingTerbaru(),
            'sensorData'      => $this->getSensorData(),
            'alertSummary'    => $this->getAlertSummary(),
            'recentAlerts'    => $this->getRecentAlerts(),
        ]);
    }

    // ─── DASH-01: Dummy Data Methods ───────────────────────────

    // ─── Sub-task 1: Ringkasan Blok Kebun ────────────────────────
    // TODO: [DASH-01] Replace with actual Eloquent query
    // Real query: UnitBudidaya::whereHas('jenisBudidaya', fn($q) => $q->where('tipe', 'tumbuhan'))->where('status', 1)->where('isDeleted', 0)->count()
    private function getKebunStats(): array
    {
        return [
            'total_blok'     => 8,
            'aktif'          => 5,
            'nonaktif'       => 3,
            'total_tanaman'  => 480,
        ];
    }

    // ─── Sub-task 2: Evaluasi SPK Terbaru ────────────────────────
    // TODO: [DASH-01] Replace with actual Eloquent query
    // Real query: SpkMelonSesiPenilaian::where('isDeleted', 0)->orderByDesc('createdAt')->first()
    private function getEvaluasiTerbaru(): array
    {
        return [
            'nama_sesi'         => 'Evaluasi Produktivitas Siklus Maret 2026',
            'tipe'              => 'produktivitas',
            'status'            => 'selesai',
            'tanggal'           => '2026-03-01',
            'jumlah_alternatif' => 5,
            'dinilai_oleh'      => 'Dr. Ahmad (Pakar)',
        ];
    }

    // ─── Sub-task 3: Ranking Terbaru ─────────────────────────────
    // TODO: [DASH-01] Replace with actual Eloquent query
    // Real query: SpkMelonRanking::where('sesiId', $latestCompletedSession->id)->orderBy('peringkat')->with('unitBudidaya')->get()
    private function getRankingTerbaru(): array
    {
        return [
            'sesi_nama' => 'Evaluasi Produktivitas Siklus Maret 2026',
            'sesi_tipe' => 'produktivitas',
            'items' => [
                ['peringkat' => 1, 'blok' => 'Greenhouse A', 'skor' => 0.8542, 'status_keputusan' => 'disetujui'],
                ['peringkat' => 2, 'blok' => 'Greenhouse D', 'skor' => 0.7831, 'status_keputusan' => 'disetujui'],
                ['peringkat' => 3, 'blok' => 'Greenhouse B', 'skor' => 0.6925, 'status_keputusan' => 'ditunda'],
                ['peringkat' => 4, 'blok' => 'Greenhouse E', 'skor' => 0.5480, 'status_keputusan' => 'belum_divalidasi'],
                ['peringkat' => 5, 'blok' => 'Greenhouse C', 'skor' => 0.4120, 'status_keputusan' => 'ditolak'],
            ],
        ];
    }

    // ─── Sub-task 4: Data Sensor Terkini ─────────────────────────
    // TODO: [IOT-01] BLOCKED — Replace setelah diskusi akses Antares dengan Pak Dwi
    // Real query: Latest spk_melon_log_sensor per unitBudidayaId
    private function getSensorData(): array
    {
        return [
            [
                'name'       => 'Greenhouse A',
                'lastUpdate' => '5 menit lalu',
                'status'     => 'normal',
                'sensors'    => [
                    ['label' => 'pH Tanah',   'value' => 6.5,  'unit' => '',      'status' => 'normal'],
                    ['label' => 'EC',         'value' => 2.1,  'unit' => 'mS/cm', 'status' => 'normal'],
                    ['label' => 'Suhu',       'value' => 28.5, 'unit' => '°C',    'status' => 'normal'],
                    ['label' => 'Kelembaban', 'value' => 72,   'unit' => '%',     'status' => 'normal'],
                    ['label' => 'Nitrogen',   'value' => 45,   'unit' => 'ppm',   'status' => 'normal'],
                    ['label' => 'Fosfor',     'value' => 30,   'unit' => 'ppm',   'status' => 'normal'],
                    ['label' => 'Kalium',     'value' => 180,  'unit' => 'ppm',   'status' => 'normal'],
                ],
            ],
            [
                'name'       => 'Greenhouse B',
                'lastUpdate' => '5 menit lalu',
                'status'     => 'warning',
                'sensors'    => [
                    ['label' => 'pH Tanah',   'value' => 5.2,  'unit' => '',      'status' => 'warning'],
                    ['label' => 'EC',         'value' => 3.8,  'unit' => 'mS/cm', 'status' => 'warning'],
                    ['label' => 'Suhu',       'value' => 32.1, 'unit' => '°C',    'status' => 'warning'],
                    ['label' => 'Kelembaban', 'value' => 85,   'unit' => '%',     'status' => 'warning'],
                    ['label' => 'Nitrogen',   'value' => 25,   'unit' => 'ppm',   'status' => 'warning'],
                    ['label' => 'Fosfor',     'value' => 15,   'unit' => 'ppm',   'status' => 'normal'],
                    ['label' => 'Kalium',     'value' => 90,   'unit' => 'ppm',   'status' => 'warning'],
                ],
            ],
            [
                'name'       => 'Greenhouse C',
                'lastUpdate' => '12 menit lalu',
                'status'     => 'critical',
                'sensors'    => [
                    ['label' => 'pH Tanah',   'value' => 4.8,  'unit' => '',      'status' => 'critical'],
                    ['label' => 'EC',         'value' => 4.5,  'unit' => 'mS/cm', 'status' => 'critical'],
                    ['label' => 'Suhu',       'value' => 35.2, 'unit' => '°C',    'status' => 'critical'],
                    ['label' => 'Kelembaban', 'value' => 45,   'unit' => '%',     'status' => 'warning'],
                    ['label' => 'Nitrogen',   'value' => 12,   'unit' => 'ppm',   'status' => 'critical'],
                    ['label' => 'Fosfor',     'value' => 8,    'unit' => 'ppm',   'status' => 'critical'],
                    ['label' => 'Kalium',     'value' => 50,   'unit' => 'ppm',   'status' => 'critical'],
                ],
            ],
        ];
    }

    // ─── Sub-task 5: Alert Aktif ─────────────────────────────────
    // TODO: [ALERT-02] Replace with actual Eloquent query
    // Real query: SpkMelonAlert::where('status', 'belum_dibaca')->where('isDeleted', 0)->selectRaw('severity, count(*) as total')->groupBy('severity')->get()
    private function getAlertSummary(): array
    {
        return [
            'total'    => 5,
            'critical' => 1,
            'warning'  => 3,
            'info'     => 1,
        ];
    }

    // TODO: [ALERT-03] Replace with actual Eloquent query
    // Real query: SpkMelonAlert::where('status', 'belum_dibaca')->where('isDeleted', 0)->orderByDesc('createdAt')->limit(5)->get()
    private function getRecentAlerts(): array
    {
        return [
            [
                'id'       => 'alert-001',
                'message'  => 'pH Tanah Greenhouse C turun ke 4.8 (threshold: 5.5)',
                'severity' => 'critical',
                'block'    => 'Greenhouse C',
                'time'     => '10 menit lalu',
            ],
            [
                'id'       => 'alert-002',
                'message'  => 'Suhu Greenhouse B melebihi 32°C (threshold: 30°C)',
                'severity' => 'warning',
                'block'    => 'Greenhouse B',
                'time'     => '25 menit lalu',
            ],
            [
                'id'       => 'alert-003',
                'message'  => 'EC Greenhouse C mencapai 4.5 mS/cm (threshold: 3.5)',
                'severity' => 'warning',
                'block'    => 'Greenhouse C',
                'time'     => '30 menit lalu',
            ],
            [
                'id'       => 'alert-004',
                'message'  => 'Kelembaban Greenhouse B naik ke 85% (threshold: 80%)',
                'severity' => 'warning',
                'block'    => 'Greenhouse B',
                'time'     => '45 menit lalu',
            ],
            [
                'id'       => 'alert-005',
                'message'  => 'Evaluasi produktivitas siklus Maret 2026 telah selesai',
                'severity' => 'info',
                'block'    => '-',
                'time'     => '2 jam lalu',
            ],
        ];
    }
}
