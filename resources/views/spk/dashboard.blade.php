@extends('layouts.app')

@section('title', 'Analisa SPK')
@section('breadcrumb', 'Analisa SPK')

@section('content')
    <div x-data="spkDashboard()" class="max-w-full space-y-6" x-cloak>

        {{-- ═══ HEADER & FILTERS ═══ --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white px-5 py-4 rounded-xl border border-gray-100 shadow-sm">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Pusat Analisis & SPK</h1>
                <p class="text-xs text-gray-400 mt-0.5">Diagnosa Fuzzy 3-Mode, Kausalitas Multi-Sensor, dan Logistik</p>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                
                {{-- Histori Navigasi dipindahkan ke komponen Fuzzy Logic --}}

                <form id="spkFilterForm" method="GET" action="{{ route('spk.dashboard') }}" class="flex flex-wrap items-center gap-3">
                    {{-- Komoditas --}}
                    <select name="komoditas" class="text-xs border border-gray-200 rounded-lg px-2.5 py-2 bg-white text-gray-600 focus:outline-none focus:border-emerald-400 cursor-pointer" onchange="document.getElementById('spkFilterForm').submit()">
                        @foreach ($filterOptions['komoditas'] as $val => $label)
                            <option value="{{ $val }}" {{ $komoditas === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>

                    {{-- Lokasi / Kandang --}}
                    <select name="coop_id" class="text-xs border border-gray-200 rounded-lg px-2.5 py-2 bg-white text-gray-600 focus:outline-none focus:border-emerald-400 cursor-pointer" onchange="document.getElementById('spkFilterForm').submit()">
                        @foreach ($barnsOption as $barn)
                            <option value="{{ $barn['id'] }}" {{ $coopId == $barn['id'] ? 'selected' : '' }}>{{ $barn['name'] }}</option>
                        @endforeach
                    </select>

                    {{-- Mode SPK (Fuzzy Logic) Dihapus sesuai permintaan karena membingungkan variabel --}}
                </form>
            </div>
        </div>

        {{-- ═══ 2. CARD FUZZY LOGIC (Full Width) ═══ --}}
        <x-fuzzy-decision-engine 
            :barns="$barnsOption" 
            :indicators="$fuzzyData['indicators']" 
            :spkResults="$fuzzyData['results']"
            :hideBarnFilter="true"
            :evaluationTime="$activeHistory['date'] . ', ' . $activeHistory['time'] . ' — Mode: ' . $activeHistory['mode']"
        />

        {{-- ═══ 3. TODO LIST / ACTION TRACKER (Full Width) ═══ --}}
        <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
            <div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                        <svg class="w-4 h-4 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Penugasan Action Tracker (Hasil SPK {{ $activeHistory['id'] }})
                    </h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Daftar tugas rekomendasi yang belum dan sudah dikerjakan berdasarkan analisa ini.</p>
                </div>
                <button class="text-xs font-semibold text-purple-600 bg-purple-50 px-3 py-1.5 rounded-lg hover:bg-purple-100 transition whitespace-nowrap">
                    + Buat Tiket Manual
                </button>
            </div>

            {{-- Kanban Mini Board --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {{-- Column: TO DO --}}
                <div class="bg-gray-50 rounded-xl p-3 border border-gray-100 min-h-[180px] shadow-inner flex flex-col gap-2.5">
                    <h4 class="text-[10px] font-bold text-gray-500 uppercase tracking-widest px-1 py-1 flex items-center justify-between">
                        To Do
                        <span class="bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded h-5 min-w-[20px] text-center leading-4">{{ count(array_filter($actionTickets, fn($t) => $t['status'] === 'To Do')) }}</span>
                    </h4>
                    @foreach ($actionTickets as $ticket)
                        @if ($ticket['status'] === 'To Do') <x-spk-ticket-card :ticket="$ticket" /> @endif
                    @endforeach
                </div>

                {{-- Column: IN PROGRESS --}}
                <div class="bg-blue-50/40 rounded-xl p-3 border border-blue-50 min-h-[180px] shadow-inner flex flex-col gap-2.5">
                    <h4 class="text-[10px] font-bold text-blue-600 uppercase tracking-widest px-1 py-1 flex items-center justify-between">
                        In Progress
                        <span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded h-5 min-w-[20px] text-center leading-4">{{ count(array_filter($actionTickets, fn($t) => $t['status'] === 'In Progress')) }}</span>
                    </h4>
                    @foreach ($actionTickets as $ticket)
                        @if ($ticket['status'] === 'In Progress') <x-spk-ticket-card :ticket="$ticket" /> @endif
                    @endforeach
                </div>

                {{-- Column: DONE --}}
                <div class="bg-emerald-50/40 rounded-xl p-3 border border-emerald-50 min-h-[180px] shadow-inner flex flex-col gap-2.5">
                    <h4 class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest px-1 py-1 flex items-center justify-between">
                        Done
                        <span class="bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded h-5 min-w-[20px] text-center leading-4">{{ count(array_filter($actionTickets, fn($t) => $t['status'] === 'Done')) }}</span>
                    </h4>
                    @foreach ($actionTickets as $ticket)
                        @if ($ticket['status'] === 'Done') <x-spk-ticket-card :ticket="$ticket" /> @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ═══ 4. DATA MENTAH + GRAFIK ←→ RIWAYAT SPK (Side-by-Side) ═══ --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:items-start">

            {{-- LEFT: Data Mentah + Grafik + KPI (8/12) --}}
            <div class="lg:col-span-8 space-y-4 order-2 lg:order-1">

                {{-- Raw Data Snapshot Card --}}
                <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-xs font-bold text-gray-700">Data Mentah (Snapshot {{ $activeHistory['id'] }})</h4>
                        <span class="text-[9px] text-gray-400">{{ $activeHistory['date'] }}, {{ $activeHistory['time'] }}</span>
                    </div>
                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
                        @foreach($activeHistory['raw'] as $key => $val)
                            <div class="text-center p-2.5 rounded-lg {{ $val !== '-' ? 'bg-gray-50' : 'bg-gray-50/50' }}">
                                <p class="text-[9px] uppercase font-semibold text-gray-400 mb-0.5">{{ $key }}</p>
                                <p class="text-sm font-black {{ $val !== '-' ? 'text-gray-800' : 'text-gray-300' }}">{{ $val }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Charts Row --}}
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    {{-- Chart 1: HDP vs Standard --}}
                    <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800">Kurva Produksi (HDP) vs Standar</h3>
                                <p class="text-[11px] text-gray-400">Referensi: Strain Lohmann Brown Classic</p>
                            </div>
                        </div>
                        <div style="height: 220px;">
                            <canvas x-ref="hdpCanvas"></canvas>
                        </div>
                    </div>

                    {{-- Chart 2: Multi-Sensor Causality --}}
                    <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800">Kausalitas Multi-Sensor vs FCR</h3>
                                <p class="text-[11px] text-gray-400">Dampak Suhu, Kelembaban, Amonia (7 hari sebelum analisa)</p>
                            </div>
                        </div>
                        <div style="height: 220px;">
                            <canvas x-ref="causalityCanvas"></canvas>
                        </div>
                    </div>
                </div>

                {{-- KPI Mini Row --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach ($kpi as $m)
                        <div class="bg-white border border-gray-100 rounded-xl p-4 shadow-sm flex flex-col justify-center">
                            <span class="text-[10px] uppercase font-semibold text-gray-400 mb-1 line-clamp-1" title="{{ $m['label'] }}">{{ $m['label'] }}</span>
                            <div class="flex items-end justify-between">
                                <span class="text-lg font-black text-gray-900">{{ $m['value'] }}</span>
                                @if($m['trend']['status'] !== 'neutral')
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $m['trend']['status'] == 'positive' ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600' }} flex items-center">
                                        {{ $m['trend']['direction'] == 'up' ? '↗' : '↘' }} {{ $m['trend']['value'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- RIGHT: Riwayat Analisa SPK (4/12) --}}
            <div class="lg:col-span-4 order-1 lg:order-2">
                <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden flex flex-col" style="max-height: 580px;">
                    {{-- Header --}}
                    <div class="px-4 pt-4 pb-3 border-b border-gray-100">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-bold text-gray-800">Riwayat Analisa SPK</h3>
                            <button class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-lg hover:bg-emerald-100 transition whitespace-nowrap">
                                Lihat Semua
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="date" class="flex-1 text-[10px] border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:border-emerald-400 text-gray-500">
                            <div class="relative flex-1">
                                <svg class="absolute left-2 top-1/2 -translate-y-1/2 w-3 h-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <input type="text" placeholder="Cari ID..." class="w-full text-[10px] border border-gray-200 rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:border-emerald-400 text-gray-500">
                            </div>
                        </div>
                    </div>

                    {{-- Scrollable List --}}
                    <div class="flex-1 overflow-y-auto px-3 py-3 space-y-1">
                        @php $currentDate = ''; @endphp
                        @foreach($spkHistory as $hist)
                            @if($hist['date'] !== $currentDate)
                                @php $currentDate = $hist['date']; @endphp
                                <p class="text-[9px] font-bold uppercase tracking-wider text-gray-400 px-1 pt-2 pb-1">{{ $currentDate }}</p>
                            @endif

                            @php
                                $isActive = $activeHistory['id'] === $hist['id'];
                                $statusDotColors = [
                                    'red' => 'bg-red-500', 'amber' => 'bg-amber-500',
                                    'emerald' => 'bg-emerald-500', 'blue' => 'bg-blue-500',
                                ];
                                $modeBgColors = [
                                    'blue' => 'bg-blue-100 text-blue-700',
                                    'amber' => 'bg-amber-100 text-amber-700',
                                    'purple' => 'bg-purple-100 text-purple-700',
                                ];
                            @endphp
                            <a href="?komoditas={{ $komoditas }}&coop_id={{ $coopId }}&history_id={{ $hist['id'] }}"
                               class="block rounded-lg p-3 transition-all cursor-pointer {{ $isActive ? 'bg-gray-800 text-white shadow-md ring-2 ring-gray-700' : 'bg-gray-50 hover:bg-gray-100 border border-gray-100' }}">
                                
                                {{-- Top row: ID + Mode Badge + Time --}}
                                <div class="flex items-center justify-between mb-1.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full {{ $statusDotColors[$hist['color']] ?? 'bg-gray-400' }}"></span>
                                        <span class="text-[11px] font-bold">{{ $hist['id'] }}</span>
                                        <span class="text-[8px] font-bold px-1.5 py-0.5 rounded {{ $isActive ? 'bg-white/20 text-white' : ($modeBgColors[$hist['modeColor']] ?? 'bg-gray-100 text-gray-600') }}">{{ $hist['mode'] }}</span>
                                    </div>
                                    <span class="text-[9px] {{ $isActive ? 'text-gray-300' : 'text-gray-400' }}">{{ $hist['time'] }}</span>
                                </div>

                                {{-- Status + Barn --}}
                                <p class="text-[10px] font-semibold {{ $isActive ? 'text-white' : 'text-gray-700' }} mb-0.5">
                                    {{ $hist['status'] }} · <span class="{{ $isActive ? 'text-gray-300' : 'text-gray-400' }} font-normal">{{ $hist['barn'] }}</span>
                                </p>
                                
                                {{-- Verdict --}}
                                <p class="text-[9px] {{ $isActive ? 'text-gray-400' : 'text-gray-500' }} line-clamp-2 mb-2">{{ $hist['verdict'] }}</p>

                                {{-- Raw Data Mini Badges --}}
                                <div class="flex flex-wrap gap-1">
                                    @foreach($hist['raw'] as $key => $val)
                                        @if($val !== '-')
                                            <span class="text-[8px] font-medium px-1.5 py-0.5 rounded {{ $isActive ? 'bg-white/10 text-gray-300' : 'bg-gray-100 text-gray-500' }}">
                                                {{ strtoupper($key) }}: {{ $val }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ 5. AHP-SAW SUPPLIER (Standalone Full Width) ═══ --}}
        <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-4">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Rekomendasi Restock Supplier (AHP-SAW)
                    </h3>
                    <p class="text-[10px] text-gray-400 mt-0.5">Analisa Kriteria Harga, Waktu, & Kualitas</p>
                </div>
                <button class="text-[10px] font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 px-2.5 py-1 rounded transition">
                    Lihat Marketplace →
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($recommendedSuppliers as $supplier)
                    @php
                        $isTop = $supplier['rank'] === 1;
                        $border = $isTop ? 'border-blue-200 bg-blue-50/20' : 'border-gray-100 bg-white hover:border-gray-200';
                    @endphp
                    <div class="border {{ $border }} rounded-lg p-4 transition-colors">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <div class="flex items-center justify-center w-6 h-6 rounded {{ $isTop ? 'bg-blue-500 text-white shadow-sm' : 'bg-gray-100 text-gray-600' }} text-[10px] font-black">
                                    #{{ $supplier['rank'] }}
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-gray-900">{{ $supplier['name'] }}</h4>
                                    <span class="text-[9px] text-gray-400">{{ $supplier['category'] }}</span>
                                </div>
                            </div>
                            <span class="text-lg font-black {{ $isTop ? 'text-blue-600' : 'text-gray-900' }}">{{ number_format($supplier['score'], 1) }}</span>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-2 mt-3 pt-2 border-t {{ $isTop ? 'border-blue-100' : 'border-gray-50' }} text-[9px]">
                            <div class="text-center">
                                <span class="block text-gray-400 mb-0.5">Harga</span>
                                <span class="font-bold text-gray-700">{{ $supplier['price_rating'] }}</span>
                            </div>
                            <div class="text-center">
                                <span class="block text-gray-400 mb-0.5">Lead Time</span>
                                <span class="font-bold text-gray-700">{{ $supplier['lead_time'] }}</span>
                            </div>
                            <div class="text-center">
                                <span class="block text-gray-400 mb-0.5">Kualitas</span>
                                <span class="font-bold text-emerald-600">{{ $supplier['quality'] }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>



    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('spkDashboard', () => ({
                fuzzyFilter: 'all',
                fuzzySensors: {
                    lingkungan: @js($fuzzyData['sensors']['lingkungan']),
                    produktivitas: @js($fuzzyData['sensors']['produktivitas'])
                },
                _hdpChart: null,
                _causalityChart: null,
                _spiderChart: null,

                init() {
                    if (typeof Chart === 'undefined') {
                        setTimeout(() => this.init(), 100);
                        return;
                    }
                    this.$nextTick(() => {
                        this.renderSpiderChart();
                        this.renderHdpChart();
                        this.renderCausalityChart();
                    });
                },

                renderSpiderChart() {
                    const ctx = this.$refs.spiderCanvas;
                    if (!ctx || typeof Chart === 'undefined') return;

                    const activeSpkColor = '{{ $fuzzyData['color'] }}';
                    const activeSpiderData = @js($fuzzyData['spider']);
                    
                    let strokeColor = '#10B981'; // emerald
                    let fillColor = 'rgba(16, 185, 129, 0.2)';
                    
                    if(activeSpkColor === 'amber') {
                        strokeColor = '#F59E0B';
                        fillColor = 'rgba(245, 158, 11, 0.2)';
                    } else if (activeSpkColor === 'red') {
                        strokeColor = '#EF4444';
                        fillColor = 'rgba(239, 68, 68, 0.2)';
                    }

                    this._spiderChart = new Chart(ctx, {
                        type: 'radar',
                        data: {
                            labels: ['Suhu', 'Kelembaban', 'Amonia', 'HDP', 'FCR', 'Mortalitas'],
                            datasets: [{
                                label: 'Status (%)',
                                data: activeSpiderData, // [Env x3, Prod x3]
                                backgroundColor: fillColor,
                                borderColor: strokeColor,
                                pointBackgroundColor: strokeColor,
                                pointBorderColor: '#fff',
                                pointHoverBackgroundColor: '#fff',
                                pointHoverBorderColor: strokeColor,
                                borderWidth: 1.5,
                                pointRadius: 2,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false }, tooltip: {
                                callbacks: {
                                    label: function(context) { return context.raw + '% Impact'; }
                                }
                            } },
                            scales: {
                                r: {
                                    angleLines: { color: 'rgba(0,0,0,0.05)' },
                                    grid: { color: 'rgba(0,0,0,0.05)' },
                                    pointLabels: { font: { size: 8, family: 'Inter' }, color: '#9CA3AF' },
                                    ticks: { display: false, min: 0, max: 100, stepSize: 25 }
                                }
                            }
                        }
                    });
                },

                renderHdpChart() {
                    const ctx = this.$refs.hdpCanvas;
                    if (!ctx || typeof Chart === 'undefined') return;

                    const data = @js($chartData['hdpComparison']);

                    this._hdpChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [
                                {
                                    label: 'Aktual Kandang (%)',
                                    data: data.actual,
                                    borderColor: '#10B981', // Emerald
                                    backgroundColor: '#10B981',
                                    borderWidth: 2.5,
                                    tension: 0.4,
                                    pointRadius: 3,
                                    pointBackgroundColor: '#fff',
                                    pointBorderWidth: 2,
                                    fill: false
                                },
                                {
                                    label: 'Standar Lohmann Brown (%)',
                                    data: data.standard,
                                    borderColor: '#9CA3AF', // Gray
                                    borderWidth: 2,
                                    borderDash: [5, 5],
                                    tension: 0.4,
                                    pointRadius: 0,
                                    fill: false
                                }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 6, font: { size: 10, family: 'Inter' } } }
                            },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { size: 9, family: 'Inter' }, color: '#9CA3AF' } },
                                y: { 
                                    grid: { color: 'rgba(0,0,0,0.04)' }, 
                                    ticks: { font: { size: 9, family: 'Inter' }, color: '#9CA3AF' },
                                    suggestedMin: 50,
                                    suggestedMax: 100
                                },
                            },
                        }
                    });
                },

                renderCausalityChart() {
                    const ctx = this.$refs.causalityCanvas;
                    if (!ctx || typeof Chart === 'undefined') return;

                    const data = @js($chartData['causality']);

                    // This is a complex multi-axis chart overlaying Suhu, Kelembaban, Amonia against FCR Trends
                    this._causalityChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [
                                {
                                    label: 'FCR Harian (Produktivitas)',
                                    data: data.fcr,
                                    borderColor: '#3B82F6', // Blue
                                    backgroundColor: '#3B82F610',
                                    borderWidth: 3,
                                    tension: 0.4,
                                    pointRadius: 4,
                                    pointBackgroundColor: '#fff',
                                    fill: true,
                                    yAxisID: 'yFcr',
                                    order: 1 // Drawn on top
                                },
                                {
                                    label: 'Suhu Rata-rata (°C)',
                                    data: data.suhu,
                                    borderColor: '#F59E0B', // Amber
                                    borderWidth: 2,
                                    borderDash: [5, 5],
                                    tension: 0.4,
                                    pointRadius: 0,
                                    yAxisID: 'ySuhu',
                                    order: 2
                                },
                                {
                                    label: 'Amonia (ppm)',
                                    data: data.amonia,
                                    borderColor: '#EF4444', // Red
                                    borderWidth: 2,
                                    borderDash: [3, 3],
                                    tension: 0.4,
                                    pointRadius: 0,
                                    yAxisID: 'yAmonia',
                                    order: 3
                                },
                                {
                                    label: 'Kelembaban (%)',
                                    type: 'bar',
                                    data: data.kelembaban,
                                    backgroundColor: 'rgba(156, 163, 175, 0.15)', // Grayish bar background
                                    borderColor: 'transparent',
                                    borderWidth: 0,
                                    yAxisID: 'yKelembaban',
                                    order: 4 // Drawn on bottom
                                }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'top', align: 'center', labels: { usePointStyle: true, boxWidth: 6, font: { size: 9, family: 'Inter' } } },
                                tooltip: { titleFont: { size: 10, family: 'Inter' }, bodyFont: { size: 10, family: 'Inter' } }
                            },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { size: 9, family: 'Inter' }, color: '#9CA3AF' } },
                                
                                // Left Axis: FCR (Primary)
                                yFcr: { 
                                    type: 'linear', display: true, position: 'left',
                                    grid: { color: 'rgba(0,0,0,0.04)' }, 
                                    title: { display: true, text: 'FCR', font: { size: 9 }, color: '#3B82F6' },
                                    ticks: { font: { size: 9, family: 'Inter' }, color: '#3B82F6' },
                                    suggestedMin: 1.8, suggestedMax: 2.5
                                },
                                // Right Axis 1: Suhu
                                ySuhu: {
                                    type: 'linear', display: true, position: 'right',
                                    grid: { drawOnChartArea: false },
                                    title: { display: true, text: 'Suhu (°C)', font: { size: 9 }, color: '#F59E0B' },
                                    ticks: { font: { size: 9, family: 'Inter' }, color: '#F59E0B' },
                                    suggestedMin: 20, suggestedMax: 40
                                },
                                // Right Axis 2: Amonia (Hidden labels, just for scaling correctly)
                                yAmonia: {
                                    type: 'linear', display: false, position: 'right',
                                    suggestedMin: 0, suggestedMax: 50
                                },
                                // Right Axis 3: Kelembaban (Hidden labels)
                                yKelembaban: {
                                    type: 'linear', display: false, position: 'right',
                                    suggestedMin: 0, suggestedMax: 100
                                }
                            },
                        }
                    });
                }
            }));
        });
    </script>
@endpush
