@extends('layouts.app')

@section('title', 'Peternakan')

@push('styles')
    <style>
        @media (min-width: 1024px) {
            .summary-balanced-grid {
                grid-template-columns: repeat(var(--summary-cols, 1), minmax(0, 1fr));
            }
        }

        .iot-sensor-grid {
            grid-template-columns: 1fr;
        }

        @media (min-width: 640px) {
            .iot-sensor-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
@endpush

@section('content')
    @php
        $statusLabels = ['normal' => 'Aman', 'warning' => 'Perhatian', 'danger' => 'Kritis'];
        $kpiHints = [
            'HDP %' => [
                'body' => 'Hen Day Production, persentase jumlah telur hari ini dibanding populasi ayam aktif.',
                'formula' => '(total telur hari ini / populasi aktif) x 100%',
                'source' => 'laporan, panen, unitBudidaya',
            ],
            'HHEP %' => [
                'body' => 'Hen Housed Egg Production, persentase jumlah telur hari ini dibanding estimasi populasi awal kandang.',
                'formula' => '(total telur hari ini / populasi awal) x 100%',
                'source' => 'laporan, panen, unitBudidaya, kematian',
            ],
            'FCR' => [
                'body' => 'Feed Conversion Ratio, rasio pakan terhadap egg mass. Semakin kecil biasanya semakin efisien.',
                'formula' => 'total pakan / total egg mass',
                'source' => 'harianTernak, panen',
            ],
            'Umur Biologis' => [
                'body' => 'Rata-rata umur flock/kandang aktif dari input mobile. Data lama fallback ke tanggal kandang dibuat.',
                'formula' => 'rata-rata unitBudidaya.umurMinggu',
                'source' => 'unitBudidaya.umurMinggu',
            ],
            'Feed Intake' => [
                'body' => 'Estimasi konsumsi pakan per ekor per hari.',
                'formula' => '(total pakan / populasi aktif) x 1000 gram',
                'source' => 'harianTernak, unitBudidaya',
            ],
            'Egg Mass' => [
                'body' => 'Berat total telur yang dipanen hari ini dari laporan panen mobile.',
                'formula' => 'SUM(panen.berat)',
                'source' => 'panen',
            ],
            'Berat Rata-rata' => [
                'body' => 'Rata-rata berat telur/panen harian berdasarkan total berat dan jumlah telur.',
                'formula' => '(total berat panen x 1000) / total telur',
                'source' => 'panen.berat, panen.jumlah',
            ],
            'Mortality' => [
                'body' => 'Persentase kematian ayam terhadap populasi pada periode berjalan.',
                'formula' => '(jumlah kematian / populasi) x 100%',
                'source' => 'kematian, laporan, unitBudidaya',
            ],
            'Mortalitas' => [
                'body' => 'Persentase kematian ayam terhadap populasi pada periode berjalan.',
                'formula' => '(jumlah kematian / populasi) x 100%',
                'source' => 'kematian, laporan, unitBudidaya',
            ],
        ];
        $spkMetricHints = [
            'Skor rata-rata' => ['body' => 'Rata-rata skor hasil analisis SPK hari ini. Jika belum ada analisis hari ini, nilai dapat kosong.', 'formula' => 'AVG(spk_fuzzy_logs.output_value)', 'source' => 'spk_fuzzy_logs'],
            'Analisa hari ini' => ['body' => 'Jumlah proses analisis SPK yang tersimpan pada tanggal hari ini.', 'formula' => 'COUNT(log hari ini)', 'source' => 'spk_fuzzy_logs'],
            'Perlu tindakan' => ['body' => 'Jumlah hasil SPK yang berstatus Waspada/Buruk atau skor rendah dan perlu tindak lanjut.', 'formula' => 'status Waspada/Buruk atau output < 70', 'source' => 'spk_fuzzy_logs'],
            'Tugas aktif' => ['body' => 'Jumlah tugas tindak lanjut SPK yang masih todo atau in progress.', 'formula' => 'COUNT(status todo/in_progress)', 'source' => 'spk_action_tasks'],
        ];
        $summaryGridColumns = static function ($items): int {
            $count = is_countable($items) ? count($items) : (int) $items;

            return $count <= 5 ? max(1, $count) : min(5, (int) ceil($count / 2));
        };
        $inactiveDashboardPlaceholders = ['HDP', 'HHEP', 'FCR', 'Feed Intake', 'Egg Mass', 'Berat Avg', 'Lingkungan', 'SPK'];
    @endphp

    <div x-data="{
                activeBarn: 0,
                barns: @js($barnEnvironment['barns']),
                searchLog: '',
                fuzzyFilter: 'all',
                fuzzyByBarn: @js($fuzzyByBarn),
                fuzzyProduktivitasByBarn: @js($fuzzyProduktivitasByBarn),
                activeKomoditasId: @js($activeKomoditasId),
                activeJenisTernakId: @js($activeJenisTernakId ?? null),
                evaluationTimeLabel: @js($evaluationTime),
                evaluating: false,
                evalMessage: '',
                evalSuccess: false,
                _spiderChart: null,

                get activeSummary() {
                    return this.barns[this.activeBarn]?.summary ?? {};
                },

                summaryCols(count) {
                    const total = Number(count) || 0;
                    if (total <= 5) return Math.max(1, total);
                    return Math.min(5, Math.ceil(total / 2));
                },

                sensorIconPath(iconKey) {
                    const icons = {
                        sensor: 'M4 17h2m3 0h2m3 0h6M5 7h14M7 7v10m10-10v10M9 11h6m-6 3h6',
                        gauge: 'M12 14l3-3m5 3a8 8 0 11-16 0 8 8 0 0116 0z',
                        air: 'M3 12h12a3 3 0 100-6H9m-6 10h14a2 2 0 110 4h-3',
                        water: 'M12 3l5 6a7 7 0 11-10 0l5-6z',
                        light: 'M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36l-1.41-1.41M7.05 7.05L5.64 5.64m12.72 0l-1.41 1.41M7.05 16.95l-1.41 1.41M16 12a4 4 0 11-8 0 4 4 0 018 0z',
                        alert: 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
                    };
                    return icons[iconKey] || icons.sensor;
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
                    const fuzzy = this.fuzzyByBarn[this.activeFuzzyKey] ?? this.fuzzyByBarn['all'] ?? {};
                    if (Array.isArray(fuzzy.indicators) && fuzzy.indicators.length) return fuzzy.indicators;
                    const prod = this.fuzzyProduktivitasByBarn[this.activeFuzzyKey] ?? this.fuzzyProduktivitasByBarn['all'] ?? {};
                    return prod.indicators ?? @js($produktivitas['indicators']);
                },

                get activeSpider() {
                    const fuzzy = this.fuzzyByBarn[this.activeFuzzyKey] ?? this.fuzzyByBarn['all'] ?? {};
                    if (fuzzy.spider && Array.isArray(fuzzy.spider.labels) && fuzzy.spider.labels.length) return fuzzy.spider;
                    const prod = this.fuzzyProduktivitasByBarn[this.activeFuzzyKey] ?? this.fuzzyProduktivitasByBarn['all'] ?? {};
                    return prod.spider ?? @js($produktivitas['spider']);
                },

                onJenisTernakChange(event) {
                    const url = new URL(window.location.href);
                    const id = event.target.value;
                    id ? url.searchParams.set('jenis_ternak', id) : url.searchParams.delete('jenis_ternak');
                    url.searchParams.delete('komoditas');
                    window.location.href = url.toString();
                },

                barnDetailUrl(id) {
                    if (!id || id === 'no-data') return '#';
                    const url = new URL('{{ url('/peternakan') }}/' + id, window.location.origin);
                    if (this.activeJenisTernakId) url.searchParams.set('jenis_ternak', this.activeJenisTernakId);
                    if (this.activeKomoditasId) url.searchParams.set('komoditas', this.activeKomoditasId);
                    return url.toString();
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
                            body: JSON.stringify({
                                jenis_ternak_id: this.activeJenisTernakId,
                                komoditas_id: this.activeKomoditasId,
                            }),
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
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between mb-2">
            <div class="min-w-0 max-w-4xl">
                <h1 class="text-xl font-bold text-gray-900 tracking-tight sm:text-2xl">Decision Support & Operations</h1>
                <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500 mt-1.5 leading-relaxed">
                    <span class="inline-flex items-center gap-1.5 shrink-0">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="font-medium text-emerald-600">Live monitoring</span>
                    </span>
                    <span class="hidden h-1 w-1 rounded-full bg-gray-300 sm:inline-block"></span>
                    <span class="font-semibold text-gray-700">{{ $activeJenisTernakNama ?? $activeKomoditasNama }}</span>
                    <span class="hidden h-1 w-1 rounded-full bg-gray-300 sm:inline-block"></span>
                    <span>{{ count($barnEnvironment['barns']) }} kandang aktif</span>
                    <span class="hidden h-1 w-1 rounded-full bg-gray-300 sm:inline-block"></span>
                    <span>{{ count($productionLog) }} log produksi</span>
                </p>
            </div>
            <div class="grid w-full grid-cols-1 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-center sm:justify-end">
                <div class="flex w-full items-center gap-2.5 overflow-hidden rounded-xl border border-gray-200 bg-white pr-3 text-sm text-gray-600 shadow-sm focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500 sm:w-auto sm:min-w-[220px]">
                    <div class="pl-3.5 py-2.5 pointer-events-none">
                        <svg class="w-4.5 h-4.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <select @change="onJenisTernakChange($event)"
                        class="w-full min-w-0 cursor-pointer appearance-none border-none bg-transparent py-2.5 pl-1 pr-7 text-sm font-semibold text-gray-700 focus:outline-none sm:w-56">
                        @forelse($jenisTernakOptions as $jenis)
                            <option value="{{ $jenis->id }}" {{ $jenis->id === ($activeJenisTernakId ?? null) ? 'selected' : '' }}>{{ $jenis->nama }}</option>
                        @empty
                            <option value="">Belum ada jenis ternak</option>
                        @endforelse
                    </select>
                </div>
                <div class="flex w-full items-center justify-center gap-2.5 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-600 shadow-sm sm:w-auto">
                    <svg class="w-4.5 h-4.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span class="font-medium">{{ now()->format('m/d/Y') }}</span>
                </div>
            </div>
        </div>

        @if(!($hasJenisTernak ?? $hasKomoditas))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800 shadow-sm">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span class="font-medium">Belum ada data jenis ternak.</span>
            </div>
            <p class="text-xs text-amber-700 mt-1 ml-7">Tambahkan jenis ternak dari mobile agar filter dashboard berfungsi.</p>
        </div>
        @endif

        @if(($hasJenisTernak ?? $hasKomoditas) && !($masterConfigStatus['data_master_configured'] ?? $masterConfigStatus['configured'] ?? false))
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex gap-3">
                        <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z"/></svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-sm font-bold">{{ $masterConfigStatus['title'] ?? 'Belum terhubung Data Master' }}</h2>
                                <span class="rounded-full bg-white/80 px-2.5 py-1 text-[11px] font-bold text-amber-700">
                                    {{ $masterConfigStatus['data_master_environment_count'] ?? $masterConfigStatus['environment_count'] ?? 0 }} lingkungan
                                </span>
                            </div>
                            <p class="mt-1 text-sm leading-relaxed">{{ $masterConfigStatus['message'] ?? 'Lengkapi Data Master sebelum IoT dan Fuzzy SPK dikonfigurasi.' }}</p>
                            @if(!empty($masterConfigStatus['hints']))
                                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                    @foreach($masterConfigStatus['hints'] as $hint)
                                        <span class="rounded-full bg-white/70 px-2.5 py-1 font-semibold">{{ $hint }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                    <a href="{{ $masterConfigStatus['data_master_url'] ?? route('data-master.index') }}"
                       class="inline-flex items-center justify-center rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-700"
                       style="text-decoration:none;">
                        Konfigurasi Data Master
                    </a>
                </div>
            </div>
        @endif

        @if(($hasJenisTernak ?? $hasKomoditas) && ($masterConfigStatus['data_master_configured'] ?? $masterConfigStatus['configured'] ?? false) && !($masterConfigStatus['spk_configured'] ?? false))
            <section class="rounded-xl border border-dashed border-amber-200 bg-white p-5 shadow-sm xl:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-3xl">
                        <h2 class="text-lg font-black text-gray-900">SPK peternakan belum aktif</h2>
                        <p class="mt-1 text-sm leading-relaxed text-gray-500">
                            Parameter produktivitas yang tampil di SPK harus dibuat sebagai variabel input dan diberi sumber data di Pengaturan Fuzzy.
                        </p>
                        @if(!empty($masterConfigStatus['spk_hints']))
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                @foreach($masterConfigStatus['spk_hints'] as $hint)
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 font-semibold text-amber-700">{{ $hint }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <a href="{{ $masterConfigStatus['spk_config_url'] ?? route('settings.fuzzy.index') }}"
                        class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                        style="text-decoration:none;">
                        Buka Pengaturan Fuzzy
                    </a>
                </div>
                <div class="summary-balanced-grid mt-5 grid grid-cols-2 gap-3"
                    style="--summary-cols: {{ $summaryGridColumns($inactiveDashboardPlaceholders) }};">
                    @foreach($inactiveDashboardPlaceholders as $placeholder)
                        <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-4">
                            <div class="h-2 w-12 rounded-full bg-gray-200"></div>
                            <p class="mt-4 text-xs font-bold uppercase tracking-wide text-gray-400">{{ $placeholder }}</p>
                            <p class="mt-2 text-lg font-black text-gray-300">-</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @elseif($hasJenisTernak ?? $hasKomoditas)

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
                                <x-metric-hint title="Status Laporan Harian" body="Banner ini menunjukkan kelengkapan laporan harian per kandang. Jika sebagian kandang belum mengirim laporan, KPI produksi dan hasil SPK bisa belum sepenuhnya merepresentasikan kondisi farm." formula="kandang terlapor / total kandang aktif" source="laporan, unitBudidaya" />
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
        <div class="summary-balanced-grid grid grid-cols-2 gap-3 xl:gap-4"
            style="--summary-cols: {{ $summaryGridColumns($kpiMetrics) }};">
            @forelse($kpiMetrics as $kpi)
                @php
                    $hint = $kpiHints[$kpi['label']] ?? null;
                @endphp
                <x-peternakan.kpi-card
                    :label="$kpi['label']"
                    :value="$kpi['value']"
                    :trend="$kpi['trend']"
                    :hint="$hint['body'] ?? null"
                    :formula="$hint['formula'] ?? null"
                    :source="$hint['source'] ?? null"
                />
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                    <p class="font-bold">Card produktivitas belum aktif dari Pengaturan Fuzzy.</p>
                    <p class="mt-1 text-xs leading-5">Tambahkan variabel input produktivitas dan sumber data seperti HDP, pakan, atau mortalitas agar KPI dashboard tampil dinamis.</p>
                </div>
            @endforelse
        </div>

        {{-- SECTION 2: CHART + BARN ENVIRONMENT --}}
        <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">
            <x-peternakan.performance-chart
                chartId="effChart"
                :labels="$chartData['labels']"
                :hdpData="$chartData['hdp']"
                :fcrData="$chartData['fcr']"
                :chartDataByRange="$chartDataByRange"
                :defaultRange="$chartRange"
                class="xl:col-span-3"
            />

            <div class="xl:col-span-2 bg-white border border-gray-100 rounded-xl p-5 xl:p-6 shadow-sm flex flex-col h-full">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-5">
                    <div class="flex items-center gap-2">
                        <h3 class="text-lg font-semibold text-gray-800">Barn Environment</h3>
                        <x-metric-hint title="Barn Environment" body="Panel ini membaca nilai sensor aktif sesuai kandang yang dipilih. Parameter mengikuti batas IoT dan konfigurasi jenis ternak." formula="latest/average sensor per kandang" source="iot_sensor_data, iot_parameter, unitBudidaya" />
                    </div>
                    <span class="text-xs text-gray-400 bg-gray-50 px-2 py-1 rounded-md">Batas IoT jenis ternak</span>
                </div>

                <div class="summary-balanced-grid mb-5 grid grid-cols-2 gap-2.5"
                    style="--summary-cols: {{ $summaryGridColumns($barnEnvironment['barns'] ?? []) }};">
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
                            class="rounded-lg border px-2.5 py-3 text-center cursor-pointer transition-all {{ $baseColor }}"
                            title="Klik untuk melihat sensor kandang ini">
                            <p class="text-xs font-semibold truncate">{{ $barn['name'] }}</p>
                            @if(isset($barn['display_sensor_value']))
                                <p class="text-lg font-bold mt-0.5">{{ $barn['display_sensor_value'] }}</p>
                                <p class="mt-0.5 truncate text-[10px] font-semibold opacity-75">{{ $barn['display_sensor_label'] ?? 'Sensor' }}</p>
                            @else
                            <p class="text-lg font-bold mt-0.5">{{ $barn['temp'] }}°C</p>
                            @endif
                        </button>
                    @endforeach
                </div>

                <div class="iot-sensor-grid grid gap-3 xl:gap-3.5">
                    <template x-for="sensor in activeSummary.parameters || []" :key="sensor.code || sensor.name || sensor.label">
                        <div class="flex min-w-0 items-center gap-2.5 rounded-lg border px-2.5 py-2.5"
                             :class="{
                                'bg-red-50/90 border border-red-100': sensor.status === 'danger',
                                'bg-amber-50/90 border border-amber-100': sensor.status === 'warning',
                                'border-gray-100 bg-gray-50/50': sensor.status === 'normal' || !sensor.status,
                             }">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-gray-500 shadow-sm">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="sensorIconPath(sensor.iconKey || sensor.icon_key || 'sensor')"></path>
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="break-words text-xs font-medium leading-snug text-gray-500" x-text="sensor.name || sensor.label"></p>
                                <p class="text-sm font-bold"
                                   :class="{ 'text-red-600': sensor.status === 'danger', 'text-amber-600': sensor.status === 'warning', 'text-gray-900': sensor.status === 'normal' || !sensor.status }"
                                   x-text="sensor.valueLabel || '-'"></p>
                                <p x-show="sensor.dataSourceLabel" class="mt-0.5 text-[10px] font-semibold text-gray-400" x-text="sensor.dataSourceLabel"></p>
                            </div>
                            <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                                :class="{
                                    'bg-red-100 text-red-700': sensor.status === 'danger',
                                    'bg-amber-100 text-amber-700': sensor.status === 'warning',
                                    'bg-white text-gray-500': sensor.status === 'normal' || !sensor.status,
                                }"
                                x-text="sensor.statusLabel || (sensor.status === 'danger' ? 'Kritis' : (sensor.status === 'warning' ? 'Cek' : 'OK'))"></span>
                        </div>
                    </template>
                    <div x-show="!(activeSummary.parameters || []).length" class="col-span-full rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                        Parameter lingkungan belum aktif dari Data Master.
                    </div>
                </div>

                <a :href="barnDetailUrl(barns[activeBarn]?.id)"
                    class="mt-auto flex items-center justify-center gap-2 w-full py-3 rounded-lg text-sm font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition-colors"
                    style="text-decoration: none;">
                    Lihat Detail Kandang
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>

        {{-- SECTION 3: DAILY SPK SUMMARY --}}
        @php
            $spkTone = [
                'emerald' => ['wrap' => 'border-emerald-200 bg-emerald-50', 'text' => 'text-emerald-700', 'badge' => 'bg-emerald-100 text-emerald-700'],
                'amber' => ['wrap' => 'border-amber-200 bg-amber-50', 'text' => 'text-amber-700', 'badge' => 'bg-amber-100 text-amber-700'],
                'red' => ['wrap' => 'border-red-200 bg-red-50', 'text' => 'text-red-700', 'badge' => 'bg-red-100 text-red-700'],
                'gray' => ['wrap' => 'border-gray-200 bg-gray-50', 'text' => 'text-gray-700', 'badge' => 'bg-gray-100 text-gray-700'],
            ][$spkDailySummary['tone'] ?? 'gray'];
        @endphp
        <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm xl:p-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-semibold text-gray-800">Ringkasan SPK Hari Ini</h3>
                        <x-metric-hint title="Ringkasan SPK Hari Ini" body="Ringkasan ini mengambil hasil analisis fuzzy hari ini, peringatan SPK, dan tugas aktif yang belum selesai." formula="log SPK hari ini + task aktif" source="spk_fuzzy_logs, spk_action_tasks" />
                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $spkTone['badge'] }}">{{ $spkDailySummary['status'] }}</span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">
                        Dashboard peternakan menampilkan kondisi umum farm. Informasi detail per kandang tetap dibuka dari halaman detail kandang.
                    </p>
                    <p class="mt-2 text-xs font-semibold {{ $spkTone['text'] }}">
                        Update terakhir: {{ $spkDailySummary['last_update'] ?? 'Belum ada update SPK' }}
                        @if(!empty($spkDailySummary['last_update_human']))
                            <span class="font-normal text-gray-400">({{ $spkDailySummary['last_update_human'] }})</span>
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ $spkDailySummary['spk_url'] }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700" style="text-decoration:none;">
                        Buka Analisa SPK
                    </a>
                    <a href="{{ $spkDailySummary['tasks_url'] }}" class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">
                        Lihat Penugasan
                    </a>
                </div>
            </div>

            <div class="mt-4 rounded-lg border {{ ($masterConfigStatus['spk_configured'] ?? false) ? 'border-emerald-100 bg-emerald-50/60' : 'border-amber-100 bg-amber-50/60' }} px-4 py-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-bold text-gray-800">Input aktif dari Pengaturan Fuzzy</p>
                        <p class="mt-0.5 text-xs text-gray-500">Card lingkungan dan produktivitas SPK mengikuti variabel input yang sudah diberi sumber data.</p>
                    </div>
                    <div class="flex flex-wrap gap-1.5 lg:justify-end">
                        @forelse(collect($masterConfigStatus['spk_environment_parameters'] ?? [])->take(5) as $parameter)
                            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-emerald-700">{{ $parameter['name'] ?? $parameter['code'] ?? '-' }}</span>
                        @empty
                            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-amber-700">Lingkungan belum aktif</span>
                        @endforelse
                        @forelse(collect($masterConfigStatus['spk_productivity_parameters'] ?? [])->take(5) as $function)
                            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-sky-700">{{ $function['name'] ?? $function['code'] ?? '-' }}</span>
                        @empty
                            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-amber-700">Produktivitas belum aktif</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-[0.95fr_1.05fr]">
                <div class="rounded-lg border border-gray-100 bg-gray-50/60 p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold text-gray-800">Diagram Produktivitas</p>
                            <p class="text-xs text-gray-500">Radar HDP, feed, umur biologis, dan mortalitas.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-metric-hint title="Diagram Produktivitas" body="Radar menormalisasi beberapa indikator produktivitas ke skala 0-100 agar mudah dibandingkan." formula="normalisasi HDP, feed, umur, mortalitas" source="laporan, panen, harianTernak, kematian" />
                            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-500">SPK</span>
                        </div>
                    </div>
                    <div class="h-64">
                        <canvas x-ref="spiderCanvas"></canvas>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-100 p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold text-gray-800">Indikator Produktivitas</p>
                            <p class="text-xs text-gray-500">Ringkasan parameter yang mempengaruhi keputusan SPK.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-metric-hint title="Indikator Produktivitas" body="Daftar indikator ini dinamis mengikuti variabel aktif pada konfigurasi SPK. Jika variabel baru ditambah, card akan ikut bertambah." formula="variabel aktif pada profil fuzzy" source="spk_fuzzy_variables, spk_fuzzy_input_sources" />
                            <span class="text-xs text-gray-400" x-text="(activeIndicators?.length ?? 0) + ' indikator'"></span>
                        </div>
                    </div>

                    <div class="summary-balanced-grid grid grid-cols-2 gap-2"
                        :style="'--summary-cols: ' + summaryCols((activeIndicators || []).length)">
                        <template x-for="indicator in activeIndicators" :key="indicator.label">
                            <div class="rounded-lg border border-gray-100 bg-white px-3 py-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold text-gray-500" x-text="indicator.label"></p>
                                        <p class="mt-1 text-base font-black text-gray-900" x-text="indicator.value ?? '-'"></p>
                                    </div>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                        :class="{
                                            'bg-emerald-50 text-emerald-700': indicator.color === 'emerald',
                                            'bg-sky-50 text-sky-700': indicator.color === 'blue',
                                            'bg-amber-50 text-amber-700': indicator.color === 'amber',
                                            'bg-red-50 text-red-700': indicator.color === 'red',
                                            'bg-gray-100 text-gray-600': !['emerald', 'blue', 'amber', 'red'].includes(indicator.color),
                                        }"
                                        x-text="indicator.score !== undefined ? indicator.score : '-'"></span>
                                </div>
                                <p class="mt-2 text-xs text-gray-500" x-text="indicator.detail ?? '-'"></p>
                            </div>
                        </template>
                    </div>

                    <div x-show="!activeIndicators || activeIndicators.length === 0" class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                        Belum ada indikator produktivitas aktif.
                    </div>
                </div>
            </div>

            <div class="summary-balanced-grid mt-5 grid grid-cols-2 gap-3"
                style="--summary-cols: {{ $summaryGridColumns(4) }};">
                @foreach([
                    ['label' => 'Skor rata-rata', 'value' => $spkDailySummary['score'] !== null ? $spkDailySummary['score'].'/100' : '-', 'class' => 'text-gray-900'],
                    ['label' => 'Analisa hari ini', 'value' => $spkDailySummary['analyses_today'], 'class' => 'text-gray-900'],
                    ['label' => 'Perlu tindakan', 'value' => $spkDailySummary['needs_action_count'], 'class' => $spkDailySummary['needs_action_count'] > 0 ? 'text-red-600' : 'text-gray-900'],
                    ['label' => 'Tugas aktif', 'value' => $spkDailySummary['active_tasks'], 'class' => $spkDailySummary['active_tasks'] > 0 ? 'text-amber-600' : 'text-gray-900'],
                ] as $metric)
                    @php
                        $hint = $spkMetricHints[$metric['label']];
                    @endphp
                    <div class="rounded-lg border border-gray-100 px-4 py-3">
                        <div class="flex items-center gap-1">
                            <p class="text-xs font-semibold text-gray-400">{{ $metric['label'] }}</p>
                            <x-metric-hint :title="$metric['label']" :body="$hint['body']" :formula="$hint['formula']" :source="$hint['source']" />
                        </div>
                        <p class="mt-1 text-xl font-black {{ $metric['class'] }}">{{ $metric['value'] }}</p>
                    </div>
                @endforeach
            </div>

            @if(!empty($spkDailySummary['hints']))
                <details class="mt-5 rounded-lg border {{ $spkTone['wrap'] }} px-4 py-3" open>
                    <summary class="cursor-pointer text-sm font-bold {{ $spkTone['text'] }}">Hint kesiapan SPK</summary>
                    <ul class="mt-3 space-y-2 text-sm text-gray-700">
                        @foreach($spkDailySummary['hints'] as $hint)
                            <li class="flex gap-2">
                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-current {{ $spkTone['text'] }}"></span>
                                <span>{{ $hint }}</span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            <div class="mt-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h4 class="text-sm font-bold text-gray-800">Tindakan yang Disarankan</h4>
                    <div class="flex items-center gap-2">
                        <x-metric-hint title="Tindakan yang Disarankan" body="Kandidat tindakan berasal dari hasil SPK yang perlu ditindaklanjuti dan dapat dilanjutkan menjadi penugasan." source="spk_fuzzy_logs, spk_action_tasks" />
                        <span class="text-xs text-gray-400">{{ count($spkDailySummary['action_candidates']) }} kandidat</span>
                    </div>
                </div>

                @if(empty($spkDailySummary['action_candidates']))
                    <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                        Tidak ada hasil SPK yang membutuhkan penugasan saat ini.
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-3 xl:grid-cols-2">
                        @foreach($spkDailySummary['action_candidates'] as $candidate)
                            <article class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700">{{ $candidate['priority'] }}</span>
                                            <span class="text-xs text-gray-400">{{ $candidate['barn'] }} - {{ $candidate['time'] }}</span>
                                        </div>
                                        <h5 class="mt-2 font-bold text-gray-900">{{ $candidate['title'] }}</h5>
                                        <p class="mt-1 text-sm text-gray-500">{{ $candidate['description'] }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-lg bg-gray-50 px-3 py-2 text-sm font-black text-gray-800">{{ $candidate['score'] }}</span>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <a href="{{ $candidate['spk_url'] }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">Detail SPK</a>
                                    <a href="{{ $candidate['task_url'] }}" class="rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-800" style="text-decoration:none;">Buat Penugasan</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- SECTION 4: DAFTAR KANDANG --}}
        <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-gray-50 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Daftar Kandang (Unit Budidaya)</h3>
                    <p class="text-sm text-gray-500 mt-1">Jenis ternak: <span class="font-medium text-gray-700">{{ $activeJenisTernakNama ?? $activeKomoditasNama }}</span> - klik kartu untuk membuka halaman detail</p>
                </div>
                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <x-metric-hint title="Daftar Kandang" body="Setiap kartu kandang menampilkan status lingkungan dan HDP hari ini sebagai pintu masuk ke detail kandang." formula="status terburuk sensor + HDP hari ini" source="iot_sensor_data, panen, unitBudidaya" />
                    <span class="text-xs font-semibold text-gray-600 bg-gray-100 px-3 py-1.5 rounded-full">{{ count($listKandang) }} kandang</span>
                </div>
            </div>
            <div class="space-y-3.5 p-4 sm:p-6">
                @forelse($listKandang as $kandang)
                    @php
                        $cfg = match($kandang['status']) {
                            'danger' => ['dot' => 'bg-red-500', 'badge' => 'bg-red-50 text-red-700 border-red-200'],
                            'warning' => ['dot' => 'bg-amber-500', 'badge' => 'bg-amber-50 text-amber-700 border-amber-200'],
                            default => ['dot' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                        };
                    @endphp
                    <a href="{{ route('peternakan.show', array_filter(['id' => $kandang['id'], 'jenis_ternak' => $activeJenisTernakId ?? null, 'komoditas' => $activeKomoditasId])) }}"
                       class="group flex flex-col gap-4 border border-gray-100 rounded-xl px-5 py-4 hover:border-emerald-300 hover:bg-emerald-50/50 hover:shadow-sm transition-all sm:flex-row sm:items-center sm:justify-between"
                       style="text-decoration: none;">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="relative h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-gray-100 bg-gray-100">
                                <img src="{{ $kandang['photo'] ?? asset('images/barn-placeholder.jpg') }}"
                                    alt="{{ $kandang['nama'] }}"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                    onerror="this.onerror=null;this.src='{{ $kandang['photoFallback'] ?? asset('images/barn-placeholder.jpg') }}';">
                                <span class="absolute bottom-1 right-1 h-2.5 w-2.5 rounded-full border-2 border-white {{ $cfg['dot'] }}"></span>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <h4 class="font-semibold text-gray-800 text-base group-hover:text-emerald-700">{{ $kandang['nama'] }}</h4>
                                    <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full border {{ $cfg['badge'] }}">{{ $statusLabels[$kandang['status']] ?? 'Aman' }}</span>
                                </div>
                                <p class="text-sm text-gray-500 mt-1.5">
                                    {{ number_format((float)($kandang['jumlah'] ?? 0), 0, ',', '.') }} ekor
                                    @if($kandang['kapasitas']) <span class="mx-1.5 text-gray-300">/</span> kapasitas {{ $kandang['kapasitas'] }} @endif
                                    @if($kandang['lokasi']) <span class="mx-1.5 text-gray-300">/</span> {{ $kandang['lokasi'] }} @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex w-full items-center justify-between gap-5 shrink-0 text-right sm:w-auto sm:justify-end">
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
                        <p class="text-sm text-gray-500">Belum ada kandang aktif untuk jenis ternak ini.</p>
                        <p class="text-xs text-gray-400 mt-1.5">Tambahkan unit budidaya di Data Master.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- SECTION 5: DAILY PRODUCTION LOG --}}
        <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-50 p-4 sm:p-6">
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <h3 class="text-lg font-semibold text-gray-800">Daily Production Log</h3>
                    <x-metric-hint title="Daily Production Log" body="Log ini menampilkan laporan panen dan kematian terbaru. Rejects hanya menghitung telur rusak/reject dari rincian grade, sedangkan mortalitas ditampilkan terpisah." source="laporan, panen, panenRincianGrade, kematian, unitBudidaya" />
                </div>
                <div class="flex min-w-[220px] flex-1 items-center gap-3 sm:flex-none">
                    <div class="relative w-full sm:w-64">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4.5 h-4.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input x-model="searchLog" type="text" placeholder="Cari log..." class="w-full rounded-lg border border-gray-200 py-2.5 pl-10 pr-4 text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50/90">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 sm:px-6 sm:py-4">Date</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 sm:px-6 sm:py-4">Barn</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 sm:px-6 sm:py-4">Flock Age</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 sm:px-6 sm:py-4">Birds</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 sm:px-6 sm:py-4">Eggs Collected</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 sm:px-6 sm:py-4">Rejects</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 sm:px-6 sm:py-4">Mortalitas</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 sm:px-6 sm:py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($productionLog as $log)
                            @php
                                $logStatus = ['Optimal' => 'text-emerald-600 bg-emerald-50', 'Check' => 'text-sky-600 bg-sky-50', 'Attention' => 'text-amber-600 bg-amber-50', 'Critical' => 'text-red-600 bg-red-50'];
                                $statusClass = $logStatus[$log['status']] ?? 'text-gray-600 bg-gray-50';
                                $searchHay = strtolower(($log['date'] ?? '') . ($log['barn'] ?? '') . ($log['status'] ?? ''));
                            @endphp
                            <tr class="hover:bg-gray-50/70 transition-colors" x-show="!searchLog || @js($searchHay).includes(searchLog.toLowerCase())">
                                <td class="px-4 py-3 text-gray-600 font-medium sm:px-6 sm:py-4">{{ $log['date'] }}</td>
                                <td class="px-4 py-3 font-semibold text-blue-600 sm:px-6 sm:py-4">{{ $log['barn'] }}</td>
                                <td class="px-4 py-3 text-gray-600 sm:px-6 sm:py-4">{{ $log['flock_age'] }}</td>
                                <td class="px-4 py-3 text-gray-800 font-semibold sm:px-6 sm:py-4">{{ $log['birds'] }}</td>
                                <td class="px-4 py-3 text-gray-800 font-semibold sm:px-6 sm:py-4">{{ $log['eggs'] }}</td>
                                <td class="px-4 py-3 text-gray-600 sm:px-6 sm:py-4">{{ $log['rejects'] }}</td>
                                <td class="px-4 py-3 text-gray-600 sm:px-6 sm:py-4">{{ $log['mortality'] ?? '-' }}</td>
                                <td class="px-4 py-3 sm:px-6 sm:py-4"><span class="px-2.5 py-1 text-xs font-semibold rounded-full sm:px-3 sm:py-1.5 {{ $statusClass }}">{{ $log['status'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t border-gray-50 px-4 py-3 sm:px-6 sm:py-4">
                <p class="text-sm text-gray-500">Menampilkan {{ count($productionLog) }} entri terakhir</p>
            </div>
        </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush
