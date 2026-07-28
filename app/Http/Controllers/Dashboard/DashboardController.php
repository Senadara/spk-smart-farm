<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\IotDeviceLog;
use App\Models\SpkActionTask;
use App\Models\SpkFuzzyLog;
use App\Services\Fuzzy\NarrativeGenerator;
use App\Services\Inventory\MobileInventorySyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function __construct(private MobileInventorySyncService $mobileInventorySync) {}

    public function index()
    {
        $livestock = $this->livestockProductivity();
        $crop = $this->cropProductivity();
        $spk = $this->spkProductivityStatus();
        $inventory = $this->inventoryStockSummary();
        $unitOverview = $this->unitOverview();
        $masterConfig = $this->masterConfigSummary($unitOverview);
        $iot = $this->iotReadinessSummary($unitOverview);
        $workSummary = $this->requiredWorkSummary($unitOverview, $spk, $inventory, $masterConfig);
        $overviewCards = $this->overviewCards($unitOverview, $spk, $workSummary, $masterConfig);

        return view('dashboard.index', [
            'user' => session('user', []),
            'overviewCards' => $overviewCards,
            'productivityCards' => $overviewCards,
            'unitOverview' => $unitOverview,
            'masterConfig' => $masterConfig,
            'iot' => $iot,
            'workSummary' => $workSummary,
            'systemNotices' => $this->systemNotices($masterConfig, $iot, $spk, $inventory),
            'staffActivities' => $this->staffActivities(),
            'livestock' => $livestock,
            'crop' => $crop,
            'spk' => $spk,
            'inventory' => $inventory,
            'trend' => $this->productivityTrend(),
            'alerts' => $this->alerts($inventory),
            'quickLinks' => $this->quickLinks(),
        ]);
    }

    private function overviewCards(array $unitOverview, array $spk, array $workSummary, array $masterConfig): array
    {
        $harvest = $workSummary['harvest_schedules'];
        $tasks = $workSummary['active_tasks'];

        return [
            [
                'label' => 'Prioritas Hari Ini',
                'value' => $this->formatNumber($workSummary['pending_count']),
                'caption' => $workSummary['pending_caption'],
                'tone' => $workSummary['pending_count'] > 0 ? 'amber' : 'emerald',
                'url' => $workSummary['primary_url'],
            ],
            [
                'label' => 'Data Master',
                'value' => $masterConfig['summary_label'],
                'caption' => $masterConfig['message'],
                'tone' => $masterConfig['tone'],
                'url' => route('data-master.index'),
            ],
            [
                'label' => 'Laporan Unit',
                'value' => $unitOverview['report_coverage_label'],
                'caption' => $unitOverview['report_message'],
                'tone' => $unitOverview['total_units'] === 0
                    ? 'gray'
                    : ($unitOverview['report_coverage'] >= 100
                    ? 'emerald'
                    : ($unitOverview['report_coverage'] > 0 ? 'amber' : 'red')),
                'url' => route('peternakan'),
            ],
            [
                'label' => 'Jadwal Panen',
                'value' => $harvest['overview_label'],
                'caption' => $harvest['message'],
                'tone' => $harvest['tone'],
                'url' => route('settings.notifications.index', ['tab' => 'harvest']),
            ],
            [
                'label' => 'Penugasan SPK',
                'value' => $tasks['overview_label'],
                'caption' => $tasks['message'],
                'tone' => $tasks['tone'] === 'emerald' && $spk['tone'] === 'red' ? 'red' : $tasks['tone'],
                'url' => route('spk.tasks.index', ['tab' => 'active']),
            ],
        ];
    }

    private function unitOverview(): array
    {
        $units = $this->activeUnitRows();
        $unitIds = $units->pluck('id')->filter()->values()->all();
        $reportCoverage = $this->reportCoverage($unitIds, now()->toDateString());
        $livestockUnits = $units->where('budidaya_type', 'hewan')->count();
        $cropUnits = $units->where('budidaya_type', 'tumbuhan')->count();
        $otherUnits = max(0, $units->count() - $livestockUnits - $cropUnits);

        $captionParts = [];
        if ($livestockUnits > 0) {
            $captionParts[] = $livestockUnits.' ternak';
        }

        if ($cropUnits > 0) {
            $captionParts[] = $cropUnits.' kebun';
        }

        if ($otherUnits > 0) {
            $captionParts[] = $otherUnits.' lain';
        }

        return [
            'units' => $units,
            'unit_ids' => $unitIds,
            'total_units' => $units->count(),
            'livestock_units' => $livestockUnits,
            'crop_units' => $cropUnits,
            'other_units' => $otherUnits,
            'total_population' => (float) $units->sum('quantity'),
            'total_population_label' => $units->sum('quantity') > 0 ? $this->formatNumber($units->sum('quantity')).' entitas' : '-',
            'report_coverage' => $reportCoverage['percentage'],
            'report_coverage_label' => $reportCoverage['reported'].'/'.$reportCoverage['total'],
            'report_message' => $reportCoverage['message'],
            'caption' => $captionParts ? implode(' - ', $captionParts) : 'menunggu data mobile',
        ];
    }

    private function masterConfigSummary(array $unitOverview): array
    {
        if (! Schema::hasTable('jenisBudidaya')) {
            return [
                'total' => 0,
                'configured' => 0,
                'missing' => 0,
                'summary_label' => 'Belum tersedia',
                'message' => 'Tabel jenis budidaya belum tersedia.',
                'examples' => 'Periksa struktur data master.',
                'tone' => 'gray',
            ];
        }

        $typesQuery = DB::table('jenisBudidaya');
        if (Schema::hasColumn('jenisBudidaya', 'isDeleted')) {
            $typesQuery->where('isDeleted', 0);
        }

        if (Schema::hasColumn('jenisBudidaya', 'status')) {
            $typesQuery->where('status', 1);
        }

        $types = $typesQuery
            ->orderBy('nama')
            ->get(['id', 'nama', 'tipe']);
        $total = $types->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'configured' => 0,
                'missing' => 0,
                'summary_label' => 'Menunggu jenis',
                'message' => 'Jenis ternak/tanaman belum terbaca dari mobile.',
                'examples' => 'Buat jenis budidaya dan unit dari mobile terlebih dahulu.',
                'tone' => 'amber',
            ];
        }

        if (! Schema::hasTable('livestock_master_configs')) {
            return [
                'total' => $total,
                'configured' => 0,
                'missing' => $total,
                'summary_label' => 'Perlu setting',
                'message' => $total.' jenis belum punya konfigurasi data master.',
                'examples' => $types->take(3)->pluck('nama')->implode(', '),
                'tone' => 'amber',
            ];
        }

        $hasEnvironmentTable = Schema::hasTable('livestock_environment_parameters');
        $hasProductivityConfigTable = Schema::hasTable('livestock_productivity_function_configs');
        $configuredQuery = DB::table('livestock_master_configs as c');
        if ($hasEnvironmentTable) {
            $configuredQuery->leftJoin('livestock_environment_parameters as e', function ($join) {
                $join->on('e.config_id', '=', 'c.id')
                    ->where('e.is_active', true);

                if (Schema::hasColumn('livestock_environment_parameters', 'required_for_iot')) {
                    $join->where('e.required_for_iot', true);
                }
            });
        }

        if ($hasProductivityConfigTable) {
            $configuredQuery->leftJoin('livestock_productivity_function_configs as p', function ($join) {
                $join->on('p.config_id', '=', 'c.id')
                    ->where('p.is_active', true);
            });
        }

        $configuredIds = $configuredQuery
            ->when(Schema::hasColumn('livestock_master_configs', 'status'), fn ($query) => $query->whereIn('c.status', ['configured', 'active']))
            ->when($hasEnvironmentTable || $hasProductivityConfigTable, function ($query) use ($hasEnvironmentTable, $hasProductivityConfigTable) {
                $query->where(function ($inner) use ($hasEnvironmentTable, $hasProductivityConfigTable) {
                    if ($hasEnvironmentTable) {
                        $inner->whereNotNull('e.id');
                    }

                    if ($hasProductivityConfigTable) {
                        $hasEnvironmentTable
                            ? $inner->orWhereNotNull('p.id')
                            : $inner->whereNotNull('p.id');
                    }
                });
            })
            ->distinct()
            ->pluck('c.jenis_budidaya_id')
            ->filter()
            ->values()
            ->all();
        $missingTypes = $types
            ->reject(fn ($type) => in_array($type->id, $configuredIds, true))
            ->values();
        $missing = $missingTypes->count();
        $examples = $missingTypes->take(3)->pluck('nama')->filter()->implode(', ');

        if ($missingTypes->count() > 3) {
            $examples .= ' +'.($missingTypes->count() - 3).' jenis lain';
        }

        return [
            'total' => $total,
            'configured' => max(0, $total - $missing),
            'missing' => $missing,
            'summary_label' => $missing > 0 ? 'Perlu setting' : 'Siap',
            'message' => $missing > 0
                ? $missing.' jenis belum terhubung data master.'
                : 'Semua jenis aktif sudah punya data master.',
            'examples' => $examples ?: 'Konfigurasi data master sudah lengkap.',
            'tone' => $missing > 0 ? 'amber' : 'emerald',
        ];
    }

    private function iotReadinessSummary(array $unitOverview): array
    {
        if ($unitOverview['total_units'] === 0) {
            return [
                'device_count' => 0,
                'mapped_count' => 0,
                'missing_units' => 0,
                'summary_label' => 'Menunggu unit',
                'message' => 'IoT bisa dikaitkan setelah unit dibuat.',
                'tone' => 'gray',
                'url' => route('iot.devices'),
            ];
        }

        if (! Schema::hasTable('iot_device')) {
            return [
                'device_count' => 0,
                'mapped_count' => 0,
                'missing_units' => $unitOverview['total_units'],
                'summary_label' => 'Belum tersedia',
                'message' => 'Tabel device IoT belum tersedia.',
                'tone' => 'gray',
                'url' => route('iot.devices'),
            ];
        }

        $unitIds = $unitOverview['unit_ids'];
        $deviceQuery = DB::table('iot_device')
            ->whereIn('unitBudidayaId', $unitIds);

        if (Schema::hasColumn('iot_device', 'status')) {
            $deviceQuery->where('status', '!=', 'inactive');
        }

        $devices = $deviceQuery->get(['id', 'unitBudidayaId']);
        $deviceCount = $devices->count();
        $deviceUnitCount = $devices->pluck('unitBudidayaId')->filter()->unique()->count();
        $mappedDeviceCount = 0;

        if ($deviceCount > 0 && Schema::hasTable('iot_parameter_mapping')) {
            $mappedDeviceCount = DB::table('iot_parameter_mapping')
                ->whereIn('deviceId', $devices->pluck('id')->all())
                ->distinct()
                ->count('deviceId');
        }

        if ($deviceCount === 0) {
            return [
                'device_count' => 0,
                'mapped_count' => 0,
                'missing_units' => $unitOverview['total_units'],
                'summary_label' => 'Belum ada device',
                'message' => 'Unit aktif belum terhubung device IoT.',
                'tone' => 'amber',
                'url' => route('iot.devices'),
            ];
        }

        if ($deviceUnitCount < $unitOverview['total_units']) {
            return [
                'device_count' => $deviceCount,
                'mapped_count' => $mappedDeviceCount,
                'missing_units' => $unitOverview['total_units'] - $deviceUnitCount,
                'summary_label' => 'Sebagian unit',
                'message' => ($unitOverview['total_units'] - $deviceUnitCount).' unit belum punya device IoT.',
                'tone' => 'amber',
                'url' => route('iot.devices'),
            ];
        }

        if ($mappedDeviceCount < $deviceCount) {
            return [
                'device_count' => $deviceCount,
                'mapped_count' => $mappedDeviceCount,
                'missing_units' => 0,
                'summary_label' => 'Mapping belum lengkap',
                'message' => ($deviceCount - $mappedDeviceCount).' device belum punya mapping parameter.',
                'tone' => 'amber',
                'url' => route('iot.devices'),
            ];
        }

        return [
            'device_count' => $deviceCount,
            'mapped_count' => $mappedDeviceCount,
            'missing_units' => 0,
            'summary_label' => 'Siap',
            'message' => 'Device dan mapping IoT utama sudah terbaca.',
            'tone' => 'emerald',
            'url' => route('iot.monitoring'),
        ];
    }

    private function systemNotices(array $masterConfig, array $iot, array $spk, array $inventory): array
    {
        $notices = [];

        if (($masterConfig['missing'] ?? 0) > 0) {
            $notices[] = [
                'title' => 'Data master belum lengkap',
                'message' => $masterConfig['message'],
                'status_label' => 'Atur',
                'tone' => 'amber',
                'url' => route('data-master.index'),
            ];
        }

        if (! in_array($iot['tone'] ?? 'gray', ['emerald', 'gray'], true)) {
            $notices[] = [
                'title' => 'Koneksi IoT perlu dicek',
                'message' => $iot['message'],
                'status_label' => 'Kelola',
                'tone' => $iot['tone'],
                'url' => $iot['url'],
            ];
        }

        if (($spk['urgent_count'] ?? 0) > 0 || ($spk['active_tasks'] ?? 0) > 0 || ($spk['analyses_today'] ?? 0) === 0) {
            $notices[] = [
                'title' => 'SPK perlu perhatian',
                'message' => $spk['message'],
                'status_label' => ($spk['active_tasks'] ?? 0) > 0 ? 'Tugas' : 'Cek SPK',
                'tone' => $spk['tone'],
                'url' => ($spk['active_tasks'] ?? 0) > 0
                    ? route('spk.tasks.index', ['tab' => 'active'])
                    : route('spk.dashboard'),
            ];
        }

        if (($inventory['needs_restock'] ?? 0) > 0) {
            $notices[] = [
                'title' => 'Stok perlu restock',
                'message' => $inventory['message'],
                'status_label' => 'Cek stok',
                'tone' => $inventory['tone'],
                'url' => route('inventory'),
            ];
        }

        if ($notices === []) {
            $notices[] = [
                'title' => 'Tidak ada catatan kritis',
                'message' => 'Data master, SPK, IoT, dan stok tidak menunjukkan peringatan utama.',
                'status_label' => 'Aman',
                'tone' => 'emerald',
                'url' => route('dashboard'),
            ];
        }

        return array_slice($notices, 0, 4);
    }

    private function requiredWorkSummary(array $unitOverview, array $spk, array $inventory, array $masterConfig): array
    {
        $dailyReports = $this->missingDailyReportSummary($unitOverview['units']);
        $harvestSchedules = $this->harvestScheduleSummary();
        $activeTasks = $this->activeTaskSummary();
        $restockCount = (int) ($inventory['needs_restock'] ?? 0);
        $items = [];

        if ($unitOverview['total_units'] === 0) {
            $items[] = [
                'title' => 'Data unit operasional',
                'value' => 'Belum ada unit',
                'caption' => 'Buat ternak/kandang dari mobile, lalu lengkapi data master di web.',
                'detail' => 'Dashboard akan aktif setelah data unit masuk.',
                'status_label' => 'Setup',
                'tone' => 'amber',
                'url' => route('data-master.index'),
            ];
        } else {
            if ($masterConfig['missing'] > 0) {
                $items[] = [
                    'title' => 'Konfigurasi data master',
                    'value' => $masterConfig['missing'].' jenis belum siap',
                    'caption' => $masterConfig['examples'],
                    'detail' => $masterConfig['configured'].' dari '.$masterConfig['total'].' jenis sudah dikonfigurasi.',
                    'status_label' => 'Perlu setting',
                    'tone' => 'amber',
                    'url' => route('data-master.index'),
                ];
            }

            $items[] = [
                'title' => 'Laporan harian unit',
                'value' => $dailyReports['missing'] > 0 ? $dailyReports['missing'].' belum' : 'Lengkap',
                'caption' => $dailyReports['missing'] > 0
                    ? $dailyReports['examples']
                    : 'Semua unit aktif sudah punya laporan hari ini.',
                'detail' => $dailyReports['reported'].' dari '.$dailyReports['total'].' unit sudah lapor.',
                'status_label' => $dailyReports['missing'] > 0 ? 'Perlu input' : 'Selesai',
                'tone' => $dailyReports['missing'] > 0 ? 'red' : 'emerald',
                'url' => route('peternakan'),
            ];
        }

        $items[] = [
            'title' => 'Panen terjadwal',
            'value' => $harvestSchedules['overdue'] > 0
                ? $harvestSchedules['overdue'].' terlambat'
                : ($harvestSchedules['waiting'] > 0 ? $harvestSchedules['waiting'].' menunggu' : ($harvestSchedules['total'] > 0 ? 'Terkontrol' : 'Belum ada')),
            'caption' => $harvestSchedules['message'],
            'detail' => $harvestSchedules['detail'],
            'status_label' => $harvestSchedules['status_label'],
            'tone' => $harvestSchedules['tone'],
            'url' => route('peternakan'),
        ];

        $items[] = [
            'title' => 'Penugasan SPK',
            'value' => $activeTasks['active'] > 0 ? $activeTasks['active'].' aktif' : 'Kosong',
            'caption' => $activeTasks['message'],
            'detail' => $activeTasks['examples'],
            'status_label' => $activeTasks['status_label'],
            'tone' => $activeTasks['tone'],
            'url' => route('spk.tasks.index', ['tab' => 'active']),
        ];

        $items[] = [
            'title' => 'Restock gudang',
            'value' => $restockCount > 0 ? $restockCount.' item' : 'Aman',
            'caption' => $inventory['message'] ?? 'Stok belum tersedia.',
            'detail' => 'Critical: '.($inventory['critical'] ?? 0).' - Warning: '.($inventory['warning'] ?? 0),
            'status_label' => $restockCount > 0 ? 'Cek stok' : 'Aman',
            'tone' => $restockCount > 0 ? (($inventory['critical'] ?? 0) > 0 ? 'red' : 'amber') : 'emerald',
            'url' => route('inventory'),
        ];

        $setupPending = $unitOverview['total_units'] === 0 ? 1 : 0;
        $masterPending = $unitOverview['total_units'] > 0 && $masterConfig['missing'] > 0 ? 1 : 0;
        $pendingCount = $setupPending
            + $masterPending
            + (int) $dailyReports['missing']
            + (int) $harvestSchedules['overdue']
            + (int) $activeTasks['active']
            + $restockCount;
        $primaryItem = collect($items)
            ->first(fn (array $item) => ! in_array($item['tone'], ['emerald', 'gray'], true));
        $primaryUrl = $primaryItem['url'] ?? route('peternakan');

        return [
            'items' => $items,
            'pending_count' => $pendingCount,
            'pending_caption' => $unitOverview['total_units'] === 0
                ? 'setup awal diperlukan'
                : ($pendingCount > 0 ? 'butuh tindak lanjut hari ini' : 'operasional terlihat rapi'),
            'primary_url' => $primaryUrl,
            'daily_reports' => $dailyReports,
            'harvest_schedules' => $harvestSchedules,
            'active_tasks' => $activeTasks,
        ];
    }

    private function livestockProductivity(): array
    {
        $unitIds = $this->unitIdsByBudidayaType('hewan');
        $today = now()->toDateString();
        $population = $this->sumUnitPopulation($unitIds);
        $harvest = $this->harvestTotals($unitIds, $today, $today);
        $feedKg = $this->feedTotal($unitIds, $today, $today);
        $mortalityCount = $this->mortalityCount($unitIds, now()->startOfMonth()->toDateString(), $today);
        $totalDeaths = $this->mortalityCount($unitIds, '1970-01-01', $today);
        $reportCoverage = $this->reportCoverage($unitIds, $today);

        $hdp = $population > 0 ? round(($harvest['total'] / $population) * 100, 1) : 0.0;
        $initialPopulation = $population + $totalDeaths;
        $hhep = $initialPopulation > 0 ? round(($harvest['total'] / $initialPopulation) * 100, 1) : 0.0;
        $fcr = $harvest['mass'] > 0 ? round($feedKg / $harvest['mass'], 2) : 0.0;
        $mortalityRate = $population > 0 ? round(($mortalityCount / $population) * 100, 2) : 0.0;
        $avgEggWeight = $harvest['total'] > 0 ? round(($harvest['mass'] * 1000) / $harvest['total'], 1) : 0.0;

        return [
            'active_units' => count($unitIds),
            'population' => $population,
            'population_label' => $population > 0 ? $this->formatNumber($population).' populasi' : '-',
            'initial_population' => $initialPopulation,
            'today_eggs' => $harvest['total'],
            'today_eggs_label' => $this->formatNumber($harvest['total']),
            'harvest_total' => $harvest['mass'] > 0 ? $harvest['mass'] : $harvest['total'],
            'harvest_label' => ($harvest['mass'] > 0)
                ? $this->formatDecimal($harvest['mass']).' kg'
                : ($harvest['total'] > 0 ? $this->formatNumber($harvest['total']) : '-'),
            'egg_mass_label' => $harvest['mass'] > 0 ? $this->formatDecimal($harvest['mass']).' kg massa panen' : '-',
            'avg_egg_weight' => $avgEggWeight,
            'avg_egg_weight_label' => $avgEggWeight > 0 ? $this->formatDecimal($avgEggWeight).' g' : '-',
            'feed_kg' => $feedKg,
            'feed_label' => $this->formatDecimal($feedKg).' kg',
            'hdp' => $hdp,
            'hdp_label' => $hdp > 0 ? $this->formatDecimal($hdp).'%' : '-',
            'hhep' => $hhep,
            'hhep_label' => $hhep > 0 ? $this->formatDecimal($hhep).'%' : '-',
            'fcr' => $fcr,
            'fcr_label' => $fcr > 0 ? $this->formatDecimal($fcr, 2) : '-',
            'mortality_count' => $mortalityCount,
            'mortality_label' => $this->formatNumber($mortalityCount).' kejadian',
            'mortality_rate_label' => $this->formatDecimal($mortalityRate).'%',
            'report_coverage' => $reportCoverage['percentage'],
            'report_coverage_label' => $reportCoverage['reported'].'/'.$reportCoverage['total'],
            'report_message' => $reportCoverage['message'],
        ];
    }

    private function cropProductivity(): array
    {
        $unitIds = $this->unitIdsByBudidayaType('tumbuhan');
        $today = now()->toDateString();
        $plants = $this->sumUnitPopulation($unitIds);
        $harvest = $this->harvestTotals($unitIds, $today, $today);
        $reportCoverage = $this->reportCoverage($unitIds, $today);
        $sensorRisks = $this->iotRiskCount($unitIds);

        return [
            'active_units' => count($unitIds),
            'plants' => $plants,
            'plants_label' => $plants > 0 ? $this->formatNumber($plants).' tanaman' : '-',
            'harvest_total' => $harvest['mass'] > 0 ? $harvest['mass'] : $harvest['total'],
            'harvest_label' => ($harvest['mass'] > 0)
                ? $this->formatDecimal($harvest['mass']).' kg'
                : ($harvest['total'] > 0 ? $this->formatNumber($harvest['total']) : '-'),
            'report_coverage' => $reportCoverage['percentage'],
            'report_coverage_label' => $reportCoverage['reported'].'/'.$reportCoverage['total'],
            'sensor_risks' => $sensorRisks,
            'status_label' => $unitIds
                ? ($sensorRisks > 0 ? 'Perlu cek sensor' : 'Terpantau')
                : 'Belum ada blok kebun',
            'note' => $unitIds
                ? 'Ringkasan memakai cakupan laporan dan sensor pada unit bertipe tumbuhan.'
                : 'Data perkebunan akan aktif setelah ada unit budidaya bertipe tumbuhan.',
        ];
    }

    private function missingDailyReportSummary($units): array
    {
        $unitIds = $units->pluck('id')->filter()->values()->all();
        $total = count($unitIds);

        if ($total === 0 || ! Schema::hasTable('laporan')) {
            return [
                'reported' => 0,
                'missing' => $total,
                'total' => $total,
                'examples' => $total === 0 ? 'Belum ada unit aktif.' : 'Tabel laporan belum tersedia.',
            ];
        }

        $query = DB::table('laporan')
            ->whereIn('unitBudidayaId', $unitIds)
            ->whereDate('createdAt', now()->toDateString());

        if (Schema::hasColumn('laporan', 'isDeleted')) {
            $query->where('isDeleted', 0);
        }

        $reportedIds = $query
            ->distinct()
            ->pluck('unitBudidayaId')
            ->filter()
            ->values()
            ->all();

        $missingUnits = $units
            ->reject(fn ($unit) => in_array($unit->id, $reportedIds, true))
            ->values();

        $examples = $missingUnits
            ->take(3)
            ->pluck('name')
            ->filter()
            ->implode(', ');

        if ($missingUnits->count() > 3) {
            $examples .= ' +'.($missingUnits->count() - 3).' unit lain';
        }

        return [
            'reported' => count($reportedIds),
            'missing' => $missingUnits->count(),
            'total' => $total,
            'examples' => $examples ?: 'Tidak ada unit yang tertinggal.',
        ];
    }

    private function harvestScheduleSummary(): array
    {
        if (! Schema::hasTable('scheduledUnitNotification')) {
            return [
                'total' => 0,
                'done' => 0,
                'waiting' => 0,
                'overdue' => 0,
                'overview_label' => 'Belum tersedia',
                'status_label' => 'Belum tersedia',
                'tone' => 'gray',
                'message' => 'Jadwal panen dari mobile belum tersedia.',
                'detail' => 'Sinkronkan jadwal dari mobile saat unit sudah dibuat.',
            ];
        }

        $now = Carbon::now('Asia/Jakarta');
        $query = DB::table('scheduledUnitNotification as n');
        $columns = [
            'n.id',
            'n.unitBudidayaId',
            'n.title',
            'n.notificationType',
            'n.dayOfWeek',
            'n.dayOfMonth',
            'n.scheduledTime',
            'n.isActive',
        ];

        if (Schema::hasTable('unitBudidaya')) {
            $query->leftJoin('unitBudidaya as u', 'u.id', '=', 'n.unitBudidayaId');
            $columns[] = 'u.nama as unit_name';
        } else {
            $columns[] = DB::raw("'Unit' as unit_name");
        }

        if (Schema::hasColumn('scheduledUnitNotification', 'isDeleted')) {
            $query->where('n.isDeleted', 0);
        }

        if (Schema::hasColumn('scheduledUnitNotification', 'isActive')) {
            $query->where('n.isActive', 1);
        }

        if (Schema::hasColumn('scheduledUnitNotification', 'tipeLaporan')) {
            $query->where('n.tipeLaporan', 'panen');
        }

        $schedules = $query
            ->select($columns)
            ->orderBy('n.scheduledTime')
            ->get()
            ->filter(fn ($row) => $this->scheduleRunsToday($row, $now))
            ->map(function ($row) {
                $row->time = $this->normalizeScheduleTime($row->scheduledTime);

                return $row;
            })
            ->values();

        if ($schedules->isEmpty()) {
            return [
                'total' => 0,
                'done' => 0,
                'waiting' => 0,
                'overdue' => 0,
                'overview_label' => 'Tidak ada',
                'status_label' => 'Tidak ada jadwal',
                'tone' => 'gray',
                'message' => 'Belum ada jadwal panen yang berjalan hari ini.',
                'detail' => 'Atur jadwal dari mobile atau menu notifikasi saat dibutuhkan.',
            ];
        }

        $harvestTimes = $this->harvestReportTimesByUnit(
            $schedules->pluck('unitBudidayaId')->filter()->unique()->values()->all(),
            $now->toDateString()
        );

        $rows = $schedules->map(function ($row) use ($schedules, $harvestTimes, $now) {
            $time = $row->time;
            $nextTime = $schedules
                ->where('unitBudidayaId', $row->unitBudidayaId)
                ->pluck('time')
                ->filter(fn ($candidate) => $candidate !== null && ($time === null || $candidate > $time))
                ->sort()
                ->first();

            $reportTimes = collect($harvestTimes[$row->unitBudidayaId] ?? []);
            $isDone = $reportTimes->contains(function ($reportTime) use ($time, $nextTime) {
                if ($time === null) {
                    return true;
                }

                return $reportTime >= $time && ($nextTime === null || $reportTime < $nextTime);
            });
            $hasPassed = $time !== null && $time <= $now->format('H:i');

            return [
                'unit_name' => $row->unit_name ?? 'Unit',
                'time' => $time ?: '-',
                'status' => $isDone ? 'done' : ($hasPassed ? 'overdue' : 'waiting'),
            ];
        });

        $done = $rows->where('status', 'done')->count();
        $overdue = $rows->where('status', 'overdue')->count();
        $waiting = $rows->where('status', 'waiting')->count();

        if ($overdue > 0) {
            $tone = 'amber';
            $statusLabel = 'Perlu reminder';
            $overviewLabel = $overdue.' terlambat';
            $message = $overdue.' jadwal panen sudah lewat dan belum tercatat.';
        } elseif ($waiting > 0) {
            $tone = 'sky';
            $statusLabel = 'Menunggu jam';
            $overviewLabel = $waiting.' menunggu';
            $message = $waiting.' jadwal panen masih menunggu waktu input.';
        } else {
            $tone = 'emerald';
            $statusLabel = 'Selesai';
            $overviewLabel = 'Terkontrol';
            $message = 'Jadwal panen hari ini sudah tercatat.';
        }

        $detailRows = $rows
            ->whereIn('status', ['overdue', 'waiting'])
            ->take(2)
            ->map(fn ($row) => $row['unit_name'].' '.$row['time'])
            ->implode(', ');

        return [
            'total' => $rows->count(),
            'done' => $done,
            'waiting' => $waiting,
            'overdue' => $overdue,
            'overview_label' => $overviewLabel,
            'status_label' => $statusLabel,
            'tone' => $tone,
            'message' => $message,
            'detail' => $detailRows ?: $done.' dari '.$rows->count().' jadwal selesai.',
        ];
    }

    private function activeTaskSummary(): array
    {
        if (! Schema::hasTable('spk_action_tasks')) {
            return [
                'active' => 0,
                'urgent' => 0,
                'overview_label' => 'Belum tersedia',
                'status_label' => 'Belum tersedia',
                'tone' => 'gray',
                'message' => 'Tabel penugasan SPK belum tersedia.',
                'examples' => 'Buka SPK setelah data laporan masuk.',
            ];
        }

        $query = DB::table('spk_action_tasks as t')
            ->whereIn('t.status', ['todo', 'in_progress']);

        $active = (clone $query)->count();
        $urgent = (clone $query)->whereIn('t.priority', ['urgent', 'high'])->count();

        if ($active === 0) {
            return [
                'active' => 0,
                'urgent' => 0,
                'overview_label' => 'Kosong',
                'status_label' => 'Tidak ada',
                'tone' => 'emerald',
                'message' => 'Belum ada penugasan SPK yang harus dikerjakan.',
                'examples' => 'Tugas baru akan muncul dari hasil analisa SPK.',
            ];
        }

        $itemsQuery = clone $query;
        if (Schema::hasTable('unitBudidaya')) {
            $itemsQuery->leftJoin('unitBudidaya as u', 'u.id', '=', 't.unit_budidaya_id');
        }

        if (Schema::hasTable('user')) {
            $itemsQuery->leftJoin('user as assignee', 'assignee.id', '=', 't.assigned_to');
        }

        $items = $itemsQuery
            ->select([
                't.title',
                't.priority',
                't.status',
                DB::raw(Schema::hasTable('unitBudidaya') ? 'u.nama as unit_name' : "'Unit' as unit_name"),
                DB::raw(Schema::hasTable('user') ? 'assignee.name as assignee_name' : "'Petugas' as assignee_name"),
            ])
            ->orderByRaw("FIELD(t.priority, 'urgent', 'high', 'medium', 'low')")
            ->orderBy('t.due_date')
            ->take(2)
            ->get()
            ->map(fn ($item) => trim(($item->unit_name ?: 'Unit').' - '.($item->assignee_name ?: 'Belum ditugaskan')))
            ->implode(', ');

        return [
            'active' => $active,
            'urgent' => $urgent,
            'overview_label' => $active.' aktif',
            'status_label' => $urgent > 0 ? 'Prioritas' : 'Aktif',
            'tone' => $urgent > 0 ? 'amber' : 'sky',
            'message' => $active.' penugasan masih perlu dipantau.',
            'examples' => $items ?: 'Buka daftar penugasan untuk melihat detail.',
        ];
    }

    private function staffActivities(): array
    {
        return collect()
            ->merge($this->reportActivities())
            ->merge($this->spkTaskActivities())
            ->merge($this->inventoryActivities())
            ->sortByDesc('sort_key')
            ->take(8)
            ->map(function (array $activity) {
                unset($activity['sort_key']);

                return $activity;
            })
            ->values()
            ->all();
    }

    private function reportActivities()
    {
        if (! Schema::hasTable('laporan')) {
            return collect();
        }

        $query = DB::table('laporan as l');
        $columns = [
            'l.id',
            'l.unitBudidayaId',
            'l.tipe',
            'l.judul',
            'l.createdAt',
        ];

        if (Schema::hasTable('unitBudidaya')) {
            $query->leftJoin('unitBudidaya as u', 'u.id', '=', 'l.unitBudidayaId');
            $columns[] = 'u.nama as unit_name';
        } else {
            $columns[] = DB::raw("'Unit' as unit_name");
        }

        if (Schema::hasTable('unitBudidaya') && Schema::hasTable('jenisBudidaya')) {
            $query->leftJoin('jenisBudidaya as j', 'j.id', '=', 'u.jenisBudidayaId');
            $columns[] = 'j.tipe as budidaya_type';
        } else {
            $columns[] = DB::raw('NULL as budidaya_type');
        }

        if (Schema::hasTable('user')) {
            $query->leftJoin('user as usr', 'usr.id', '=', 'l.userId');
            $columns[] = 'usr.name as user_name';
        } else {
            $columns[] = DB::raw("'Petugas' as user_name");
        }

        if (Schema::hasColumn('laporan', 'isDeleted')) {
            $query->where('l.isDeleted', 0);
        }

        return $query
            ->select($columns)
            ->orderByDesc('l.createdAt')
            ->take(8)
            ->get()
            ->map(function ($row) {
                $type = $row->tipe ? Str::headline((string) $row->tipe) : 'Laporan';
                $unitName = $row->unit_name ?: 'Unit operasional';
                $url = ($row->budidaya_type ?? null) === 'tumbuhan'
                    ? route('perkebunan.index')
                    : ($row->unitBudidayaId ? route('peternakan.show', $row->unitBudidayaId) : route('peternakan'));

                return [
                    'sort_key' => $this->activityTimestamp($row->createdAt),
                    'title' => 'Laporan '.$type,
                    'actor' => $row->user_name ?: 'Petugas',
                    'meta' => $unitName,
                    'time' => $this->activityTimeLabel($row->createdAt),
                    'tone' => 'emerald',
                    'url' => $url,
                ];
            });
    }

    private function spkTaskActivities()
    {
        if (! Schema::hasTable('spk_action_reports')) {
            return collect();
        }

        $query = DB::table('spk_action_reports as r')
            ->leftJoin('spk_action_tasks as t', 't.id', '=', 'r.task_id');
        $columns = [
            'r.task_id',
            'r.status_update',
            'r.createdAt',
            't.title as task_title',
        ];

        if (Schema::hasTable('user')) {
            $query->leftJoin('user as usr', 'usr.id', '=', 'r.reported_by');
            $columns[] = 'usr.name as user_name';
        } else {
            $columns[] = DB::raw("'Petugas' as user_name");
        }

        if (Schema::hasTable('unitBudidaya')) {
            $query->leftJoin('unitBudidaya as u', 'u.id', '=', 't.unit_budidaya_id');
            $columns[] = 'u.nama as unit_name';
        } else {
            $columns[] = DB::raw("'Unit' as unit_name");
        }

        return $query
            ->select($columns)
            ->orderByDesc('r.createdAt')
            ->take(6)
            ->get()
            ->map(fn ($row) => [
                'sort_key' => $this->activityTimestamp($row->createdAt),
                'title' => $row->status_update === 'done' ? 'Tugas SPK selesai' : 'Progres tugas SPK',
                'actor' => $row->user_name ?: 'Petugas',
                'meta' => Str::limit($row->task_title ?: ($row->unit_name ?: 'Penugasan SPK'), 64),
                'time' => $this->activityTimeLabel($row->createdAt),
                'tone' => $row->status_update === 'done' ? 'emerald' : 'sky',
                'url' => $row->task_id ? route('spk.tasks.show', $row->task_id) : route('spk.tasks.index'),
            ]);
    }

    private function inventoryActivities()
    {
        if (! Schema::hasTable('inventory_movements')) {
            return collect();
        }

        $query = DB::table('inventory_movements as m');
        $columns = [
            'm.type',
            'm.quantity',
            'm.unit',
            'm.created_at',
        ];

        if (Schema::hasTable('inventory_items')) {
            $query->leftJoin('inventory_items as item', 'item.id', '=', 'm.inventory_item_id');
            $columns[] = 'item.name as item_name';
        } else {
            $columns[] = DB::raw("'Item stok' as item_name");
        }

        if (Schema::hasTable('user')) {
            $query->leftJoin('user as usr', 'usr.id', '=', 'm.user_id');
            $columns[] = 'usr.name as user_name';
        } else {
            $columns[] = DB::raw("'Petugas' as user_name");
        }

        return $query
            ->select($columns)
            ->orderByDesc('m.created_at')
            ->take(6)
            ->get()
            ->map(function ($row) {
                $title = match ($row->type) {
                    'inflow' => 'Restock stok',
                    'outflow' => 'Pemakaian stok',
                    default => 'Penyesuaian stok',
                };

                return [
                    'sort_key' => $this->activityTimestamp($row->created_at),
                    'title' => $title,
                    'actor' => $row->user_name ?: 'Petugas',
                    'meta' => trim(($row->item_name ?: 'Item stok').' - '.$this->formatQuantity((float) $row->quantity, $row->unit)),
                    'time' => $this->activityTimeLabel($row->created_at),
                    'tone' => $row->type === 'inflow' ? 'emerald' : ($row->type === 'outflow' ? 'amber' : 'sky'),
                    'url' => route('inventory'),
                ];
            });
    }

    private function scheduleRunsToday(object $row, Carbon $now): bool
    {
        return match ($row->notificationType) {
            'weekly' => (int) $row->dayOfWeek === $now->dayOfWeek,
            'monthly' => (int) $row->dayOfMonth === $now->day,
            default => true,
        };
    }

    private function normalizeScheduleTime(mixed $value): ?string
    {
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)/', trim((string) $value), $matches)) {
            return str_pad((string) ((int) $matches[1]), 2, '0', STR_PAD_LEFT).':'.$matches[2];
        }

        return null;
    }

    private function harvestReportTimesByUnit(array $unitIds, string $date): array
    {
        if (empty($unitIds) || ! Schema::hasTable('panen') || ! Schema::hasTable('laporan')) {
            return [];
        }

        $query = DB::table('panen as p')
            ->join('laporan as l', 'l.id', '=', 'p.laporanId')
            ->whereIn('l.unitBudidayaId', $unitIds)
            ->whereDate('l.createdAt', $date);

        if (Schema::hasColumn('laporan', 'tipe')) {
            $query->where('l.tipe', 'panen');
        }

        if (Schema::hasColumn('laporan', 'isDeleted')) {
            $query->where('l.isDeleted', 0);
        }

        if (Schema::hasColumn('panen', 'isDeleted')) {
            $query->where('p.isDeleted', 0);
        }

        return $query
            ->select([
                'l.unitBudidayaId as unit_id',
                DB::raw("DATE_FORMAT(l.createdAt, '%H:%i') as report_time"),
            ])
            ->get()
            ->groupBy('unit_id')
            ->map(fn ($rows) => $rows->pluck('report_time')->sort()->values()->all())
            ->all();
    }

    private function activityTimestamp(mixed $value): int
    {
        return $value ? Carbon::parse($value)->timestamp : 0;
    }

    private function activityTimeLabel(mixed $value): string
    {
        return $value ? Carbon::parse($value)->diffForHumans() : '-';
    }

    private function spkProductivityStatus(): array
    {
        if (! Schema::hasTable('spk_fuzzy_logs')) {
            return [
                'score' => null,
                'score_label' => '-',
                'urgent_count' => 0,
                'analyses_today' => 0,
                'latest_label' => 'Belum ada analisa',
                'latest_human' => null,
                'active_tasks' => 0,
                'summary_label' => 'Menunggu',
                'summary_caption' => 'Analisa SPK belum tersedia',
                'badge_label' => 'Menunggu Data',
                'tone' => 'amber',
                'message' => 'Data SPK belum tersedia. Jalankan analisa setelah laporan harian dan sensor masuk.',
                'action_hint' => 'Lengkapi laporan harian lalu buka Analisa SPK.',
                'warnings' => [],
            ];
        }

        $todayLogs = SpkFuzzyLog::query()
            ->whereDate('createdAt', now()->toDateString())
            ->get();

        $latest = SpkFuzzyLog::query()->latest('createdAt')->first();
        $urgentQuery = SpkFuzzyLog::query()
            ->where('createdAt', '>=', now()->subDay())
            ->where(function ($query) {
                $query->whereIn('status_lingkungan', ['Waspada', 'Buruk'])
                    ->orWhereIn('status_kesehatan', ['Waspada', 'Buruk'])
                    ->orWhere('output_value', '<', 70);
            });
        $urgent = (clone $urgentQuery)->count();
        $warnings = (clone $urgentQuery)
            ->latest('createdAt')
            ->take(3)
            ->get()
            ->map(fn (SpkFuzzyLog $log) => [
                'title' => $this->spkWarningTitle($log),
                'message' => Str::limit(
                    NarrativeGenerator::sanitizePlainText($log->recommendation)
                        ?: NarrativeGenerator::sanitizePlainText($log->narrative)
                        ?: 'Tinjau hasil analisa SPK terbaru untuk menentukan tindakan.',
                    120
                ),
                'time' => $log->createdAt?->diffForHumans() ?? '-',
                'url' => route('spk.dashboard', array_filter([
                    'history_id' => $log->id,
                    'coop_id' => data_get($log, 'unit_budidaya_id'),
                ])),
            ])
            ->values()
            ->all();

        $activeTasks = Schema::hasTable('spk_action_tasks')
            ? SpkActionTask::withoutGlobalScopes()->whereIn('status', ['todo', 'in_progress'])->count()
            : 0;

        $score = $todayLogs->isNotEmpty()
            ? round((float) $todayLogs->avg('output_value'), 1)
            : ($latest ? round((float) $latest->output_value, 1) : null);

        $latestLabel = $latest?->createdAt?->locale('id')->translatedFormat('d M Y, H:i') ?? 'Belum ada analisa';

        if ($urgent > 0) {
            $tone = 'red';
            $summaryLabel = 'Perlu tindakan';
            $summaryCaption = $urgent.' peringatan SPK aktif';
            $badgeLabel = 'Perlu Tindakan';
            $message = 'Ada peringatan SPK yang perlu ditindaklanjuti hari ini.';
            $actionHint = 'Buka Analisa SPK, cek rekomendasi, lalu buat atau lanjutkan penugasan.';
        } elseif ($activeTasks > 0) {
            $tone = 'amber';
            $summaryLabel = 'Tugas aktif';
            $summaryCaption = $activeTasks.' penugasan belum selesai';
            $badgeLabel = 'Pantau Tugas';
            $message = 'Tidak ada peringatan SPK baru, tetapi masih ada penugasan aktif yang perlu dipantau.';
            $actionHint = 'Pantau progres penugasan agar rekomendasi SPK benar-benar selesai di lapangan.';
        } elseif ($todayLogs->isEmpty()) {
            $tone = 'amber';
            $summaryLabel = 'Menunggu data';
            $summaryCaption = 'Belum ada analisa hari ini';
            $badgeLabel = 'Menunggu Data';
            $message = 'Belum ada analisa SPK hari ini. Hasil akan lebih akurat setelah laporan harian dan data sensor tersedia.';
            $actionHint = 'Lengkapi laporan harian lalu jalankan Analisa SPK.';
        } else {
            $tone = 'emerald';
            $summaryLabel = 'Terkendali';
            $summaryCaption = 'Tidak ada peringatan SPK';
            $badgeLabel = 'Aman';
            $message = 'SPK tidak menemukan kondisi darurat yang membutuhkan tindakan segera.';
            $actionHint = 'Tetap pantau laporan harian dan sensor untuk menjaga produktivitas.';
        }

        return [
            'score' => $score,
            'score_label' => $score !== null ? $this->formatDecimal($score).'/100' : '-',
            'urgent_count' => $urgent,
            'analyses_today' => $todayLogs->count(),
            'latest_label' => $latestLabel,
            'latest_human' => $latest?->createdAt?->diffForHumans(),
            'active_tasks' => $activeTasks,
            'summary_label' => $summaryLabel,
            'summary_caption' => $summaryCaption,
            'badge_label' => $badgeLabel,
            'tone' => $tone,
            'message' => $message,
            'action_hint' => $actionHint,
            'warnings' => $warnings,
        ];
    }

    private function spkWarningTitle(SpkFuzzyLog $log): string
    {
        $status = collect([
            $log->status_lingkungan,
            $log->status_kesehatan,
            $log->output_label,
        ])->filter()->join(' / ');

        if ($status !== '') {
            return 'Peringatan SPK: '.$status;
        }

        return 'Peringatan SPK perlu ditinjau';
    }

    private function productivityTrend(int $days = 7): array
    {
        $start = now()->subDays($days - 1)->toDateString();
        $end = now()->toDateString();
        $units = $this->activeUnitRows()
            ->filter(fn ($unit) => in_array($unit->budidaya_type, ['hewan', 'tumbuhan'], true))
            ->groupBy(fn ($unit) => ($unit->budidaya_type ?: 'umum').'|'.($unit->kind_name ?: 'Umum'));

        $labels = [];
        $dates = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d/m');
            $dates[] = $date->toDateString();
        }

        $palette = ['#059669', '#0284c7', '#d97706', '#7c3aed', '#dc2626', '#0f766e', '#4f46e5', '#be123c'];
        $series = [];
        $maxValue = 0.0;

        foreach ($units->values() as $index => $group) {
            $first = $group->first();
            $unitIds = $group->pluck('id')->filter()->values()->all();
            $harvest = $this->harvestByDate($unitIds, $start, $end);
            $useMass = (float) $harvest->sum('mass') > 0;
            $values = collect($dates)
                ->map(function (string $date) use ($harvest, $useMass) {
                    $row = $harvest[$date] ?? null;

                    return round((float) ($useMass ? ($row->mass ?? 0) : ($row->total ?? 0)), 1);
                })
                ->values()
                ->all();
            $total = array_sum($values);
            $maxValue = max($maxValue, ...$values);

            $series[] = [
                'label' => $first->kind_name ?: 'Umum',
                'type' => $first->budidaya_type ?: 'umum',
                'type_label' => $first->budidaya_type === 'tumbuhan' ? 'Tanaman' : 'Ternak',
                'unit_count' => $group->count(),
                'unit' => $useMass ? 'kg' : 'laporan',
                'total_label' => $useMass
                    ? $this->formatDecimal($total).' kg'
                    : $this->formatNumber($total),
                'values' => $values,
                'has_data' => $total > 0,
                'color' => $palette[$index % count($palette)],
            ];
        }

        $maxValue = max($maxValue, 1.0);
        $chartWidth = 320;
        $chartHeight = 124;
        $left = 14;
        $top = 12;
        $innerWidth = 292;
        $innerHeight = 82;
        $series = collect($series)
            ->map(function (array $item) use ($maxValue, $days, $left, $top, $innerWidth, $innerHeight) {
                $points = collect($item['values'])
                    ->map(function (float $value, int $index) use ($maxValue, $days, $left, $top, $innerWidth, $innerHeight) {
                        $x = $left + ($days <= 1 ? 0 : ($index / ($days - 1)) * $innerWidth);
                        $y = $top + $innerHeight - (($value / $maxValue) * $innerHeight);

                        return round($x, 1).','.round($y, 1);
                    })
                    ->implode(' ');

                $item['points'] = $points;

                return $item;
            })
            ->values()
            ->all();

        return [
            'labels' => $labels,
            'series' => $series,
            'max_value' => $maxValue,
            'chart_width' => $chartWidth,
            'chart_height' => $chartHeight,
        ];
    }

    private function inventoryStockSummary(): array
    {
        $this->mobileInventorySync->sync();

        if (! Schema::hasTable('inventory_items')) {
            return [
                'total' => 0,
                'total_label' => '0 item',
                'critical' => 0,
                'warning' => 0,
                'safe' => 0,
                'needs_restock' => 0,
                'badge_label' => 'Belum ada data',
                'tone' => 'gray',
                'message' => 'Data stok gudang belum tersedia.',
                'items' => [],
                'alerts' => [],
            ];
        }

        $items = InventoryItem::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(function (InventoryItem $item) {
                $daysLeft = $item->daily_usage > 0
                    ? (int) floor((float) $item->stock / max((float) $item->daily_usage, 0.0001))
                    : null;
                $status = $this->inventoryStatus($item, $daysLeft);
                $score = $this->inventoryRestockScore($item, $daysLeft, $status);

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category' => $item->category ?: 'Umum',
                    'stock' => (float) $item->stock,
                    'stock_label' => $this->formatQuantity((float) $item->stock, $item->unit),
                    'unit' => $item->unit,
                    'days_left' => $daysLeft,
                    'days_left_label' => $daysLeft === null ? 'pemakaian belum tercatat' : $daysLeft.' hari',
                    'minimum_stock' => (float) $item->minimum_stock,
                    'reorder_point' => (float) $item->reorder_point,
                    'status' => $status,
                    'score' => $score,
                ];
            });

        $critical = $items->where('status', 'critical')->count();
        $warning = $items->where('status', 'warning')->count();
        $safe = $items->where('status', 'safe')->count();
        $needsRestock = $critical + $warning;

        if ($critical > 0) {
            $tone = 'red';
            $badge = 'Restock urgent';
            $message = $critical.' item sudah berada di bawah stok minimum atau sisa harinya kritis.';
        } elseif ($warning > 0) {
            $tone = 'amber';
            $badge = 'Perlu restock';
            $message = $warning.' item mendekati reorder point. Siapkan pemesanan sebelum stok menipis.';
        } elseif ($items->isEmpty()) {
            $tone = 'gray';
            $badge = 'Belum ada data';
            $message = 'Belum ada item inventaris aktif yang dapat dipantau.';
        } else {
            $tone = 'emerald';
            $badge = 'Stok aman';
            $message = 'Stok gudang aktif masih berada di atas batas minimum.';
        }

        $priorityItems = $items
            ->filter(fn (array $item) => in_array($item['status'], ['critical', 'warning'], true))
            ->sortByDesc('score')
            ->take(3)
            ->values()
            ->all();

        return [
            'total' => $items->count(),
            'total_label' => $items->count().' item',
            'critical' => $critical,
            'warning' => $warning,
            'safe' => $safe,
            'needs_restock' => $needsRestock,
            'badge_label' => $badge,
            'tone' => $tone,
            'message' => $message,
            'items' => $priorityItems,
            'alerts' => collect($priorityItems)->map(fn (array $item) => [
                'sort_key' => now()->timestamp,
                'type' => $item['status'] === 'critical' ? 'danger' : 'warning',
                'title' => 'Stok: '.$item['name'],
                'message' => 'Sisa '.$item['stock_label'].'; estimasi '.$item['days_left_label'].'. Prioritaskan restock gudang.',
                'time' => 'Hari ini',
                'url' => route('inventory'),
            ])->all(),
        ];
    }

    private function inventoryStatus(InventoryItem $item, ?int $daysLeft): string
    {
        if ($item->stock <= $item->minimum_stock || ($daysLeft !== null && $daysLeft <= max(2, (int) $item->lead_time_days))) {
            return 'critical';
        }

        $warningDays = (int) $item->lead_time_days + max(1, (int) ($item->safety_stock_days ?? 5));
        if ($item->stock <= $item->reorder_point || ($daysLeft !== null && $daysLeft <= $warningDays)) {
            return 'warning';
        }

        return 'safe';
    }

    private function inventoryRestockScore(InventoryItem $item, ?int $daysLeft, string $status): float
    {
        $statusWeight = ['critical' => 0.65, 'warning' => 0.4, 'safe' => 0.1][$status] ?? 0.1;
        $daysWeight = $daysLeft === null ? 0.05 : max(0, min(0.25, (30 - min($daysLeft, 30)) / 120));
        $leadWeight = min(0.1, ((int) $item->lead_time_days) / 80);

        return round($statusWeight + $daysWeight + $leadWeight, 3);
    }

    private function unitIdsByBudidayaType(string $type): array
    {
        return $this->activeUnitRows($type)
            ->pluck('id')
            ->filter()
            ->values()
            ->all();
    }

    private function activeUnitRows(?string $type = null)
    {
        if (! Schema::hasTable('unitBudidaya')) {
            return collect();
        }

        $query = DB::table('unitBudidaya');
        $columns = [
            'unitBudidaya.id',
            DB::raw("COALESCE(unitBudidaya.nama, 'Unit tanpa nama') as name"),
            DB::raw(Schema::hasColumn('unitBudidaya', 'jumlah') ? 'COALESCE(unitBudidaya.jumlah, 0) as quantity' : '0 as quantity'),
            DB::raw('NULL as budidaya_type'),
            DB::raw("'Umum' as kind_name"),
        ];

        if (Schema::hasColumn('unitBudidaya', 'isDeleted')) {
            $query->where('unitBudidaya.isDeleted', 0);
        }

        if (Schema::hasColumn('unitBudidaya', 'status')) {
            $query->where('unitBudidaya.status', 1);
        }

        if (Schema::hasTable('jenisBudidaya') && Schema::hasColumn('unitBudidaya', 'jenisBudidayaId')) {
            $query->leftJoin('jenisBudidaya', 'unitBudidaya.jenisBudidayaId', '=', 'jenisBudidaya.id');
            $columns = [
                'unitBudidaya.id',
                DB::raw("COALESCE(unitBudidaya.nama, 'Unit tanpa nama') as name"),
                DB::raw(Schema::hasColumn('unitBudidaya', 'jumlah') ? 'COALESCE(unitBudidaya.jumlah, 0) as quantity' : '0 as quantity'),
                'jenisBudidaya.tipe as budidaya_type',
                DB::raw("COALESCE(jenisBudidaya.nama, 'Umum') as kind_name"),
            ];

            if (Schema::hasColumn('jenisBudidaya', 'isDeleted')) {
                $query->where(function ($inner) {
                    $inner->where('jenisBudidaya.isDeleted', 0)->orWhereNull('jenisBudidaya.id');
                });
            }

            if ($type !== null && Schema::hasColumn('jenisBudidaya', 'tipe')) {
                $query->whereRaw('LOWER(jenisBudidaya.tipe) = ?', [$type]);
            } elseif ($type !== null && Schema::hasColumn('jenisBudidaya', 'nama')) {
                $query->where(function ($inner) use ($type) {
                    if ($type === 'hewan') {
                        $inner->where('jenisBudidaya.nama', 'like', '%Ayam%')
                            ->orWhere('jenisBudidaya.nama', 'like', '%Ternak%');
                    } else {
                        $inner->where('jenisBudidaya.nama', 'like', '%Tanam%')
                            ->orWhere('jenisBudidaya.nama', 'like', '%Kebun%')
                            ->orWhere('jenisBudidaya.nama', 'like', '%Melon%');
                    }
                });
            }
        }

        return $query
            ->select($columns)
            ->orderBy('unitBudidaya.nama')
            ->get();
    }

    private function sumUnitPopulation(array $unitIds): float
    {
        if (empty($unitIds) || ! Schema::hasTable('unitBudidaya') || ! Schema::hasColumn('unitBudidaya', 'jumlah')) {
            return 0.0;
        }

        return (float) DB::table('unitBudidaya')
            ->whereIn('id', $unitIds)
            ->sum('jumlah');
    }

    private function harvestTotals(array $unitIds, string $startDate, string $endDate): array
    {
        $row = $this->harvestQuery($unitIds, $startDate, $endDate)
            ?->selectRaw($this->harvestSelectRaw())
            ->first();

        return [
            'total' => (float) ($row->total ?? 0),
            'mass' => (float) ($row->mass ?? 0),
        ];
    }

    private function harvestByDate(array $unitIds, string $startDate, string $endDate)
    {
        $query = $this->harvestQuery($unitIds, $startDate, $endDate);
        if (! $query) {
            return collect();
        }

        return $query
            ->selectRaw('DATE(laporan.createdAt) as dt, '.$this->harvestSelectRaw())
            ->groupBy('dt')
            ->get()
            ->keyBy('dt');
    }

    private function harvestQuery(array $unitIds, string $startDate, string $endDate)
    {
        if (empty($unitIds) || ! Schema::hasTable('panen') || ! Schema::hasTable('laporan')) {
            return null;
        }

        $query = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $unitIds)
            ->whereDate('laporan.createdAt', '>=', $startDate)
            ->whereDate('laporan.createdAt', '<=', $endDate);

        if (Schema::hasColumn('laporan', 'isDeleted')) {
            $query->where('laporan.isDeleted', 0);
        }

        if (Schema::hasColumn('panen', 'isDeleted')) {
            $query->where('panen.isDeleted', 0);
        }

        return $query;
    }

    private function harvestSelectRaw(): string
    {
        $massExpression = Schema::hasColumn('panen', 'berat')
            ? 'SUM(COALESCE(panen.berat, 0))'
            : '0';

        return 'SUM(COALESCE(panen.jumlah, 0)) as total, '.$massExpression.' as mass';
    }

    private function feedTotal(array $unitIds, string $startDate, string $endDate): float
    {
        $row = $this->feedQuery($unitIds, $startDate, $endDate)
            ?->selectRaw('SUM(COALESCE(harianTernak.pakan, 0)) as total')
            ->first();

        return (float) ($row->total ?? 0);
    }

    private function feedByDate(array $unitIds, string $startDate, string $endDate)
    {
        $query = $this->feedQuery($unitIds, $startDate, $endDate);
        if (! $query) {
            return collect();
        }

        return $query
            ->selectRaw('DATE(laporan.createdAt) as dt, SUM(COALESCE(harianTernak.pakan, 0)) as total')
            ->groupBy('dt')
            ->get()
            ->keyBy('dt');
    }

    private function feedQuery(array $unitIds, string $startDate, string $endDate)
    {
        if (empty($unitIds) || ! Schema::hasTable('harianTernak') || ! Schema::hasTable('laporan')) {
            return null;
        }

        $query = DB::table('harianTernak')
            ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $unitIds)
            ->whereDate('laporan.createdAt', '>=', $startDate)
            ->whereDate('laporan.createdAt', '<=', $endDate);

        if (Schema::hasColumn('laporan', 'isDeleted')) {
            $query->where('laporan.isDeleted', 0);
        }

        if (Schema::hasColumn('harianTernak', 'isDeleted')) {
            $query->where('harianTernak.isDeleted', 0);
        }

        return $query;
    }

    private function mortalityCount(array $unitIds, string $startDate, string $endDate): int
    {
        if (empty($unitIds) || ! Schema::hasTable('kematian') || ! Schema::hasTable('laporan')) {
            return 0;
        }

        $query = DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $unitIds);

        $query->whereDate('laporan.createdAt', '>=', $startDate)
            ->whereDate('laporan.createdAt', '<=', $endDate);

        if (Schema::hasColumn('laporan', 'isDeleted')) {
            $query->where('laporan.isDeleted', 0);
        }

        if (Schema::hasColumn('kematian', 'isDeleted')) {
            $query->where('kematian.isDeleted', 0);
        }

        return (int) $query->count();
    }

    private function reportCoverage(array $unitIds, string $date): array
    {
        $total = count($unitIds);
        if ($total === 0 || ! Schema::hasTable('laporan')) {
            return [
                'reported' => 0,
                'total' => $total,
                'percentage' => 0,
                'message' => $total === 0 ? 'Belum ada unit aktif.' : 'Tabel laporan belum tersedia.',
            ];
        }

        $query = DB::table('laporan')
            ->whereIn('unitBudidayaId', $unitIds)
            ->whereDate('createdAt', $date);

        if (Schema::hasColumn('laporan', 'isDeleted')) {
            $query->where('isDeleted', 0);
        }

        $reported = $query->distinct()->count('unitBudidayaId');
        $percentage = $total > 0 ? (int) round(($reported / $total) * 100) : 0;

        return [
            'reported' => $reported,
            'total' => $total,
            'percentage' => $percentage,
            'message' => $percentage >= 100
                ? 'Data laporan harian lengkap.'
                : 'Data laporan belum lengkap, hasil produktivitas bisa belum mewakili seluruh unit.',
        ];
    }

    private function iotRiskCount(array $unitIds): int
    {
        if (empty($unitIds) || ! Schema::hasTable('iot_device') || ! Schema::hasTable('iot_device_log')) {
            return 0;
        }

        return (int) DB::table('iot_device_log')
            ->join('iot_device', 'iot_device_log.deviceId', '=', 'iot_device.id')
            ->whereIn('iot_device.unitBudidayaId', $unitIds)
            ->whereIn('iot_device_log.logType', ['WARNING', 'ERROR'])
            ->where('iot_device_log.createdAt', '>=', now()->subDay())
            ->count();
    }

    private function formatNumber(float|int $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private function formatDecimal(float|int $value, int $decimals = 1): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }

    private function formatQuantity(float|int $value, ?string $unit = null): string
    {
        $formatted = floor((float) $value) === (float) $value
            ? $this->formatNumber($value)
            : $this->formatDecimal($value, 1);

        return trim($formatted.' '.($unit ?? ''));
    }

    private function alerts(array $inventory = []): array
    {
        $iotAlerts = Schema::hasTable('iot_device_log')
            ? IotDeviceLog::query()
                ->with('device')
                ->whereIn('logType', ['WARNING', 'ERROR'])
                ->latest('createdAt')
                ->take(4)
                ->get()
                ->toBase()
                ->map(fn (IotDeviceLog $log) => [
                    'sort_key' => $log->createdAt?->timestamp ?? 0,
                    'type' => $log->logType === 'ERROR' ? 'danger' : 'warning',
                    'title' => 'IoT: '.($log->device?->deviceName ?? $log->device?->deviceCode ?? 'Device'),
                    'message' => Str::limit($log->message, 110),
                    'time' => $log->createdAt?->diffForHumans() ?? '-',
                    'url' => route('iot.monitoring'),
                ])
            : collect();

        $spkAlerts = Schema::hasTable('spk_fuzzy_logs')
            ? SpkFuzzyLog::query()
                ->where('createdAt', '>=', now()->subDays(3))
                ->where(function ($query) {
                    $query->whereIn('status_lingkungan', ['Waspada', 'Buruk'])
                        ->orWhereIn('status_kesehatan', ['Waspada', 'Buruk'])
                        ->orWhere('output_value', '<', 70);
                })
                ->latest('createdAt')
                ->take(4)
                ->get()
                ->toBase()
                ->map(fn (SpkFuzzyLog $log) => [
                    'sort_key' => $log->createdAt?->timestamp ?? 0,
                    'type' => ((float) $log->output_value < 55 || $log->status_lingkungan === 'Buruk') ? 'danger' : 'warning',
                    'title' => 'SPK: '.($log->diagnosis_kausalitas ?: $log->status_lingkungan ?: 'Perlu tindakan'),
                    'message' => Str::limit(
                        NarrativeGenerator::sanitizePlainText($log->recommendation)
                            ?: NarrativeGenerator::sanitizePlainText($log->narrative)
                            ?: 'Tinjau hasil analisa SPK terbaru.',
                        110
                    ),
                    'time' => $log->createdAt?->diffForHumans() ?? '-',
                    'url' => route('spk.dashboard', array_filter(['history_id' => $log->id, 'coop_id' => $log->unit_budidaya_id])),
                ])
            : collect();

        $inventoryAlerts = collect($inventory['alerts'] ?? []);

        return $iotAlerts
            ->merge($spkAlerts)
            ->merge($inventoryAlerts)
            ->sortByDesc('sort_key')
            ->take(6)
            ->map(function (array $alert) {
                unset($alert['sort_key']);

                return $alert;
            })
            ->values()
            ->all();
    }

    private function quickLinks(): array
    {
        return [
            ['label' => 'Peternakan', 'caption' => 'Ringkasan farm dan kandang', 'url' => route('peternakan'), 'tone' => 'emerald'],
            ['label' => 'Analisa SPK', 'caption' => 'Evaluasi dan tindakan', 'url' => route('spk.dashboard'), 'tone' => 'sky'],
            ['label' => 'Inventaris', 'caption' => 'Stok dan restock', 'url' => route('inventory'), 'tone' => 'amber'],
            ['label' => 'IoT', 'caption' => 'Device, mapping, monitoring', 'url' => route('iot.dashboard'), 'tone' => 'gray'],
        ];
    }
}
