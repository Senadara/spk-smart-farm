<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SpkDashboardController extends Controller
{
    /**
     * Tampilkan halaman utama SPK Analysis Dashboard.
     */
    public function index(Request $request)
    {
        // Get Filters
        $komoditas = $request->input('komoditas', 'petelur');
        $lokasi = $request->input('lokasi', 'all');
        $historyId = $request->input('history_id', null);

        // Metriks, AHP, Tiket tetap
        $kpi = $this->getKpiMetrics();
        $recommendedSuppliers = $this->getAhpSawRanking();
        
        // History List
        $spkHistory = $this->getSpkHistory($lokasi);
        $activeHistory = collect($spkHistory)->firstWhere('id', $historyId) ?? $spkHistory[0];

        // Action Tickets related to this specific history run (mock: filtering by history ID or just returning mock)
        $actionTickets = $this->getActionTickets($activeHistory['id']);

        // Data Dinamis
        $fuzzyData = $this->getFuzzyStatus($activeHistory);
        $chartData = $this->getChartData($activeHistory);

        // Data that acts as props for x-fuzzy-decision-engine
        $barnsOption = [];
        // Return Data (simulasikan filter dropdown list)
        $filterOptions = [
            'komoditas' => [
                'petelur' => 'Ayam Petelur',
                'lele' => 'Perikanan Lele',
                'melon' => 'Perkebunan Melon',
            ],
            'lokasi' => [
                'all' => 'Semua Wilayah',
                'barn_a' => 'Barn A (Layer)',
                'barn_b' => 'Barn B (Layer)',
                'kolam_1' => 'Kolam Bioflok 1',
                'gh_1' => 'Greenhouse 1',
            ]
        ];

        foreach ($filterOptions['lokasi'] as $key => $val) {
            $barnsOption[] = ['id' => $key, 'name' => $val];
        }

        return view('spk.dashboard', compact(
            'komoditas', 'lokasi',
            'filterOptions', 'kpi', 'fuzzyData', 
            'chartData', 'recommendedSuppliers', 'actionTickets', 'barnsOption',
            'spkHistory', 'activeHistory'
        ));
    }

    // ═══════════════════════════════════════════════════════════════
    // DUMMY DATA METHODS
    // ═══════════════════════════════════════════════════════════════

    private function getKpiMetrics(): array
    {
        return [
            ['label' => 'Total Tiket Aktif', 'value' => '5', 'trend' => ['direction' => 'down', 'value' => '-2', 'status' => 'positive']],
            ['label' => 'Avg Score Supplier', 'value' => '84.5', 'trend' => ['direction' => 'up', 'value' => '+1.2', 'status' => 'positive']],
            ['label' => 'Avg HDP Performance', 'value' => '94.2%', 'trend' => ['direction' => 'stable', 'value' => '0', 'status' => 'neutral']],
            ['label' => 'Rata-rata FCR', 'value' => '2.14', 'trend' => ['direction' => 'up', 'value' => '+0.02', 'status' => 'negative']],
        ];
    }

    private function getSpkHistory($lokasi): array
    {
        return [
            [
                'id' => 'H-001', 'date' => 'Hari ini', 'time' => '14:30 WIB',
                'mode' => 'Gabungan', 'modeColor' => 'purple',
                'status' => 'Waspada Kritis', 'color' => 'red',
                'barn' => 'Barn A',
                'verdict' => 'Suhu & amonia tinggi berdampak pada produktivitas.',
                'raw' => ['suhu' => '32.5°C', 'kelembaban' => '60%', 'amonia' => '24 ppm', 'hdp' => '92.1%', 'fcr' => '2.14'],
            ],
            [
                'id' => 'H-002', 'date' => 'Hari ini', 'time' => '08:00 WIB',
                'mode' => 'Lingkungan', 'modeColor' => 'blue',
                'status' => 'Normal', 'color' => 'emerald',
                'barn' => 'Barn A',
                'verdict' => 'Parameter lingkungan stabil. Tidak ada anomali.',
                'raw' => ['suhu' => '24.0°C', 'kelembaban' => '65%', 'amonia' => '8 ppm', 'hdp' => '-', 'fcr' => '-'],
            ],
            [
                'id' => 'H-003', 'date' => 'Kemarin', 'time' => '19:45 WIB',
                'mode' => 'Produktivitas', 'modeColor' => 'amber',
                'status' => 'Waspada', 'color' => 'amber',
                'barn' => 'Semua Wilayah',
                'verdict' => 'HDP turun 2.3% dalam 3 hari terakhir.',
                'raw' => ['suhu' => '-', 'kelembaban' => '-', 'amonia' => '-', 'hdp' => '89.8%', 'fcr' => '2.20'],
            ],
            [
                'id' => 'H-004', 'date' => 'Kemarin', 'time' => '07:15 WIB',
                'mode' => 'Gabungan', 'modeColor' => 'purple',
                'status' => 'Normal', 'color' => 'emerald',
                'barn' => 'Barn B',
                'verdict' => 'Seluruh parameter kandang B dalam kondisi ideal.',
                'raw' => ['suhu' => '23.5°C', 'kelembaban' => '68%', 'amonia' => '7 ppm', 'hdp' => '95.0%', 'fcr' => '1.45'],
            ],
            [
                'id' => 'H-005', 'date' => '25 Okt 2023', 'time' => '14:00 WIB',
                'mode' => 'Lingkungan', 'modeColor' => 'blue',
                'status' => 'Normal', 'color' => 'emerald',
                'barn' => 'Barn C',
                'verdict' => 'Semua sensor dalam batas aman.',
                'raw' => ['suhu' => '24.5°C', 'kelembaban' => '62%', 'amonia' => '10 ppm', 'hdp' => '-', 'fcr' => '-'],
            ],
            [
                'id' => 'H-006', 'date' => '25 Okt 2023', 'time' => '08:30 WIB',
                'mode' => 'Gabungan', 'modeColor' => 'purple',
                'status' => 'Normal', 'color' => 'emerald',
                'barn' => 'Barn A',
                'verdict' => 'Performa puncak. Semua KPI di atas standar.',
                'raw' => ['suhu' => '23.0°C', 'kelembaban' => '66%', 'amonia' => '6 ppm', 'hdp' => '96.1%', 'fcr' => '1.42'],
            ],
        ];
    }

    private function getFuzzyStatus($activeHistory): array
    {
        // Base Environment Sensors
        $envSensors = [
            ['label' => 'Suhu Udara', 'percent' => 85, 'status' => 'warning', 'statusLabel' => 'Tinggi (32.5°C)'],
            ['label' => 'Kelembaban', 'percent' => 60, 'status' => 'normal', 'statusLabel' => 'Normal (60%)'],
            ['label' => 'Amonia', 'percent' => 70, 'status' => 'warning', 'statusLabel' => 'Waspada (24 ppm)'],
        ];

        // Base Productivity Sensors
        $prodSensors = [
            ['label' => 'HDP (Hen-Day)', 'percent' => 92, 'status' => 'warning', 'statusLabel' => 'Turun (92.1%)'],
            ['label' => 'FCR', 'percent' => 45, 'status' => 'normal', 'statusLabel' => 'Normal (2.12)'],
            ['label' => 'Mortalitas', 'percent' => 95, 'status' => 'normal', 'statusLabel' => 'Aman (0.1%)'],
        ];

        // Response Logic
        $response = [
            'confidence' => 86,
            'spider' => [85, 60, 70, 92, 45, 95], // All sensors connected to central spider
            'sensors' => [
                'lingkungan' => $envSensors,
                'produktivitas' => $prodSensors,
            ],
            'color' => 'red'
        ];

        // Add indicators for the right side of spider chart
        $response['indicators'] = [
            ['label' => 'HDP Score', 'value' => '92.1%', 'color' => 'amber'],
            ['label' => 'FCR Score', 'value' => '2.14', 'color' => 'emerald'],
            ['label' => 'Livability', 'value' => '99.9%', 'color' => 'emerald'],
        ];

        // Output specific to each column
        $response['results'] = [
            'lingkungan' => [
                'status' => $activeHistory['status'] === 'Normal' ? 'AMAN' : 'WASPADA',
                'statusColor' => $activeHistory['color'],
                'title' => $activeHistory['status'] === 'Normal' ? 'Parameter Stabil' : 'Suhu & Amonia Tinggi',
                'description' => $activeHistory['status'] === 'Normal' ? 'Fluktuasi suhu harian wajar. Kipas exhaust beroperasi optimal.' : 'Suhu 32.5°C dan Amonia mendekati batas. Segera nyalakan kipas exhaust.',
                'link' => '#',
            ],
            'produktivitas' => [
                'status' => 'WASPADA',
                'statusColor' => 'amber',
                'title' => 'Penurunan HDP',
                'description' => 'Produksi turun di angka 92.1%. Periksa asupan nutrisi.',
                'link' => '#',
            ],
            'gabungan' => [
                'status' => strtoupper($activeHistory['status']),
                'statusColor' => $activeHistory['color'],
                'title' => 'Diagnostic Verdict',
                'description' => $activeHistory['status'] === 'Normal' ? 'Aktivitas kandang normal. Tidak ada rekomendasi khusus untuk penanganan saat ini.' : 'Kondisi LINGKUNGAN buruk mulai berdampak pada PRODUKTIVITAS. Berikan multivitamin.',
                'link' => '#',
                'isMain' => true,
            ]
        ];

        return $response;
    }

    private function getChartData($activeHistory): array
    {
        // Dummy data for HDP vs Standard (Week 20 to 40)
        $weeks = [];
        $hdpActual = [];
        $hdpStandard = [];
        
        for ($w = 20; $w <= 40; $w++) {
            $weeks[] = 'W' . $w;
            // Standard Lohmann Brown curve
            if ($w < 25) { $std = 60 + (($w - 20) * 7); } 
            elseif ($w >= 25 && $w <= 30) { $std = 95 - (($w - 25) * 0.2); } 
            else { $std = 94 - (($w - 30) * 0.4); }
            $hdpStandard[] = round($std, 1);
            
            // Actual
            if ($w >= 38) { $hdpActual[] = round($std - 3 - ($w - 38), 1); } 
            elseif ($w <= 37 && $w > 20) { $hdpActual[] = round($std + mt_rand(-15, 15) / 10, 1); } 
            else { $hdpActual[] = null; }
        }

        return [
            'hdpComparison' => [
                'labels' => $weeks,
                'actual' => $hdpActual,
                'standard' => $hdpStandard,
            ],
            'causality' => [
                'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'fcr' => [2.1, 2.12, 2.15, 2.2, 2.18, 2.22, 2.25],            // Trend naik
                'suhu' => [29.5, 30.1, 31.0, 32.5, 31.8, 32.2, 32.8],       // Suhu naik mendorong FCR naik
                'kelembaban' => [65, 62, 58, 55, 60, 58, 54],               // Kelembaban turun saat Suhu naik
                'amonia' => [15, 18, 20, 24, 22, 25, 26]                    // Amonia menumpuk bertahap
            ]
        ];
    }

    private function getAhpSawRanking(): array
    {
        return [
            [
                'rank' => 1,
                'name' => 'PT Agrinusa Jaya',
                'category' => 'Pakan & Vitamin',
                'score' => 92.4,
                'price_rating' => 'Terjangkau',
                'lead_time' => '1 Hari',
                'quality' => 'Sangat Baik',
                'status' => 'Recommended'
            ],
            [
                'rank' => 2,
                'name' => 'CV Medion Farma',
                'category' => 'Obat & Vaksin',
                'score' => 88.7,
                'price_rating' => 'Standar',
                'lead_time' => '2 Hari',
                'quality' => 'Sangat Baik',
            ],
            [
                'rank' => 3,
                'name' => 'Sinar Gemilang Supply',
                'category' => 'Perlengkapan',
                'score' => 76.2,
                'price_rating' => 'Murah',
                'lead_time' => '4 Hari',
                'quality' => 'Cukup',
            ],
        ];
    }

    private function getActionTickets($historyId): array
    {
        return [
            ['id' => 'T-001', 'title' => 'Nyalakan Kipas Tambahan (Suhu Tinggi)', 'source' => "Analisa $historyId", 'priority' => 'High', 'status' => 'To Do', 'assignee' => 'Samsul'],
            ['id' => 'T-002', 'title' => 'Cek Kualitas Pakan (FCR Naik)', 'source' => "Analisa $historyId", 'priority' => 'Medium', 'status' => 'In Progress', 'assignee' => 'Budi'],
            ['id' => 'T-003', 'title' => 'Pesan Pakan Layer Grower', 'source' => "Analisa $historyId", 'priority' => 'Medium', 'status' => 'To Do', 'assignee' => 'Admin Rini'],
            ['id' => 'T-004', 'title' => 'Vaksinasi ND-IB Rutin', 'source' => 'Schedule', 'priority' => 'Low', 'status' => 'Done', 'assignee' => 'Samsul'],
        ];
    }
}
