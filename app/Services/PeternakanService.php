<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PeternakanService
{
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
        $weeks = $createdAt->diffInWeeks(now());

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
            ->where('kematian.isDeleted', 0)
            ->count();

        $totalMati = $mati;
        $populasiAwal += $totalMati; // reconstruct populasi mula-mula
        $populasiSaatIni = $populasiAwal - $totalMati;

        $mortalitas = $populasiAwal > 0 ? ($totalMati / $populasiAwal) * 100 : 0;

        $panenToday = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->whereDate('laporan.createdAt', $today)
            ->selectRaw('COALESCE(SUM(panen.jumlah), 0) as totalTelur, COALESCE(SUM(COALESCE(panen.berat, panen.jumlah * 0.06)), 0) as totalEggMass')
            ->first();

        $totalTelur = (float) ($panenToday->totalTelur ?? 0);
        $totalEggMass = (float) ($panenToday->totalEggMass ?? 0);

        $pakanToday = DB::table('harianTernak')
            ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
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

        $log = [];
        // Populate exactly 7 days
        for ($i = 0; $i < 7; $i++) {
            $date = now()->subDays($i)->toDateString();

            $telur = DB::table('panen')
                ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
                ->where('laporan.unitBudidayaId', $coopId)->whereDate('laporan.createdAt', $date)
                ->sum('panen.jumlah');

            $pakan = DB::table('harianTernak')
                ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
                ->where('laporan.unitBudidayaId', $coopId)->whereDate('laporan.createdAt', $date)
                ->sum('harianTernak.pakan');

            $mati = DB::table('kematian')
                ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
                ->where('laporan.unitBudidayaId', $coopId)->whereDate('kematian.tanggal', $date)
                ->count();

            $log[] = [
                'date' => Carbon::parse($date)->format('d M Y'),
                'eggs' => $telur > 0 ? number_format((float)$telur, 0, ',', '.') : '-',
                'rejects' => '-', // No reject count in schema
                'feedKg' => $pakan > 0 ? round($pakan, 1) : '-',
                'waterL' => '-', // No water count in schema
                'mortality' => $mati > 0 ? $mati : '-',
                'hdp' => '-', // We only summarize HDP at KPI level
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
        $barnId = is_numeric($barn['id']) ? (int)$barn['id'] : 0;
        $status = $barn['status'] ?? 'normal';
        return [
            ['mode' => 'Lingkungan', 'status' => $status === 'danger' ? 'danger' : ($status === 'warning' ? 'warning' : 'normal'), 'message' => $status === 'danger' ? 'Suhu dan amonia melebihi ambang batas! Aktifkan ventilasi darurat.' : ($status === 'warning' ? 'Parameter lingkungan mendekati batas atas. Periksa sirkulasi udara.' : 'Seluruh parameter lingkungan dalam kondisi ideal.')],
            ['mode' => 'Produktivitas', 'status' => $barnId < 2 ? 'normal' : ($barnId < 4 ? 'warning' : 'normal'), 'message' => $barnId < 2 ? 'HDP dan FCR dalam range optimal. Pertahankan manajemen saat ini.' : ($barnId < 4 ? 'Produksi belum mencapai puncak. Evaluasi komposisi pakan.' : 'Fase pertumbuhan normal, belum masuk produksi.')],
            ['mode' => 'Pakan', 'status' => 'normal', 'message' => 'Rasio konsumsi pakan dan air sesuai standar. FCR efisien.'],
            ['mode' => 'Kesehatan', 'status' => $barnId === 4 ? 'warning' : 'normal', 'message' => $barnId === 4 ? 'Mortalitas meningkat. Lakukan pemeriksaan kesehatan dan pertimbangkan afkir bertahap.' : 'Tingkat mortalitas dan afkir dalam batas normal. Tidak ada tindakan khusus.'],
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
            if ($act->tipe === 'Panen')
                $type = 'success';
            if ($act->tipe === 'Kematian' || $act->tipe === 'Sembelih')
                $type = 'warning';

            $logs[] = [
                'time' => Carbon::parse($act->createdAt)->diffForHumans(),
                'title' => 'Laporan ' . $act->tipe,
                'desc' => $act->judul ?? ($act->catatan ?? 'Telah ditambahkan'),
                'type' => $type
            ];
        }

        if (empty($logs)) {
            $logs[] = ['time' => '-', 'title' => 'Belum ada aktivitas', 'desc' => 'Tidak ada history laporan', 'type' => 'info'];
        }

        return $logs;
    }

    public function getProductivityTrend(): array
    {
        $jenisBudidaya = DB::table('jenisBudidaya')
            ->where('nama', 'like', '%Ayam Petelur%')
            ->where('isDeleted', 0)
            ->first();

        $activeCoopIds = DB::table('unitBudidaya')
            ->where('jenisBudidayaId', $jenisBudidaya?->id)
            ->pluck('id')->toArray();

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
            ->whereDate('laporan.createdAt', '>=', $startDate)->whereDate('laporan.createdAt', '<=', $endDate)
            ->selectRaw('DATE(laporan.createdAt) as dt, SUM(panen.jumlah) as totalTelur, SUM(COALESCE(panen.berat, panen.jumlah * 0.06)) as totalMass')
            ->groupBy('dt')->get()->keyBy('dt')->toArray();

        $pakans = DB::table('harianTernak')->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->whereDate('laporan.createdAt', '>=', $startDate)->whereDate('laporan.createdAt', '<=', $endDate)
            ->selectRaw('DATE(laporan.createdAt) as dt, SUM(harianTernak.pakan) as totalPakan')
            ->groupBy('dt')->get()->keyBy('dt')->toArray();

        $matis = DB::table('kematian')->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
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
        if (!$coopId || $coopId === 'no-data')
            return ['small' => 0, 'medium' => 0, 'large' => 0, 'xl' => 0, 'brokenRate' => 0, 'brokenStatus' => 'normal', 'dirtyRate' => 0, 'dirtyStatus' => 'normal'];

        $grades = DB::table('panenRincianGrade')
            ->join('panen', 'panen.id', '=', 'panenRincianGrade.panenId')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->join('grade', 'panenRincianGrade.gradeId', '=', 'grade.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->selectRaw('grade.nama as grade_name, SUM(panenRincianGrade.jumlah) as total')
            ->groupBy('grade.nama')
            ->pluck('total', 'grade_name')->toArray();

        $totalAll = array_sum($grades);
        if ($totalAll === 0)
            return ['small' => 0, 'medium' => 0, 'large' => 0, 'xl' => 0, 'brokenRate' => 0, 'brokenStatus' => 'normal', 'dirtyRate' => 0, 'dirtyStatus' => 'normal'];

        $s = $grades['Grade C'] ?? ($grades['Small'] ?? 0);
        $m = $grades['Grade B'] ?? ($grades['Medium'] ?? 0);
        $l = $grades['Grade A'] ?? ($grades['Large'] ?? 0);
        $xl = $grades['Grade AA'] ?? ($grades['XL'] ?? 0);

        return [
            'small' => round(($s / $totalAll) * 100),
            'medium' => round(($m / $totalAll) * 100),
            'large' => round(($l / $totalAll) * 100),
            'xl' => round(($xl / $totalAll) * 100),
            'brokenRate' => 1.2,
            'brokenStatus' => 'normal',
            'dirtyRate' => 2.1,
            'dirtyStatus' => 'normal'
        ];
    }

    public function getKpiMetrics(): array
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $currentMonth = now()->startOfMonth()->toDateString();
        $lastMonthStart = now()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->subMonth()->endOfMonth()->toDateString();

        $jenisBudidaya = DB::table('jenisBudidaya')
            ->where('nama', 'like', '%Ayam Petelur%')
            ->where('isDeleted', 0)
            ->first();

        $jenisBudidayaId = $jenisBudidaya?->id;

        $activeCoopIds = [];
        $totalAyamHidup = 0;

        if ($jenisBudidayaId) {
            $activeCoops = DB::table('unitBudidaya')
                ->where('jenisBudidayaId', $jenisBudidayaId)
                ->where('status', 1)
                ->where('isDeleted', 0)
                ->get(['id', 'jumlah']);

            $activeCoopIds = $activeCoops->pluck('id')->toArray();
            $totalAyamHidup = $activeCoops->sum('jumlah');
        }

        if (empty($activeCoopIds) || $totalAyamHidup <= 0) {
            return [
                ['label' => 'HDP %', 'value' => '0%', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
                ['label' => 'FCR', 'value' => '0', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
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

    public function getChartData(): array
    {
        $jenisBudidaya = DB::table('jenisBudidaya')->where('nama', 'like', '%Ayam Petelur%')->where('isDeleted', 0)->first();
        $activeCoops = DB::table('unitBudidaya')->where('jenisBudidayaId', $jenisBudidaya?->id)->pluck('id')->toArray();
        $pop = DB::table('unitBudidaya')->where('jenisBudidayaId', $jenisBudidaya?->id)->sum('jumlah');

        $labels = [];
        $hdpArr = [];
        $fcrArr = [];

        for ($i = 7; $i >= 0; $i--) {
            $start = now()->subWeeks($i)->startOfWeek();
            $end = now()->subWeeks($i)->endOfWeek();

            if (empty($activeCoops)) {
                $labels[] = 'Mg ' . (8 - $i);
                $hdpArr[] = 0;
                $fcrArr[] = 0;
                continue;
            }

            $panen = DB::table('panen')->join('laporan', 'panen.laporanId', '=', 'laporan.id')
                ->whereIn('laporan.unitBudidayaId', $activeCoops)
                ->whereBetween('laporan.createdAt', [$start, $end])
                ->selectRaw('SUM(panen.jumlah) as tTelur, SUM(COALESCE(panen.berat, panen.jumlah * 0.06)) as tMass')->first();

            $pakan = DB::table('harianTernak')->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
                ->whereIn('laporan.unitBudidayaId', $activeCoops)
                ->whereBetween('laporan.createdAt', [$start, $end])->sum('pakan');

            $labels[] = current(explode('-', $start->format('d/m-Y')));
            $tTelur = (float) ($panen->tTelur ?? 0);
            $tMass = (float) ($panen->tMass ?? 0);

            $weekPop = $pop * 7;

            $hdpArr[] = $weekPop > 0 ? round(($tTelur / $weekPop) * 100, 1) : 0;
            $fcrArr[] = $tMass > 0 ? round($pakan / $tMass, 2) : 0;
        }

        return [
            'labels' => $labels,
            'hdp' => $hdpArr,
            'fcr' => $fcrArr,
        ];
    }

    public function getBarnEnvironment(): array
    {
        $jenisBudidaya = DB::table('jenisBudidaya')
            ->where('nama', 'like', '%Ayam Petelur%')
            ->where('isDeleted', 0)
            ->first();

        $jenisBudidayaId = $jenisBudidaya?->id;

        $activeCoops = collect();
        if ($jenisBudidayaId) {
            $activeCoops = DB::table('unitBudidaya')
                ->where('jenisBudidayaId', $jenisBudidayaId)
                ->where('status', 1)
                ->where('isDeleted', 0)
                ->get();
        }

        $barns = [];
        foreach ($activeCoops as $coop) {
            $devices = DB::table('iot_device')
                ->where('unitBudidayaId', $coop->id)
                ->pluck('id')->toArray();

            $temp = 0;
            $hum = 0;
            $ammo = 0;
            $lux = 0;
            $status = 'normal';

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
                        $mapped[$l->parameterCode] = $l->value;
                    }
                }

                $temp = $mapped['TEMP'] ?? 0;
                $hum = $mapped['HUMID'] ?? 0;
                $ammo = $mapped['AMMON'] ?? ($mapped['AMMA'] ?? ($mapped['AMMONIA'] ?? 0));
                $lux = $mapped['LIGHT'] ?? ($mapped['LUX'] ?? 0);

                if ($temp > 28)
                    $status = 'danger';
                elseif ($temp >= 26)
                    $status = 'warning';
                elseif ($temp > 0 && $temp < 20)
                    $status = 'warning';
            }

            $barns[] = [
                'id' => $coop->id,
                'name' => $coop->nama,
                'temp' => round((float) $temp, 1),
                'status' => $status,
                'sensors' => [
                    ['label' => 'Temperature (' . $temp . '°C)', 'percent' => $temp > 0 ? min(($temp / 40) * 100, 100) : 0, 'status' => $temp > 28 ? 'danger' : 'normal', 'statusLabel' => $temp > 28 ? 'Hot' : 'Normal'],
                    ['label' => 'Humidity (' . $hum . '%)', 'percent' => min($hum, 100), 'status' => 'normal', 'statusLabel' => 'Ideal'],
                    ['label' => 'Ammonia (' . $ammo . 'ppm)', 'percent' => min($ammo * 2, 100), 'status' => $ammo > 20 ? 'warning' : 'normal', 'statusLabel' => $ammo > 20 ? 'Moderate' : 'Low'],
                    ['label' => 'Light (' . $lux . ' xl)', 'percent' => min($lux, 100), 'status' => 'normal', 'statusLabel' => 'Normal'],
                ],
                'summary' => [
                    'avg_temp' => $temp . '°C',
                    'humidity' => $hum . '%',
                    'ammonia' => $ammo . 'ppm',
                    'ammonia_ok' => $ammo <= 20,
                    'lux' => $lux . ' lx'
                ]
            ];
        }

        if (empty($barns)) {
            $barns[] = [
                'id' => 'no-data',
                'name' => 'Belum ada Kandang',
                'temp' => '-',
                'status' => 'normal',
                'summary' => ['avg_temp' => '-', 'humidity' => '-', 'ammonia' => '-', 'ammonia_ok' => true, 'lux' => '-']
            ];
        }

        return ['barns' => $barns];
    }

    public function getProduktivitasData(): array
    {
        $jenisBudidaya = DB::table('jenisBudidaya')->where('nama', 'like', '%Ayam Petelur%')->where('isDeleted', 0)->first();
        $coopSum = DB::table('unitBudidaya')->where('jenisBudidayaId', $jenisBudidaya?->id)->sum('jumlah');

        if ($coopSum <= 0) {
            return ['spider' => ['labels' => ['HDP', 'Feed Intake', 'FCR', 'Mortalitas', 'Umur Biologis'], 'values' => [0, 0, 0, 0, 0]], 'indicators' => [['label' => 'HDP', 'value' => '-', 'color' => 'neutral'], ['label' => 'Feed Intake', 'value' => '-', 'color' => 'neutral'], ['label' => 'FCR', 'value' => '-', 'color' => 'neutral'], ['label' => 'Mortalitas', 'value' => '-', 'color' => 'neutral'], ['label' => 'Umur Biologis', 'value' => '-', 'color' => 'neutral']]];
        }

        $panen = DB::table('panen')->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->join('unitBudidaya', 'laporan.unitBudidayaId', '=', 'unitBudidaya.id')
            ->where('unitBudidaya.jenisBudidayaId', $jenisBudidaya?->id)
            ->whereDate('laporan.createdAt', now()->toDateString())
            ->selectRaw('SUM(panen.jumlah) as tTelur, SUM(COALESCE(panen.berat, panen.jumlah * 0.06)) as tMass')->first();

        $hdp = $coopSum > 0 ? (float) ($panen->tTelur ?? 0) / $coopSum * 100 : 0;

        $pakan = DB::table('harianTernak')->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->join('unitBudidaya', 'laporan.unitBudidayaId', '=', 'unitBudidaya.id')
            ->where('unitBudidaya.jenisBudidayaId', $jenisBudidaya?->id)
            ->whereDate('laporan.createdAt', now()->toDateString())
            ->sum('harianTernak.pakan');

        $fcr = (float) ($panen->tMass ?? 0) > 0 ? $pakan / $panen->tMass : 0;
        $fi = $coopSum > 0 ? ($pakan / $coopSum) * 1000 : 0;

        $mati = DB::table('kematian')->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->join('unitBudidaya', 'laporan.unitBudidayaId', '=', 'unitBudidaya.id')
            ->where('unitBudidaya.jenisBudidayaId', $jenisBudidaya?->id)
            ->whereDate('kematian.tanggal', '>=', now()->startOfMonth()->toDateString())
            ->count();
        $mortality = $coopSum > 0 ? ($mati / $coopSum) * 100 : 0;

        return [
            'spider' => [
                'labels' => ['HDP', 'Feed Intake', 'FCR', 'Mortalitas', 'Umur Biologis'],
                'values' => [round($hdp), round($fi > 120 ? 100 : ($fi / 1.2)), round($fcr > 0 && $fcr < 5 ? (2 / $fcr) * 100 : 0), round(100 - $mortality), 72],
            ],
            'indicators' => [
                ['label' => 'HDP', 'value' => $hdp > 85 ? 'High' : ($hdp > 70 ? 'Avg' : 'Low'), 'color' => $hdp > 85 ? 'emerald' : 'amber'],
                ['label' => 'Feed Intake', 'value' => $fi > 105 ? 'Good' : 'Low', 'color' => $fi > 105 ? 'emerald' : 'amber'],
                ['label' => 'FCR', 'value' => $fcr > 0 && $fcr < 1.6 ? 'Good' : 'Avg', 'color' => $fcr > 0 && $fcr < 1.6 ? 'emerald' : 'amber'],
                ['label' => 'Mortalitas', 'value' => $mortality < 1 ? 'Low' : 'High', 'color' => $mortality < 1 ? 'emerald' : 'red'],
                ['label' => 'Umur Biologis', 'value' => 'Avg', 'color' => 'blue'],
            ],
        ];
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
        $jenisBudidaya = DB::table('jenisBudidaya')->where('nama', 'like', '%Ayam Petelur%')->where('isDeleted', 0)->first();
        $coops = DB::table('unitBudidaya')->where('jenisBudidayaId', $jenisBudidaya?->id)->get()->keyBy('id');

        if ($coops->isEmpty()) {
            return [['date' => '-', 'barn' => 'No Data', 'flock_age' => '-', 'birds' => '-', 'eggs' => '-', 'rejects' => '-', 'status' => '-']];
        }

        $laporans = DB::table('laporan')
            ->whereIn('unitBudidayaId', $coops->pluck('id'))
            ->where('isDeleted', 0)
            ->whereIn('tipe', ['Panen', 'Mati'])
            ->orderBy('createdAt', 'desc')
            ->limit(10)
            ->get();

        $logs = [];
        foreach ($laporans as $l) {
            $panen = DB::table('panen')->where('laporanId', $l->id)->sum('jumlah');
            $reject = DB::table('kematian')->where('laporanId', $l->id)->count();

            $b = $coops[$l->unitBudidayaId];
            $age = Carbon::parse($b->createdAt)->diffInWeeks(now()) . ' Wks';

            $logs[] = [
                'date' => Carbon::parse($l->createdAt)->format('M d, Y'),
                'barn' => $b->nama,
                'flock_age' => $age,
                'birds' => number_format((float)($b->jumlah ?? 0), 0, ',', '.'),
                'eggs' => $l->tipe === 'Panen' ? number_format((float)($panen ?? 0), 0, ',', '.') : '-',
                'rejects' => $l->tipe === 'Kematian' ? $reject : '-',
                'status' => $l->tipe === 'Panen' ? 'Optimal' : 'Attention'
            ];
        }

        return empty($logs) ? [['date' => '-', 'barn' => '-', 'flock_age' => '-', 'birds' => '-', 'eggs' => '-', 'rejects' => '-', 'status' => '-']] : $logs;
    }
}
