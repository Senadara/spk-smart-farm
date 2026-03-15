@extends('layouts.app')

@section('title', $barn['name'] . ' — Detail Kandang')
@section('breadcrumb', 'Peternakan › ' . $barn['name'])

@section('content')
    <div x-data="{
        sensorFilter: 'all',
        sensorRange: '24h',
        prodFilter: 'all',
        prodRange: '30d',
        _trendChart: null,
        _prodChart: null,
        init() {
            this.$nextTick(() => {
                this.renderTrend();
                this.renderProd();
            });
        },
        renderTrend() {
            const ctx = this.$refs.trendCanvas;
            if (!ctx) return;
            if (this._trendChart) this._trendChart.destroy();
            const datasets = [];
            const colors = ['#EF4444','#3B82F6','#F59E0B','#8B5CF6'];
            const labels = ['Suhu (°C)','Kelembapan (%)','Amonia (ppm)','Cahaya (lux)'];
            const allData = @js([$sensorTrend['temperature'], $sensorTrend['humidity'], $sensorTrend['ammonia'] ?? [], $sensorTrend['light'] ?? []]);
            const allLabels = @js($sensorTrend['labels']);
            const range = this.sensorRange;
            const sliceN = range === '6h' ? 6 : range === '12h' ? 12 : 24;
            const slicedLabels = allLabels.slice(-sliceN);
            const filter = this.sensorFilter;
            allData.forEach((d, i) => {
                if (d.length === 0) return;
                if (filter !== 'all' && parseInt(filter) !== i) return;
                datasets.push({
                    label: labels[i], data: d.slice(-sliceN), borderColor: colors[i],
                    backgroundColor: colors[i] + '10', borderWidth: 2, fill: true,
                    tension: 0.4, pointRadius: 0, pointHoverRadius: 4,
                });
            });
            this._trendChart = new Chart(ctx, {
                type: 'line',
                data: { labels: slicedLabels, datasets },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'top', labels: { usePointStyle: true, pointStyle: 'circle', padding: 14, font: { size: 10, family: 'Inter' } } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 9, family: 'Inter' }, color: '#9CA3AF', maxTicksLimit: 12 } },
                        y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 9, family: 'Inter' }, color: '#9CA3AF' } },
                    },
                },
            });
        },
        renderProd() {
            const ctx = this.$refs.prodCanvas;
            if (!ctx) return;
            if (this._prodChart) this._prodChart.destroy();
            const datasets = [];
            const colors = ['#10B981','#3B82F6','#F59E0B','#8B5CF6','#EF4444'];
            const labels = ['HDP (%)','HHEP (%)','FCR','Feed Intake (g)','Mortalitas (%)'];
            const allData = @js([$productivityTrend['hdp'], $productivityTrend['hhep'], $productivityTrend['fcr'], $productivityTrend['feedIntake'], $productivityTrend['mortality']]);
            const allLabels = @js($productivityTrend['labels']);
            const range = this.prodRange;
            const sliceN = range === '7d' ? 7 : range === '14d' ? 14 : 30;
            const slicedLabels = allLabels.slice(-sliceN);
            const filter = this.prodFilter;
            allData.forEach((d, i) => {
                if (filter !== 'all' && parseInt(filter) !== i) return;
                datasets.push({
                    label: labels[i], data: d.slice(-sliceN), borderColor: colors[i],
                    backgroundColor: colors[i] + '10', borderWidth: 2, fill: false,
                    tension: 0.4, pointRadius: 0, pointHoverRadius: 4,
                    yAxisID: (i === 2 || i === 4) ? 'y1' : 'y',
                });
            });
            this._prodChart = new Chart(ctx, {
                type: 'line',
                data: { labels: slicedLabels, datasets },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'top', labels: { usePointStyle: true, pointStyle: 'circle', padding: 14, font: { size: 10, family: 'Inter' } } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 9, family: 'Inter' }, color: '#9CA3AF', maxTicksLimit: 15 } },
                        y: { type: 'linear', position: 'left', title: { display: true, text: '%  /  g', font: { size: 10 }, color: '#6B7280' }, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 9 }, color: '#9CA3AF' } },
                        y1: { type: 'linear', position: 'right', title: { display: true, text: 'FCR / Mort.%', font: { size: 10 }, color: '#6B7280' }, grid: { drawOnChartArea: false }, ticks: { font: { size: 9 }, color: '#9CA3AF' } },
                    },
                },
            });
        },
    }" class="max-w-full space-y-5">

        {{-- ═══ HEADER ═══ --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold text-gray-900">{{ $barn['name'] }}</h1>
                    @php
                        $sc = [
                            'normal'  => ['label'=>'Optimal','cls'=>'text-emerald-600 bg-emerald-50'],
                            'warning' => ['label'=>'Perhatian','cls'=>'text-amber-600 bg-amber-50'],
                            'danger'  => ['label'=>'Kritis','cls'=>'text-red-600 bg-red-50'],
                        ][$barn['status']] ?? ['label'=>'Normal','cls'=>'text-gray-500 bg-gray-50'];
                    @endphp
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold rounded-full {{ $sc['cls'] }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                        {{ $sc['label'] }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-0.5">
                    {{ $barn['breed'] }} · {{ $barn['location'] }} · Umur Flock: {{ $barn['flockAge'] }} · {{ $barn['totalBirds'] }} / {{ $barn['capacity'] }} ekor
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('peternakan') }}" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition flex items-center gap-2 no-underline">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    Kembali
                </a>
                <button class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition flex items-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export
                </button>
            </div>
        </div>

        {{-- ═══ BARN OVERVIEW — COMPACT ═══ --}}
        <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden flex flex-col lg:flex-row">
            <div class="lg:w-1/8 h-36 lg:h-auto relative bg-gray-100">
                <img src="{{ $barn['photo'] }}" alt="{{ $barn['name'] }}" class="w-full h-full object-cover">
            </div>
            <div class="lg:w-3/4 p-4 lg:p-5 flex items-center">
                <div class="grid grid-cols-3 md:grid-cols-6 gap-4 w-full">
                    @foreach ([
                        ['l'=>'Lokasi','v'=>$barn['location']],
                        ['l'=>'Breed','v'=>$barn['breed']],
                        ['l'=>'Tanggal Masuk','v'=> \Carbon\Carbon::parse($barn['startDate'])->format('d M Y')],
                        ['l'=>'Populasi','v'=>$barn['totalBirds']],
                        ['l'=>'Kapasitas','v'=>$barn['capacity']],
                        ['l'=>'Umur Flock','v'=>$barn['flockAge']],
                    ] as $f)
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-0.5">{{ $f['l'] }}</p>
                            <p class="text-sm font-bold text-gray-900">{{ $f['v'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ═══ KPI ROW — SAME AS DASHBOARD ═══ --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach ([
                ['label'=>'HDP %','value'=>$kpi['hdp'] ? $kpi['hdp'].'%' : '-','trend'=>['direction'=>'up','value'=>'+1.2%','status'=>'positive']],
                ['label'=>'HHEP %','value'=>$kpi['hhep'] ? $kpi['hhep'].'%' : '-','trend'=>['direction'=>'up','value'=>'+0.5%','status'=>'positive']],
                ['label'=>'Feed Intake','value'=>$kpi['feedIntake'].'g','trend'=>['direction'=>'stable','value'=>'Stable','status'=>'neutral']],
                ['label'=>'FCR','value'=>$kpi['fcr'] ?: '-','trend'=>['direction'=>'down','value'=>'-0.02','status'=>'positive']],
                ['label'=>'Mortalitas','value'=>$kpi['mortalitas'].'%','trend'=>['direction'=>$kpi['mortalitas']>0.05?'up':'stable','value'=>$kpi['mortalitas']>0.05?'+0.01%':'Stable','status'=>$kpi['mortalitas']>0.05?'warning':'neutral']],
                ['label'=>'Afkir','value'=>$kpi['afkir'].'%','trend'=>['direction'=>'stable','value'=>'Stable','status'=>'neutral']],
            ] as $m)
                <x-peternakan.kpi-card :label="$m['label']" :value="$m['value']" :trend="$m['trend']" />
            @endforeach
        </div>

        {{-- ═══ SENSOR TREND CHART + LIVE SENSORS ═══ --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
            <div class="lg:col-span-3 bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <h3 class="text-base font-semibold text-gray-800">Tren Sensor</h3>
                    <div class="flex items-center gap-2">
                        <div class="flex bg-gray-100 rounded-lg p-0.5">
                            <template x-for="r in [{v:'6h',l:'6J'},{v:'12h',l:'12J'},{v:'24h',l:'24J'}]">
                                <button @click="sensorRange=r.v; renderTrend()" :class="sensorRange===r.v ? 'bg-white shadow-sm text-gray-900':'text-gray-500 hover:text-gray-700'" class="px-2.5 py-1 text-[10px] font-semibold rounded-md transition-all" x-text="r.l"></button>
                            </template>
                        </div>
                        <select x-model="sensorFilter" @change="renderTrend()" class="text-xs border border-gray-200 rounded-lg px-2 py-1 bg-white text-gray-600 focus:outline-none focus:ring-1 focus:ring-emerald-300">
                            <option value="all">Semua</option>
                            <option value="0">Suhu</option>
                            <option value="1">Kelembapan</option>
                            <option value="2">Amonia</option>
                            <option value="3">Cahaya</option>
                        </select>
                    </div>
                </div>
                <div style="height: 260px;">
                    <canvas x-ref="trendCanvas"></canvas>
                </div>
            </div>

            {{-- Live Sensors + IoT Device --}}
            <div class="lg:col-span-2 bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-gray-800">Sensor & Perangkat</h3>
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    @php
                        $sIcons = [
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9V3m0 0a2 2 0 10-4 0v9.764a4 4 0 106.764 1.528A3.99 3.99 0 0012 13V3z"/>',
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a8 8 0 004-14.947L12 2l-4 4.053A8 8 0 0012 21z"/>',
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>',
                        ];
                        $statusMap = ['normal'=>'text-emerald-600 bg-emerald-50','warning'=>'text-amber-600 bg-amber-50','danger'=>'text-red-600 bg-red-50'];
                        $statusLabel = ['normal'=>'OK','warning'=>'⚠','danger'=>'!'];
                    @endphp
                    @foreach ($sensors as $i => $s)
                        <div class="rounded-xl border border-gray-100 p-3 hover:shadow-sm transition-all">
                            <div class="flex items-center justify-between mb-1">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">{!! $sIcons[$i] !!}</svg>
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full {{ $statusMap[$s['status']] ?? $statusMap['normal'] }}">{{ $statusLabel[$s['status']] ?? 'OK' }}</span>
                            </div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">{{ $s['label'] }}</p>
                            <p class="text-lg font-bold text-gray-900">{{ $s['value'] }}<span class="text-xs font-normal text-gray-400">{{ $s['unit'] }}</span></p>
                        </div>
                    @endforeach
                </div>
                @if ($iotDevice)
                    <div class="rounded-xl border border-dashed border-gray-200 p-3 bg-gray-50/50">
                        <p class="text-[10px] text-gray-400 uppercase tracking-wider mb-1">Perangkat IoT</p>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-900 font-mono">{{ $iotDevice['code'] }}</p>
                                <p class="text-[10px] text-gray-500">{{ $iotDevice['name'] }} · {{ $iotDevice['protocol'] }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold {{ $iotDevice['status'] === 'active' ? 'text-emerald-600 bg-emerald-50' : 'text-red-600 bg-red-50' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                {{ ucfirst($iotDevice['status']) }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ═══ PRODUCTIVITY TREND CHART ═══ --}}
        <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">Tren Produktivitas</h3>
                    <p class="text-xs text-gray-400 mt-0.5">HDP, HHEP, FCR, Feed Intake, Mortalitas</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex bg-gray-100 rounded-lg p-0.5">
                        <template x-for="r in [{v:'7d',l:'7H'},{v:'14d',l:'14H'},{v:'30d',l:'30H'}]">
                            <button @click="prodRange=r.v; renderProd()" :class="prodRange===r.v ? 'bg-white shadow-sm text-gray-900':'text-gray-500 hover:text-gray-700'" class="px-2.5 py-1 text-[10px] font-semibold rounded-md transition-all" x-text="r.l"></button>
                        </template>
                    </div>
                    <select x-model="prodFilter" @change="renderProd()" class="text-xs border border-gray-200 rounded-lg px-2 py-1 bg-white text-gray-600 focus:outline-none focus:ring-1 focus:ring-emerald-300">
                        <option value="all">Semua</option>
                        <option value="0">HDP</option>
                        <option value="1">HHEP</option>
                        <option value="2">FCR</option>
                        <option value="3">Feed Intake</option>
                        <option value="4">Mortalitas</option>
                    </select>
                </div>
            </div>
            <div style="height: 280px;">
                <canvas x-ref="prodCanvas"></canvas>
            </div>
        </div>

        {{-- ═══ EGG QUALITY CARD ═══ --}}
        <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
            <h3 class="text-base font-semibold text-gray-800 mb-5">Egg Production Details (Today)</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                {{-- Size Distribution --}}
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Size Distribution</p>
                    {{-- Stacked bar --}}
                    <div class="h-4 w-full rounded-full overflow-hidden flex mb-4">
                        <div class="h-full bg-blue-600" style="width: {{ $eggQuality['small'] }}%" title="Small"></div>
                        <div class="h-full bg-emerald-500" style="width: {{ $eggQuality['medium'] }}%" title="Medium"></div>
                        <div class="h-full bg-emerald-700" style="width: {{ $eggQuality['large'] }}%" title="Large"></div>
                        <div class="h-full bg-emerald-900" style="width: {{ $eggQuality['xl'] }}%" title="XL"></div>
                    </div>
                    <div class="space-y-2">
                        @foreach ([
                            ['label'=>'Small (<53g)','pct'=>$eggQuality['small'],'color'=>'bg-blue-600'],
                            ['label'=>'Medium (53-63g)','pct'=>$eggQuality['medium'],'color'=>'bg-emerald-500'],
                            ['label'=>'Large (63-73g)','pct'=>$eggQuality['large'],'color'=>'bg-emerald-700'],
                            ['label'=>'XL (>73g)','pct'=>$eggQuality['xl'],'color'=>'bg-emerald-900'],
                        ] as $sz)
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full {{ $sz['color'] }}"></div>
                                    <span class="text-gray-600">{{ $sz['label'] }}</span>
                                </div>
                                <span class="font-bold text-gray-900">{{ $sz['pct'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Broken Egg Rate --}}
                @php
                    $brokenOk = $eggQuality['brokenStatus'] === 'normal';
                @endphp
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Broken Egg Rate</p>
                    <div class="flex items-end gap-2 mb-2">
                        <span class="text-3xl font-bold text-gray-900">{{ $eggQuality['brokenRate'] }}%</span>
                        <span class="text-xs font-semibold px-1.5 py-0.5 rounded {{ $brokenOk ? 'text-emerald-600 bg-emerald-50' : 'text-amber-600 bg-amber-50' }} mb-1">{{ $brokenOk ? 'Optimal' : 'Warning' }}</span>
                    </div>
                    <div class="h-1.5 w-full rounded-full overflow-hidden bg-gray-100 mb-2">
                        <div class="h-full rounded-full {{ $brokenOk ? 'bg-blue-500' : 'bg-amber-500' }}" style="width: {{ min($eggQuality['brokenRate'] / 4 * 100, 100) }}%"></div>
                    </div>
                    <p class="text-[10px] text-gray-400">Target: < 2.0%</p>
                </div>

                {{-- Dirty Egg Rate --}}
                @php
                    $dirtyOk = $eggQuality['dirtyStatus'] === 'normal';
                @endphp
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Dirty Egg Rate</p>
                    <div class="flex items-end gap-2 mb-2">
                        <span class="text-3xl font-bold text-gray-900">{{ $eggQuality['dirtyRate'] }}%</span>
                        <span class="text-xs font-semibold px-1.5 py-0.5 rounded {{ $dirtyOk ? 'text-emerald-600 bg-emerald-50' : 'text-amber-600 bg-amber-50' }} mb-1">{{ $dirtyOk ? 'Optimal' : 'Warning' }}</span>
                    </div>
                    <div class="h-1.5 w-full rounded-full overflow-hidden bg-gray-100 mb-2">
                        <div class="h-full rounded-full {{ $dirtyOk ? 'bg-blue-500' : 'bg-amber-500' }}" style="width: {{ min($eggQuality['dirtyRate'] / 6 * 100, 100) }}%"></div>
                    </div>
                    <p class="text-[10px] text-gray-400">Target: < 3.0%</p>
                </div>
            </div>
        </div>

        {{-- ═══ PRODUCTION LOG TABLE ═══ --}}
        <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
            <h3 class="text-base font-semibold text-gray-800 mb-4">Log Produksi 7 Hari</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            @foreach (['Tanggal','Telur','Reject','Pakan (kg)','Air (L)','Mortalitas','HDP'] as $th)
                                <th class="text-{{ $loop->first ? 'left' : 'right' }} py-2.5 px-3 text-xs font-medium text-gray-400 uppercase tracking-wider">{{ $th }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($productionLog as $log)
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="py-2.5 px-3 font-medium text-gray-900">{{ $log['date'] }}</td>
                                <td class="py-2.5 px-3 text-right text-gray-700">{{ $log['eggs'] }}</td>
                                <td class="py-2.5 px-3 text-right text-gray-500">{{ $log['rejects'] }}</td>
                                <td class="py-2.5 px-3 text-right text-gray-500">{{ number_format($log['feedKg']) }}</td>
                                <td class="py-2.5 px-3 text-right text-gray-500">{{ number_format($log['waterL']) }}</td>
                                <td class="py-2.5 px-3 text-right">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $log['mortality'] > 2 ? 'text-red-600 bg-red-50' : 'text-emerald-600 bg-emerald-50' }}">{{ $log['mortality'] }}</span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-bold text-gray-900">{{ $log['hdp'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══ SPK MESSAGES + ACTIVITY LOG ═══ --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- SPK Messages --}}
            <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    <h3 class="text-base font-semibold text-gray-800">Analisis SPK</h3>
                </div>
                <div class="space-y-3">
                    @foreach ($spkMessages as $msg)
                        @php
                            $msgCfg = [
                                'normal'  => ['icon'=>'✅','border'=>'border-l-emerald-500','bg'=>'bg-emerald-50/50'],
                                'warning' => ['icon'=>'⚠️','border'=>'border-l-amber-500','bg'=>'bg-amber-50/50'],
                                'danger'  => ['icon'=>'🚨','border'=>'border-l-red-500','bg'=>'bg-red-50/50'],
                            ][$msg['status']] ?? ['icon'=>'ℹ️','border'=>'border-l-gray-300','bg'=>''];
                        @endphp
                        <div class="border-l-4 {{ $msgCfg['border'] }} {{ $msgCfg['bg'] }} rounded-r-lg pl-3 pr-4 py-2.5">
                            <p class="text-xs font-semibold text-gray-700 mb-0.5">{{ $msgCfg['icon'] }} {{ $msg['mode'] }}</p>
                            <p class="text-xs text-gray-600 leading-relaxed">{{ $msg['message'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Activity Log --}}
            <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Aktivitas Petugas Hari Ini</h3>
                <div class="relative pl-4 border-l-2 border-gray-200 space-y-5">
                    @foreach ($activityLog as $act)
                        @php
                            $dotColor = ['success'=>'bg-emerald-500','info'=>'bg-blue-400','warning'=>'bg-amber-500'][$act['type']] ?? 'bg-gray-400';
                        @endphp
                        <div class="relative">
                            <div class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full border-2 border-white {{ $dotColor }}"></div>
                            <div class="flex items-center justify-between gap-2 mb-0.5">
                                <h4 class="text-sm font-medium text-gray-900">{{ $act['title'] }}</h4>
                                <span class="text-[10px] text-gray-400 shrink-0">{{ $act['time'] }}</span>
                            </div>
                            <p class="text-xs text-gray-500">{{ $act['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush
