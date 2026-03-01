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
        $productionLog = $this->getProductionLog();

        return view('peternakan.dashboard', compact(
            'kpiMetrics',
            'chartData',
            'barnEnvironment',
            'produktivitas',
            'spkResults',
            'productionLog',
        ));
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
            [
                'status' => 'Monitor',
                'statusColor' => 'amber',
                'title' => 'Decision: Check Ventilation.',
                'description' => 'Environment score is 76.4/100. Humidity is ideal, but elevated temperature and ammonia levels suggest reduced airflow efficiency.',
                'link' => '#',
            ],
            [
                'status' => 'Maintain',
                'statusColor' => 'blue',
                'title' => 'Decision: Keep Current Rations.',
                'description' => 'Health score is 92.5/100. Birds are performing optimally. Feed quality dip is negligible given high HDP output.',
                'link' => '#',
            ],
            [
                'status' => 'Excellent',
                'statusColor' => 'emerald',
                'title' => 'Decision: Expand Phase 2.',
                'description' => 'Combined weighted score indicates peak performance. Current environmental stress is minor compared to productivity gains.',
                'link' => '#',
                'isMain' => true,
            ],
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
