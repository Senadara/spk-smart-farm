@extends('layouts.app')

@section('title', 'Peternakan')

@section('content')
    @php
        $statusLabels = ['normal' => 'Aman', 'warning' => 'Perhatian', 'danger' => 'Kritis'];
    @endphp

    <div x-data="{
                activeBarn: 0,
                barns: @js($barnEnvironment['barns']),
                searchLog: '',
                fuzzyFilter: 'all',
                fuzzyByBarn: @js($fuzzyByBarn),
                fuzzyProduktivitasByBarn: @js($fuzzyProduktivitasByBarn),
                activeKomoditasId: @js($activeKomoditasId),
                evaluationTimeLabel: @js($evaluationTime),
                evaluating: false,
                evalMessage: '',
                evalSuccess: false,
                _spiderChart: null,

                get activeSummary() {
                    return this.barns[this.activeBarn]?.summary ?? {};
                },

                get activeFuzzyKey() {
                    return this.fuzzyFilter === 'all' ? 'all' : this.fuzzyFilter;
                },

                get fuzzySensors() {
                    const data = this.fuzzyByBarn[this.activeFuzzyKey] ?? this.fuzzyByBarn['all'] ?? {};
                    return data.fuzzySensors ?? { lingkungan: [], produktivitas: [] };
                },

                get activeSpkResults() {
                    const data = this.fuzzyByBarn[this.activeFuzzyKey] ?? this.fuzzyByBarn['all'] ?? {};
                    return data.spkResults ?? {};
                },

                get activeIndicators() {
                    const prod = this.fuzzyProduktivitasByBarn[this.activeFuzzyKey] ?? this.fuzzyProduktivitasByBarn['all'] ?? {};
                    return prod.indicators ?? @js($produktivitas['indicators']);
                },

                get activeSpider() {
                    const prod = this.fuzzyProduktivitasByBarn[this.activeFuzzyKey] ?? this.fuzzyProduktivitasByBarn['all'] ?? {};
                    return prod.spider ?? @js($produktivitas['spider']);
                },

                onKomoditasChange(event) {
                    const url = new URL(window.location.href);
                    const id = event.target.value;
                    id ? url.searchParams.set('komoditas', id) : url.searchParams.delete('komoditas');
                    window.location.href = url.toString();
                },

                selectBarn(index) {
                    this.activeBarn = index;
                    const id = this.barns[index]?.id;
                    if (id && id !== 'no-data') this.fuzzyFilter = id;
                    this.$nextTick(() => this.renderSpider());
                },

                onFuzzyBarnChange() {
                    if (this.fuzzyFilter !== 'all') {
                        const idx = this.barns.findIndex(b => b.id === this.fuzzyFilter);
                        if (idx >= 0) this.activeBarn = idx;
                    }
                    this.$nextTick(() => this.renderSpider());
                },

                async runFullEvaluation() {
                    this.evaluating = true;
                    this.evalMessage = '';
                    try {
                        const res = await fetch(@js(route('peternakan.evaluate-all')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ komoditas_id: this.activeKomoditasId }),
                        });
                        const data = await res.json();
                        this.evalSuccess = data.success;
                        if (data.success) {
                            this.evalMessage = 'Evaluasi selesai (' + data.processed + ' kandang). Memuat ulang...';
                            if (data.evaluation_time) {
                                this.evaluationTimeLabel = 'Auto evaluated terakhir: ' + data.evaluation_time;
                            }
                            setTimeout(() => window.location.reload(), 1200);
                        } else {
                            this.evalMessage = 'Sebagian evaluasi gagal. Coba lagi.';
                        }
                    } catch (e) {
                        this.evalSuccess = false;
                        this.evalMessage = 'Gagal menjalankan evaluasi.';
                    } finally {
                        this.evaluating = false;
                    }
                },

                init() {
                    this.$nextTick(() => this.renderSpider());
                },

                renderSpider() {
                    const canvas = this.$refs.spiderCanvas;
                    if (!canvas) return;
                    if (this._spiderChart) this._spiderChart.destroy();

                    const spider = this.activeSpider;
                    const indicators = this.activeIndicators ?? [];

                    this._spiderChart = new Chart(canvas, {
                        type: 'radar',
                        data: {
                            labels: spider.labels ?? [],
                            datasets: [{
                                label: 'Skor SPK',
                                data: spider.values ?? [],
                                borderColor: '#059669',
                                backgroundColor: 'rgba(16,185,129,0.22)',
                                borderWidth: 2.5,
                                pointRadius: 5,
                                pointHoverRadius: 7,
                                pointBackgroundColor: '#10B981',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label(ctx) {
                                            const idx = ctx.dataIndex;
                                            const ind = indicators[idx];
                                            const score = ctx.raw ?? 0;
                                            if (ind?.value) {
                                                return ` ${ind.label}: ${ind.value} (skor ${score})`;
                                            }
                                            return ` Skor: ${score}/100`;
                                        },
                                    },
                                },
                            },
                            scales: {
                                r: {
                                    beginAtZero: true,
                                    max: 100,
                                    ticks: {
                                        stepSize: 25,
                                        count: 5,
                                        font: { size: 10, family: 'Inter' },
                                        backdropColor: 'transparent',
                                        color: '#9CA3AF',
                                    },
                                    pointLabels: {
                                        font: { size: 11, weight: '600', family: 'Inter' },
                                        color: '#374151',
                                        padding: 10,
                                    },
                                    grid: { color: 'rgba(0,0,0,0.08)' },
                                    angleLines: { color: 'rgba(0,0,0,0.08)' },
                                },
                            },
                        },
                    });
                },

                summaryClass(field) {
                    const s = this.activeSummary[field + '_status'] ?? 'normal';
                    return { normal: 'text-gray-900', warning: 'text-amber-600', danger: 'text-red-600' }[s] ?? 'text-gray-900';
                },
            }" class="max-w-full space-y-5">

        {{-- HEADER --}}
        <div class="flex flex-wrap items-center justify-between gap-4 mb-2">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Decision Support & Operations</h1>
                <p class="text-sm text-gray-500 mt-1.5 leading-relaxed">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="font-medium text-emerald-600">Live monitoring</span>
                    </span>
                    <span class="mx-2 text-gray-300">•</span>
                    <span class="font-semibold text-gray-700">{{ $activeKomoditasNama }}</span>
                    <span class="mx-2 text-gray-300">•</span>
                    <span>{{ count($barnEnvironment['barns']) }} kandang aktif</span>
                    <span class="mx-2 text-gray-300">•</span>
                    <span>{{ count($productionLog) }} log produksi</span>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2.5 bg-white border border-gray-200 rounded-xl text-sm text-gray-600 focus-within:ring-2 focus-within:ring-emerald-500 focus-within:border-emerald-500 overflow-hidden pr-3 shadow-sm">
                    <div class="pl-3.5 py-2.5 pointer-events-none">
                        <svg class="w-4.5 h-4.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <select @change="onKomoditasChange($event)"
                        class="py-2.5 pl-1 pr-7 bg-transparent border-none text-gray-700 focus:outline-none cursor-pointer text-sm font-semibold appearance-none">
                        @forelse($komoditas as $k)
                            <option value="{{ $k->id }}" {{ $k->id === $activeKomoditasId ? 'selected' : '' }}>{{ $k->nama }}</option>
                        @empty
                            <option value="">Belum ada komoditas</option>
                        @endforelse
                    </select>
                </div>
                <div class="flex items-center gap-2.5 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm text-gray-600 shadow-sm">
                    <svg class="w-4.5 h-4.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span class="font-medium">{{ now()->format('m/d/Y') }}</span>
                </div>
            </div>
        </div>

        @if(!$hasKomoditas)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800 shadow-sm">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span class="font-medium">Belum ada data komoditas.</span>
            </div>
            <p class="text-xs text-amber-700 mt-1 ml-7">Tambahkan komoditas di Data Master / IoT Config agar filter dashboard berfungsi.</p>
        </div>
        @endif

        @if(!($dailyReportStatus['isReady'] ?? false))
            @php
                $dailyStatus = $dailyReportStatus['status'] ?? 'empty';
                $dailyTone = match($dailyStatus) {
                    'partial' => [
                        'wrap' => 'border-sky-200 bg-sky-50 text-sky-900',
                        'icon' => 'text-sky-600 bg-sky-100',
                        'pill' => 'bg-sky-100 text-sky-700',
                    ],
                    'no_coops' => [
                        'wrap' => 'border-gray-200 bg-gray-50 text-gray-800',
                        'icon' => 'text-gray-600 bg-gray-100',
                        'pill' => 'bg-gray-100 text-gray-600',
                    ],
                    default => [
                        'wrap' => 'border-amber-200 bg-amber-50 text-amber-900',
                        'icon' => 'text-amber-600 bg-amber-100',
                        'pill' => 'bg-amber-100 text-amber-700',
                    ],
                };
                $missingBarns = array_slice($dailyReportStatus['missingBarns'] ?? [], 0, 4);
            @endphp
            <div class="rounded-xl border px-5 py-4 shadow-sm {{ $dailyTone['wrap'] }}">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex gap-3">
                        <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $dailyTone['icon'] }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6h6v6m2 4H7a2 2 0 01-2-2V7a2 2 0 012-2h2l2-2h2l2 2h2a2 2 0 012 2v12a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-sm font-bold">{{ $dailyReportStatus['title'] ?? 'Laporan harian belum tersedia' }}</h2>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $dailyTone['pill'] }}">
                                    {{ $dailyReportStatus['reportedCount'] ?? 0 }}/{{ $dailyReportStatus['totalCoops'] ?? 0 }} kandang
                                </span>
                            </div>
                            <p class="mt-1 text-sm leading-relaxed opacity-90">
                                {{ $dailyReportStatus['message'] ?? 'Data produksi akan tampil setelah laporan harian dicatat.' }}
                            </p>
                            <div class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                                <div class="rounded-lg bg-white/60 px-3 py-2">
                                    <span class="font-semibold">Tanggal laporan:</span>
                                    <span>{{ $dailyReportStatus['date'] ?? now()->format('d M Y') }}</span>
                                </div>
                                <div class="rounded-lg bg-white/60 px-3 py-2">
                                    <span class="font-semibold">Data yang tetap valid:</span>
                                    <span>sensor IoT, status kandang, dan riwayat laporan lama</span>
                                </div>
                            </div>
                            @if(!empty($missingBarns))
                                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                                    <span class="font-semibold">Belum ada laporan:</span>
                                    @foreach($missingBarns as $barnName)
                                        <span class="rounded-full bg-white/70 px-2.5 py-1 font-medium">{{ $barnName }}</span>
                                    @endforeach
                                    @if(count($dailyReportStatus['missingBarns'] ?? []) > count($missingBarns))
                                        <span class="font-medium opacity-80">+{{ count($dailyReportStatus['missingBarns']) - count($missingBarns) }} kandang lain</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="shrink-0 rounded-lg bg-white/70 px-4 py-3 text-xs leading-relaxed lg:w-72">
                        <p class="font-bold">Apa yang perlu dilakukan?</p>
                        <p class="mt-1 opacity-90">Input laporan panen, pakan, atau kematian harian dari aplikasi/API Smart Farming. Setelah tersimpan, refresh dashboard atau jalankan evaluasi SPK.</p>
                        @if(!empty($dailyReportStatus['lastReportAt']))
                            <p class="mt-2 opacity-75">Laporan terakhir: {{ $dailyReportStatus['lastReportAt'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- SECTION 1: KPI --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            @foreach($kpiMetrics as $kpi)
                <x-peternakan.kpi-card :label="$kpi['label']" :value="$kpi['value']" :trend="$kpi['trend']" />
            @endforeach
        </div>

        {{-- SECTION 2: CHART + BARN ENVIRONMENT --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
            <x-peternakan.performance-chart
                chartId="effChart"
                :labels="$chartData['labels']"
                :hdpData="$chartData['hdp']"
                :fcrData="$chartData['fcr']"
                :chartDataByRange="$chartDataByRange"
                :defaultRange="$chartRange"
                class="lg:col-span-3"
            />

            <div class="lg:col-span-2 bg-white border border-gray-100 rounded-xl p-6 shadow-sm flex flex-col h-full">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-semibold text-gray-800">Barn Environment</h3>
                    <span class="text-xs text-gray-400 bg-gray-50 px-2 py-1 rounded-md">Batas IoT komoditas</span>
                </div>

                <div class="grid grid-cols-3 gap-2.5 mb-5">
                    @foreach($barnEnvironment['barns'] as $i => $barn)
                        @php
                            $barnColors = [
                                'normal'  => 'bg-emerald-50 border-emerald-200 text-emerald-700',
                                'warning' => 'bg-amber-50 border-amber-200 text-amber-700',
                                'danger'  => 'bg-red-50 border-red-200 text-red-700',
                            ];
                            $activeRing = ['normal' => 'ring-emerald-400', 'warning' => 'ring-amber-400', 'danger' => 'ring-red-400'];
                            $baseColor = $barnColors[$barn['status']] ?? $barnColors['normal'];
                            $ring = $activeRing[$barn['status']] ?? 'ring-emerald-400';
                        @endphp
                        <button type="button"
                            @click="selectBarn({{ $i }})"
                            :class="activeBarn === {{ $i }} ? 'ring-2 {{ $ring }} scale-105 shadow-md' : 'hover:shadow-sm'"
                            class="rounded-lg border px-3 py-3 text-center cursor-pointer transition-all {{ $baseColor }}"
                            title="Klik untuk melihat sensor kandang ini">
                            <p class="text-xs font-semibold truncate">{{ $barn['name'] }}</p>
                            <p class="text-lg font-bold mt-0.5">{{ $barn['temp'] }}°</p>
                        </button>
                    @endforeach
                </div>

                <div class="grid grid-cols-2 gap-3.5">
                    @foreach([
                        ['key' => 'avg_temp', 'label' => 'Suhu', 'icon' => 'M12 9V3m0 0a2 2 0 10-4 0v9.764a4 4 0 106.764 1.528A3.99 3.99 0 0012 13V3z', 'status' => 'temp_status'],
                        ['key' => 'humidity', 'label' => 'Kelembapan', 'icon' => 'M12 21a8 8 0 004-14.947L12 2l-4 4.053A8 8 0 0012 21z', 'status' => 'humidity_status'],
                        ['key' => 'ammonia', 'label' => 'Amonia', 'icon' => 'M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'status' => 'ammonia_status'],
                        ['key' => 'lux', 'label' => 'Lux', 'icon' => 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z', 'status' => 'lux_status'],
                    ] as $sensor)
                    <div class="flex items-center gap-2.5 rounded-lg px-2.5 py-2"
                         :class="{
                            'bg-red-50/90 border border-red-100': activeSummary['{{ $sensor['status'] }}'] === 'danger',
                            'bg-amber-50/90 border border-amber-100': activeSummary['{{ $sensor['status'] }}'] === 'warning',
                            'bg-gray-50/50': activeSummary['{{ $sensor['status'] }}'] === 'normal' || !activeSummary['{{ $sensor['status'] }}'],
                         }">
                        <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sensor['icon'] }}"/></svg>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 font-medium">{{ $sensor['label'] }}</p>
                            <p class="text-sm font-bold" :class="summaryClass('{{ str_replace('_status', '', $sensor['status']) }}')" x-text="activeSummary['{{ $sensor['key'] }}'] || '-'"></p>
                        </div>
                    </div>
                    @endforeach
                </div>

                <a :href="barns[activeBarn]?.id && barns[activeBarn].id !== 'no-data'
                        ? '{{ url('/peternakan') }}/' + barns[activeBarn].id + '?komoditas=' + activeKomoditasId
                        : '#'"
                    class="mt-auto flex items-center justify-center gap-2 w-full py-3 rounded-lg text-sm font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition-colors"
                    style="text-decoration: none;">
                    Lihat Detail Kandang
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>

        {{-- SECTION 3: FUZZY ENGINE --}}
        <x-fuzzy-decision-engine
            :barns="$barnEnvironment['barns']"
            :indicators="$produktivitas['indicators']"
            :spkResults="$spkResults"
            :evaluationTime="$evaluationTime"
        />

        {{-- SECTION 4: DAFTAR KANDANG --}}
        <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 p-6 border-b border-gray-50">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Daftar Kandang (Unit Budidaya)</h3>
                    <p class="text-sm text-gray-500 mt-1">Komoditas: <span class="font-medium text-gray-700">{{ $activeKomoditasNama }}</span> — klik kartu untuk membuka halaman detail</p>
                </div>
                <span class="text-xs font-semibold text-gray-600 bg-gray-100 px-3 py-1.5 rounded-full">{{ count($listKandang) }} kandang</span>
            </div>
            <div class="p-6 space-y-3.5">
                @forelse($listKandang as $kandang)
                    @php
                        $cfg = match($kandang['status']) {
                            'danger' => ['dot' => 'bg-red-500', 'badge' => 'bg-red-50 text-red-700 border-red-200'],
                            'warning' => ['dot' => 'bg-amber-500', 'badge' => 'bg-amber-50 text-amber-700 border-amber-200'],
                            default => ['dot' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                        };
                    @endphp
                    <a href="{{ route('peternakan.show', $kandang['id']) }}?komoditas={{ $activeKomoditasId }}"
                       class="group flex items-center justify-between gap-4 border border-gray-100 rounded-xl px-5 py-4 hover:border-emerald-300 hover:bg-emerald-50/50 hover:shadow-sm transition-all"
                       style="text-decoration: none;">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <span class="w-3 h-3 rounded-full shrink-0 {{ $cfg['dot'] }}"></span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <h4 class="font-semibold text-gray-800 text-base group-hover:text-emerald-700">{{ $kandang['nama'] }}</h4>
                                    <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full border {{ $cfg['badge'] }}">{{ $statusLabels[$kandang['status']] ?? 'Aman' }}</span>
                                </div>
                                <p class="text-sm text-gray-500 mt-1.5">
                                    {{ number_format((float)($kandang['jumlah'] ?? 0), 0, ',', '.') }} ekor
                                    @if($kandang['kapasitas']) <span class="mx-1.5 text-gray-300">•</span> kapasitas {{ $kandang['kapasitas'] }} @endif
                                    @if($kandang['lokasi']) <span class="mx-1.5 text-gray-300">•</span> {{ $kandang['lokasi'] }} @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-5 shrink-0 text-right">
                            <div class="text-right">
                                <p class="text-xs text-gray-400 font-medium">HDP hari ini</p>
                                <p class="text-base font-bold text-gray-800">{{ $kandang['hdp'] }}%</p>
                            </div>
                            <div class="flex items-center gap-1.5 text-sm font-semibold text-emerald-600 group-hover:text-emerald-700">
                                Detail
                                <svg class="w-4.5 h-4.5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="py-12 text-center">
                        <p class="text-sm text-gray-500">Belum ada kandang aktif untuk komoditas ini.</p>
                        <p class="text-xs text-gray-400 mt-1.5">Tambahkan unit budidaya di Data Master.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- SECTION 5: DAILY PRODUCTION LOG --}}
        <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Daily Production Log</h3>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-model="searchLog" type="text" placeholder="Cari log..." class="pl-10 pr-4 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 w-56">
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50/90">
                        <tr>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-gray-500">Date</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-gray-500">Barn</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-gray-500">Flock Age</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-gray-500">Birds</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-gray-500">Eggs Collected</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-gray-500">Rejects</th>
                            <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($productionLog as $log)
                            @php
                                $logStatus = ['Optimal' => 'text-emerald-600 bg-emerald-50', 'Attention' => 'text-amber-600 bg-amber-50', 'Critical' => 'text-red-600 bg-red-50'];
                                $statusClass = $logStatus[$log['status']] ?? 'text-gray-600 bg-gray-50';
                                $searchHay = strtolower(($log['date'] ?? '') . ($log['barn'] ?? '') . ($log['status'] ?? ''));
                            @endphp
                            <tr class="hover:bg-gray-50/70 transition-colors" x-show="!searchLog || @js($searchHay).includes(searchLog.toLowerCase())">
                                <td class="px-6 py-4 text-gray-600 font-medium">{{ $log['date'] }}</td>
                                <td class="px-6 py-4 font-semibold text-blue-600">{{ $log['barn'] }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $log['flock_age'] }}</td>
                                <td class="px-6 py-4 text-gray-800 font-semibold">{{ $log['birds'] }}</td>
                                <td class="px-6 py-4 text-gray-800 font-semibold">{{ $log['eggs'] }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $log['rejects'] }}</td>
                                <td class="px-6 py-4"><span class="px-3 py-1.5 text-xs font-semibold rounded-full {{ $statusClass }}">{{ $log['status'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between px-6 py-4 border-t border-gray-50">
                <p class="text-sm text-gray-500">Menampilkan {{ count($productionLog) }} entri terakhir</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush
