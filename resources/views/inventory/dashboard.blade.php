@extends('layouts.app')

@section('title', 'Manajemen Inventaris')
@section('breadcrumb', 'Inventaris')

@section('content')
    <div x-data="inventoryDashboard()" class="max-w-full space-y-5" x-cloak>

            {{-- ═══ HEADER & ACTIONS ═══ --}}
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">Manajemen Inventaris</h1>
                    <p class="text-xs text-gray-400 mt-0.5">Pantau stok pakan, obat, dan perlengkapan kandang</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select x-model="barnFilter" class="text-xs border border-gray-200 rounded-lg px-3 py-2 bg-white text-gray-600 focus:outline-none focus:ring-1 focus:ring-emerald-300">
                        <option value="all">Semua Kandang</option>
                        <option value="Barn A">Barn A</option>
                        <option value="Barn B">Barn B</option>
                        <option value="Barn C">Barn C</option>
                    </select>
                    <select x-model="categoryFilter" class="text-xs border border-gray-200 rounded-lg px-3 py-2 bg-white text-gray-600 focus:outline-none focus:ring-1 focus:ring-emerald-300">
                        <option value="all">Semua Kategori</option>
                        <option value="Pakan">Pakan</option>
                        <option value="Obat & Vaksin">Obat & Vaksin</option>
                        <option value="Vitamin">Vitamin</option>
                        <option value="Perlengkapan">Perlengkapan</option>
                    </select>
                    <div class="h-6 w-px bg-gray-200 mx-1"></div>
                    <button class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                        Adjustment
                    </button>
                    <button class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg transition flex items-center gap-2 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Tambah Inventaris
                    </button>
                </div>
            </div>

            {{-- ═══ KPI ROW ═══ --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($kpi as $m)
                    <x-peternakan.kpi-card :label="$m['label']" :value="$m['value']" :trend="$m['trend']" />
                @endforeach
            </div>

            {{-- ═══ CHARTS & SPK CARD ROW ═══ --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 h-full">

                {{-- Charts (span 2) --}}
                <div class="lg:col-span-2 grid grid-cols-1 gap-4">
                    <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800">Tren Konsumsi Pakan</h3>
                                <p class="text-[11px] text-gray-400">Berdasarkan pengeluaran gudang</p>
                            </div>
                            <div class="flex bg-gray-100 rounded-lg p-0.5">
                                <template x-for="r in [{v:'3d',l:'3H'},{v:'5d',l:'5H'},{v:'7d',l:'7H'}]" :key="r.v">
                                    <button @click="consumptionRange=r.v; renderConsumption()" :class="consumptionRange===r.v ? 'bg-white shadow-sm text-gray-900':'text-gray-500 hover:text-gray-700'" class="px-2.5 py-1 text-[10px] font-semibold rounded-md transition-all" x-text="r.l"></button>
                                </template>
                            </div>
                        </div>
                        <div style="height: 220px;">
                            <canvas x-ref="consumptionCanvas"></canvas>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-gray-800">Distribusi Pemakaian per Kandang</h3>
                                <p class="text-[11px] text-gray-400">Pakan & Vitamin harian</p>
                            </div>
                            <select x-model="usageFilter" @change="renderUsage()" class="text-xs border border-gray-200 rounded-lg px-2 py-1 bg-white text-gray-600 focus:outline-none focus:ring-1 focus:ring-emerald-300">
                                <option value="all">Semua</option>
                                <option value="pakan">Pakan</option>
                                <option value="vitamin">Vitamin</option>
                            </select>
                        </div>
                        <div style="height: 220px;">
                            <canvas x-ref="usageCanvas"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Compact SPK Card (span 1) --}}
                <div class="lg:col-span-1 bg-white border border-emerald-100 rounded-xl p-4 shadow-sm flex flex-col h-full">
                    {{-- Card Header --}}
                    <div class="flex items-center justify-between border-b border-gray-50 pb-3 mb-3">
                        <div class="flex items-center gap-2">
                            <div class="bg-emerald-50 text-emerald-600 p-1 rounded-md">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                            </div>
                            <h3 class="text-sm font-bold text-gray-900">Smart Restock AI</h3>
                        </div>
                        <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 border border-emerald-100 rounded text-[10px] font-bold tracking-wide">AHP-SAW</span>
                    </div>

                    <p class="text-[11px] text-gray-500 mb-3 leading-relaxed">
                        Items ranked by criticality considering stock, daily usage, lead time, and livestock priority.
                    </p>

                    {{-- AHP Config & Marketplace Bar --}}
                    <div class="flex items-center gap-2 mb-3">
                        <select x-model="ahpTemplate" class="flex-1 text-[10px] border border-gray-200 rounded-md px-2 py-1.5 bg-gray-50 text-gray-600 focus:outline-none focus:ring-1 focus:ring-emerald-300">
                            <option value="default">Default Priority</option>
                            <option value="price">Price Priority</option>
                            <option value="brand">Brand Priority</option>
                            <option value="urgent">Urgent Delivery</option>
                        </select>
                        <button class="flex items-center justify-center w-8 h-8 rounded-md bg-orange-50 text-orange-600 hover:bg-orange-100 transition-colors border border-orange-100" title="Buka Marketplace">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 0a2 2 0 100 4 2 2 0 000-4z"/></svg>
                        </button>
                    </div>

                    {{-- Ranked List (Scrollable) --}}
                    <div class="flex-1 overflow-y-auto pr-1 space-y-3 max-h-auto custom-scrollbar">
                        @foreach ($recommendedRestocks as $item)
                            @php
                                $pColor = ['Critical' => 'bg-red-50 text-red-600 border-red-100', 'Warning' => 'bg-amber-50 text-amber-600 border-amber-100', 'Safe' => 'bg-gray-50 text-gray-600 border-gray-100'][$item['priority']] ?? 'bg-gray-50 text-gray-600 border-gray-100';
                                $cardBorder = ['Critical' => 'border-red-100', 'Warning' => 'border-amber-100', 'Safe' => 'border-gray-100'][$item['priority']] ?? 'border-gray-100';
                                $btnColor = ['Critical' => 'bg-emerald-50 text-emerald-600', 'Warning' => 'bg-gray-50 text-gray-600', 'Safe' => 'bg-gray-50 text-gray-600'][$item['priority']] ?? 'bg-gray-50 text-gray-600';
                                $actionText = ['Critical' => 'Action Plan', 'Warning' => 'Review', 'Safe' => 'Details'][$item['priority']] ?? 'View';
                            @endphp
                            <div class="border {{ $cardBorder }} rounded-lg p-3 bg-white">
                                <div class="flex items-start justify-between mb-1.5">
                                    <h4 class="text-xs font-bold text-gray-900 pr-2">{{ $item['name'] }}</h4>
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold border {{ $pColor }} whitespace-nowrap flex items-center gap-1">
                                        <span class="w-1 h-1 rounded-full bg-current"></span>{{ $item['priority'] }}
                                    </span>
                                </div>
                                <div class="text-[10px] text-gray-500 mb-3 flex items-center justify-between">
                                    <span>Est. <span class="{{ $item['days_remaining'] <= 3 ? 'text-red-500 font-bold' : '' }}">{{ $item['days_remaining'] }} days</span> left • Lead time: {{ $item['lead_time'] }} days</span>
                                </div>
                                <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-50">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[9px] text-gray-400">AI Score:</span>
                                        <span class="px-1.5 py-0.5 bg-gray-50 text-gray-600 border border-gray-100 rounded text-[10px] font-mono">{{ $item['score'] }}</span>
                                    </div>
                                    <button class="px-3 py-1 text-[10px] font-semibold rounded-md {{ $btnColor }} transition-colors">
                                        {{ $actionText }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Footer Actions --}}
                    <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-gray-50">
                        <button class="col-span-2 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm">
                            Generate PO
                        </button>
                        <button class="col-span-1 py-2 bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 text-xs font-semibold rounded-lg transition-colors">
                            Analysis
                        </button>
                    </div>
                </div>

            </div>

            {{-- ═══ INVENTORY TABLE & MOVEMENT LOG ROW ═══ --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- Inventory Table (col-span-2) --}}
                <div class="lg:col-span-2 bg-white border border-gray-100 rounded-xl p-5 shadow-sm flex flex-col">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                        <div>
                            <h3 class="text-sm font-bold text-gray-800">Detail Inventaris</h3>
                            <p class="text-[11px] text-gray-400">Semua item gudang</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <select x-model="tableStatusFilter" class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-white text-gray-600 focus:outline-none focus:ring-1 focus:ring-emerald-300">
                                <option value="all">Semua Status</option>
                                <option value="optimal">Optimal</option>
                                <option value="warning">Warning</option>
                                <option value="critical">Critical</option>
                            </select>
                            <div class="relative">
                                <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input type="text" x-model="searchQuery" placeholder="Cari item..."
                                    class="pl-8 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg w-48 focus:outline-none focus:ring-1 focus:ring-emerald-400">
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto max-h-auto custom-scrollbar pr-1">
                        <table class="w-full text-left text-sm relative">
                            <thead class="sticky top-0 bg-white z-10">
                                <tr class="text-[10px] text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                    <th class="pb-2 font-medium">Item & Kategori</th>
                                    <th class="pb-2 font-medium text-right">Stok</th>
                                    <th class="pb-2 font-medium text-right">Penggunaan/Hari</th>
                                    <th class="pb-2 font-medium text-right">Est. Habis</th>
                                    <th class="pb-2 font-medium text-center">Status</th>
                                    <th class="pb-2 font-medium text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($inventoryItems as $item)
                                    <tr x-show="(categoryFilter === 'all' || categoryFilter === '{{ $item['category'] }}') && (tableStatusFilter === 'all' || tableStatusFilter === '{{ $item['status'] }}') && (searchQuery === '' || '{{ strtolower($item['name']) }}'.includes(searchQuery.toLowerCase()) || '{{ strtolower($item['id']) }}'.includes(searchQuery.toLowerCase()))"
                                        class="hover:bg-gray-50/50 transition-colors">
                                        <td class="py-2.5 flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg overflow-hidden bg-gray-100 border border-gray-200 shrink-0">
                                                @if(isset($item['photo']))
                                                    <img src="{{ $item['photo'] }}" alt="{{ $item['name'] }}"
                                                        class="w-full h-full object-cover">
                                                @else
                                                    <div class="w-full h-full flex items-center justify-center text-gray-400 text-[10px] font-bold">{{ substr($item['name'], 0, 1) }}</div>
                                                @endif
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold text-gray-900">{{ $item['name'] }}</p>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="text-[9px] font-mono text-gray-400">{{ $item['id'] }}</span>
                                                    <span class="text-[10px] text-gray-500">{{ $item['category'] }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2.5 text-right">
                                            <span class="text-xs font-bold text-gray-900">{{ $item['stock'] }}</span>
                                            <span class="text-[10px] text-gray-500">{{ $item['unit'] }}</span>
                                        </td>
                                        <td class="py-2.5 text-right text-[11px] text-gray-600">{{ $item['daily_usage'] }} {{ $item['unit'] }}</td>
                                        <td class="py-2.5 text-right text-[11px] font-medium {{ $item['days_left'] <= 5 ? 'text-red-500' : 'text-gray-700' }}">{{ $item['days_left'] }} Hari</td>
                                        <td class="py-2.5 text-center">
                                            @php
                                                $sColor = ['critical' => 'bg-red-50 text-red-600 border-red-100', 'warning' => 'bg-amber-50 text-amber-600 border-amber-100', 'optimal' => 'bg-emerald-50 text-emerald-600 border-emerald-100'][$item['status']] ?? 'bg-gray-50 text-gray-600 border-gray-100';
                                                $sLabel = ucfirst($item['status']);
                                            @endphp
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-semibold border {{ $sColor }}">{{ $sLabel }}</span>
                                        </td>
                                        <td class="py-2.5 text-right">
                                            <button class="text-gray-400 hover:text-emerald-600 transition-colors">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Stock Movement Log (col-span-1) --}}
                <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                    <div class="mb-5">
                        <h3 class="text-sm font-bold text-gray-800">Riwayat Pergerakan Stok</h3>
                        <p class="text-[11px] text-gray-400">Inflow, Outflow & Adjustment</p>
                    </div>

                    <div class="relative pl-4 border-l-2 border-gray-100 space-y-4">
                        @foreach ($movementLog as $log)
                            @php
                                $dotColor = ['inflow' => 'bg-emerald-400', 'outflow' => 'bg-blue-400', 'adjustment' => 'bg-amber-400'][$log['type']] ?? 'bg-gray-400';
                                $qtyColor = ['inflow' => 'text-emerald-600', 'outflow' => 'text-blue-600', 'adjustment' => 'text-amber-600'][$log['type']] ?? 'text-gray-600';
                            @endphp
                            <div class="relative group">
                                <div class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full border-2 border-white {{ $dotColor }} shadow-sm"></div>
                                <div class="flex items-center justify-between mb-0.5">
                                    <h4 class="text-xs font-bold text-gray-900">{{ $log['item'] }}</h4>
                                    <span class="text-[10px] font-bold {{ $qtyColor }}">{{ $log['qty'] }}</span>
                                </div>
                                <p class="text-[10px] text-gray-500 mb-0.5">{{ $log['note'] }}</p>
                                <div class="flex items-center gap-1 text-[9px] text-gray-400">
                                    <span>{{ $log['time'] }}</span>
                                    <span>•</span>
                                    <span>{{ $log['user'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button class="w-full mt-4 py-2 text-xs font-semibold text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors">
                        Lihat Semua Log
                    </button>
                </div>

            </div>

            <style>
                [x-cloak] { display: none !important; }
                .custom-scrollbar::-webkit-scrollbar {
                    width: 4px;
                }
                .custom-scrollbar::-webkit-scrollbar-track {
                    background: #f9fafb;
                    border-radius: 4px;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb {
                    background: #d1d5db;
                    border-radius: 4px;
                }
                .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                    background: #9ca3af;
                }
            </style>

        </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('inventoryDashboard', () => ({
                inventoryFilter: 'all',
                categoryFilter: 'all',
                barnFilter: 'all',
                searchQuery: '',
                ahpTemplate: 'default',
                tableStatusFilter: 'all',
                consumptionRange: '7d',
                usageFilter: 'all',
                _consumptionChart: null,
                _usageChart: null,

                init() {
                    // Pastikan Chart.js sudah ke-load, kalau undefined tunggu sebentar (handling async loading)
                    if (typeof Chart === 'undefined') {
                        setTimeout(() => this.init(), 100);
                        return;
                    }
                    this.$nextTick(() => {
                        this.renderConsumption();
                        this.renderUsage();
                    });
                },

                renderConsumption() {
                    const ctx = this.$refs.consumptionCanvas;
                    if (!ctx || typeof Chart === 'undefined') return;
                    if (this._consumptionChart) this._consumptionChart.destroy();

                    let labels = @js($charts['consumptionTrend']['labels']);
                    let layerData = @js($charts['consumptionTrend']['layer']);
                    let starterData = @js($charts['consumptionTrend']['starter']);
                    const sliceN = this.consumptionRange === '3d' ? 3 : (this.consumptionRange === '5d' ? 5 : 7);

                    this._consumptionChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels.slice(-sliceN),
                            datasets: [
                                {
                                    label: 'Pakan Layer (kg)',
                                    data: layerData.slice(-sliceN),
                                    borderColor: '#10B981',
                                    backgroundColor: '#10B98110',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4,
                                    pointRadius: 0,
                                    pointHoverRadius: 4,
                                },
                                {
                                    label: 'Pakan Starter (kg)',
                                    data: starterData.slice(-sliceN),
                                    borderColor: '#3B82F6',
                                    backgroundColor: '#3B82F610',
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4,
                                    pointRadius: 0,
                                    pointHoverRadius: 4,
                                }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: { legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 6, font: { size: 10, family: 'Inter' } } } },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { size: 9, family: 'Inter' }, color: '#9CA3AF' } },
                                y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 9, family: 'Inter' }, color: '#9CA3AF' } },
                            },
                        },
                    });
                },

                renderUsage() {
                    const ctx = this.$refs.usageCanvas;
                    if (!ctx || typeof Chart === 'undefined') return;
                    if (this._usageChart) this._usageChart.destroy();

                    let datasets = [];
                    if (this.usageFilter === 'all' || this.usageFilter === 'pakan') {
                        datasets.push({
                            label: 'Pakan (kg)',
                            data: @js($charts['usagePerBarn']['pakan']),
                            backgroundColor: '#10B981',
                            borderRadius: 4,
                            barPercentage: 0.6,
                            categoryPercentage: 0.8,
                            yAxisID: 'y'
                        });
                    }
                    if (this.usageFilter === 'all' || this.usageFilter === 'vitamin') {
                        datasets.push({
                            label: 'Vitamin (Pack)',
                            data: @js($charts['usagePerBarn']['vitamin']),
                            backgroundColor: '#F59E0B',
                            borderRadius: 4,
                            barPercentage: 0.6,
                            categoryPercentage: 0.8,
                            yAxisID: 'y1'
                        });
                    }

                    this._usageChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: @js($charts['usagePerBarn']['labels']),
                            datasets: datasets
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 6, font: { size: 10, family: 'Inter' } } } },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { size: 9, family: 'Inter' }, color: '#9CA3AF' } },
                                y: { position: 'left', grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 9, family: 'Inter' }, color: '#9CA3AF' } },
                                y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: { font: { size: 9, family: 'Inter' }, color: '#9CA3AF' } },
                            },
                        },
                    });
                }
            }));
        });
    </script>
@endpush