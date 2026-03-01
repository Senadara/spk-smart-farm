@extends('layouts.app')

@section('title', 'Peternakan')

@section('content')
    <div x-data="{
                activeBarn: 0,
                barns: @js($barnEnvironment['barns']),
                searchLog: '',
                fuzzyFilter: 'all',
                _spiderChart: null,

                get activeSummary() {
                    return this.barns[this.activeBarn]?.summary ?? {};
                },

                get fuzzySensors() {
                    if (this.fuzzyFilter === 'all') {
                        // Average across all barns
                        const allSensors = this.barns.map(b => b.sensors);
                        if (!allSensors.length) return [];
                        return allSensors[0].map((s, i) => {
                            const avgPercent = Math.round(allSensors.reduce((sum, barn) => sum + barn[i].percent, 0) / allSensors.length);
                            const worstStatus = allSensors.reduce((worst, barn) => {
                                const rank = { normal: 0, warning: 1, danger: 2 };
                                return rank[barn[i].status] > rank[worst] ? barn[i].status : worst;
                            }, 'normal');
                            return {
                                label: s.label,
                                percent: avgPercent,
                                status: worstStatus,
                                statusLabel: s.statusLabel.replace(/[\d.]+\)/, avgPercent/100 + ')'),
                            };
                        });
                    }
                    const barn = this.barns[parseInt(this.fuzzyFilter)];
                    return barn?.sensors ?? [];
                },

                init() {
                    this.$nextTick(() => this.renderSpider());
                },

                renderSpider() {
                    const canvas = this.$refs.spiderCanvas;
                    if (!canvas) return;
                    if (this._spiderChart) this._spiderChart.destroy();

                    this._spiderChart = new Chart(canvas, {
                        type: 'radar',
                        data: {
                            labels: @js($produktivitas['spider']['labels']),
                            datasets: [{
                                label: 'Score',
                                data: @js($produktivitas['spider']['values']),
                                borderColor: '#10B981',
                                backgroundColor: 'rgba(16,185,129,0.15)',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointBackgroundColor: '#10B981',
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: { legend: { display: false } },
                            scales: {
                                r: {
                                    beginAtZero: true,
                                    max: 100,
                                    ticks: { stepSize: 25, font: { size: 9, family: 'Inter' }, backdropColor: 'transparent' },
                                    pointLabels: { font: { size: 9, family: 'Inter' }, color: '#6B7280' },
                                    grid: { color: 'rgba(0,0,0,0.06)' },
                                    angleLines: { color: 'rgba(0,0,0,0.06)' },
                                },
                            },
                        },
                    });
                },
            }" class="max-w-full space-y-5">
        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- HEADER BAR                                                 --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Decision Support & Operations</h1>
                <p class="text-xs text-gray-400 mt-0.5">
                    <span class="inline-flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Live monitoring •
                    </span>
                    {{ count($barnEnvironment['barns']) }} kandang aktif •
                    {{ count($productionLog) }} log produksi hari ini
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <span>Layer</span>
                </div>
                <div class="flex items-center gap-2 px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>{{ now()->format('m/d/Y') }}</span>
                </div>
                <button class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition flex items-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export Report
                </button>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- SECTION 1: KPI METRICS ROW                                 --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach($kpiMetrics as $kpi)
                <x-peternakan.kpi-card :label="$kpi['label']" :value="$kpi['value']" :trend="$kpi['trend']" />
            @endforeach
        </div>

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- SECTION 2: PRODUCTION CHART + BARN ENVIRONMENT             --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
            {{-- Production Efficiency (3/5) --}}
            <div class="lg:col-span-3">
                <x-peternakan.performance-chart chartId="effChart" :labels="$chartData['labels']"
                    :hdpData="$chartData['hdp']" :fcrData="$chartData['fcr']" />
            </div>

            {{-- Barn Environment (2/5) — clickable grid for IoT summary only --}}
            <div class="lg:col-span-2 bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-gray-800">Barn Environment</h3>
                    <a href="#" class="text-xs font-medium text-emerald-600 hover:text-emerald-700">Full Report</a>
                </div>

                {{-- Clickable Barn Grid --}}
                <div class="grid grid-cols-3 gap-2 mb-4">
                    @foreach($barnEnvironment['barns'] as $i => $barn)
                        @php
                            $barnColors = [
                                'normal'  => 'bg-emerald-50 border-emerald-200 text-emerald-700',
                                'warning' => 'bg-amber-50 border-amber-200 text-amber-700',
                                'danger'  => 'bg-red-50 border-red-200 text-red-700',
                            ];
                            $activeBorderColor = [
                                'normal'  => 'ring-emerald-400',
                                'warning' => 'ring-amber-400',
                                'danger'  => 'ring-red-400',
                            ];
                            $baseColor  = $barnColors[$barn['status']] ?? $barnColors['normal'];
                            $activeRing = $activeBorderColor[$barn['status']] ?? 'ring-emerald-400';
                        @endphp
                        <button
                            @click="activeBarn = {{ $i }}"
                            :class="activeBarn === {{ $i }} ? 'ring-2 {{ $activeRing }} scale-105 shadow-md' : 'hover:shadow-sm'"
                            class="rounded-lg border px-3 py-2.5 text-center cursor-pointer transition-all {{ $baseColor }}"
                        >
                            <p class="text-xs font-medium truncate">{{ $barn['name'] }}</p>
                            <p class="text-base font-bold">{{ $barn['temp'] }}°</p>
                        </button>
                    @endforeach
                </div>

                {{-- Dynamic IoT Summary (changes per active barn) --}}
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9V3m0 0a2 2 0 10-4 0v9.764a4 4 0 106.764 1.528A3.99 3.99 0 0012 13V3z"/></svg>
                        <div>
                            <p class="text-xs text-gray-400">Avg Temp</p>
                            <p class="text-sm font-bold text-gray-900" x-text="activeSummary.avg_temp || '-'"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a8 8 0 004-14.947L12 2l-4 4.053A8 8 0 0012 21z"/></svg>
                        <div>
                            <p class="text-xs text-gray-400">Humidity</p>
                            <p class="text-sm font-bold text-gray-900" x-text="activeSummary.humidity || '-'"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <p class="text-xs text-gray-400">Ammonia</p>
                            <p class="text-sm font-bold text-gray-900">
                                <span x-text="activeSummary.ammonia || '-'"></span>
                                <span x-show="activeSummary.ammonia_ok" class="text-xs font-normal text-emerald-500">✓</span>
                                <span x-show="!activeSummary.ammonia_ok" class="text-xs font-normal text-amber-500">⚠</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <div>
                            <p class="text-xs text-gray-400">Lux</p>
                            <p class="text-sm font-bold text-gray-900" x-text="activeSummary.lux || '-'"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- SECTION 3: FUZZY ENGINE + SPK RESULTS (UNIFIED CARD)       --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
            {{-- Header --}}
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-800">Fuzzy Productivity Decision Engine</h3>
                        <p class="text-xs text-gray-400">Mamdani Inference System for Farm Performance Decision Support</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <select x-model="fuzzyFilter" class="text-xs border border-gray-200 rounded-lg px-3 py-1.5 bg-white text-gray-600 focus:outline-none focus:ring-1 focus:ring-emerald-300 focus:border-emerald-400">
                        <option value="all">All Barn</option>
                        @foreach($barnEnvironment['barns'] as $barn)
                            <option value="{{ $barn['id'] }}">{{ $barn['name'] }}</option>
                        @endforeach
                    </select>
                    <span class="px-2.5 py-1 text-xs font-medium bg-emerald-50 text-emerald-700 rounded-full flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Auto Evaluated
                    </span>
                </div>
            </div>

            {{-- 3 columns side-by-side --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:divide-x lg:divide-gray-100">

                {{-- COL 1: Environment Logic (driven by fuzzyFilter dropdown) --}}
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                            <span class="text-sm font-semibold text-gray-700">Environment Logic</span>
                        </div>
                        <span class="px-1.5 py-0.5 text-[10px] font-semibold bg-emerald-100 text-emerald-700 rounded">IoT Real-time</span>
                    </div>

                    {{-- Sensor bars: driven by fuzzyFilter (dropdown), NOT activeBarn --}}
                    <div class="space-y-3">
                        <template x-for="(sensor, idx) in fuzzySensors" :key="idx">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs text-gray-600" x-text="sensor.label"></span>
                                    <span
                                        class="text-xs font-medium px-2 py-0.5 rounded"
                                        :class="{
                                            'text-emerald-600 bg-emerald-50': sensor.status === 'normal',
                                            'text-amber-600 bg-amber-50': sensor.status === 'warning',
                                            'text-red-600 bg-red-50': sensor.status === 'danger',
                                        }"
                                        x-text="sensor.statusLabel"
                                    ></span>
                                </div>
                                <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="{
                                            'bg-emerald-500': sensor.status === 'normal',
                                            'bg-amber-500': sensor.status === 'warning',
                                            'bg-red-500': sensor.status === 'danger',
                                        }"
                                        :style="'width: ' + sensor.percent + '%'"
                                    ></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- COL 2: Productivity Logic (Spider Chart) --}}
                <div class="lg:pl-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <span class="text-sm font-semibold text-gray-700">Productivity Logic</span>
                        </div>
                        <span class="px-1.5 py-0.5 text-[10px] font-semibold bg-blue-100 text-blue-700 rounded">Data Driven</span>
                    </div>

                    <div class="flex items-start gap-4">
                        {{-- Spider Chart --}}
                        <div class="w-36 h-36 shrink-0">
                            <canvas x-ref="spiderCanvas"></canvas>
                        </div>
                        {{-- Indicators --}}
                        <div class="flex-1 space-y-2.5 pt-2">
                            @foreach($produktivitas['indicators'] as $item)
                                @php
                                    $dotColors = [
                                        'emerald' => 'bg-emerald-500',
                                        'amber'   => 'bg-amber-500',
                                        'red'     => 'bg-red-500',
                                    ];
                                    $dotColor = $dotColors[$item['color']] ?? 'bg-gray-400';
                                @endphp
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-600">{{ $item['label'] }}</span>
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-1.5 rounded-full {{ $dotColor }}"></span>
                                        <span class="text-xs font-semibold text-gray-800 w-8">{{ $item['value'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- COL 3: AI Console --}}
                <div class="lg:pl-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                            <span class="text-sm font-semibold text-gray-700">AI Console</span>
                        </div>
                        <span class="text-xs text-gray-400">Manual Trigger</span>
                    </div>

                    <div class="flex flex-col items-center text-center pt-2">
                        <div class="w-14 h-14 rounded-full bg-purple-50 flex items-center justify-center mb-3">
                            <svg class="w-7 h-7 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        </div>
                        <h4 class="text-sm font-semibold text-gray-800 mb-1">Full Farm Diagnostic</h4>
                        <p class="text-xs text-gray-400 max-w-[200px] mb-4">Run a comprehensive analysis combining environmental and productivity data points.</p>
                        <button class="px-5 py-2.5 bg-gradient-to-r from-red-500 to-rose-500 text-white text-sm font-medium rounded-full hover:shadow-lg transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                            Run Full Evaluation
                        </button>
                    </div>
                </div>
            </div>

            {{-- ─── SPK Analysis Results (inside same card) ─────────── --}}
            <div class="border-t border-gray-100 mt-5 pt-5">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-sm font-semibold text-gray-700">SPK Analysis Result</h4>
                    <span class="text-xs text-gray-400">just now</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($spkResults as $result)
                        @php
                            $statusBadge = [
                                'amber'   => 'bg-amber-100 text-amber-700 border-amber-200',
                                'blue'    => 'bg-blue-100 text-blue-700 border-blue-200',
                                'emerald' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                            ];
                            $badge = $statusBadge[$result['statusColor']] ?? 'bg-gray-100 text-gray-700 border-gray-200';
                            $isMain = isset($result['isMain']);
                            $cardBg = $isMain ? 'bg-emerald-50/40 border-emerald-200' : 'bg-gray-50/50 border-gray-100';
                        @endphp
                        <div class="border rounded-lg p-4 {{ $cardBg }}">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full {{ $badge }}">{{ $result['status'] }}</span>
                            </div>
                            <p class="text-sm text-gray-700 leading-relaxed">
                                <span class="font-semibold text-gray-900">{{ $result['title'] }}</span>
                                {{ $result['description'] }}
                            </p>
                            @if($isMain)
                                <a href="{{ $result['link'] }}" class="inline-block mt-3 px-4 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition">View Full Diagnostic Report</a>
                            @else
                                <a href="{{ $result['link'] }}" class="inline-block mt-2 text-xs font-semibold text-blue-600 hover:text-blue-700">See Detail →</a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════ --}}
        {{-- SECTION 4: DAILY PRODUCTION LOG                            --}}
        {{-- ═══════════════════════════════════════════════════════════ --}}
        <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between p-5 border-b border-gray-50">
                <h3 class="text-base font-semibold text-gray-800">Daily Production Log</h3>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-model="searchLog" type="text" placeholder="Search logs..." class="pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-emerald-400 focus:ring-1 focus:ring-emerald-200 w-48">
                    </div>
                    <button class="flex items-center gap-1.5 px-3 py-2 text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filter
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50/80">
                        <tr>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Date</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Barn</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Flock Age</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Birds</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Eggs Collected</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Rejects</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Status</th>
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($productionLog as $log)
                            @php
                                $logStatus = ['Optimal' => 'text-emerald-600 bg-emerald-50', 'Attention' => 'text-amber-600 bg-amber-50', 'Critical' => 'text-red-600 bg-red-50'];
                                $statusClass = $logStatus[$log['status']] ?? 'text-gray-600 bg-gray-50';
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-5 py-3.5 text-gray-600">{{ $log['date'] }}</td>
                                <td class="px-5 py-3.5 font-medium text-blue-600">{{ $log['barn'] }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $log['flock_age'] }}</td>
                                <td class="px-5 py-3.5 text-gray-800 font-medium">{{ $log['birds'] }}</td>
                                <td class="px-5 py-3.5 text-gray-800 font-medium">{{ $log['eggs'] }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $log['rejects'] }}</td>
                                <td class="px-5 py-3.5"><span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $statusClass }}">{{ $log['status'] }}</span></td>
                                <td class="px-5 py-3.5">
                                    <button class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between px-5 py-3 border-t border-gray-50">
                <p class="text-xs text-gray-400">Showing 4 of 128 rows</p>
                <div class="flex items-center gap-2">
                    <button class="px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700">Prev</button>
                    <button class="px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700">Next</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush