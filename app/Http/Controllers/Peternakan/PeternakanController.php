<?php

namespace App\Http\Controllers\Peternakan;

use App\Http\Controllers\Controller;

class PeternakanController extends Controller
{
    /**
     * Dashboard utama peternakan — Decision Support & Operations.
     */
    public function index()
    {
        $kpiMetrics = $this->getKpiMetrics();
        $chartData = $this->getChartData();
        $barnEnvironment = $this->getBarnEnvironment();
        $produktivitas = $this->getProduktivitasData();
        $spkResults = $this->getSpkResults();

        // Gabungkan sensor menjadi format terpisah (Lingkungan & Produktivitas)
        $fuzzySensors = [
            'lingkungan' => [],
            'produktivitas' => []
        ];

        if (isset($barnEnvironment['barns'][0]['sensors'])) {
             $fuzzySensors['lingkungan'] = $barnEnvironment['barns'][0]['sensors']; // Assuming taking Barn A for default view
        }

        // Mocking productivity sensors similar to SpkDashboardController
        $fuzzySensors['produktivitas'] = [
            ['label' => 'HDP (Hen-Day)', 'percent' => 94, 'status' => 'normal', 'statusLabel' => 'Optimal (94.5%)'],
            ['label' => 'FCR', 'percent' => 45, 'status' => 'normal', 'statusLabel' => 'Efisien (1.45)'],
            ['label' => 'Mortalitas', 'percent' => 95, 'status' => 'normal', 'statusLabel' => 'Aman (0.02%)'],
        ];

        $productionLog = $this->getProductionLog();

        return view('peternakan.dashboard', compact(
            'kpiMetrics',
            'chartData',
            'barnEnvironment',
            'fuzzySensors',
            'produktivitas',
            'spkResults',
            'productionLog',
        ));
    }

    /**
     * Detail halaman per-kandang — full information view for owner.
     */
    public function show($id)
    {
        $barns = $this->getBarnEnvironment()['barns'];
        $barn = $barns[$id] ?? $barns[0];
        $iotDevices = $this->getBarnIotDevices($barn);

        return view('peternakan.show', [
            'barn' => $this->getBarnDetail($barn),
            'sensors' => $this->getBarnSensors($barn),
            'sensorTrend' => $this->getBarnSensorTrend(),
            'kpi' => $this->getBarnKpi($barn),
            'productionLog' => $this->getBarnProductionLog($barn),
            'iotDevice' => $iotDevices[0] ?? null,
            'spkMessages' => $this->getBarnSpkMessages($barn),
            'activityLog' => $this->getBarnActivityLog($barn),
            'productivityTrend' => $this->getProductivityTrend(),
            'eggQuality' => $this->getEggQuality($barn),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // BARN DETAIL DUMMY DATA
    // ═══════════════════════════════════════════════════════════════

    private function getBarnDetail(array $barn): array
    {
        $details = [
            0 => ['flockAge' => '45 Minggu', 'totalBirds' => '12.450', 'capacity' => '15.000', 'breed' => 'ISA Brown', 'startDate' => '2025-01-15', 'location' => 'Blok A — Zona Utara'],
            1 => ['flockAge' => '28 Minggu', 'totalBirds' => '10.200', 'capacity' => '12.000', 'breed' => 'Lohmann Brown', 'startDate' => '2025-05-20', 'location' => 'Blok A — Zona Selatan'],
            2 => ['flockAge' => '12 Minggu', 'totalBirds' => '15.000', 'capacity' => '15.000', 'breed' => 'Hy-Line Brown', 'startDate' => '2025-09-01', 'location' => 'Blok B — Zona Utara'],
            3 => ['flockAge' => '38 Minggu', 'totalBirds' => '11.800', 'capacity' => '14.000', 'breed' => 'ISA Brown', 'startDate' => '2025-03-10', 'location' => 'Blok B — Zona Selatan'],
            4 => ['flockAge' => '52 Minggu', 'totalBirds' => '9.500', 'capacity' => '12.000', 'breed' => 'Lohmann Brown', 'startDate' => '2024-11-25', 'location' => 'Blok C — Zona Utara'],
            5 => ['flockAge' => '8 Minggu', 'totalBirds' => '13.000', 'capacity' => '15.000', 'breed' => 'Hy-Line Brown', 'startDate' => '2025-10-20', 'location' => 'Blok C — Zona Selatan'],
        ];
        $d = $details[$barn['id']] ?? $details[0];

        return array_merge($barn, $d, [
            'photo' => asset('images/barn-placeholder.jpg'),
        ]);
    }

    private function getBarnSensors(array $barn): array
    {
        return [
            ['label' => 'Suhu', 'value' => floatval(str_replace('°C', '', $barn['summary']['avg_temp'])), 'unit' => '°C', 'min' => 18, 'max' => 30, 'idealMin' => 20, 'idealMax' => 28, 'status' => $barn['sensors'][0]['status'] ?? 'normal', 'icon' => '🌡️'],
            ['label' => 'Kelembapan', 'value' => floatval(str_replace('%', '', $barn['summary']['humidity'])), 'unit' => '%', 'min' => 30, 'max' => 100, 'idealMin' => 50, 'idealMax' => 70, 'status' => $barn['sensors'][1]['status'] ?? 'normal', 'icon' => '💧'],
            ['label' => 'Amonia', 'value' => floatval(str_replace('ppm', '', $barn['summary']['ammonia'])), 'unit' => 'ppm', 'min' => 0, 'max' => 50, 'idealMin' => 0, 'idealMax' => 15, 'status' => $barn['sensors'][2]['status'] ?? 'normal', 'icon' => '🌬️'],
            ['label' => 'Cahaya', 'value' => floatval(str_replace(' lx', '', $barn['summary']['lux'])), 'unit' => 'lux', 'min' => 0, 'max' => 50, 'idealMin' => 15, 'idealMax' => 30, 'status' => $barn['sensors'][3]['status'] ?? 'normal', 'icon' => '☀️'],
        ];
    }

    private function getBarnSensorTrend(): array
    {
        $labels = [];
        $temp = [];
        $hum = [];
        $ammonia = [];
        $light = [];
        for ($i = 23; $i >= 0; $i--) {
            $labels[] = now()->subHours($i)->format('H:i');
            $temp[] = round(23 + mt_rand(0, 40) / 10, 1);
            $hum[] = round(58 + mt_rand(0, 120) / 10, 1);
            $ammonia[] = round(8 + mt_rand(0, 80) / 10, 1);
            $light[] = round(150 + mt_rand(0, 100), 0);
        }
        return ['labels' => $labels, 'temperature' => $temp, 'humidity' => $hum, 'ammonia' => $ammonia, 'light' => $light];
    }

    private function getBarnKpi(array $barn): array
    {
        $kpiSets = [
            0 => ['hdp' => 94.5, 'hhep' => 91.2, 'feedIntake' => 112, 'fcr' => 1.45, 'gradeTelur' => ['A' => 88, 'B' => 10, 'C' => 2], 'mortalitas' => 0.02, 'afkir' => 0.8, 'usiaAwalBertelur' => '18 Minggu', 'puncakProduksi' => '95.2% (Mg-32)'],
            1 => ['hdp' => 88.0, 'hhep' => 85.5, 'feedIntake' => 108, 'fcr' => 1.52, 'gradeTelur' => ['A' => 85, 'B' => 12, 'C' => 3], 'mortalitas' => 0.04, 'afkir' => 0.5, 'usiaAwalBertelur' => '19 Minggu', 'puncakProduksi' => '90.8% (Mg-28)'],
            2 => ['hdp' => 0, 'hhep' => 0, 'feedIntake' => 65, 'fcr' => 0, 'gradeTelur' => ['A' => 0, 'B' => 0, 'C' => 0], 'mortalitas' => 0.01, 'afkir' => 0.0, 'usiaAwalBertelur' => '-', 'puncakProduksi' => 'Belum Produksi'],
            3 => ['hdp' => 92.0, 'hhep' => 89.3, 'feedIntake' => 115, 'fcr' => 1.48, 'gradeTelur' => ['A' => 86, 'B' => 11, 'C' => 3], 'mortalitas' => 0.03, 'afkir' => 1.2, 'usiaAwalBertelur' => '18 Minggu', 'puncakProduksi' => '94.0% (Mg-30)'],
            4 => ['hdp' => 82.5, 'hhep' => 78.0, 'feedIntake' => 120, 'fcr' => 1.60, 'gradeTelur' => ['A' => 78, 'B' => 16, 'C' => 6], 'mortalitas' => 0.06, 'afkir' => 2.5, 'usiaAwalBertelur' => '18 Minggu', 'puncakProduksi' => '93.5% (Mg-30)'],
            5 => ['hdp' => 0, 'hhep' => 0, 'feedIntake' => 45, 'fcr' => 0, 'gradeTelur' => ['A' => 0, 'B' => 0, 'C' => 0], 'mortalitas' => 0.01, 'afkir' => 0.0, 'usiaAwalBertelur' => '-', 'puncakProduksi' => 'Belum Produksi'],
        ];
        return $kpiSets[$barn['id']] ?? $kpiSets[0];
    }

    private function getBarnProductionLog(array $barn): array
    {
        $log = [];
        for ($i = 0; $i < 7; $i++) {
            $log[] = [
                'date' => now()->subDays($i)->format('d M Y'),
                'eggs' => $barn['id'] < 2 ? number_format(10000 + mt_rand(0, 2000)) : ($barn['id'] < 4 ? number_format(8000 + mt_rand(0, 2000)) : '-'),
                'rejects' => $barn['id'] < 4 ? mt_rand(20, 50) : '-',
                'feedKg' => round(1200 + mt_rand(0, 200), 0),
                'waterL' => round(2400 + mt_rand(0, 400), 0),
                'mortality' => $barn['id'] < 4 ? mt_rand(0, 3) : mt_rand(0, 1),
                'hdp' => $barn['id'] < 2 ? round(93 + mt_rand(0, 30) / 10, 1) . '%' : ($barn['id'] < 4 ? round(88 + mt_rand(0, 40) / 10, 1) . '%' : '-'),
            ];
        }
        return $log;
    }

    private function getBarnIotDevices(array $barn): array
    {
        $deviceSets = [
            0 => [
                ['code' => 'DHT22-KA-01', 'name' => 'Sensor Suhu & Kelembapan', 'status' => 'active', 'lastData' => now()->subMinutes(2)->format('H:i'), 'protocol' => 'MQTT'],
                ['code' => 'MQ135-KA-01', 'name' => 'Sensor Amonia', 'status' => 'active', 'lastData' => now()->subMinutes(5)->format('H:i'), 'protocol' => 'MQTT'],
                ['code' => 'LDR-KA-01', 'name' => 'Sensor Cahaya', 'status' => 'active', 'lastData' => now()->subMinutes(3)->format('H:i'), 'protocol' => 'MQTT'],
            ],
            1 => [
                ['code' => 'DHT22-KB-01', 'name' => 'Sensor Suhu & Kelembapan', 'status' => 'active', 'lastData' => now()->subMinutes(1)->format('H:i'), 'protocol' => 'MQTT'],
                ['code' => 'MQ135-KB-01', 'name' => 'Sensor Amonia', 'status' => 'active', 'lastData' => now()->subMinutes(4)->format('H:i'), 'protocol' => 'MQTT'],
            ],
        ];

        return $deviceSets[$barn['id']] ?? [
            ['code' => 'DHT22-K' . chr(65 + $barn['id']) . '-01', 'name' => 'Sensor Suhu & Kelembapan', 'status' => 'active', 'lastData' => now()->subMinutes(3)->format('H:i'), 'protocol' => 'MQTT'],
        ];
    }

    private function getBarnSpkResult(array $barn): array
    {
        $results = [
            0 => ['status' => 'Excellent', 'color' => 'emerald', 'title' => 'Performa Optimal', 'description' => 'Lingkungan kandang dalam kondisi ideal. HDP tinggi di 94.5%, FCR efisien. Pertahankan manajemen pakan dan ventilasi saat ini.', 'score' => 92],
            1 => ['status' => 'Maintain', 'color' => 'blue', 'title' => 'Performa Baik — Tingkatkan', 'description' => 'Produksi masih dalam fase ramp-up. Kelembapan sedikit tinggi, pertimbangkan peningkatan sirkulasi udara untuk optimasi.', 'score' => 85],
            2 => ['status' => 'Growing', 'color' => 'purple', 'title' => 'Fase Pertumbuhan', 'description' => 'Flock masih dalam fase grower (12 minggu). Fokus pada kualitas pakan starter dan kontrol suhu untuk pertumbuhan optimal.', 'score' => 78],
            3 => ['status' => 'Monitor', 'color' => 'amber', 'title' => 'Perlu Perhatian Ventilasi', 'description' => 'Suhu 26°C mendekati batas atas. Ammonia 18ppm sudah moderate. Segera periksa sistem ventilasi dan kurangi kepadatan jika perlu.', 'score' => 72],
            4 => ['status' => 'Aging', 'color' => 'amber', 'title' => 'Pertimbangkan Afkir Bertahap', 'description' => 'Flock sudah 52 minggu. HDP turun ke 82.5% dengan FCR meningkat. Evaluasi titik impas untuk keputusan culling.', 'score' => 65],
            5 => ['status' => 'Alert', 'color' => 'red', 'title' => 'Suhu Kritis — Tindakan Segera', 'description' => 'Suhu kandang 27°C melebihi batas ideal. Ammonia 22ppm tinggi. Aktifkan ventilasi darurat dan monitor mortalitas.', 'score' => 52],
        ];
        return $results[$barn['id']] ?? $results[0];
    }

    private function getBarnSpkMessages(array $barn): array
    {
        return [
            ['mode' => 'Lingkungan', 'status' => $barn['status'] === 'danger' ? 'danger' : ($barn['status'] === 'warning' ? 'warning' : 'normal'), 'message' => $barn['status'] === 'danger' ? 'Suhu dan amonia melebihi ambang batas! Aktifkan ventilasi darurat.' : ($barn['status'] === 'warning' ? 'Parameter lingkungan mendekati batas atas. Periksa sirkulasi udara.' : 'Seluruh parameter lingkungan dalam kondisi ideal.')],
            ['mode' => 'Produktivitas', 'status' => $barn['id'] < 2 ? 'normal' : ($barn['id'] < 4 ? 'warning' : 'normal'), 'message' => $barn['id'] < 2 ? 'HDP dan FCR dalam range optimal. Pertahankan manajemen saat ini.' : ($barn['id'] < 4 ? 'Produksi belum mencapai puncak. Evaluasi komposisi pakan.' : 'Fase pertumbuhan normal, belum masuk produksi.')],
            ['mode' => 'Pakan', 'status' => 'normal', 'message' => 'Rasio konsumsi pakan dan air sesuai standar. FCR efisien.'],
            ['mode' => 'Kesehatan', 'status' => $barn['id'] === 4 ? 'warning' : 'normal', 'message' => $barn['id'] === 4 ? 'Mortalitas meningkat. Lakukan pemeriksaan kesehatan dan pertimbangkan afkir bertahap.' : 'Tingkat mortalitas dan afkir dalam batas normal. Tidak ada tindakan khusus.'],
        ];
    }

    private function getBarnActivityLog(array $barn): array
    {
        return [
            ['time' => now()->subMinutes(30)->format('H:i'), 'title' => 'Pengumpulan Telur Siang', 'desc' => 'Telur siang dikumpulkan. Total: 11.402 butir.', 'type' => 'success'],
            ['time' => now()->subHours(2)->format('H:i'), 'title' => 'Pemberian Pakan', 'desc' => 'Pakan fase layer didistribusikan. Total: 1.2 ton.', 'type' => 'info'],
            ['time' => now()->subHours(4)->format('H:i'), 'title' => 'Penyesuaian Ventilasi', 'desc' => 'Kecepatan kipas ditingkatkan 10% karena suhu naik.', 'type' => 'warning'],
            ['time' => now()->subHours(6)->format('H:i'), 'title' => 'Pengumpulan Telur Pagi', 'desc' => 'Telur pagi dikumpulkan. Total: 12.010 butir.', 'type' => 'success'],
            ['time' => now()->subHours(8)->format('H:i'), 'title' => 'Inspeksi Kandang Rutin', 'desc' => 'Pemeriksaan rutin selesai. Tidak ditemukan anomali.', 'type' => 'info'],
        ];
    }

    private function getProductivityTrend(): array
    {
        $labels = [];
        $hdp = [];
        $hhep = [];
        $fcr = [];
        $feedIntake = [];
        $mortality = [];
        for ($i = 29; $i >= 0; $i--) {
            $labels[] = now()->subDays($i)->format('d/m');
            $hdp[] = round(90 + mt_rand(0, 50) / 10, 1);
            $hhep[] = round(87 + mt_rand(0, 50) / 10, 1);
            $fcr[] = round(1.40 + mt_rand(0, 20) / 100, 2);
            $feedIntake[] = round(108 + mt_rand(0, 80) / 10, 1);
            $mortality[] = round(mt_rand(0, 8) / 100, 2);
        }
        return [
            'labels' => $labels,
            'hdp' => $hdp,
            'hhep' => $hhep,
            'fcr' => $fcr,
            'feedIntake' => $feedIntake,
            'mortality' => $mortality,
        ];
    }

    private function getEggQuality(array $barn): array
    {
        $qualitySets = [
            0 => ['small' => 5, 'medium' => 25, 'large' => 55, 'xl' => 15, 'brokenRate' => 1.2, 'brokenStatus' => 'normal', 'dirtyRate' => 2.1, 'dirtyStatus' => 'normal'],
            1 => ['small' => 8, 'medium' => 30, 'large' => 48, 'xl' => 14, 'brokenRate' => 1.5, 'brokenStatus' => 'normal', 'dirtyRate' => 2.8, 'dirtyStatus' => 'normal'],
            3 => ['small' => 6, 'medium' => 28, 'large' => 50, 'xl' => 16, 'brokenRate' => 1.8, 'brokenStatus' => 'normal', 'dirtyRate' => 3.5, 'dirtyStatus' => 'warning'],
            4 => ['small' => 10, 'medium' => 32, 'large' => 42, 'xl' => 16, 'brokenRate' => 2.5, 'brokenStatus' => 'warning', 'dirtyRate' => 4.2, 'dirtyStatus' => 'warning'],
        ];
        return $qualitySets[$barn['id']] ?? ['small' => 0, 'medium' => 0, 'large' => 0, 'xl' => 0, 'brokenRate' => 0, 'brokenStatus' => 'normal', 'dirtyRate' => 0, 'dirtyStatus' => 'normal'];
    }

    // ─── KPI Metrics Row ───────────────────────────────────────────
    private function getKpiMetrics(): array
    {
        return [
            ['label' => 'HDP %', 'value' => '94.5%', 'trend' => ['direction' => 'up', 'value' => '1.2%', 'status' => 'positive']],
            ['label' => 'FCR', 'value' => '1.45', 'trend' => ['direction' => 'down', 'value' => '0.05', 'status' => 'positive']],
            ['label' => 'Feed Intake', 'value' => '112g', 'trend' => ['direction' => 'up', 'value' => '7g', 'status' => 'warning']],
            ['label' => 'Egg Mass', 'value' => '58.2kg', 'trend' => ['direction' => 'up', 'value' => '1.5kg', 'status' => 'positive']],
            ['label' => 'Mortality', 'value' => '0.02%', 'trend' => ['direction' => 'stable', 'value' => 'Stable', 'status' => 'neutral']],
            ['label' => 'Avg Temp', 'value' => '24.5°C', 'trend' => ['direction' => 'up', 'value' => 'High', 'status' => 'warning']],
        ];
    }

    // ─── Production Efficiency Chart ───────────────────────────────
    private function getChartData(): array
    {
        return [
            'labels' => ['Mg 1', 'Mg 2', 'Mg 3', 'Mg 4', 'Mg 5', 'Mg 6', 'Mg 7', 'Mg 8'],
            'hdp' => [89, 90, 91, 93, 92, 94, 95, 94.5],
            'fcr' => [1.55, 1.52, 1.50, 1.48, 1.47, 1.46, 1.45, 1.45],
        ];
    }

    // ─── Barn Environment (per-barn IoT summary + sensors) ────────
    private function getBarnEnvironment(): array
    {
        return [
            'barns' => [
                [
                    'id' => 0,
                    'name' => 'Barn A',
                    'temp' => 24,
                    'status' => 'normal',
                    'sensors' => [
                        ['label' => 'Temperature (24.0°C)', 'percent' => 65, 'status' => 'normal', 'statusLabel' => 'Normal (0.78)'],
                        ['label' => 'Humidity (65%)', 'percent' => 65, 'status' => 'normal', 'statusLabel' => 'Ideal (0.85)'],
                        ['label' => 'Ammonia (8ppm)', 'percent' => 16, 'status' => 'normal', 'statusLabel' => 'Low (0.92)'],
                        ['label' => 'Light (20 lx)', 'percent' => 20, 'status' => 'normal', 'statusLabel' => 'Normal (0.80)'],
                    ],
                    'summary' => ['avg_temp' => '24.0°C', 'humidity' => '65%', 'ammonia' => '8ppm', 'ammonia_ok' => true, 'lux' => '20 lx'],
                ],
                [
                    'id' => 1,
                    'name' => 'Barn B',
                    'temp' => 25,
                    'status' => 'normal',
                    'sensors' => [
                        ['label' => 'Temperature (25.0°C)', 'percent' => 68, 'status' => 'normal', 'statusLabel' => 'Normal (0.72)'],
                        ['label' => 'Humidity (70%)', 'percent' => 70, 'status' => 'normal', 'statusLabel' => 'Ideal (0.80)'],
                        ['label' => 'Ammonia (10ppm)', 'percent' => 20, 'status' => 'normal', 'statusLabel' => 'Low (0.88)'],
                        ['label' => 'Light (22 lx)', 'percent' => 22, 'status' => 'normal', 'statusLabel' => 'Normal (0.78)'],
                    ],
                    'summary' => ['avg_temp' => '25.0°C', 'humidity' => '70%', 'ammonia' => '10ppm', 'ammonia_ok' => true, 'lux' => '22 lx'],
                ],
                [
                    'id' => 2,
                    'name' => 'Barn C',
                    'temp' => 24,
                    'status' => 'normal',
                    'sensors' => [
                        ['label' => 'Temperature (24.5°C)', 'percent' => 70, 'status' => 'warning', 'statusLabel' => 'Warm (0.65)'],
                        ['label' => 'Humidity (62%)', 'percent' => 62, 'status' => 'normal', 'statusLabel' => 'Ideal (0.82)'],
                        ['label' => 'Ammonia (12ppm)', 'percent' => 24, 'status' => 'normal', 'statusLabel' => 'Low (0.88)'],
                        ['label' => 'Light (18 lx)', 'percent' => 18, 'status' => 'normal', 'statusLabel' => 'Normal (0.75)'],
                    ],
                    'summary' => ['avg_temp' => '24.5°C', 'humidity' => '62%', 'ammonia' => '12ppm', 'ammonia_ok' => true, 'lux' => '18 lx'],
                ],
                [
                    'id' => 3,
                    'name' => 'Barn D',
                    'temp' => 26,
                    'status' => 'warning',
                    'sensors' => [
                        ['label' => 'Temperature (26.0°C)', 'percent' => 75, 'status' => 'warning', 'statusLabel' => 'Warm (0.58)'],
                        ['label' => 'Humidity (58%)', 'percent' => 58, 'status' => 'warning', 'statusLabel' => 'Low (0.60)'],
                        ['label' => 'Ammonia (18ppm)', 'percent' => 36, 'status' => 'warning', 'statusLabel' => 'Moderate (0.70)'],
                        ['label' => 'Light (25 lx)', 'percent' => 25, 'status' => 'normal', 'statusLabel' => 'Normal (0.72)'],
                    ],
                    'summary' => ['avg_temp' => '26.0°C', 'humidity' => '58%', 'ammonia' => '18ppm', 'ammonia_ok' => false, 'lux' => '25 lx'],
                ],
                [
                    'id' => 4,
                    'name' => 'Barn E',
                    'temp' => 24,
                    'status' => 'normal',
                    'sensors' => [
                        ['label' => 'Temperature (24.0°C)', 'percent' => 65, 'status' => 'normal', 'statusLabel' => 'Normal (0.80)'],
                        ['label' => 'Humidity (68%)', 'percent' => 68, 'status' => 'normal', 'statusLabel' => 'Ideal (0.84)'],
                        ['label' => 'Ammonia (9ppm)', 'percent' => 18, 'status' => 'normal', 'statusLabel' => 'Low (0.90)'],
                        ['label' => 'Light (21 lx)', 'percent' => 21, 'status' => 'normal', 'statusLabel' => 'Normal (0.79)'],
                    ],
                    'summary' => ['avg_temp' => '24.0°C', 'humidity' => '68%', 'ammonia' => '9ppm', 'ammonia_ok' => true, 'lux' => '21 lx'],
                ],
                [
                    'id' => 5,
                    'name' => 'Barn F',
                    'temp' => 27,
                    'status' => 'danger',
                    'sensors' => [
                        ['label' => 'Temperature (27.0°C)', 'percent' => 80, 'status' => 'danger', 'statusLabel' => 'Hot (0.45)'],
                        ['label' => 'Humidity (55%)', 'percent' => 55, 'status' => 'warning', 'statusLabel' => 'Low (0.55)'],
                        ['label' => 'Ammonia (22ppm)', 'percent' => 44, 'status' => 'warning', 'statusLabel' => 'Moderate (0.62)'],
                        ['label' => 'Light (30 lx)', 'percent' => 30, 'status' => 'normal', 'statusLabel' => 'Normal (0.70)'],
                    ],
                    'summary' => ['avg_temp' => '27.0°C', 'humidity' => '55%', 'ammonia' => '22ppm', 'ammonia_ok' => false, 'lux' => '30 lx'],
                ],
            ],
        ];
    }

    // ─── Productivity Spider Chart (HDP, Feed Intake, FCR, Mortalitas, Umur Biologis)
    private function getProduktivitasData(): array
    {
        return [
            'spider' => [
                'labels' => ['HDP', 'Feed Intake', 'FCR', 'Mortalitas', 'Umur Biologis'],
                'values' => [94, 78, 85, 95, 72],
            ],
            'indicators' => [
                ['label' => 'HDP', 'value' => 'High', 'color' => 'emerald'],
                ['label' => 'Feed Intake', 'value' => 'Good', 'color' => 'emerald'],
                ['label' => 'FCR', 'value' => 'Good', 'color' => 'emerald'],
                ['label' => 'Mortalitas', 'value' => 'Low', 'color' => 'emerald'],
                ['label' => 'Umur Biologis', 'value' => 'Avg', 'color' => 'amber'],
            ],
        ];
    }

    // ─── SPK Analysis Results ──────────────────────────────────────
    private function getSpkResults(): array
    {
        return [
            'lingkungan' => [
                'status' => 'Monitor',
                'statusColor' => 'amber',
                'title' => 'Decision: Check Ventilation.',
                'description' => 'Environment score is 76.4/100. Humidity is ideal, but elevated temperature and ammonia levels suggest reduced airflow efficiency.',
                'link' => '#'
            ],
            'produktivitas' => [
                'status' => 'Maintain',
                'statusColor' => 'blue',
                'title' => 'Decision: Keep Current Rations.',
                'description' => 'Health score is 92.5/100. Birds are performing optimally. Feed quality dip is negligible given high HDP output.',
                'link' => '#'
            ],
            'gabungan' => [
                'status' => 'Excellent',
                'statusColor' => 'emerald',
                'title' => 'Decision: Expand Phase 2.',
                'description' => 'Combined weighted score indicates peak performance. Current environmental stress is minor compared to productivity gains.',
                'link' => '#',
                'isMain' => true
            ]
        ];
    }

    // ─── Daily Production Log ──────────────────────────────────────
    private function getProductionLog(): array
    {
        return [
            ['date' => 'Oct 24, 2023', 'barn' => 'Barn A', 'flock_age' => '45 Weeks', 'birds' => '12,450', 'eggs' => '11,850', 'rejects' => '42', 'status' => 'Optimal'],
            ['date' => 'Oct 24, 2023', 'barn' => 'Barn B', 'flock_age' => '28 Weeks', 'birds' => '10,200', 'eggs' => '9,800', 'rejects' => '35', 'status' => 'Optimal'],
            ['date' => 'Oct 24, 2023', 'barn' => 'Barn C', 'flock_age' => '12 Weeks', 'birds' => '15,000', 'eggs' => '-', 'rejects' => '-', 'status' => 'Attention'],
            ['date' => 'Oct 23, 2023', 'barn' => 'Barn A', 'flock_age' => '45 Weeks', 'birds' => '12,452', 'eggs' => '11,900', 'rejects' => '38', 'status' => 'Optimal'],
        ];
    }
}
