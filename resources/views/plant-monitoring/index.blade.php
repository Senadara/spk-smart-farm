@extends('layouts.app')

@section('title', 'Plant Monitoring')
@section('breadcrumb', 'Plant Monitoring')

@section('content')
    <div class="max-w-full space-y-5">

        {{-- ═══════════ SECTION A: Header + Filter ═══════════ --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-2">
            <div>
                <h1 class="text-2xl font-bold text-[var(--color-gray-900)] tracking-tight">Pemantauan Kondisi Perkebunan</h1>
                <p class="text-sm text-[var(--color-gray-500)] mt-1">
                    Data operasional harian & kondisi lingkungan per blok kebun
                </p>
            </div>

            {{-- Filter Bar --}}
            <form method="GET" action="{{ route('plant-monitoring.index') }}"
                class="flex items-center gap-2 bg-white p-1.5 rounded-xl border border-[var(--color-gray-200)] shadow-sm">
                <div class="flex items-center pl-3 pr-2 border-r border-[var(--color-gray-100)]">
                    <i data-lucide="filter" class="w-4 h-4 text-[var(--color-gray-400)] mr-2"></i>
                    <span class="text-xs font-medium text-[var(--color-gray-500)] uppercase tracking-wider">Filter:</span>
                </div>

                <select name="blok_kebun_id"
                    class="px-3 py-1.5 text-sm border-none bg-transparent text-[var(--color-gray-700)] font-medium focus:ring-0 cursor-pointer outline-none">
                    <option value="">Semua Blok</option>
                    @foreach ($blokKebun as $blok)
                        <option value="{{ $blok->id }}" {{ $selectedBlokId == $blok->id ? 'selected' : '' }}>
                            {{ $blok->nama }}
                        </option>
                    @endforeach
                </select>

                <div class="w-px h-5 bg-[var(--color-gray-200)]"></div>

                <select name="periode"
                    class="px-3 py-1.5 text-sm border-none bg-transparent text-[var(--color-gray-700)] font-medium focus:ring-0 cursor-pointer outline-none">
                    <option value="7" {{ $periode == '7' ? 'selected' : '' }}>7 Hari</option>
                    <option value="14" {{ $periode == '14' ? 'selected' : '' }}>14 Hari</option>
                    <option value="30" {{ $periode == '30' ? 'selected' : '' }}>30 Hari</option>
                </select>

                <button type="submit"
                    class="p-1.5 bg-emerald-50 text-emerald-600 rounded-lg hover:bg-emerald-100 transition-colors tooltip"
                    title="Terapkan Filter">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </button>
            </form>
        </div>

        {{-- ═══════════ SECTION B: Sensor Cards ═══════════ --}}
        @if (!empty($sensorData))
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($sensorData as $sensor)
                    <x-plant-monitoring.sensor-card :sensor="$sensor" />
                @endforeach
            </div>
        @else
            <div class="bg-white rounded-xl shadow-sm border border-[var(--color-gray-100)] p-12">
                <div class="text-center">
                    <div
                        class="w-16 h-16 rounded-full bg-emerald-50 flex items-center justify-center mx-auto mb-4 text-emerald-500">
                        <i data-lucide="radio-tower" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-lg font-bold text-[var(--color-gray-900)] mb-1">Belum ada data sensor</h3>
                    <p class="text-sm text-[var(--color-gray-500)] max-w-sm mx-auto">Data pemantauan sensor per blok akan
                        muncul setelah integrasi perangkat IoT Antares aktif.</p>
                </div>
            </div>
        @endif

        {{-- ═══════════ SECTION C: Grafik Tren Historis Sensor ═══════════ --}}
        <div class="bg-white rounded-xl shadow-sm border border-[var(--color-gray-100)] p-6">
            <div>
                <div
                    class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4 border-b border-[var(--color-gray-100)] pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                            <i data-lucide="line-chart" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-[var(--color-gray-900)]">Tren Sensor</h3>
                            <p class="text-xs text-[var(--color-gray-500)]">Grafik historis keseluruhan parameter</p>
                        </div>
                    </div>

                    {{-- Custom Section C Filter (Visual) --}}
                    <div class="flex items-center gap-3">
                        {{-- Dropdown Blok --}}
                        <div
                            class="flex items-center gap-2 bg-[var(--color-gray-50)] px-3 py-1.5 rounded-lg border border-[var(--color-gray-200)]">
                            <i data-lucide="map-pin" class="w-4 h-4 text-[var(--color-gray-400)]"></i>
                            <span class="text-sm font-semibold text-[var(--color-gray-700)]">
                                {{ $selectedBlokId ? collect($blokKebun)->firstWhere('id', $selectedBlokId)->nama ?? 'Greenhouse' : 'Semua Blok' }}
                            </span>
                        </div>

                        {{-- Toggle Periode Removed --}}
                    </div>
                </div>

                <div class="relative w-full" style="height: 320px;">
                    <canvas id="sensorTrendChart"></canvas>
                </div>

                <div
                    class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 mt-6 pt-4 border-t border-[var(--color-gray-50)]">
                    <span class="inline-flex items-center gap-2 text-xs font-medium text-[var(--color-gray-600)]">
                        <span class="w-3 h-3 rounded-sm bg-emerald-500"></span> pH
                    </span>
                    <span class="inline-flex items-center gap-2 text-xs font-medium text-[var(--color-gray-600)]">
                        <span class="w-3 h-3 rounded-sm bg-blue-500"></span> EC
                    </span>
                    <span class="inline-flex items-center gap-2 text-xs font-medium text-[var(--color-gray-600)]">
                        <span class="w-3 h-3 rounded-sm bg-amber-500"></span> Suhu
                    </span>
                    <span class="inline-flex items-center gap-2 text-xs font-medium text-[var(--color-gray-600)]">
                        <span class="w-3 h-3 rounded-sm bg-cyan-500"></span> Kelembaban
                    </span>
                    <span class="inline-flex items-center gap-2 text-xs font-medium text-[var(--color-gray-600)]">
                        <span class="w-3 h-3 rounded-sm bg-indigo-500"></span> Nitrogen
                    </span>
                    <span class="inline-flex items-center gap-2 text-xs font-medium text-[var(--color-gray-600)]">
                        <span class="w-3 h-3 rounded-sm bg-fuchsia-500"></span> Fosfor
                    </span>
                    <span class="inline-flex items-center gap-2 text-xs font-medium text-[var(--color-gray-600)]">
                        <span class="w-3 h-3 rounded-sm bg-pink-500"></span> Kalium
                    </span>
                    <span
                        class="inline-flex items-center gap-2 text-xs font-medium text-[var(--color-gray-500)] ml-2 pl-4 border-l border-[var(--color-gray-200)]">
                        <span class="w-4 h-0.5 border-t border-dashed border-red-400"></span> Amang Batas (Threshold)
                    </span>
                </div>
            </div>
        </div>

        {{-- ═══════════ SECTION D: Laporan Harian ═══════════ --}}
        <div class="bg-white rounded-xl shadow-sm border border-[var(--color-gray-100)] flex flex-col overflow-hidden">
            <div class="p-6 border-b border-[var(--color-gray-100)]">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-[var(--color-gray-900)]">Laporan Harian Terbaru</h3>
                        <p class="text-xs text-[var(--color-gray-500)] mt-0.5">Riwayat observasi visual dari perangkat
                            mobile</p>
                    </div>
                    <div class="px-3 py-1 bg-emerald-50 text-emerald-600 rounded-full text-xs font-semibold">
                        {{ count($dailyReports) }} Laporan
                    </div>
                </div>
            </div>

            @if (!empty($dailyReports))
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[var(--color-gray-50)] border-b border-[var(--color-gray-100)]">
                                <th
                                    class="px-6 py-4 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Tanggal</th>
                                <th
                                    class="px-6 py-4 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Blok</th>
                                <th
                                    class="px-6 py-4 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider text-right">
                                    Tinggi (cm)</th>
                                <th
                                    class="px-6 py-4 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Kondisi Daun</th>
                                <th
                                    class="px-6 py-4 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider text-center">
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-gray-100)]">
                            @foreach ($dailyReports as $report)
                                @php
                                    $kondisiConfig = [
                                        'sehat' => ['color' => 'green', 'label' => 'Sehat', 'icon' => 'check-circle-2'],
                                        'kuning' => [
                                            'color' => 'amber',
                                            'label' => 'Kuning',
                                            'icon' => 'alert-triangle',
                                        ],
                                        'layu' => ['color' => 'red', 'label' => 'Layu', 'icon' => 'x-circle'],
                                        'bercak' => ['color' => 'amber', 'label' => 'Bercak', 'icon' => 'bug'],
                                    ];
                                    $kc = $kondisiConfig[$report['kondisiDaun'] ?? 'sehat'] ?? $kondisiConfig['sehat'];
                                @endphp
                                <tr class="hover:bg-[var(--color-gray-50)]/50 transition-colors group">
                                    <td class="px-6 py-4 text-sm font-medium text-[var(--color-gray-600)]">
                                        {{ \Carbon\Carbon::parse($report['tanggal'])->translatedFormat('d M Y') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm font-bold text-[var(--color-gray-900)]">
                                        {{ $report['namaBlok'] }}
                                    </td>
                                    <td class="px-6 py-4 text-sm font-semibold text-[var(--color-gray-900)] text-right">
                                        {{ number_format($report['tinggiTanaman'], 1) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <x-badge :color="$kc['color']" class="gap-1.5 px-2.5 py-1">
                                            <i data-lucide="{{ $kc['icon'] }}" class="w-3.5 h-3.5"></i>
                                            {{ $kc['label'] }}
                                        </x-badge>
                                    </td>
                                    {{-- TODO: saat ini tidak ada action, next update action ketika icon berikut di klik --}}
                                    <td class="px-6 py-4 text-center">
                                        <button
                                            class="w-8 h-8 rounded-lg flex items-center justify-center mx-auto text-[var(--color-gray-400)] hover:bg-emerald-50 hover:text-emerald-600 transition-colors tooltip"
                                            title="{{ $report['catatan'] }}">
                                            <i data-lucide="file-text" class="w-4 h-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <div
                        class="w-16 h-16 rounded-full bg-[var(--color-gray-100)] flex items-center justify-center mx-auto mb-4 text-[var(--color-gray-400)]">
                        <i data-lucide="file-text" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-lg font-bold text-[var(--color-gray-900)] mb-1">Belum ada laporan harian</h3>
                    <p class="text-sm text-[var(--color-gray-500)] max-w-sm mx-auto mt-1">Laporan harian kebun akan muncul
                        setelah petugas mengisinya via aplikasi Mobile RFC.</p>
                </div>
            @endif
        </div>

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const historyData = @json($sensorHistory);

            if (historyData && historyData.labels && historyData.labels.length > 0) {
                const ctx = document.getElementById('sensorTrendChart');
                if (!ctx) return;

                const thresholds = historyData.thresholds || {};

                // Annotation-like threshold line datasets
                const thresholdDatasets = [];

                if (thresholds.ph_max) {
                    thresholdDatasets.push({
                        label: 'pH Max',
                        data: Array(historyData.labels.length).fill(thresholds.ph_max),
                        borderColor: 'rgba(239, 68, 68, 0.3)',
                        borderDash: [6, 4],
                        borderWidth: 1,
                        pointRadius: 0,
                        fill: false,
                    });
                }
                if (thresholds.ec_max) {
                    thresholdDatasets.push({
                        label: 'EC Max',
                        data: Array(historyData.labels.length).fill(thresholds.ec_max),
                        borderColor: 'rgba(239, 68, 68, 0.3)',
                        borderDash: [6, 4],
                        borderWidth: 1,
                        pointRadius: 0,
                        fill: false,
                    });
                }
                if (thresholds.suhu_max) {
                    thresholdDatasets.push({
                        label: 'Suhu Max',
                        data: Array(historyData.labels.length).fill(thresholds.suhu_max),
                        borderColor: 'rgba(239, 68, 68, 0.3)',
                        borderDash: [6, 4],
                        borderWidth: 1,
                        pointRadius: 0,
                        fill: false,
                    });
                }

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: historyData.labels,
                        datasets: [{
                                label: 'pH Tanah',
                                data: historyData.ph,
                                borderColor: 'rgb(16, 185, 129)',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                fill: false,
                                tension: 0.3,
                            },
                            {
                                label: 'EC (mS/cm)',
                                data: historyData.ec,
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                fill: false,
                                tension: 0.3,
                            },
                            {
                                label: 'Suhu (°C)',
                                data: historyData.suhu,
                                borderColor: 'rgb(245, 158, 11)',
                                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                fill: false,
                                tension: 0.3,
                            },
                            {
                                label: 'Kelembaban (%)',
                                data: historyData
                                    .kelembaban, // Ensure to use the correct key name sent from the PHP controller
                                borderColor: 'rgb(6, 182, 212)',
                                backgroundColor: 'rgba(6, 182, 212, 0.1)',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                fill: false,
                                tension: 0.3,
                            },
                            {
                                label: 'Nitrogen (ppm)',
                                data: historyData.nitrogen,
                                borderColor: 'rgb(99, 102, 241)',
                                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                fill: false,
                                tension: 0.3,
                            },
                            {
                                label: 'Fosfor (ppm)',
                                data: historyData.fosfor,
                                borderColor: 'rgb(217, 70, 239)',
                                backgroundColor: 'rgba(217, 70, 239, 0.1)',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                fill: false,
                                tension: 0.3,
                            },
                            {
                                label: 'Kalium (ppm)',
                                data: historyData.kalium,
                                borderColor: 'rgb(236, 72, 153)',
                                backgroundColor: 'rgba(236, 72, 153, 0.1)',
                                borderWidth: 2,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                fill: false,
                                tension: 0.3,
                            },

                            ...thresholdDatasets,
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'white',
                                titleColor: '#1f2937',
                                bodyColor: '#4b5563',
                                borderColor: '#e5e7eb',
                                borderWidth: 1,
                                cornerRadius: 8,
                                padding: 12,
                                titleFont: {
                                    family: 'Inter',
                                    size: 12,
                                    weight: '600'
                                },
                                bodyFont: {
                                    family: 'Inter',
                                    size: 11
                                },
                                filter: function(tooltipItem) {
                                    // Hide threshold datasets from tooltip
                                    return !tooltipItem.dataset.borderDash;
                                },
                            },
                        },
                        scales: {
                            x: {
                                ticks: {
                                    font: {
                                        family: 'Inter',
                                        size: 11
                                    },
                                    color: '#9ca3af'
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.04)'
                                },
                            },
                            y: {
                                ticks: {
                                    font: {
                                        family: 'Inter',
                                        size: 11
                                    },
                                    color: '#9ca3af'
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.04)'
                                },
                            },
                        },
                    },
                });
            }
        });
    </script>
@endpush
