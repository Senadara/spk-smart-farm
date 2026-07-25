@extends('layouts.app')

@section('title', 'Analisa SPK')
@section('breadcrumb', 'Analisa SPK')

@section('content')
    @php
        $toneClasses = [
            'emerald' => 'border-emerald-100 bg-emerald-50 text-emerald-800',
            'blue' => 'border-blue-100 bg-blue-50 text-blue-800',
            'amber' => 'border-amber-100 bg-amber-50 text-amber-800',
            'red' => 'border-red-100 bg-red-50 text-red-800',
            'gray' => 'border-gray-100 bg-gray-50 text-gray-700',
        ];
        $taskUrl = $selectedLog ? route('spk.tasks.index', [
            'create_task' => 1,
            'spk_id' => $selectedLog->id,
            'coop_id' => $selectedLog->unit_budidaya_id,
            'title' => 'Tindak lanjut SPK - ' . ($activeHistory['barn'] ?? 'Kandang'),
            'desc' => data_get($fuzzyData, 'results.gabungan.recommendation') ?: data_get($fuzzyData, 'results.gabungan.description'),
        ]) : null;
        $isConfigured = (bool) data_get($masterConfigStatus, 'spk_configured', false);
    @endphp

    <div x-data="spkDashboard()" class="max-w-full space-y-5">
        <section class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold text-gray-950">Analisa SPK Fuzzy Mamdani</h1>
                    <p class="mt-1 text-sm text-gray-500">Pantau diagnosis kandang, kualitas input, riwayat evaluasi, dan tindak lanjut operasional.</p>
                </div>

                <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end lg:justify-end">
                    <form id="spkFilterForm" method="GET" action="{{ route('spk.dashboard') }}" class="grid grid-cols-1 gap-2 rounded-xl border border-gray-100 bg-gray-50/80 p-2 sm:grid-cols-2 lg:flex lg:flex-wrap lg:justify-end">
                        <label class="min-w-[190px] text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            Jenis ternak
                            <select name="jenis_budidaya_id" class="mt-1 w-full cursor-pointer rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm font-medium normal-case tracking-normal text-gray-800 shadow-sm transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-50" onchange="document.getElementById('spkFilterForm').submit()">
                                @foreach ($filterOptions['jenis_ternak'] as $val => $label)
                                    <option value="{{ $val }}" {{ (string) $jenisBudidayaId === (string) $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="min-w-[200px] text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            Kandang
                            <select name="coop_id" class="mt-1 w-full cursor-pointer rounded-lg border border-sky-100 bg-white px-3 py-2 text-sm font-medium normal-case tracking-normal text-gray-800 shadow-sm transition focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-50" onchange="document.getElementById('spkFilterForm').submit()">
                                @foreach ($barnsOption as $barn)
                                    <option value="{{ $barn['id'] }}" {{ (string) $coopId === (string) $barn['id'] ? 'selected' : '' }}>{{ $barn['name'] }}</option>
                                @endforeach
                            </select>
                        </label>
                    </form>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('spk.simulation.index') }}" class="inline-flex items-center justify-center rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100" style="text-decoration:none;">
                            Simulasi
                        </a>
                        <button type="button" @click="runFullEvaluation()" :disabled="evaluating || !@js($isConfigured)"
                            class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <svg x-show="!evaluating" class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168 11.555 9.036A1 1 0 0 0 10 9.87v4.263a1 1 0 0 0 1.555.832l3.197-2.132a1 1 0 0 0 0-1.664z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12A9 9 0 1 1 3 12a9 9 0 0 1 18 0z" />
                            </svg>
                            <svg x-show="evaluating" class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                            </svg>
                            <span x-text="evaluating ? 'Mengevaluasi...' : (@js($coopId) ? 'Evaluasi Kandang' : 'Evaluasi Semua')"></span>
                        </button>
                    </div>
                </div>
            </div>

            <p x-show="evalMessage" class="mt-4 rounded-lg border px-3 py-2 text-sm font-medium"
                :class="evalSuccess ? 'border-emerald-100 bg-emerald-50 text-emerald-700' : 'border-red-100 bg-red-50 text-red-700'"
                x-text="evalMessage"></p>
        </section>

        @if(! $isConfigured)
            <section class="rounded-xl border border-amber-100 bg-amber-50 p-4 text-amber-900 shadow-sm sm:p-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-base font-semibold">{{ data_get($masterConfigStatus, 'spk_title', 'Konfigurasi SPK belum lengkap') }}</h2>
                        <p class="mt-1 text-sm leading-6">{{ data_get($masterConfigStatus, 'spk_message', 'Lengkapi Data Master dan Pengaturan Fuzzy sebelum menjalankan Analisa SPK.') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ data_get($masterConfigStatus, 'data_master_url', '#') }}" class="inline-flex items-center justify-center rounded-lg border border-white/70 bg-white px-3 py-2 text-sm font-semibold text-amber-800" style="text-decoration:none;">Data Master</a>
                        <a href="{{ data_get($masterConfigStatus, 'spk_config_url', '#') }}" class="inline-flex items-center justify-center rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white" style="text-decoration:none;">Pengaturan SPK</a>
                    </div>
                </div>
            </section>
        @endif

        @if($kpi)
            <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                @foreach($kpi as $item)
                    @php $tone = $item['tone'] ?? 'gray'; @endphp
                    <div class="rounded-xl border p-4 shadow-sm {{ $toneClasses[$tone] ?? $toneClasses['gray'] }}">
                        <p class="text-xs font-medium opacity-80">{{ $item['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-950">{{ $item['value'] }}</p>
                    </div>
                @endforeach
            </section>
        @endif

        @if($overviewCards)
            <section class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950">Overview SPK Per Kandang</h2>
                        <p class="text-sm text-gray-500">Mode semua kandang menampilkan status masing-masing kandang tanpa menggabungkan diagnosis.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach($overviewCards as $card)
                        @php $tone = $card['tone'] ?? 'gray'; @endphp
                        <a href="{{ $card['url'] }}" class="rounded-xl border p-4 transition hover:-translate-y-0.5 hover:shadow-md {{ $toneClasses[$tone] ?? $toneClasses['gray'] }}" style="text-decoration:none;">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-950" title="{{ $card['name'] }}">{{ $card['name'] }}</p>
                                    <p class="mt-1 text-xs opacity-75">{{ $card['time'] }}</p>
                                </div>
                                <span class="rounded-lg border border-white/70 bg-white/80 px-2 py-1 text-xs font-semibold text-gray-700">{{ $card['score'] !== null ? number_format((float) $card['score'], 1) : '-' }}</span>
                            </div>
                            <p class="mt-3 text-sm font-medium text-gray-800">{{ $card['status'] }}</p>
                            <p class="mt-1 line-clamp-2 text-xs leading-5 opacity-80">{{ $card['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <x-fuzzy-decision-engine
            :indicators="$fuzzyData['indicators']"
            :spider="$fuzzyData['spider']"
            :spkResults="$fuzzyData['results']"
            :environmentSensors="$fuzzyData['sensors']['lingkungan']"
            :evaluationTime="$activeHistory['date'] . ', ' . $activeHistory['time']"
            :masterConfigStatus="$masterConfigStatus"
            :inputQuality="$inputQuality"
            :calculationDetail="$calculationDetail"
            :selectedLog="$selectedLog"
            :canCreateTask="$canCreateTask"
            :taskUrl="$taskUrl"
        />

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-12">
            <div class="space-y-5 xl:col-span-8">
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="mb-4">
                            <h2 class="text-sm font-semibold text-gray-950">Tren HDP vs Standar</h2>
                            <p class="text-xs text-gray-500">Ditampilkan jika parameter HDP tersedia pada log kandang.</p>
                        </div>
                        @if($chartData['hasHdp'])
                            <div class="h-[230px]">
                                <canvas x-ref="hdpCanvas"></canvas>
                            </div>
                        @else
                            <div class="flex h-[230px] items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 text-center text-sm text-gray-500">
                                Parameter HDP belum tersedia untuk grafik ini.
                            </div>
                        @endif
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="mb-4">
                            <h2 class="text-sm font-semibold text-gray-950">Tren Parameter Lingkungan</h2>
                            <p class="text-xs text-gray-500">Mengikuti parameter lingkungan aktif pada konfigurasi SPK.</p>
                        </div>
                        @if($chartData['hasEnvironment'])
                            <div class="h-[230px]">
                                <canvas x-ref="environmentCanvas"></canvas>
                            </div>
                        @else
                            <div class="flex h-[230px] items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 text-center text-sm text-gray-500">
                                Pilih kandang dengan log SPK untuk melihat tren lingkungan.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-950">Tindak Lanjut Hasil SPK</h2>
                            <p class="text-xs text-gray-500">Tugas yang dibuat dari log SPK terpilih.</p>
                        </div>
                        @if($selectedLog)
                            <a href="{{ route('spk.tasks.index', ['spk_id' => $selectedLog->id]) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">Lihat Penugasan</a>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @forelse($actionTickets as $ticket)
                            <a href="{{ route('spk.tasks.show', $ticket['id']) }}" class="rounded-lg border border-gray-100 bg-gray-50 p-3 transition hover:border-emerald-100 hover:bg-emerald-50" style="text-decoration:none;">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $ticket['title'] }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $ticket['assignee'] }}</p>
                                    </div>
                                    <span class="rounded-full bg-white px-2 py-1 text-[10px] font-semibold text-gray-600">{{ $ticket['status'] }}</span>
                                </div>
                                <p class="mt-2 text-[11px] font-medium text-gray-500">#{{ $ticket['code'] }} - {{ $ticket['priority'] }}</p>
                            </a>
                        @empty
                            <div class="md:col-span-2 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-5 text-sm text-gray-500">
                                Belum ada tugas yang terhubung dengan hasil SPK ini.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <aside class="xl:col-span-4">
                <div class="sticky top-4 rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-sm font-semibold text-gray-950">Riwayat Analisa</h2>
                            <button type="button" @click="historyDate = ''; historySearch = ''" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                                Reset
                            </button>
                        </div>
                        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <input x-model="historyDate" type="date" class="rounded-lg border border-gray-200 px-3 py-2 text-xs text-gray-600 focus:border-emerald-400 focus:outline-none">
                            <input x-model="historySearch" type="text" placeholder="Cari kandang/status" class="rounded-lg border border-gray-200 px-3 py-2 text-xs text-gray-600 focus:border-emerald-400 focus:outline-none">
                        </div>
                    </div>
                    <div class="max-h-[520px] overflow-y-auto p-3">
                        @foreach($spkHistory as $hist)
                            @php
                                $isActive = ($activeHistory['id'] ?? null) === $hist['id'];
                                $tone = $hist['color'] ?? 'gray';
                                $url = $hist['id'] !== 'N/A'
                                    ? route('spk.dashboard', array_filter([
                                        'jenis_budidaya_id' => $jenisBudidayaId,
                                        'coop_id' => $coopId,
                                        'history_id' => $hist['id'],
                                    ]))
                                    : '#';
                            @endphp
                            <a href="{{ $url }}"
                               x-show="historyMatches(@js($hist['search'] ?? ''), @js($hist['dateKey'] ?? ''))"
                               class="mb-2 block rounded-lg border p-3 transition {{ $isActive ? 'border-gray-900 bg-gray-900 text-white' : (($toneClasses[$tone] ?? $toneClasses['gray']) . ' hover:shadow-sm') }}"
                               style="text-decoration:none;">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="truncate text-xs font-semibold">{{ $hist['barn'] }}</p>
                                    <span class="text-[10px] opacity-75">{{ $hist['time'] }}</span>
                                </div>
                                <p class="mt-2 text-sm font-semibold">{{ $hist['status'] }}</p>
                                <p class="mt-1 line-clamp-2 text-xs leading-5 opacity-80">{{ $hist['verdict'] }}</p>
                                @if(($hist['id'] ?? 'N/A') !== 'N/A')
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach(($hist['raw'] ?? []) as $key => $value)
                                            @if($loop->index < 4)
                                                <span class="rounded-full border border-white/30 bg-white/30 px-1.5 py-0.5 text-[10px]">{{ strtoupper($key) }}: {{ $value }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </aside>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        window.spkDashboard = function () {
            return {
                evaluating: false,
                evalMessage: '',
                evalSuccess: false,
                historySearch: '',
                historyDate: '',
                _hdpChart: null,
                _environmentChart: null,
                _spiderChart: null,
                historyMatches(searchText, dateKey) {
                    const q = (this.historySearch || '').toLowerCase().trim();
                    const dateOk = !this.historyDate || dateKey === this.historyDate;
                    const searchOk = !q || (searchText || '').toLowerCase().includes(q);
                    return dateOk && searchOk;
                },
                async runFullEvaluation() {
                    this.evaluating = true;
                    this.evalMessage = '';

                    try {
                        const res = await fetch(@js(route('spk.dashboard.evaluate')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                jenis_budidaya_id: @js($jenisBudidayaId),
                                komoditas: @js($activeKomoditasId),
                                coop_id: @js($coopId),
                            }),
                        });
                        const data = await res.json();
                        this.evalSuccess = Boolean(data.success);
                        this.evalMessage = data.success
                            ? 'Evaluasi selesai. ' + data.processed + ' log SPK tersimpan.'
                            : 'Evaluasi belum berhasil. Periksa data input dan konfigurasi.';
                        setTimeout(() => window.location.reload(), data.success ? 900 : 1800);
                    } catch (e) {
                        this.evalSuccess = false;
                        this.evalMessage = 'Gagal menjalankan evaluasi SPK.';
                    } finally {
                        this.evaluating = false;
                    }
                },
                init() {
                    if (typeof Chart === 'undefined') {
                        this.removeRootCloak();
                        return;
                    }

                    this.removeRootCloak();
                    this.$nextTick(() => {
                        this.renderSpiderChart();
                        this.renderHdpChart();
                        this.renderEnvironmentChart();
                    });
                },
                removeRootCloak() {
                    this.$root?.removeAttribute('x-cloak');
                },
                chartOptions() {
                    return {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: { usePointStyle: true, boxWidth: 6, font: { size: 10, family: 'Inter' } },
                            },
                            tooltip: {
                                titleFont: { size: 11, family: 'Inter' },
                                bodyFont: { size: 11, family: 'Inter' },
                            },
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 10, family: 'Inter' }, color: '#94A3B8' } },
                            y: { grid: { color: 'rgba(148, 163, 184, 0.18)' }, ticks: { font: { size: 10, family: 'Inter' }, color: '#94A3B8' } },
                        },
                    };
                },
                renderSpiderChart() {
                    if (typeof Chart === 'undefined') {
                        return;
                    }
                    const ctx = this.$refs.spiderCanvas;
                    if (!ctx) return;
                    if (this._spiderChart) this._spiderChart.destroy();

                    const spider = @js($fuzzyData['spider']);
                    const color = @js($fuzzyData['color'] ?? 'emerald');
                    const colorMap = {
                        emerald: ['#059669', 'rgba(5, 150, 105, 0.18)'],
                        blue: ['#2563EB', 'rgba(37, 99, 235, 0.16)'],
                        amber: ['#D97706', 'rgba(217, 119, 6, 0.18)'],
                        red: ['#DC2626', 'rgba(220, 38, 38, 0.16)'],
                        gray: ['#64748B', 'rgba(100, 116, 139, 0.14)'],
                    };
                    const [stroke, fill] = colorMap[color] || colorMap.emerald;

                    this._spiderChart = new Chart(ctx, {
                        type: 'radar',
                        data: {
                            labels: spider.labels || [],
                            datasets: [{
                                label: 'Status (%)',
                                data: spider.values || [],
                                backgroundColor: fill,
                                borderColor: stroke,
                                pointBackgroundColor: stroke,
                                pointBorderColor: '#fff',
                                borderWidth: 1.8,
                                pointRadius: 2,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                r: {
                                    min: 0,
                                    max: 100,
                                    ticks: { display: false, stepSize: 25 },
                                    angleLines: { color: 'rgba(148, 163, 184, 0.22)' },
                                    grid: { color: 'rgba(148, 163, 184, 0.22)' },
                                    pointLabels: { font: { size: 10, family: 'Inter' }, color: '#64748B' },
                                },
                            },
                        },
                    });
                },
                renderHdpChart() {
                    if (typeof Chart === 'undefined') {
                        return;
                    }
                    const ctx = this.$refs.hdpCanvas;
                    if (!ctx) return;
                    if (this._hdpChart) this._hdpChart.destroy();
                    const data = @js($chartData['hdpComparison']);

                    this._hdpChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [
                                {
                                    label: 'Aktual (%)',
                                    data: data.actual,
                                    borderColor: '#059669',
                                    backgroundColor: '#059669',
                                    borderWidth: 2,
                                    tension: 0.35,
                                    pointRadius: 2,
                                    fill: false,
                                },
                                {
                                    label: 'Standar (%)',
                                    data: data.standard,
                                    borderColor: '#94A3B8',
                                    borderDash: [5, 5],
                                    borderWidth: 2,
                                    tension: 0.35,
                                    pointRadius: 0,
                                    fill: false,
                                },
                            ],
                        },
                        options: this.chartOptions()
                    });
                },
                renderEnvironmentChart() {
                    if (typeof Chart === 'undefined') {
                        return;
                    }
                    const ctx = this.$refs.environmentCanvas;
                    if (!ctx) return;
                    if (this._environmentChart) this._environmentChart.destroy();
                    const data = @js($chartData['environment']);

                    this._environmentChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: (data.series || []).map((serie) => ({
                                label: serie.label,
                                data: serie.data,
                                borderColor: serie.color,
                                backgroundColor: serie.color,
                                borderWidth: 2,
                                tension: 0.35,
                                pointRadius: 2,
                                fill: false,
                            })),
                        },
                        options: this.chartOptions()
                    });
                },
            };
        };

        if (window.Alpine) {
            window.Alpine.data('spkDashboard', window.spkDashboard);
        } else {
            document.addEventListener('alpine:init', () => {
                Alpine.data('spkDashboard', window.spkDashboard);
            });
        }
    </script>
@endpush
