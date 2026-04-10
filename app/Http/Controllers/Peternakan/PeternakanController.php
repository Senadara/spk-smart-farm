<?php

namespace App\Http\Controllers\Peternakan;

use App\Http\Controllers\Controller;
use App\Services\PeternakanService;

class PeternakanController extends Controller
{
    protected $peternakanService;

    public function __construct(PeternakanService $peternakanService)
    {
        $this->peternakanService = $peternakanService;
    }

    /**
     * Dashboard utama peternakan — Decision Support & Operations.
     */
    public function index()
    {
        $kpiMetrics = $this->peternakanService->getKpiMetrics();
        $chartData = $this->peternakanService->getChartData();
        $barnEnvironment = $this->peternakanService->getBarnEnvironment();
        $produktivitas = $this->peternakanService->getProduktivitasData();
        $spkResults = $this->peternakanService->getSpkResults();

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

        $productionLog = $this->peternakanService->getProductionLog();

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
        $barns = $this->peternakanService->getBarnEnvironment()['barns'];
        $barn = collect($barns)->first(fn($b) => $b['id'] == $id) ?? $barns[0];
        $iotDevices = $this->peternakanService->getBarnIotDevices($barn);

        return view('peternakan.show', [
            'barn' => $this->peternakanService->getBarnDetail($barn),
            'sensors' => $this->peternakanService->getBarnSensors($barn),
            'sensorTrend' => $this->peternakanService->getBarnSensorTrend($barn['id'] ?? null),
            'kpi' => $this->peternakanService->getBarnKpi($barn),
            'productionLog' => $this->peternakanService->getBarnProductionLog($barn),
            'iotDevice' => $iotDevices[0] ?? null,
            'spkMessages' => $this->peternakanService->getBarnSpkMessages($barn),
            'activityLog' => $this->peternakanService->getBarnActivityLog($barn),
            'productivityTrend' => $this->peternakanService->getProductivityTrend(),
            'eggQuality' => $this->peternakanService->getEggQuality($barn),
        ]);
    }
}
