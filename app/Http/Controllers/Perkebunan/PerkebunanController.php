<?php

namespace App\Http\Controllers\Perkebunan;

use App\Http\Controllers\Controller;

class PerkebunanController extends Controller
{
    /**
     * DASH-01: Dashboard monitoring utama perkebunan melon (REDESIGN v2.1).
     * Layout 6 section mirroring Dashboard Peternakan.
     */
    public function index()
    {
        return view('perkebunan.index', [
            'user'                => session('user', []),
            'kebunStats'          => $this->getKebunStats(),
            'averagePreferensi'   => $this->getAveragePreferensi(),
            'evaluasiTerbaru'     => $this->getEvaluasiTerbaru(),
            'distribusiBobot'     => $this->getDistribusiBobot(),
            'kondisiGreenhouse'   => $this->getKondisiGreenhouse(),
            'rankingTerbaru'      => $this->getRankingTerbaru(),
            'ringkasanRekomendasi'=> $this->getRingkasanRekomendasi(),
            'alertSummary'        => $this->getAlertSummary(),
            'recentAlerts'        => $this->getRecentAlerts(),
        ]);
    }

    // ─── DASH-01: Data Methods ───────────────────────────────────

    // ─── Sub-task 1: Ringkasan Blok Kebun ────────────────────────
    // TODO: [DASH-01] Replace with actual Eloquent query
    // Real query: UnitBudidaya::whereHas('jenisBudidaya', fn($q) => $q->where('tipe', 'tumbuhan'))->where('status', 1)->where('isDeleted', 0)->count()
    private function getKebunStats(): array
    {
        return [
            'total_blok' => 8,
            'aktif'      => 5,
            'nonaktif'   => 3,
        ];
    }

    // ─── Sub-task 2: Rata-rata Skor Preferensi TOPSIS ────────────
    // TODO: [DASH-01] Query AVG(skorPreferensi) dari spk_melon_ranking WHERE sesiId = latest completed session
    // Compare with previous completed session for trend
    private function getAveragePreferensi(): array
    {
        return [
            'current'  => 0.6580,
            'previous' => 0.6120,
            'trend'    => 'up', // 'up' | 'down' | 'stable'
        ];
    }

    // ─── Sub-task 3: Evaluasi SPK Terbaru ────────────────────────
    // TODO: [DASH-01] Replace with actual Eloquent query
    // Real query: SpkMelonSesiPenilaian::where('isDeleted', 0)->orderByDesc('createdAt')->first()
    private function getEvaluasiTerbaru(): array
    {
        return [
            'nama_sesi'         => 'Evaluasi Produktivitas Siklus Maret 2026',
            'tipe'              => 'produktivitas',
            'status'            => 'selesai',
            'tanggal'           => '2026-03-01',
            'periode_start'     => '2026-01-01',
            'periode_end'       => '2026-03-01',
            'cr'                => 0.0423,
            'cr_konsisten'      => true,
            'jumlah_alternatif' => 5,
            'dinilai_oleh'      => 'Dr. Ahmad (Pakar)',
        ];
    }

    // ─── Sub-task 4: Distribusi Bobot Kriteria ───────────────────
    // TODO: [DASH-01] Query spk_melon_bobot JOIN spk_melon_kriteria
    // WHERE sesiId = latest completed session, ORDER BY bobotAkhir DESC
    private function getDistribusiBobot(): array
    {
        return [
            'sesi_nama' => 'Evaluasi Produktivitas Siklus Maret 2026',
            'items' => [
                [
                    'kode'       => 'C1',
                    'nama'       => 'Suhu Rata-rata',
                    'bobotAkhir' => 0.32,
                    'tipe'       => 'cost',
                    'spiSumber'  => 'sensor',
                ],
                [
                    'kode'       => 'C2',
                    'nama'       => 'EC',
                    'bobotAkhir' => 0.25,
                    'tipe'       => 'cost',
                    'spiSumber'  => 'sensor',
                ],
                [
                    'kode'       => 'C3',
                    'nama'       => 'Tinggi Tanaman',
                    'bobotAkhir' => 0.18,
                    'tipe'       => 'benefit',
                    'spiSumber'  => 'harianKebun',
                ],
                [
                    'kode'       => 'C4',
                    'nama'       => 'Realisasi Panen',
                    'bobotAkhir' => 0.15,
                    'tipe'       => 'benefit',
                    'spiSumber'  => 'panenKebun',
                ],
                [
                    'kode'       => 'C5',
                    'nama'       => 'Serangan Hama',
                    'bobotAkhir' => 0.10,
                    'tipe'       => 'cost',
                    'spiSumber'  => 'harianKebun',
                ],
            ],
        ];
    }

    // ─── Sub-task 5: Kondisi Greenhouse ──────────────────────────
    private function getKondisiGreenhouse(): array
    {
        return [
            'blocks' => [
                [
                    'name' => 'GH A', 'suhu' => 28, 'status' => 'normal',
                    'summary' => ['ph' => 6.5, 'ph_status' => 'normal', 'suhu' => 28.0, 'suhu_status' => 'normal', 'ec' => 3.0, 'ec_status' => 'normal', 'kelembapan' => 70, 'kelembapan_status' => 'normal']
                ],
                [
                    'name' => 'GH B', 'suhu' => 32, 'status' => 'warning',
                    'summary' => ['ph' => 5.8, 'ph_status' => 'warning', 'suhu' => 32.0, 'suhu_status' => 'warning', 'ec' => 3.5, 'ec_status' => 'normal', 'kelembapan' => 85, 'kelembapan_status' => 'warning']
                ],
                [
                    'name' => 'GH C', 'suhu' => 35, 'status' => 'critical',
                    'summary' => ['ph' => 5.5, 'ph_status' => 'warning', 'suhu' => 35.0, 'suhu_status' => 'critical', 'ec' => 4.5, 'ec_status' => 'warning', 'kelembapan' => 90, 'kelembapan_status' => 'critical']
                ],
                [
                    'name' => 'GH D', 'suhu' => 26, 'status' => 'normal',
                    'summary' => ['ph' => 6.2, 'ph_status' => 'normal', 'suhu' => 26.0, 'suhu_status' => 'normal', 'ec' => 2.5, 'ec_status' => 'normal', 'kelembapan' => 65, 'kelembapan_status' => 'normal']
                ],
                [
                    'name' => 'GH E', 'suhu' => 29, 'status' => 'normal',
                    'summary' => ['ph' => 6.8, 'ph_status' => 'normal', 'suhu' => 29.0, 'suhu_status' => 'normal', 'ec' => 2.8, 'ec_status' => 'normal', 'kelembapan' => 75, 'kelembapan_status' => 'normal']
                ],
            ],
        ];
    }

    // ─── Sub-task 6: Ranking Terbaru ─────────────────────────────
    // TODO: [DASH-01] Replace with actual Eloquent query
    // Real query: SpkMelonRanking::where('sesiId', $latestCompletedSession->id)->orderBy('peringkat')->with('unitBudidaya')->get()
    private function getRankingTerbaru(): array
    {
        return [
            'sesi_nama' => 'Evaluasi Produktivitas Siklus Maret 2026',
            'sesi_tipe' => 'produktivitas',
            'items' => [
                ['peringkat' => 1, 'blok' => 'Greenhouse A', 'skor' => 0.8542, 'status_keputusan' => 'disetujui',         'faktor_dominan' => ['Suhu', 'EC']],
                ['peringkat' => 2, 'blok' => 'Greenhouse D', 'skor' => 0.7831, 'status_keputusan' => 'disetujui',         'faktor_dominan' => ['Panen', 'EC']],
                ['peringkat' => 3, 'blok' => 'Greenhouse B', 'skor' => 0.6925, 'status_keputusan' => 'ditunda',           'faktor_dominan' => ['Suhu', 'Tinggi']],
                ['peringkat' => 4, 'blok' => 'Greenhouse E', 'skor' => 0.5480, 'status_keputusan' => 'belum_divalidasi',  'faktor_dominan' => ['Hama']],
                ['peringkat' => 5, 'blok' => 'Greenhouse C', 'skor' => 0.4120, 'status_keputusan' => 'ditolak',           'faktor_dominan' => ['pH', 'Hama']],
            ],
        ];
    }

    // ─── Sub-task 7: Ringkasan Rekomendasi ───────────────────────
    // TODO: [DASH-01] Filter blok dengan skor < avg OR status ditolak/ditunda
    // Compose narrative dari ranking + faktor dominan data
    private function getRingkasanRekomendasi(): array
    {
        return [
            [
                'blok'      => 'Greenhouse E',
                'peringkat' => 4,
                'skor'      => 0.5480,
                'badge'     => 'perhatian',
                'narasi'    => 'Skor di bawah rata-rata (0.5480). Faktor utama: serangan hama yang mempengaruhi produktivitas.',
            ],
            [
                'blok'      => 'Greenhouse C',
                'peringkat' => 5,
                'skor'      => 0.4120,
                'badge'     => 'kritis',
                'narasi'    => 'Skor preferensi terendah (0.4120). Faktor utama: pH tanah dan serangan hama yang mempengaruhi skor secara signifikan.',
            ],
        ];
    }

    // ─── Sub-task 8: Alert Aktif ─────────────────────────────────
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
                'id'              => 'alert-001',
                'message'         => 'pH Tanah Greenhouse C turun ke 4.8 (threshold: 5.5)',
                'severity'        => 'critical',
                'block'           => 'Greenhouse C',
                'time'            => '10 menit lalu',
                'ranking_context' => 'Peringkat #5 (skor: 0.4120)',
            ],
            [
                'id'              => 'alert-002',
                'message'         => 'Suhu Greenhouse B melebihi 32°C (threshold: 30°C)',
                'severity'        => 'warning',
                'block'           => 'Greenhouse B',
                'time'            => '25 menit lalu',
                'ranking_context' => 'Peringkat #3 (skor: 0.6925)',
            ],
            [
                'id'              => 'alert-003',
                'message'         => 'EC Greenhouse C mencapai 4.5 mS/cm (threshold: 3.5)',
                'severity'        => 'warning',
                'block'           => 'Greenhouse C',
                'time'            => '30 menit lalu',
                'ranking_context' => 'Peringkat #5 (skor: 0.4120)',
            ],
            [
                'id'              => 'alert-004',
                'message'         => 'Kelembaban Greenhouse B naik ke 85% (threshold: 80%)',
                'severity'        => 'warning',
                'block'           => 'Greenhouse B',
                'time'            => '45 menit lalu',
                'ranking_context' => 'Peringkat #3 (skor: 0.6925)',
            ],
            [
                'id'              => 'alert-005',
                'message'         => 'Evaluasi produktivitas siklus Maret 2026 telah selesai',
                'severity'        => 'info',
                'block'           => '-',
                'time'            => '2 jam lalu',
                'ranking_context' => null,
            ],
        ];
    }
}
