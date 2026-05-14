<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use App\Models\SpkFuzzyLog;
use App\Services\Fuzzy\InputResolver;
use App\Services\Fuzzy\MamdaniEngine;
use App\Services\Fuzzy\NarrativeGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpkDashboardController extends Controller
{
    /**
     * Tampilkan halaman utama SPK Analysis Dashboard.
     */
    public function index(Request $request)
    {
        $komoditas = $request->input('komoditas', 'petelur');
        $coopId    = $request->input('coop_id');   // null = global
        $historyId = $request->input('history_id');

        // ── Jalankan Fuzzy Engine untuk mendapat data terkini ────────
        $latestResult = $this->runFuzzyEngine($coopId);

        // ── Metriks KPI (dari AHP-SAW module, tetap) ─────────────────
        $kpi                  = $this->getKpiMetrics();
        $recommendedSuppliers = $this->getAhpSawRanking();

        // ── History dari SpkFuzzyLog ──────────────────────────────────
        $spkHistory    = $this->getSpkHistory($coopId);
        $activeHistory = collect($spkHistory)->firstWhere('id', $historyId) ?? ($spkHistory[0] ?? $this->emptyHistory());

        // ── Fuzzy Status & Chart dari hasil engine ────────────────────
        $fuzzyData  = $this->getFuzzyStatus($latestResult);
        $chartData  = $this->getChartData();

        // ── Action Tickets (tetap mock sampai modul tersedia) ─────────
        $actionTickets = $this->getActionTickets($activeHistory['id'] ?? 'N/A');

        // ── Kandang options dari unitBudidaya ─────────────────────────
        $jenis       = DB::table('jenisBudidaya')->where('nama', 'like', '%Ayam Petelur%')->where('isDeleted', 0)->first();
        $barnsOption = DB::table('unitBudidaya')
            ->where('jenisBudidayaId', $jenis?->id)
            ->where('status', 1)
            ->where('isDeleted', 0)
            ->get(['id', 'nama'])
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->nama])
            ->prepend(['id' => null, 'name' => 'Semua Kandang (Global)'])
            ->toArray();

        $filterOptions = [
            'komoditas' => ['petelur' => 'Ayam Petelur', 'lele' => 'Perikanan Lele', 'melon' => 'Perkebunan Melon'],
        ];

        return view('spk.dashboard', compact(
            'komoditas', 'coopId', 'filterOptions', 'kpi', 'fuzzyData',
            'chartData', 'recommendedSuppliers', 'actionTickets', 'barnsOption',
            'spkHistory', 'activeHistory', 'latestResult'
        ));
    }

    // ═══════════════════════════════════════════════════════════════
    // REAL DATA METHODS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Jalankan Mamdani Engine dan return hasil lengkap.
     */
    private function runFuzzyEngine(?string $coopId): array
    {
        try {
            $resolver  = app(InputResolver::class);
            $engine    = app(MamdaniEngine::class);
            $narrator  = app(NarrativeGenerator::class);

            $inputs    = $resolver->resolve($coopId);
            $result    = $engine->processCascaded($inputs);
            $barnName  = $coopId ? DB::table('unitBudidaya')->where('id', $coopId)->value('nama') : null;
            $narrative = $narrator->generate($result, $barnName);

            return array_merge($result, ['narrative' => $narrative, 'error' => null]);
        } catch (\Throwable $e) {
            \Log::error('[SpkDashboard] FuzzyEngine error: ' . $e->getMessage());
            return ['error' => $e->getMessage(), 'inputs' => [], 'lingkungan' => [], 'kesehatan' => [], 'kausalitas' => [], 'narrative' => null];
        }
    }

    /**
     * Ambil riwayat analisa dari spk_fuzzy_logs.
     */
    private function getSpkHistory(?string $coopId): array
    {
        $colorMap = ['Optimal' => 'emerald', 'Baik' => 'blue', 'Waspada' => 'amber', 'Buruk' => 'red'];

        $query = SpkFuzzyLog::query()->orderBy('createdAt', 'desc')->limit(10);
        if ($coopId) {
            $query->where('unit_budidaya_id', $coopId);
        }

        $logs = $query->get();

        if ($logs->isEmpty()) {
            return [$this->emptyHistory()];
        }

        return $logs->map(function ($log) use ($colorMap) {
            $lingkLabel = $log->status_lingkungan ?? 'Tidak Diketahui';
            $barnName   = $log->unit_budidaya_id
                ? DB::table('unitBudidaya')->where('id', $log->unit_budidaya_id)->value('nama')
                : 'Global';

            return [
                'id'        => $log->id,
                'date'      => Carbon::parse($log->createdAt)->locale('id')->diffForHumans(),
                'time'      => Carbon::parse($log->createdAt)->format('H:i') . ' WIB',
                'mode'      => 'Fuzzy Mamdani',
                'modeColor' => 'purple',
                'barn'      => $barnName,
                'status'    => $log->diagnosis_kausalitas ?? $lingkLabel,
                'color'     => $colorMap[$lingkLabel] ?? 'gray',
                'verdict'   => $log->narrative ? \Str::limit(strip_tags($log->narrative), 150) : '-',
                'recommendation' => $log->recommendation ?? '-',
                'raw'       => is_array($log->input_json) ? $log->input_json : [],
            ];
        })->toArray();
    }

    /**
     * Build fuzzyData untuk view dari hasil engine terkini.
     */
    private function getFuzzyStatus(array $result): array
    {
        $lingkungan = $result['lingkungan'] ?? [];
        $kesehatan  = $result['kesehatan']  ?? [];
        $kausalitas = $result['kausalitas'] ?? [];
        $inputs     = $result['inputs']     ?? [];

        $colorMap = ['Optimal' => 'emerald', 'Baik' => 'blue', 'Waspada' => 'amber', 'Buruk' => 'red'];

        $lingkLabel   = $lingkungan['label']  ?? 'Tidak Diketahui';
        $kesehatLabel = $kesehatan['label']   ?? 'Tidak Diketahui';
        $lingkScore   = (float) ($lingkungan['value'] ?? 0);
        $kesehatScore = (float) ($kesehatan['value']  ?? 0);

        // Sensor bars dari fuzzified (Engine 1)
        $fuzzLingk = $lingkungan['fuzzified'] ?? [];
        $fuzzKes   = $kesehatan['fuzzified']  ?? [];

        $suhuPct  = isset($inputs['suhu'])      ? min(($inputs['suhu'] / 50) * 100, 100) : 0;
        $humPct   = isset($inputs['kelembapan'])? min($inputs['kelembapan'], 100)         : 0;
        $ammoPct  = isset($inputs['amonia'])    ? min($inputs['amonia'] * 2, 100)         : 0;
        $hdpPct   = isset($inputs['hdp'])       ? min($inputs['hdp'], 100)                : 0;
        $pakanPct = isset($inputs['pakan'])     ? min(($inputs['pakan'] / 150) * 100,100) : 0;
        $mortPct  = isset($inputs['mortalitas'])? min($inputs['mortalitas'] * 20, 100)    : 0;

        $suhu   = $inputs['suhu']       ?? 0;
        $humid  = $inputs['kelembapan'] ?? 0;
        $amonia = $inputs['amonia']     ?? 0;
        $hdp    = $inputs['hdp']        ?? 0;
        $pakan  = $inputs['pakan']      ?? 0;
        $mort   = $inputs['mortalitas'] ?? 0;

        return [
            'confidence' => max($lingkScore, $kesehatScore),
            'spider'     => [round($suhuPct), round($humPct), round($ammoPct), round($hdpPct), round($pakanPct), round(100 - $mortPct)],
            'color'      => $colorMap[$lingkLabel] ?? 'gray',
            'sensors'    => [
                'lingkungan' => [
                    ['label' => 'Suhu Udara',  'percent' => round($suhuPct),  'status' => $suhu > 30 ? 'warning' : 'normal', 'statusLabel' => round($suhu, 1) . '°C — ' . (isset($fuzzLingk['suhu']) && $fuzzLingk['suhu'] ? array_search(max($fuzzLingk['suhu']), $fuzzLingk['suhu']) : '-')],
                    ['label' => 'Kelembapan', 'percent' => round($humPct),   'status' => $humid > 80 ? 'warning' : 'normal', 'statusLabel' => round($humid, 1) . '% — ' . (isset($fuzzLingk['kelembapan']) && $fuzzLingk['kelembapan'] ? array_search(max($fuzzLingk['kelembapan']), $fuzzLingk['kelembapan']) : '-')],
                    ['label' => 'Amonia',     'percent' => round($ammoPct),  'status' => $amonia > 20 ? 'warning' : 'normal', 'statusLabel' => round($amonia, 1) . ' ppm — ' . (isset($fuzzLingk['amonia']) && $fuzzLingk['amonia'] ? array_search(max($fuzzLingk['amonia']), $fuzzLingk['amonia']) : '-')],
                ],
                'produktivitas' => [
                    ['label' => 'HDP (Hen-Day)',  'percent' => round($hdpPct),   'status' => $hdp < 75 ? 'warning' : 'normal', 'statusLabel' => round($hdp, 1) . '% — ' . (isset($fuzzKes['hdp']) && $fuzzKes['hdp'] ? array_search(max($fuzzKes['hdp']), $fuzzKes['hdp']) : '-')],
                    ['label' => 'Konsumsi Pakan', 'percent' => round($pakanPct), 'status' => 'normal', 'statusLabel' => round($pakan, 1) . ' g/ekor'],
                    ['label' => 'Mortalitas',     'percent' => round(100 - $mortPct), 'status' => $mort > 1 ? 'warning' : 'normal', 'statusLabel' => round($mort, 2) . '%'],
                ],
            ],
            'indicators' => [
                ['label' => 'HDP Score',  'value' => round($hdp, 1) . '%',  'color' => $hdp >= 90 ? 'emerald' : ($hdp >= 75 ? 'blue' : 'amber')],
                ['label' => 'FCR Score',  'value' => round($inputs['fcr'] ?? 0, 2),    'color' => ($inputs['fcr'] ?? 0) <= 1.8 ? 'emerald' : 'amber'],
                ['label' => 'Livability', 'value' => round(100 - $mort, 2) . '%',      'color' => $mort < 0.5 ? 'emerald' : 'amber'],
            ],
            'results' => [
                'lingkungan' => [
                    'status'      => strtoupper($lingkLabel),
                    'statusColor' => $colorMap[$lingkLabel] ?? 'gray',
                    'title'       => $lingkungan['dominant_rule']['diagnosis'] ?? 'Analisa Lingkungan',
                    'description' => 'Score: ' . round($lingkScore, 1) . '/100. ' . ($lingkungan['dominant_rule']['diagnosis'] ?? ''),
                    'link'        => '#',
                ],
                'produktivitas' => [
                    'status'      => strtoupper($kesehatLabel),
                    'statusColor' => $colorMap[$kesehatLabel] ?? 'gray',
                    'title'       => $kesehatan['dominant_rule']['diagnosis'] ?? 'Analisa Produktivitas',
                    'description' => 'Score: ' . round($kesehatScore, 1) . '/100. ' . ($kesehatan['dominant_rule']['diagnosis'] ?? ''),
                    'link'        => '#',
                ],
                'gabungan' => [
                    'status'      => strtoupper($kausalitas['label'] ?? 'N/A'),
                    'statusColor' => $colorMap[$lingkLabel] ?? 'gray',
                    'title'       => $kausalitas['label'] ?? 'Diagnosis Kausalitas',
                    'description' => $result['narrative'] ?? ($kausalitas['diagnosis'] ?? '-'),
                    'link'        => '#',
                    'isMain'      => true,
                ],
            ],
        ];
    }

    private function emptyHistory(): array
    {
        return ['id' => 'N/A', 'date' => '-', 'time' => '-', 'mode' => '-', 'modeColor' => 'gray', 'barn' => '-', 'status' => '-', 'color' => 'gray', 'verdict' => 'Belum ada analisa', 'raw' => []];
    }

    private function getKpiMetrics(): array
    {
        return [
            ['label' => 'Total Tiket Aktif', 'value' => '5', 'trend' => ['direction' => 'down', 'value' => '-2', 'status' => 'positive']],
            ['label' => 'Avg Score Supplier', 'value' => '84.5', 'trend' => ['direction' => 'up', 'value' => '+1.2', 'status' => 'positive']],
            ['label' => 'Avg HDP Performance', 'value' => '94.2%', 'trend' => ['direction' => 'stable', 'value' => '0', 'status' => 'neutral']],
            ['label' => 'Rata-rata FCR', 'value' => '2.14', 'trend' => ['direction' => 'up', 'value' => '+0.02', 'status' => 'negative']],
        ];
    }


    private function getChartData(): array
    {
        // HDP comparison: 30 hari terakhir dari SpkFuzzyLog
        $logs = SpkFuzzyLog::query()
            ->orderBy('createdAt', 'asc')
            ->limit(30)
            ->get(['input_json', 'createdAt', 'status_lingkungan']);

        $labels      = [];
        $hdpActual   = [];
        $fcrActual   = [];
        $suhuActual  = [];
        $amoniaActual= [];

        foreach ($logs as $log) {
            $inputs       = is_array($log->input_json) ? $log->input_json : [];
            $labels[]     = \Carbon\Carbon::parse($log->createdAt)->format('d/m H:i');
            $hdpActual[]  = round($inputs['hdp'] ?? 0, 1);
            $fcrActual[]  = round($inputs['fcr'] ?? 0, 2);
            $suhuActual[] = round($inputs['suhu'] ?? 0, 1);
            $amoniaActual[]= round($inputs['amonia'] ?? 0, 1);
        }

        // Jika belum ada log, tampilkan kurva Lohmann Brown standar saja
        if (empty($labels)) {
            $weeks = []; $hdpStd = [];
            for ($w = 20; $w <= 40; $w++) {
                $weeks[] = 'W' . $w;
                if ($w < 25)       { $std = 60 + (($w - 20) * 7); }
                elseif ($w <= 30)  { $std = 95 - (($w - 25) * 0.2); }
                else               { $std = 94 - (($w - 30) * 0.4); }
                $hdpStd[] = round($std, 1);
            }
            return [
                'hdpComparison' => ['labels' => $weeks, 'actual' => array_fill(0, count($weeks), null), 'standard' => $hdpStd],
                'causality'     => ['labels' => [], 'fcr' => [], 'suhu' => [], 'kelembaban' => [], 'amonia' => []],
            ];
        }

        // HDP standard (Lohmann Brown, per-observasi disesuaikan indeks)
        $hdpStandard = array_fill(0, count($hdpActual), 93.0);

        return [
            'hdpComparison' => [
                'labels'   => $labels,
                'actual'   => $hdpActual,
                'standard' => $hdpStandard,
            ],
            'causality' => [
                'labels'     => $labels,
                'fcr'        => $fcrActual,
                'suhu'       => $suhuActual,
                'kelembaban' => [],
                'amonia'     => $amoniaActual,
            ],
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
