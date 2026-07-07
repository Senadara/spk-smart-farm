<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PeternakanService
{
    private ?string $activeKomoditasId = null;
    private ?string $activeJenisBudidayaId = null;
    private ?array $cachedBarnEnvironment = null;

    public function forKomoditas(?string $komoditasId): self
    {
        $this->activeKomoditasId = $this->resolveKomoditasId($komoditasId);
        $komod = $this->activeKomoditasId
            ? DB::table('komoditas')->where('id', $this->activeKomoditasId)->where('isDeleted', 0)->first()
            : null;

        $this->activeJenisBudidayaId = $komod?->jenisBudidayaId
            ?? DB::table('jenisBudidaya')->where('nama', 'like', '%Ayam Petelur%')->where('isDeleted', 0)->value('id');

        $this->cachedBarnEnvironment = null;

        return $this;
    }

    public function getActiveKomoditasId(): ?string
    {
        return $this->activeKomoditasId;
    }

    public function getActiveJenisBudidayaId(): ?string
    {
        return $this->activeJenisBudidayaId;
    }

    public function resolveKomoditasId(?string $requestedId): ?string
    {
        if ($requestedId) {
            $exists = DB::table('komoditas')->where('id', $requestedId)->where('isDeleted', 0)->exists();
            if ($exists) {
                return $requestedId;
            }
        }

        $layer = DB::table('komoditas')
            ->where('isDeleted', 0)
            ->whereRaw('LOWER(nama) LIKE ?', ['%layer%'])
            ->value('id');

        if ($layer) {
            return $layer;
        }

        return DB::table('komoditas')->where('isDeleted', 0)->orderBy('nama')->value('id');
    }

    public function getActiveCoopIds(bool $activeOnly = true): array
    {
        if (!$this->activeJenisBudidayaId) {
            return [];
        }

        $query = DB::table('unitBudidaya')
            ->where('jenisBudidayaId', $this->activeJenisBudidayaId)
            ->where('isDeleted', 0);

        if ($activeOnly) {
            $query->where('status', 1);
        }

        return $query->pluck('id')->toArray();
    }

    public function getDailyReportStatus(): array
    {
        $today = now()->toDateString();
        $activeCoops = $this->activeJenisBudidayaId
            ? DB::table('unitBudidaya')
                ->where('jenisBudidayaId', $this->activeJenisBudidayaId)
                ->where('status', 1)
                ->where('isDeleted', 0)
                ->orderBy('nama')
                ->get(['id', 'nama'])
            : collect();

        if ($activeCoops->isEmpty()) {
            return [
                'status' => 'no_coops',
                'isReady' => false,
                'title' => 'Belum ada kandang aktif',
                'message' => 'Tambahkan unit budidaya aktif terlebih dahulu agar laporan harian dan KPI dapat dihitung.',
                'date' => Carbon::parse($today)->locale('id')->translatedFormat('d M Y'),
                'reportedCount' => 0,
                'totalCoops' => 0,
                'missingBarns' => [],
                'lastReportAt' => null,
            ];
        }

        $coopIds = $activeCoops->pluck('id')->all();
        $reportedCoopIds = DB::table('laporan')
            ->whereIn('unitBudidayaId', $coopIds)
            ->where('isDeleted', 0)
            ->whereDate('createdAt', $today)
            ->distinct()
            ->pluck('unitBudidayaId')
            ->all();

        $missingBarns = $activeCoops
            ->reject(fn ($coop) => in_array($coop->id, $reportedCoopIds, true))
            ->pluck('nama')
            ->values()
            ->all();

        $lastReportAt = DB::table('laporan')
            ->whereIn('unitBudidayaId', $coopIds)
            ->where('isDeleted', 0)
            ->max('createdAt');

        $reportedCount = count($reportedCoopIds);
        $totalCoops = $activeCoops->count();
        $status = match (true) {
            $reportedCount === 0 => 'empty',
            $reportedCount < $totalCoops => 'partial',
            default => 'complete',
        };

        $copy = [
            'empty' => [
                'title' => 'Laporan harian hari ini belum masuk',
                'message' => 'KPI produksi seperti HDP, FCR, feed intake, dan egg mass akan tampil setelah laporan panen atau pakan hari ini dicatat.',
            ],
            'partial' => [
                'title' => 'Laporan harian belum lengkap',
                'message' => 'Sebagian kandang sudah memiliki laporan hari ini, tetapi hasil dashboard belum mewakili seluruh komoditas.',
            ],
            'complete' => [
                'title' => 'Laporan harian sudah lengkap',
                'message' => 'KPI produksi hari ini sudah dihitung dari laporan kandang aktif.',
            ],
        ];

        return [
            'status' => $status,
            'isReady' => $status === 'complete',
            'title' => $copy[$status]['title'],
            'message' => $copy[$status]['message'],
            'date' => Carbon::parse($today)->locale('id')->translatedFormat('d M Y'),
            'reportedCount' => $reportedCount,
            'totalCoops' => $totalCoops,
            'missingBarns' => $missingBarns,
            'lastReportAt' => $lastReportAt
                ? Carbon::parse($lastReportAt)->locale('id')->translatedFormat('d M Y, H:i')
                : null,
        ];
    }

    public function getCommodityThresholds(): array
    {
        $defaults = [
            'TEMP'    => ['min' => 20, 'max' => 28],
            'HUMID'   => ['min' => 50, 'max' => 70],
            'AMMON'   => ['min' => 0,  'max' => 15],
            'AMMONIA' => ['min' => 0,  'max' => 15],
            'AMMA'    => ['min' => 0,  'max' => 15],
            'LUX'     => ['min' => 15, 'max' => 50],
            'LIGHT'   => ['min' => 15, 'max' => 50],
        ];

        if (!$this->activeKomoditasId) {
            return $defaults;
        }

        $rows = DB::table('commodity_parameter')
            ->join('iot_parameter', 'commodity_parameter.parameterId', '=', 'iot_parameter.id')
            ->where('commodity_parameter.commodityId', $this->activeKomoditasId)
            ->get(['iot_parameter.parameterCode', 'commodity_parameter.minValue', 'commodity_parameter.maxValue']);

        foreach ($rows as $row) {
            $defaults[$row->parameterCode] = [
                'min' => (float) $row->minValue,
                'max' => (float) $row->maxValue,
            ];
        }

        return $defaults;
    }

    private function evaluateSensorStatus(float $value, ?float $min, ?float $max): string
    {
        if ($value <= 0) {
            return 'normal';
        }

        if ($min !== null && $value < $min) {
            $gap = ($min - $value) / max(abs($min), 1);
            return $gap > 0.1 ? 'danger' : 'warning';
        }

        if ($max !== null && $value > $max) {
            $gap = ($value - $max) / max(abs($max), 1);
            return $gap > 0.1 ? 'danger' : 'warning';
        }

        return 'normal';
    }

    private function worstStatus(string ...$statuses): string
    {
        if (in_array('danger', $statuses, true)) {
            return 'danger';
        }
        if (in_array('warning', $statuses, true)) {
            return 'warning';
        }

        return 'normal';
    }

    private function thresholdFor(array $thresholds, string $code): array
    {
        foreach ([$code, 'AMMON', 'AMMONIA', 'AMMA', 'LIGHT', 'LUX'] as $key) {
            if (isset($thresholds[$key])) {
                return $thresholds[$key];
            }
        }

        return ['min' => null, 'max' => null];
    }

    public function getBarnDetail(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        if (!$coopId || $coopId === 'no-data') {
            return array_merge($barn, [
                'flockAge' => '-',
                'totalBirds' => '-',
                'capacity' => '-',
                'breed' => '-',
                'startDate' => '-',
                'location' => '-',
                'photo' => asset('images/barn-placeholder.jpg')
            ]);
        }

        $coop = DB::table('unitBudidaya')
            ->leftJoin('jenisBudidaya', 'unitBudidaya.jenisBudidayaId', '=', 'jenisBudidaya.id')
            ->where('unitBudidaya.id', $coopId)
            ->select('unitBudidaya.*', 'jenisBudidaya.nama as breedName')
            ->first();

        if (!$coop) {
             return array_merge($barn, [
                'flockAge' => '-',
                'totalBirds' => '-',
                'capacity' => '-',
                'breed' => '-',
                'startDate' => '-',
                'location' => '-',
                'photo' => asset('images/barn-placeholder.jpg')
            ]);
        }

        // Flock age derived from createdAt
        $createdAt = \Carbon\Carbon::parse($coop->createdAt);
        $weeks = (int) floor($createdAt->diffInWeeks(now()));

        return array_merge($barn, [
            'flockAge' => $weeks . ' Minggu',
            'totalBirds' => number_format((float)($coop->jumlah ?? 0), 0, ',', '.'),
            'capacity' => number_format((float)($coop->kapasitas ?? 0), 0, ',', '.'),
            'breed' => $coop->breedName ?? '-',
            'startDate' => $createdAt->format('Y-m-d'),
            'location' => $coop->lokasi ?? '-',
            'photo' => $coop->gambar ? asset('storage/' . $coop->gambar) : asset('images/barn-placeholder.jpg'),
        ]);
    }

    public function getBarnSensors(array $barn): array
    {
        if (empty($barn['id']) || $barn['id'] === 'no-data') {
            return [
                ['label' => 'Suhu', 'value' => 0, 'unit' => '°C', 'min' => 18, 'max' => 30, 'idealMin' => 20, 'idealMax' => 28, 'status' => 'normal', 'icon' => '🌡️'],
                ['label' => 'Kelembapan', 'value' => 0, 'unit' => '%', 'min' => 30, 'max' => 100, 'idealMin' => 50, 'idealMax' => 70, 'status' => 'normal', 'icon' => '💧'],
                ['label' => 'Amonia', 'value' => 0, 'unit' => 'ppm', 'min' => 0, 'max' => 50, 'idealMin' => 0, 'idealMax' => 15, 'status' => 'normal', 'icon' => '🌬️'],
                ['label' => 'Cahaya', 'value' => 0, 'unit' => 'lux', 'min' => 0, 'max' => 50, 'idealMin' => 15, 'idealMax' => 30, 'status' => 'normal', 'icon' => '☀️'],
            ];
        }

        return [
            ['label' => 'Suhu', 'value' => floatval(str_replace('°C', '', $barn['summary']['avg_temp'])), 'unit' => '°C', 'min' => 18, 'max' => 45, 'idealMin' => 20, 'idealMax' => 28, 'status' => $barn['sensors'][0]['status'] ?? 'normal', 'icon' => '🌡️'],
            ['label' => 'Kelembapan', 'value' => floatval(str_replace('%', '', $barn['summary']['humidity'])), 'unit' => '%', 'min' => 30, 'max' => 100, 'idealMin' => 50, 'idealMax' => 70, 'status' => $barn['sensors'][1]['status'] ?? 'normal', 'icon' => '💧'],
            ['label' => 'Amonia', 'value' => floatval(str_replace('ppm', '', $barn['summary']['ammonia'])), 'unit' => 'ppm', 'min' => 0, 'max' => 50, 'idealMin' => 0, 'idealMax' => 20, 'status' => $barn['sensors'][2]['status'] ?? 'normal', 'icon' => '🌬️'],
            ['label' => 'Cahaya', 'value' => floatval(str_replace(' lx', '', $barn['summary']['lux'])), 'unit' => 'lux', 'min' => 0, 'max' => 500, 'idealMin' => 15, 'idealMax' => 50, 'status' => $barn['sensors'][3]['status'] ?? 'normal', 'icon' => '☀️'],
        ];
    }

    public function getBarnSensorTrend($barnId): array
    {
        $labels = [];
        $temp = [];
        $hum = [];
        $ammonia = [];
        $light = [];

        // Pre-fill labels 24 hours back to ensure continuity
        for ($i = 23; $i >= 0; $i--) {
            $labels[] = now()->subHours($i)->format('H:00');
            $temp[] = null;
            $hum[] = null;
            $ammonia[] = null;
            $light[] = null;
        }

        if (!$barnId || $barnId === 'no-data') {
            return ['labels' => $labels, 'temperature' => array_map(fn() => 0, $temp), 'humidity' => array_map(fn() => 0, $hum), 'ammonia' => array_map(fn() => 0, $ammonia), 'light' => array_map(fn() => 0, $light)];
        }

        $devices = DB::table('iot_device')->where('unitBudidayaId', $barnId)->pluck('id')->toArray();
        if (!empty($devices)) {
            $yesterday = now()->subHours(24);
            $logs = DB::table('iot_sensor_data')
                ->join('iot_parameter', 'iot_parameter.id', '=', 'iot_sensor_data.parameterId')
                ->whereIn('iot_sensor_data.deviceId', $devices)
                ->where('iot_sensor_data.sensorTimestamp', '>=', $yesterday)
                ->selectRaw('iot_parameter.parameterCode as code, DATE_FORMAT(iot_sensor_data.sensorTimestamp, "%H:00") as hour_label, AVG(iot_sensor_data.value) as avg_value')
                ->groupBy('code', 'hour_label')
                ->get();

            // Map data to the correct hours
            foreach ($logs as $log) {
                // Find index
                $idx = array_search($log->hour_label, $labels);
                if ($idx !== false) {
                    if ($log->code === 'TEMP')
                        $temp[$idx] = round($log->avg_value, 1);
                    if ($log->code === 'HUMID')
                        $hum[$idx] = round($log->avg_value, 1);
                    if ($log->code === 'AMMON' || $log->code === 'AMMO' || $log->code === 'AMMA' || $log->code === 'AMMONIA')
                        $ammonia[$idx] = round($log->avg_value, 1);
                    if ($log->code === 'LIGHT' || $log->code === 'LUX')
                        $light[$idx] = round($log->avg_value, 0);
                }
            }

            // Interpolate nulls or set to 0
            $temp = $this->interpolateArray($temp);
            $hum = $this->interpolateArray($hum);
            $ammonia = $this->interpolateArray($ammonia);
            $light = $this->interpolateArray($light);
        } else {
             $temp = array_map(fn() => 0, $temp);
             $hum = array_map(fn() => 0, $hum);
             $ammonia = array_map(fn() => 0, $ammonia);
             $light = array_map(fn() => 0, $light);
        }

        return ['labels' => $labels, 'temperature' => $temp, 'humidity' => $hum, 'ammonia' => $ammonia, 'light' => $light];
    }

    private function interpolateArray(array $arr): array
    {
        $lastVal = 0;
        foreach ($arr as $k => $v) {
            if ($v !== null) {
                $lastVal = $v;
            } else {
                $arr[$k] = $lastVal;
            }
        }
        return $arr;
    }

    public function getBarnKpi(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        if (!$coopId || $coopId === 'no-data') {
            return ['hdp' => 0, 'hhep' => 0, 'feedIntake' => 0, 'fcr' => 0, 'gradeTelur' => ['A' => 0, 'B' => 0, 'C' => 0], 'mortalitas' => 0, 'afkir' => 0, 'usiaAwalBertelur' => '-', 'puncakProduksi' => 'Belum Produksi'];
        }

        $today = now()->toDateString();
        $coop = DB::table('unitBudidaya')->where('id', $coopId)->first(['jumlah', 'createdAt']);
        if (!$coop) {
            return ['hdp' => 0, 'hhep' => 0, 'feedIntake' => 0, 'fcr' => 0, 'gradeTelur' => ['A' => 0, 'B' => 0, 'C' => 0], 'mortalitas' => 0, 'afkir' => 0, 'usiaAwalBertelur' => '-', 'puncakProduksi' => 'Belum Produksi'];
        }
        $populasiAwal = $coop->jumlah ?? 0;

        $mati = DB::table('kematian')
            ->join('laporan', 'laporan.id', '=', 'kematian.laporanId')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->count();

        $totalMati = $mati;
        $populasiAwal += $totalMati; // reconstruct populasi mula-mula
        $populasiSaatIni = $populasiAwal - $totalMati;

        $mortalitas = $populasiAwal > 0 ? ($totalMati / $populasiAwal) * 100 : 0;

        $panenToday = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->selectRaw('COALESCE(SUM(panen.jumlah), 0) as totalTelur, COALESCE(SUM(COALESCE(panen.berat, panen.jumlah * 0.06)), 0) as totalEggMass')
            ->first();

        $totalTelur = (float) ($panenToday->totalTelur ?? 0);
        $totalEggMass = (float) ($panenToday->totalEggMass ?? 0);

        $pakanToday = DB::table('harianTernak')
            ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->sum('harianTernak.pakan');

        $hdp = $populasiSaatIni > 0 ? ($totalTelur / $populasiSaatIni) * 100 : 0;
        $hhep = $populasiAwal > 0 ? ($totalTelur / $populasiAwal) * 100 : 0;
        $feedIntake = $populasiSaatIni > 0 ? ($pakanToday / $populasiSaatIni) * 1000 : 0;
        $fcr = $totalEggMass > 0 ? $pakanToday / $totalEggMass : 0;

        // Fetch valid grades for chart
        $grades = DB::table('panenRincianGrade')
            ->join('panen', 'panen.id', '=', 'panenRincianGrade.panenId')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->join('grade', 'panenRincianGrade.gradeId', '=', 'grade.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->where('panenRincianGrade.isDeleted', 0)
            ->where('grade.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->selectRaw('grade.nama as grade_name, SUM(panenRincianGrade.jumlah) as total')
            ->groupBy('grade.nama')
            ->pluck('total', 'grade_name')->toArray();

        $totalGradeA = $grades['Grade A'] ?? 0;
        $totalGradeB = $grades['Grade B'] ?? 0;
        $totalGradeC = $grades['Grade C'] ?? 0;
        $totalGrades = $totalGradeA + $totalGradeB + $totalGradeC;

    	$gradeTelur = ['A' => 0, 'B' => 0, 'C' => 0];
        if($totalGrades > 0) {
            $gradeTelur = [
                'A' => round(($totalGradeA / $totalGrades) * 100),
                'B' => round(($totalGradeB / $totalGrades) * 100),
                'C' => round(($totalGradeC / $totalGrades) * 100),
            ];
        }

        return [
            'hdp' => round($hdp, 1),
            'hhep' => round($hhep, 1),
            'feedIntake' => round($feedIntake, 0),
            'fcr' => round($fcr, 2),
            'gradeTelur' => $gradeTelur,
            'mortalitas' => round($mortalitas, 2),
            'afkir' => 0,
            'usiaAwalBertelur' => '18 Minggu',
            'puncakProduksi' => 'Fase Produksi',
        ];
    }

    public function getBarnProductionLog(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        if (!$coopId || $coopId === 'no-data')
            return [];

        $coop = DB::table('unitBudidaya')->where('id', $coopId)->first(['jumlah']);
        $populasi = $coop ? (float)$coop->jumlah : 0;

        $log = [];
        // Populate exactly 7 days
        for ($i = 0; $i < 7; $i++) {
            $date = now()->subDays($i)->toDateString();

            $telur = DB::table('panen')
                ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
                ->where('laporan.unitBudidayaId', $coopId)->whereDate('laporan.createdAt', $date)
                ->where('laporan.isDeleted', 0)
                ->where('panen.isDeleted', 0)
                ->sum('panen.jumlah');

            $pakan = DB::table('harianTernak')
                ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
                ->where('laporan.unitBudidayaId', $coopId)->whereDate('laporan.createdAt', $date)
                ->where('laporan.isDeleted', 0)
                ->where('harianTernak.isDeleted', 0)
                ->sum('harianTernak.pakan');

            $mati = DB::table('kematian')
                ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
                ->where('laporan.unitBudidayaId', $coopId)->whereDate('kematian.tanggal', $date)
                ->where('laporan.isDeleted', 0)
                ->where('kematian.isDeleted', 0)
                ->count();

            $rejects = DB::table('panenRincianGrade')
                ->join('panen', 'panen.id', '=', 'panenRincianGrade.panenId')
                ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
                ->join('grade', 'panenRincianGrade.gradeId', '=', 'grade.id')
                ->where('laporan.unitBudidayaId', $coopId)
                ->whereDate('laporan.createdAt', $date)
                ->where('laporan.isDeleted', 0)
                ->where('panen.isDeleted', 0)
                ->where('panenRincianGrade.isDeleted', 0)
                ->where('grade.isDeleted', 0)
                ->whereRaw('LOWER(grade.nama) LIKE ?', ['%afkir%'])
                ->sum('panenRincianGrade.jumlah');

            $hdp = $populasi > 0 && $telur > 0 ? round(($telur / $populasi) * 100, 1) . '%' : '-';

            $log[] = [
                'date' => Carbon::parse($date)->format('d M Y'),
                'eggs' => $telur > 0 ? number_format((float)$telur, 0, ',', '.') : '-',
                'rejects' => $rejects > 0 ? number_format((float)$rejects, 0, ',', '.') : '-',
                'feedKg' => $pakan > 0 ? round($pakan, 1) : '-',
                'waterL' => '-', // No water count in schema
                'mortality' => $mati > 0 ? $mati : '-',
                'hdp' => $hdp,
            ];
        }
        return $log;
    }

    public function getBarnIotDevices(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        if (!$coopId || $coopId === 'no-data')
            return [];

        $devices = DB::table('iot_device')
            ->leftJoin('iot_connection_config', 'iot_device.connectionConfigId', '=', 'iot_connection_config.id')
            ->leftJoin('iot_protocol', 'iot_connection_config.protocolId', '=', 'iot_protocol.id')
            ->where('iot_device.unitBudidayaId', $coopId)
            ->select('iot_device.*', 'iot_protocol.protocolName')
            ->get();

        $result = [];
        foreach ($devices as $d) {
            $lastData = DB::table('iot_sensor_data')
                ->where('deviceId', $d->id)
                ->orderBy('sensorTimestamp', 'desc')
                ->first(['sensorTimestamp']);

            $result[] = [
                'code' => $d->deviceCode,
                'name' => $d->deviceName,
                'status' => strtolower($d->status) === 'online' || strtolower($d->status) === 'active' ? 'active' : 'inactive',
                'lastData' => $lastData ? Carbon::parse($lastData->sensorTimestamp)->diffForHumans() : 'No Data',
                'protocol' => $d->protocolName ?? '-',
            ];
        }

        return $result;
    }

    /* SPK logic kept as dummy per request */
    public function getBarnSpkResult(array $barn): array
    {
        $results = [
            0 => ['status' => 'Excellent', 'color' => 'emerald', 'title' => 'Performa Optimal', 'description' => 'Lingkungan kandang dalam kondisi ideal. HDP tinggi di 94.5%, FCR efisien. Pertahankan manajemen pakan dan ventilasi saat ini.', 'score' => 92],
            1 => ['status' => 'Maintain', 'color' => 'blue', 'title' => 'Performa Baik — Tingkatkan', 'description' => 'Produksi masih dalam fase ramp-up. Kelembapan sedikit tinggi, pertimbangkan peningkatan sirkulasi udara untuk optimasi.', 'score' => 85],
            2 => ['status' => 'Growing', 'color' => 'purple', 'title' => 'Fase Pertumbuhan', 'description' => 'Flock masih dalam fase grower (12 minggu). Fokus pada kualitas pakan starter dan kontrol suhu untuk pertumbuhan optimal.', 'score' => 78],
            3 => ['status' => 'Monitor', 'color' => 'amber', 'title' => 'Perlu Perhatian Ventilasi', 'description' => 'Suhu 26°C mendekati batas atas. Ammonia 18ppm sudah moderate. Segera periksa sistem ventilasi dan kurangi kepadatan jika perlu.', 'score' => 72],
            4 => ['status' => 'Aging', 'color' => 'amber', 'title' => 'Pertimbangkan Afkir Bertahap', 'description' => 'Flock sudah 52 minggu. HDP turun ke 82.5% dengan FCR meningkat. Evaluasi titik impas untuk keputusan culling.', 'score' => 65],
            5 => ['status' => 'Alert', 'color' => 'red', 'title' => 'Suhu Kritis — Tindakan Segera', 'description' => 'Suhu kandang 27°C melebihi batas ideal. Ammonia 22ppm tinggi. Aktifkan ventilasi darurat dan monitor mortalitas.', 'score' => 52],
        ];
        $id = is_numeric($barn['id']) ? (int) $barn['id'] : 0;
        return $results[$id] ?? $results[0];
    }

    public function getBarnSpkMessages(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        $status = $barn['status'] ?? 'normal';
        $kpi = $this->getBarnKpi($barn);
        $hasTodayReport = $coopId && $coopId !== 'no-data'
            ? DB::table('laporan')
                ->where('unitBudidayaId', $coopId)
                ->where('isDeleted', 0)
                ->whereDate('createdAt', now()->toDateString())
                ->exists()
            : false;

        if (!$hasTodayReport) {
            return [
                ['mode' => 'Data Harian', 'status' => 'warning', 'message' => 'Belum ada laporan panen atau pakan hari ini. Hasil SPK produktivitas belum lengkap.'],
                ['mode' => 'Lingkungan', 'status' => $status === 'danger' ? 'danger' : ($status === 'warning' ? 'warning' : 'normal'), 'message' => $status === 'danger' ? 'Parameter lingkungan berada di zona kritis.' : ($status === 'warning' ? 'Parameter lingkungan perlu dipantau.' : 'Parameter lingkungan masih dalam batas aman.')],
            ];
        }

        $productivityStatus = 'normal';
        if (($kpi['hdp'] ?? 0) < 70 || ($kpi['fcr'] ?? 0) > 2.5) {
            $productivityStatus = 'warning';
        }

        return [
            ['mode' => 'Lingkungan', 'status' => $status === 'danger' ? 'danger' : ($status === 'warning' ? 'warning' : 'normal'), 'message' => $status === 'danger' ? 'Suhu dan amonia melebihi ambang batas! Aktifkan ventilasi darurat.' : ($status === 'warning' ? 'Parameter lingkungan mendekati batas atas. Periksa sirkulasi udara.' : 'Seluruh parameter lingkungan dalam kondisi ideal.')],
            ['mode' => 'Produktivitas', 'status' => $productivityStatus, 'message' => $productivityStatus === 'normal' ? 'HDP dan FCR hari ini berada dalam rentang aman.' : 'HDP atau FCR hari ini perlu ditinjau pada halaman Analisa SPK.'],
            ['mode' => 'Pakan', 'status' => ($kpi['feedIntake'] ?? 0) > 0 ? 'normal' : 'warning', 'message' => ($kpi['feedIntake'] ?? 0) > 0 ? 'Konsumsi pakan hari ini sudah tercatat.' : 'Data pakan hari ini belum tercatat.'],
            ['mode' => 'Kesehatan', 'status' => ($kpi['mortalitas'] ?? 0) > 3 ? 'warning' : 'normal', 'message' => ($kpi['mortalitas'] ?? 0) > 3 ? 'Mortalitas kumulatif meningkat, perlu pemeriksaan.' : 'Mortalitas masih dalam batas pemantauan normal.'],
        ];
    }

    public function getBarnDailyDataAudit(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        $today = now()->toDateString();

        if (!$coopId || $coopId === 'no-data') {
            return [
                'date' => Carbon::parse($today)->locale('id')->translatedFormat('d M Y'),
                'available' => [],
                'missing' => [],
                'actions' => [],
            ];
        }

        $hasPanen = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->exists();

        $hasFeed = DB::table('harianTernak')
            ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->exists();

        $hasGrade = DB::table('panenRincianGrade')
            ->join('panen', 'panen.id', '=', 'panenRincianGrade.panenId')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->where('panenRincianGrade.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->exists();

        $hasMortality = DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->whereDate('kematian.tanggal', $today)
            ->exists();

        $hasEggMass = DB::getSchemaBuilder()->hasColumn('panen', 'berat');

        return [
            'date' => Carbon::parse($today)->locale('id')->translatedFormat('d M Y'),
            'available' => [
                ['label' => 'Jumlah telur', 'status' => $hasPanen ? 'ready' : 'empty', 'source' => 'laporan + panen.jumlah'],
                ['label' => 'Berat telur / egg mass', 'status' => $hasEggMass ? ($hasPanen ? 'ready' : 'empty') : 'fallback', 'source' => $hasEggMass ? 'panen.berat' : 'fallback panen.jumlah x 0.06 kg'],
                ['label' => 'Konsumsi pakan', 'status' => $hasFeed ? 'ready' : 'empty', 'source' => 'harianTernak.pakan'],
                ['label' => 'Mortalitas', 'status' => $hasMortality ? 'ready' : 'empty', 'source' => 'kematian.tanggal'],
                ['label' => 'Rincian grade', 'status' => $hasGrade ? 'ready' : 'empty', 'source' => 'panenRincianGrade + grade'],
            ],
            'missing' => [
                ['label' => 'Telur retak', 'source' => 'Belum ada kolom/field khusus di laporan panen'],
                ['label' => 'Telur kotor', 'source' => 'Belum ada kolom/field khusus di laporan panen'],
                ['label' => 'Air minum (liter)', 'source' => 'harianTernak belum menyimpan konsumsi air'],
                ['label' => 'Afkir ayam harian', 'source' => 'Belum ada tabel/field afkir ayam; grade Afkir hanya dapat dibaca sebagai reject telur'],
            ],
            'actions' => [
                'Tambahkan field telur_retak dan telur_kotor pada payload laporan panen Node API jika metrik quality wajib ditampilkan.',
                'Tambahkan konsumsi_air_liter pada laporan harian ternak jika kolom Air (L) tetap dipakai.',
                'Gunakan grade Afkir sebagai reject telur sementara, bukan sebagai broken/dirty egg rate.',
            ],
        ];
    }

    public function getBarnActivityLog(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        if (!$coopId || $coopId === 'no-data')
            return [];

        $activities = DB::table('laporan')
            ->where('unitBudidayaId', $coopId)
            ->where('isDeleted', 0)
            ->orderBy('createdAt', 'desc')
            ->limit(5)
            ->get();

        $logs = [];
        foreach ($activities as $act) {
            $type = 'info';
            $reportType = strtolower((string) $act->tipe);
            if (in_array($reportType, ['panen', 'harian'], true))
                $type = 'success';
            if (in_array($reportType, ['kematian', 'sakit', 'hama'], true))
                $type = 'warning';

            $logs[] = [
                'time' => Carbon::parse($act->createdAt)->diffForHumans(),
                'title' => 'Laporan ' . ucfirst($reportType ?: 'harian'),
                'desc' => $act->judul ?? ($act->catatan ?? 'Telah ditambahkan'),
                'type' => $type
            ];
        }

        if (empty($logs)) {
            $logs[] = ['time' => '-', 'title' => 'Belum ada aktivitas', 'desc' => 'Tidak ada history laporan', 'type' => 'info'];
        }

        return $logs;
    }

    public function getProductivityTrend(?string $coopId = null): array
    {
        $activeCoopIds = $coopId && $coopId !== 'no-data'
            ? [$coopId]
            : $this->getActiveCoopIds(false);

        $labels = [];
        $hdp = [];
        $hhep = [];
        $fcr = [];
        $feedIntake = [];
        $mortality = [];

        if (empty($activeCoopIds)) {
            for ($i = 29; $i >= 0; $i--) {
                $labels[] = now()->subDays($i)->format('d/m');
                $hdp[] = 0;
                $hhep[] = 0;
                $fcr[] = 0;
                $feedIntake[] = 0;
                $mortality[] = 0;
            }
            return ['labels' => $labels, 'hdp' => $hdp, 'hhep' => $hhep, 'fcr' => $fcr, 'feedIntake' => $feedIntake, 'mortality' => $mortality];
        }

        $startDate = now()->subDays(29)->toDateString();
        $endDate = now()->toDateString();

        $panens = DB::table('panen')->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', '>=', $startDate)->whereDate('laporan.createdAt', '<=', $endDate)
            ->selectRaw('DATE(laporan.createdAt) as dt, SUM(panen.jumlah) as totalTelur, SUM(COALESCE(panen.berat, panen.jumlah * 0.06)) as totalMass')
            ->groupBy('dt')->get()->keyBy('dt')->toArray();

        $pakans = DB::table('harianTernak')->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', '>=', $startDate)->whereDate('laporan.createdAt', '<=', $endDate)
            ->selectRaw('DATE(laporan.createdAt) as dt, SUM(harianTernak.pakan) as totalPakan')
            ->groupBy('dt')->get()->keyBy('dt')->toArray();

        $matis = DB::table('kematian')->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->whereDate('kematian.tanggal', '>=', $startDate)->whereDate('kematian.tanggal', '<=', $endDate)
            ->selectRaw('DATE(kematian.tanggal) as dt, count(kematian.id) as totalMati')
            ->groupBy('dt')->get()->keyBy('dt')->toArray();

        $basePop = DB::table('unitBudidaya')->whereIn('id', $activeCoopIds)->sum('jumlah');

        for ($i = 29; $i >= 0; $i--) {
            $dt = now()->subDays($i)->toDateString();
            $labels[] = now()->subDays($i)->format('d/m');

            $p = $panens[$dt] ?? null;
            $pk = $pakans[$dt] ?? null;
            $m = $matis[$dt] ?? null;

            $telur = $p ? $p->totalTelur : 0;
            $mass = $p ? $p->totalMass : 0;
            $pakan = $pk ? $pk->totalPakan : 0;
            $mati = $m ? $m->totalMati : 0;

            $_hdp = $basePop > 0 ? ($telur / $basePop) * 100 : 0;
            $_fcr = $mass > 0 ? $pakan / $mass : 0;
            $_fi = $basePop > 0 ? ($pakan / $basePop) * 1000 : 0;
            $_mortality = $basePop > 0 ? ($mati / $basePop) * 100 : 0;

            $hdp[] = round($_hdp, 1);
            $hhep[] = round($_hdp * 0.95, 1);
            $fcr[] = round($_fcr, 2);
            $feedIntake[] = round($_fi, 1);
            $mortality[] = round($_mortality, 2);
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

    public function getEggQuality(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        $today = now()->toDateString();
        $empty = [
            'hasReport' => false,
            'hasGradeDetail' => false,
            'sourceDate' => Carbon::parse($today)->locale('id')->translatedFormat('d M Y'),
            'lastPanenAt' => null,
            'totalEggs' => 0,
            'totalWeightKg' => 0,
            'avgWeightGram' => null,
            'gradeDistribution' => [],
            'rejectRate' => null,
            'rejectStatus' => 'missing',
            'brokenRate' => null,
            'brokenStatus' => 'missing',
            'dirtyRate' => null,
            'dirtyStatus' => 'missing',
            'missingFields' => [
                ['label' => 'Telur retak', 'description' => 'Belum tersedia sebagai field di laporan panen.'],
                ['label' => 'Telur kotor', 'description' => 'Belum tersedia sebagai field di laporan panen.'],
            ],
        ];

        if (!$coopId || $coopId === 'no-data') {
            return $empty;
        }

        $lastPanenAt = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->max('laporan.createdAt');

        $panens = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->get([
                'panen.id',
                'panen.jumlah',
                'panen.berat',
            ]);

        if ($panens->isEmpty()) {
            return array_merge($empty, [
                'lastPanenAt' => $lastPanenAt
                    ? Carbon::parse($lastPanenAt)->locale('id')->translatedFormat('d M Y, H:i')
                    : null,
            ]);
        }

        $panenIds = $panens->pluck('id')->all();
        $totalEggs = (float) $panens->sum('jumlah');
        $totalWeightKg = (float) $panens->sum(fn ($p) => $p->berat !== null ? (float) $p->berat : ((float) $p->jumlah * 0.06));

        $grades = DB::table('panenRincianGrade')
            ->join('grade', 'panenRincianGrade.gradeId', '=', 'grade.id')
            ->whereIn('panenRincianGrade.panenId', $panenIds)
            ->where('panenRincianGrade.isDeleted', 0)
            ->where('grade.isDeleted', 0)
            ->selectRaw('grade.nama as grade_name, SUM(panenRincianGrade.jumlah) as total')
            ->groupBy('grade.nama')
            ->pluck('total', 'grade_name')
            ->toArray();

        $gradeColors = [
            'Grade AA' => 'bg-emerald-900',
            'Grade A' => 'bg-emerald-600',
            'Grade B' => 'bg-sky-500',
            'Grade C' => 'bg-amber-500',
            'Afkir' => 'bg-red-500',
        ];
        $orderedGrades = ['Grade AA', 'Grade A', 'Grade B', 'Grade C', 'Afkir'];
        $gradeTotal = array_sum($grades);
        $denominator = max($gradeTotal, $totalEggs, 1);
        $distribution = [];

        foreach ($orderedGrades as $gradeName) {
            $count = (float) ($grades[$gradeName] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $distribution[] = [
                'label' => $gradeName,
                'count' => $count,
                'pct' => round(($count / $denominator) * 100),
                'color' => $gradeColors[$gradeName],
            ];
        }

        $rejectCount = (float) ($grades['Afkir'] ?? 0);
        $rejectRate = $totalEggs > 0 ? round(($rejectCount / $totalEggs) * 100, 2) : null;

        return array_merge($empty, [
            'hasReport' => true,
            'hasGradeDetail' => !empty($distribution),
            'lastPanenAt' => $lastPanenAt
                ? Carbon::parse($lastPanenAt)->locale('id')->translatedFormat('d M Y, H:i')
                : null,
            'totalEggs' => $totalEggs,
            'totalWeightKg' => round($totalWeightKg, 2),
            'avgWeightGram' => $totalEggs > 0 ? round(($totalWeightKg * 1000) / $totalEggs, 1) : null,
            'gradeDistribution' => $distribution,
            'rejectRate' => $rejectRate,
            'rejectStatus' => $rejectRate !== null && $rejectRate <= 5 ? 'normal' : 'warning',
        ]);
    }

    public function getKpiMetrics(): array
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $currentMonth = now()->startOfMonth()->toDateString();
        $lastMonthStart = now()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->subMonth()->endOfMonth()->toDateString();

        $activeCoopIds = $this->getActiveCoopIds();
        $totalAyamHidup = empty($activeCoopIds)
            ? 0
            : (float) DB::table('unitBudidaya')->whereIn('id', $activeCoopIds)->sum('jumlah');

        if (empty($activeCoopIds) || $totalAyamHidup <= 0) {
            return [
                ['label' => 'HDP %', 'value' => '0%', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
                ['label' => 'FCR', 'value' => '0', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
                ['label' => 'Umur Biologis', 'value' => '0', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
                ['label' => 'Feed Intake', 'value' => '0g', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
                ['label' => 'Egg Mass', 'value' => '0kg', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
                ['label' => 'Mortality', 'value' => '0%', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
            ];
        }

        $panenToday = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->selectRaw('COALESCE(SUM(panen.jumlah), 0) as totalTelur, COALESCE(SUM(COALESCE(panen.berat, panen.jumlah * 0.06)), 0) as totalEggMass')
            ->first();

        $totalTelurToday = (float) ($panenToday->totalTelur ?? 0);
        $totalEggMassToday = (float) ($panenToday->totalEggMass ?? 0);

        $panenYesterday = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $yesterday)
            ->selectRaw('COALESCE(SUM(panen.jumlah), 0) as totalTelur, COALESCE(SUM(COALESCE(panen.berat, panen.jumlah * 0.06)), 0) as totalEggMass')
            ->first();

        $totalTelurYesterday = (float) ($panenYesterday->totalTelur ?? 0);
        $totalEggMassYesterday = (float) ($panenYesterday->totalEggMass ?? 0);

        $pakanToday = DB::table('harianTernak')
            ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->sum('harianTernak.pakan');

        $pakanYesterday = DB::table('harianTernak')
            ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', $yesterday)
            ->sum('harianTernak.pakan');

        $hdpToday = $totalAyamHidup > 0 ? round(($totalTelurToday / $totalAyamHidup) * 100, 1) : 0;
        $hdpYesterday = $totalAyamHidup > 0 ? round(($totalTelurYesterday / $totalAyamHidup) * 100, 1) : 0;

        $feedIntakeToday = $totalAyamHidup > 0 ? round(($pakanToday / $totalAyamHidup) * 1000, 0) : 0;
        $feedIntakeYesterday = $totalAyamHidup > 0 ? round(($pakanYesterday / $totalAyamHidup) * 1000, 0) : 0;

        $fcrToday = $totalEggMassToday > 0 ? round($pakanToday / $totalEggMassToday, 2) : 0;
        $fcrYesterday = $totalEggMassYesterday > 0 ? round($pakanYesterday / $totalEggMassYesterday, 2) : 0;

        $mortalityThisMonth = DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->whereDate('kematian.tanggal', '>=', $currentMonth)
            ->count();

        $mortalityLastMonth = DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->whereDate('kematian.tanggal', '>=', $lastMonthStart)
            ->whereDate('kematian.tanggal', '<=', $lastMonthEnd)
            ->count();

        $populasiAwal = $totalAyamHidup + $mortalityThisMonth;
        $mortalityPct = $populasiAwal > 0 ? round(($mortalityThisMonth / $populasiAwal) * 100, 2) : 0;

        $oldestCoop = DB::table('unitBudidaya')
            ->whereIn('id', $activeCoopIds)
            ->orderBy('createdAt', 'asc')
            ->first();
        $umurBiologis = $oldestCoop ? (int) floor(Carbon::parse($oldestCoop->createdAt)->diffInWeeks(now())) . ' Mgg' : '0 Mgg';

        return [
            [
                'label' => 'HDP %',
                'value' => $hdpToday . '%',
                'trend' => $this->calcTrend($hdpToday, $hdpYesterday, 'higher_is_better'),
            ],
            [
                'label' => 'FCR',
                'value' => $fcrToday > 0 ? (string) $fcrToday : '0',
                'trend' => $this->calcTrend($fcrToday, $fcrYesterday, 'lower_is_better'),
            ],
            [
                'label' => 'Umur Biologis',
                'value' => $umurBiologis,
                'trend' => ['direction' => 'stable', 'value' => 'Fase Produksi', 'status' => 'neutral'],
            ],
            [
                'label' => 'Feed Intake',
                'value' => $feedIntakeToday . 'g',
                'trend' => $this->calcTrend($feedIntakeToday, $feedIntakeYesterday, 'neutral'),
            ],
            [
                'label' => 'Egg Mass',
                'value' => round($totalEggMassToday, 1) . 'kg',
                'trend' => $this->calcTrend($totalEggMassToday, $totalEggMassYesterday, 'higher_is_better'),
            ],
            [
                'label' => 'Mortality',
                'value' => $mortalityPct . '%',
                'trend' => $this->calcMortalityTrend($mortalityThisMonth, $mortalityLastMonth),
            ],
        ];
    }

    private function calcTrend(float $current, float $previous, string $mode): array
    {
        if ($previous == 0 && $current == 0) {
            return ['direction' => 'stable', 'value' => 'Stable', 'status' => 'neutral'];
        }

        $diff = round($current - $previous, 2);

        if (abs($diff) < 0.01) {
            return ['direction' => 'stable', 'value' => 'Stable', 'status' => 'neutral'];
        }

        $direction = $diff > 0 ? 'up' : 'down';
        $absVal = abs($diff);

        if ($mode === 'higher_is_better') {
            $status = $diff > 0 ? 'positive' : 'warning';
        } elseif ($mode === 'lower_is_better') {
            $status = $diff < 0 ? 'positive' : 'warning';
        } else {
            $status = abs($diff) > 10 ? 'warning' : 'neutral';
        }

        return ['direction' => $direction, 'value' => (string) $absVal, 'status' => $status];
    }

    private function calcMortalityTrend(int $thisMonth, int $lastMonth): array
    {
        $diff = $thisMonth - $lastMonth;

        if ($diff === 0) {
            return ['direction' => 'stable', 'value' => 'Stable', 'status' => 'neutral'];
        }

        return [
            'direction' => $diff > 0 ? 'up' : 'down',
            'value' => abs($diff) . ' ekor',
            'status' => $diff > 0 ? 'warning' : 'positive',
        ];
    }

    public function getChartData(string $range = '30d'): array
    {
        $activeCoops = $this->getActiveCoopIds(false);
        $pop = empty($activeCoops)
            ? 0
            : (float) DB::table('unitBudidaya')->whereIn('id', $activeCoops)->sum('jumlah');

        [$startDate, $labelFormat, $stepDays] = match ($range) {
            '90d' => [now()->subDays(89)->toDateString(), 'd/m', 1],
            'ytd' => [now()->startOfYear()->toDateString(), 'd/m', 1],
            default => [now()->subDays(29)->toDateString(), 'd/m', 1],
        };

        $endDate = now()->toDateString();
        $labels = [];
        $hdpArr = [];
        $fcrArr = [];

        if (empty($activeCoops) || $pop <= 0) {
            $days = max(1, Carbon::parse($startDate)->diffInDays($endDate) + 1);
            for ($i = $days - 1; $i >= 0; $i -= $stepDays) {
                $dt = Carbon::parse($endDate)->subDays($i);
                $labels[] = $dt->format($labelFormat);
                $hdpArr[] = 0;
                $fcrArr[] = 0;
            }

            return ['labels' => $labels, 'hdp' => $hdpArr, 'fcr' => $fcrArr, 'range' => $range];
        }

        $panens = DB::table('panen')->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoops)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', '>=', $startDate)
            ->whereDate('laporan.createdAt', '<=', $endDate)
            ->selectRaw('DATE(laporan.createdAt) as dt, SUM(panen.jumlah) as tTelur, SUM(COALESCE(panen.berat, panen.jumlah * 0.06)) as tMass')
            ->groupBy('dt')
            ->get()
            ->keyBy('dt');

        $pakans = DB::table('harianTernak')->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoops)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', '>=', $startDate)
            ->whereDate('laporan.createdAt', '<=', $endDate)
            ->selectRaw('DATE(laporan.createdAt) as dt, SUM(harianTernak.pakan) as totalPakan')
            ->groupBy('dt')
            ->get()
            ->keyBy('dt');

        $cursor = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($cursor->lte($end)) {
            $dt = $cursor->toDateString();
            $labels[] = $cursor->format($labelFormat);

            $p = $panens[$dt] ?? null;
            $pk = $pakans[$dt] ?? null;
            $telur = $p ? (float) $p->tTelur : 0;
            $mass = $p ? (float) $p->tMass : 0;
            $pakan = $pk ? (float) $pk->totalPakan : 0;

            $hdpArr[] = $pop > 0 ? round(($telur / $pop) * 100, 1) : 0;
            $fcrArr[] = $mass > 0 ? round($pakan / $mass, 2) : 0;

            $cursor->addDays($stepDays);
        }

        return [
            'labels' => $labels,
            'hdp' => $hdpArr,
            'fcr' => $fcrArr,
            'range' => $range,
        ];
    }

    public function getChartDataByRange(): array
    {
        return [
            '30d' => $this->getChartData('30d'),
            '90d' => $this->getChartData('90d'),
            'ytd' => $this->getChartData('ytd'),
        ];
    }

    public function getBarnEnvironment(): array
    {
        if ($this->cachedBarnEnvironment !== null) {
            return $this->cachedBarnEnvironment;
        }

        $thresholds = $this->getCommodityThresholds();

        $activeCoops = $this->activeJenisBudidayaId
            ? DB::table('unitBudidaya')
                ->where('jenisBudidayaId', $this->activeJenisBudidayaId)
                ->where('status', 1)
                ->where('isDeleted', 0)
                ->get()
            : collect();

        $barns = [];
        foreach ($activeCoops as $coop) {
            $devices = DB::table('iot_device')
                ->where('unitBudidayaId', $coop->id)
                ->pluck('id')->toArray();

            $temp = 0.0;
            $hum = 0.0;
            $ammo = 0.0;
            $lux = 0.0;

            if (!empty($devices)) {
                $latestLogs = DB::table('iot_sensor_data')
                    ->join('iot_parameter', 'iot_sensor_data.parameterId', '=', 'iot_parameter.id')
                    ->whereIn('iot_sensor_data.deviceId', $devices)
                    ->orderBy('iot_sensor_data.sensorTimestamp', 'desc')
                    ->limit(50)
                    ->get(['iot_sensor_data.value', 'iot_parameter.parameterCode']);

                $mapped = [];
                foreach ($latestLogs as $l) {
                    if (!isset($mapped[$l->parameterCode])) {
                        $mapped[$l->parameterCode] = (float) $l->value;
                    }
                }

                $temp = $mapped['TEMP'] ?? 0;
                $hum = $mapped['HUMID'] ?? 0;
                $ammo = $mapped['AMMON'] ?? ($mapped['AMMA'] ?? ($mapped['AMMONIA'] ?? 0));
                $lux = $mapped['LIGHT'] ?? ($mapped['LUX'] ?? 0);
            }

            $tempThr = $this->thresholdFor($thresholds, 'TEMP');
            $humThr = $this->thresholdFor($thresholds, 'HUMID');
            $ammoThr = $this->thresholdFor($thresholds, 'AMMON');
            $luxThr = $this->thresholdFor($thresholds, 'LUX');

            $tempStatus = $this->evaluateSensorStatus($temp, $tempThr['min'], $tempThr['max']);
            $humStatus = $this->evaluateSensorStatus($hum, $humThr['min'], $humThr['max']);
            $ammoStatus = $this->evaluateSensorStatus($ammo, $ammoThr['min'], $ammoThr['max']);
            $luxStatus = $this->evaluateSensorStatus($lux, $luxThr['min'], $luxThr['max']);
            $status = $this->worstStatus($tempStatus, $humStatus, $ammoStatus, $luxStatus);

            $statusLabels = [
                'normal' => 'Normal',
                'warning' => 'Warning',
                'danger' => 'Critical',
            ];

            $ammoMax = $ammoThr['max'] ?? 15;

            $barns[] = [
                'id' => $coop->id,
                'name' => $coop->nama,
                'temp' => round($temp, 1),
                'status' => $status,
                'sensors' => [
                    ['label' => 'Temperature (' . round($temp, 1) . '°C)', 'percent' => $temp > 0 ? min(($temp / max($tempThr['max'] ?? 40, 1)) * 100, 100) : 0, 'status' => $tempStatus, 'statusLabel' => $statusLabels[$tempStatus]],
                    ['label' => 'Humidity (' . round($hum, 1) . '%)', 'percent' => min($hum, 100), 'status' => $humStatus, 'statusLabel' => $statusLabels[$humStatus]],
                    ['label' => 'Ammonia (' . round($ammo, 1) . 'ppm)', 'percent' => min($ammo * 2, 100), 'status' => $ammoStatus, 'statusLabel' => $statusLabels[$ammoStatus]],
                    ['label' => 'Light (' . round($lux, 1) . ' lx)', 'percent' => min($lux, 100), 'status' => $luxStatus, 'statusLabel' => $statusLabels[$luxStatus]],
                ],
                'summary' => [
                    'avg_temp' => round($temp, 1) . '°C',
                    'humidity' => round($hum, 1) . '%',
                    'ammonia' => round($ammo, 1) . 'ppm',
                    'ammonia_ok' => $ammoStatus === 'normal',
                    'lux' => round($lux, 1) . ' lx',
                    'temp_status' => $tempStatus,
                    'humidity_status' => $humStatus,
                    'ammonia_status' => $ammoStatus,
                    'lux_status' => $luxStatus,
                ],
            ];
        }

        if (empty($barns)) {
            $barns[] = [
                'id' => 'no-data',
                'name' => 'Belum ada Kandang',
                'temp' => '-',
                'status' => 'normal',
                'sensors' => [],
                'summary' => ['avg_temp' => '-', 'humidity' => '-', 'ammonia' => '-', 'ammonia_ok' => true, 'lux' => '-'],
            ];
        }

        $this->cachedBarnEnvironment = ['barns' => $barns];

        return $this->cachedBarnEnvironment;
    }

    public function getProduktivitasData(?string $coopId = null): array
    {
        $activeCoops = $coopId ? [$coopId] : $this->getActiveCoopIds(false);
        $coopSum = empty($activeCoops)
            ? 0
            : (float) DB::table('unitBudidaya')->whereIn('id', $activeCoops)->sum('jumlah');

        if ($coopSum <= 0) {
            return [
                'spider' => [
                    'labels' => ['HDP', 'Umur Biologis', 'Feed Consumption', 'Mortalitas'],
                    'values' => [0, 0, 0, 0],
                ],
                'indicators' => [
                    ['label' => 'HDP', 'value' => '-', 'color' => 'neutral', 'detail' => '-', 'score' => 0],
                    ['label' => 'Umur Biologis', 'value' => '-', 'color' => 'neutral', 'detail' => '-', 'score' => 0],
                    ['label' => 'Feed Consumption', 'value' => '-', 'color' => 'neutral', 'detail' => '-', 'score' => 0],
                    ['label' => 'Mortalitas', 'value' => '-', 'color' => 'neutral', 'detail' => '-', 'score' => 0],
                ],
                'productivitySensors' => [],
            ];
        }

        $panenQuery = DB::table('panen')->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoops)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', now()->toDateString());

        $panen = $panenQuery->selectRaw('SUM(panen.jumlah) as tTelur, SUM(COALESCE(panen.berat, panen.jumlah * 0.06)) as tMass')->first();

        $hdp = $coopSum > 0 ? (float) ($panen->tTelur ?? 0) / $coopSum * 100 : 0;

        $pakan = DB::table('harianTernak')->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoops)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', now()->toDateString())
            ->sum('harianTernak.pakan');

        $fi = $coopSum > 0 ? ($pakan / $coopSum) * 1000 : 0;

        $mati = DB::table('kematian')->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoops)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->whereDate('kematian.tanggal', '>=', now()->startOfMonth()->toDateString())
            ->count();
        $mortality = $coopSum > 0 ? ($mati / $coopSum) * 100 : 0;

        $avgWeeks = 0;
        if (!empty($activeCoops)) {
            $coops = DB::table('unitBudidaya')->whereIn('id', $activeCoops)->get(['createdAt']);
            $weeks = $coops->map(fn ($c) => (int) floor(Carbon::parse($c->createdAt)->diffInWeeks(now())));
            $avgWeeks = $weeks->isEmpty() ? 0 : (int) round($weeks->avg());
        }

        $hdpScore = min(100, max(0, round($hdp)));
        $ageScore = min(100, max(0, $avgWeeks * 2));
        $feedScore = min(100, max(0, round($fi > 120 ? 100 : ($fi / 1.2))));
        $mortScore = min(100, max(0, round(100 - ($mortality * 10))));

        return [
            'spider' => [
                'labels' => ['HDP', 'Umur Biologis', 'Feed Consumption', 'Mortalitas'],
                'values' => [$hdpScore, $ageScore, $feedScore, $mortScore],
            ],
            'indicators' => [
                ['label' => 'HDP', 'value' => round($hdp, 1) . '%', 'color' => $hdp > 85 ? 'emerald' : ($hdp > 70 ? 'amber' : 'red'), 'detail' => $hdp > 85 ? 'Optimal' : ($hdp > 70 ? 'Cukup' : 'Rendah'), 'score' => $hdpScore],
                ['label' => 'Umur Biologis', 'value' => $avgWeeks > 0 ? $avgWeeks . ' mg' : '-', 'color' => 'blue', 'detail' => 'Rata-rata flock', 'score' => $ageScore],
                ['label' => 'Feed Consumption', 'value' => round($fi, 0) . ' g', 'color' => $fi >= 100 && $fi <= 130 ? 'emerald' : 'amber', 'detail' => 'Per ekor/hari', 'score' => $feedScore],
                ['label' => 'Mortalitas', 'value' => round($mortality, 2) . '%', 'color' => $mortality < 1 ? 'emerald' : ($mortality < 3 ? 'amber' : 'red'), 'detail' => 'Bulan ini', 'score' => $mortScore],
            ],
            'productivitySensors' => [
                ['label' => 'HDP (Hen-Day)', 'percent' => $hdpScore, 'status' => $hdp >= 85 ? 'normal' : ($hdp >= 70 ? 'warning' : 'danger'), 'statusLabel' => round($hdp, 1) . '%'],
                ['label' => 'Feed Consumption', 'percent' => $feedScore, 'status' => $fi >= 100 && $fi <= 130 ? 'normal' : 'warning', 'statusLabel' => round($fi, 0) . ' g/ekor'],
                ['label' => 'Mortalitas', 'percent' => $mortScore, 'status' => $mortality < 1 ? 'normal' : ($mortality < 3 ? 'warning' : 'danger'), 'statusLabel' => round($mortality, 2) . '%'],
            ],
        ];
    }

    public function getListKandang(): array
    {
        if (!$this->activeJenisBudidayaId) {
            return [];
        }

        $coops = DB::table('unitBudidaya')
            ->where('jenisBudidayaId', $this->activeJenisBudidayaId)
            ->where('status', 1)
            ->where('isDeleted', 0)
            ->orderBy('nama')
            ->get(['id', 'nama', 'kapasitas', 'jumlah', 'lokasi', 'createdAt']);

        $envById = collect($this->getBarnEnvironment()['barns'])->keyBy('id');
        $today = now()->toDateString();

        return $coops->map(function ($coop) use ($envById, $today) {
            $env = $envById->get($coop->id);
            $status = $env['status'] ?? 'normal';

            $hdpToday = 0.0;
            $pop = (float) ($coop->jumlah ?? 0);
            if ($pop > 0) {
                $telur = DB::table('panen')
                    ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
                    ->where('laporan.unitBudidayaId', $coop->id)
                    ->where('laporan.isDeleted', 0)
                    ->where('panen.isDeleted', 0)
                    ->whereDate('laporan.createdAt', $today)
                    ->sum('panen.jumlah');
                $hdpToday = round(($telur / $pop) * 100, 1);
            }

            return [
                'id' => $coop->id,
                'nama' => $coop->nama,
                'kapasitas' => $coop->kapasitas,
                'jumlah' => $coop->jumlah,
                'lokasi' => $coop->lokasi,
                'status' => $status,
                'temp' => $env['temp'] ?? '-',
                'hdp' => $hdpToday,
            ];
        })->values()->all();
    }

    public function getLastFuzzyEvaluationAt(): ?Carbon
    {
        $coopIds = $this->getActiveCoopIds();

        $query = DB::table('spk_fuzzy_logs')->orderByDesc('createdAt');

        if (!empty($coopIds)) {
            $query->where(function ($q) use ($coopIds) {
                $q->whereIn('unit_budidaya_id', $coopIds)->orWhereNull('unit_budidaya_id');
            });
        }

        $ts = $query->value('createdAt');

        return $ts ? Carbon::parse($ts) : null;
    }

    public function getSpkResults(): array
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

    public function getProductionLog(): array
    {
        $coops = $this->activeJenisBudidayaId
            ? DB::table('unitBudidaya')->where('jenisBudidayaId', $this->activeJenisBudidayaId)->where('isDeleted', 0)->get()->keyBy('id')
            : collect();

        if ($coops->isEmpty()) {
            return [['date' => '-', 'barn' => 'No Data', 'flock_age' => '-', 'birds' => '-', 'eggs' => '-', 'rejects' => '-', 'status' => '-']];
        }

        $laporans = DB::table('laporan')
            ->whereIn('unitBudidayaId', $coops->pluck('id'))
            ->where('isDeleted', 0)
            ->whereIn('tipe', ['panen', 'kematian', 'Panen', 'Mati'])
            ->orderBy('createdAt', 'desc')
            ->limit(10)
            ->get();

        $logs = [];
        foreach ($laporans as $l) {
            $panen = DB::table('panen')->where('laporanId', $l->id)->where('isDeleted', 0)->sum('jumlah');
            $reject = DB::table('kematian')->where('laporanId', $l->id)->where('isDeleted', 0)->count();

            $b = $coops[$l->unitBudidayaId];
            $age = (int) floor(Carbon::parse($b->createdAt)->diffInWeeks(now())) . ' Wks';

            $logs[] = [
                'date' => Carbon::parse($l->createdAt)->format('M d, Y'),
                'barn' => $b->nama,
                'flock_age' => $age,
                'birds' => number_format((float)($b->jumlah ?? 0), 0, ',', '.'),
                'eggs' => strtolower($l->tipe) === 'panen' ? number_format((float)($panen ?? 0), 0, ',', '.') : '-',
                'rejects' => strtolower($l->tipe) === 'kematian' ? $reject : '-',
                'status' => strtolower($l->tipe) === 'panen' ? 'Optimal' : 'Attention'
            ];
        }

        return empty($logs) ? [['date' => '-', 'barn' => '-', 'flock_age' => '-', 'birds' => '-', 'eggs' => '-', 'rejects' => '-', 'status' => '-']] : $logs;
    }
}
