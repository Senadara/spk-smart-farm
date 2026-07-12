@extends('layouts.app')

@section('title', 'Manajemen Inventaris')
@section('breadcrumb', 'Inventaris')

@section('content')
    @php
        $statusMeta = [
            'critical' => ['label' => 'Critical', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
            'warning' => ['label' => 'Warning', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
            'optimal' => ['label' => 'Optimal', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        ];
    @endphp

    <div x-data="inventoryDashboard()" class="max-w-full space-y-5" x-cloak>
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="h-1 bg-gradient-to-r from-emerald-500 via-sky-500 to-amber-500"></div>
            <div class="flex flex-col gap-4 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Manajemen Inventaris</h1>
                    <p class="mt-0.5 text-xs text-slate-500">Pantau stok pakan, obat, vitamin, dan perlengkapan kandang.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select x-model="barnFilter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 focus:outline-none focus:ring-1 focus:ring-emerald-300">
                        <option value="all">Semua Kandang</option>
                        @foreach($barnOptions as $barn)
                            <option value="{{ $barn->nama }}">{{ $barn->nama }}</option>
                        @endforeach
                        <option value="Umum">Umum</option>
                    </select>
                    <select x-model="categoryFilter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 focus:outline-none focus:ring-1 focus:ring-emerald-300">
                        <option value="all">Semua Kategori</option>
                        @foreach($categoryOptions as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                    <button @click="loadAnalysis()" class="inline-flex items-center gap-2 rounded-lg border border-sky-100 bg-sky-50 px-4 py-2 text-sm font-semibold text-sky-700 transition hover:bg-sky-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3a8 8 0 108 8h-8V3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 3.252A8.014 8.014 0 0118.748 9H13V3.252z"/></svg>
                        Analisis
                    </button>
                    <button @click="showCreateModal = true" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Tambah Inventaris
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($kpi as $m)
                <x-peternakan.kpi-card :label="$m['label']" :value="$m['value']" :trend="$m['trend']" />
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="grid grid-cols-1 gap-4 lg:col-span-2">
                <div class="rounded-xl border border-slate-100 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">Tren Konsumsi Stok</h3>
                            <p class="text-[11px] text-slate-400">Outflow 7 hari terakhir atau estimasi penggunaan harian.</p>
                        </div>
                        <div class="flex rounded-lg bg-slate-100 p-0.5">
                            <template x-for="r in [{v:'3d',l:'3H'},{v:'5d',l:'5H'},{v:'7d',l:'7H'}]" :key="r.v">
                                <button @click="consumptionRange=r.v; renderConsumption()" :class="consumptionRange===r.v ? 'bg-white shadow-sm text-slate-900':'text-slate-500 hover:text-slate-700'" class="rounded-md px-2.5 py-1 text-[10px] font-semibold transition-all" x-text="r.l"></button>
                            </template>
                        </div>
                    </div>
                    <div class="relative h-[220px]">
                        <canvas x-ref="consumptionCanvas"></canvas>
                        <div x-show="!chartReady" class="absolute inset-0 flex items-center justify-center rounded-lg bg-slate-50 text-xs font-semibold text-slate-500">
                            Grafik siap setelah Chart.js termuat.
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-100 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">Distribusi Pemakaian per Kandang</h3>
                            <p class="text-[11px] text-slate-400">Estimasi pemakaian harian dari item aktif.</p>
                        </div>
                        <select x-model="usageFilter" @change="renderUsage()" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600 focus:outline-none focus:ring-1 focus:ring-emerald-300">
                            <option value="all">Semua</option>
                            <option value="pakan">Pakan</option>
                            <option value="vitamin">Vitamin</option>
                        </select>
                    </div>
                    <div class="relative h-[220px]">
                        <canvas x-ref="usageCanvas"></canvas>
                        <div x-show="!chartReady" class="absolute inset-0 flex items-center justify-center rounded-lg bg-slate-50 text-xs font-semibold text-slate-500">
                            Grafik siap setelah Chart.js termuat.
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex h-full flex-col rounded-xl border border-emerald-100 bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="rounded-md bg-emerald-50 p-1 text-emerald-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">Smart Restock</h3>
                    </div>
                    <span class="rounded border border-emerald-100 bg-emerald-50 px-2 py-0.5 text-[10px] font-bold tracking-wide text-emerald-600">SPK</span>
                </div>

                <p class="mb-3 text-[11px] leading-relaxed text-slate-500">
                    Ranking restock dihitung dari status stok, sisa hari, dan lead time supplier.
                </p>

                <div class="mb-3 flex items-center gap-2">
                    <button @click="generatePurchaseOrder()" class="flex-1 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700">
                        Generate PO
                    </button>
                    <a href="{{ route('spk.suppliers.products') }}" class="flex h-9 w-9 items-center justify-center rounded-lg border border-amber-100 bg-amber-50 text-amber-600 transition hover:bg-amber-100" title="Buka Marketplace">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 0a2 2 0 100 4 2 2 0 000-4z"/></svg>
                    </a>
                </div>

                <div class="custom-scrollbar max-h-[520px] flex-1 space-y-3 overflow-y-auto pr-1">
                    @forelse ($recommendedRestocks as $item)
                        @php
                            $pColor = ['Critical' => 'bg-rose-50 text-rose-700 border-rose-100', 'Warning' => 'bg-amber-50 text-amber-700 border-amber-100', 'Safe' => 'bg-slate-50 text-slate-600 border-slate-100'][$item['priority']] ?? 'bg-slate-50 text-slate-600 border-slate-100';
                        @endphp
                        <div class="rounded-lg border border-slate-100 bg-white p-3">
                            <div class="mb-1.5 flex items-start justify-between gap-2">
                                <h4 class="pr-2 text-xs font-bold text-slate-900">{{ $item['name'] }}</h4>
                                <span class="whitespace-nowrap rounded border px-1.5 py-0.5 text-[9px] font-semibold {{ $pColor }}">{{ $item['priority'] }}</span>
                            </div>
                            <div class="mb-3 flex items-center justify-between text-[10px] text-slate-500">
                                <span>{{ $item['current_stock'] }}</span>
                                <span class="{{ ($item['days_remaining'] ?? 999) <= 3 ? 'font-bold text-rose-600' : '' }}">{{ $item['days_label'] }}</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-slate-50 pt-2">
                                <span class="rounded border border-slate-100 bg-slate-50 px-1.5 py-0.5 font-mono text-[10px] text-slate-600">Score {{ $item['score'] }}</span>
                                <button @click="openRestockPlan(@js($item))" class="rounded-md bg-slate-900 px-3 py-1 text-[10px] font-semibold text-white transition hover:bg-slate-700">
                                    Action Plan
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-xs text-slate-500">
                            Belum ada item inventaris aktif.
                        </div>
                    @endforelse
                </div>

                <button @click="loadAnalysis()" class="mt-4 rounded-lg border border-slate-200 bg-white py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                    Analysis
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="flex flex-col rounded-xl border border-slate-100 bg-white p-5 shadow-sm lg:col-span-2">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Detail Inventaris</h3>
                        <p class="text-[11px] text-slate-400">Semua item gudang dan stok aktif.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <select x-model="tableStatusFilter" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-600 focus:outline-none focus:ring-1 focus:ring-emerald-300">
                            <option value="all">Semua Status</option>
                            <option value="optimal">Optimal</option>
                            <option value="warning">Warning</option>
                            <option value="critical">Critical</option>
                        </select>
                        <div class="relative">
                            <svg class="absolute left-2.5 top-2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input type="text" x-model="searchQuery" placeholder="Cari item..." class="w-48 rounded-lg border border-slate-200 py-1.5 pl-8 pr-3 text-xs focus:outline-none focus:ring-1 focus:ring-emerald-400">
                        </div>
                    </div>
                </div>

                <div class="custom-scrollbar max-h-[560px] flex-1 overflow-x-auto overflow-y-auto pr-1">
                    <table class="w-full min-w-[820px] text-left text-sm">
                        <thead class="sticky top-0 z-10 bg-white">
                            <tr class="border-b border-slate-100 text-[10px] uppercase tracking-wider text-slate-400">
                                <th class="pb-2 font-medium">Item & Kategori</th>
                                <th class="pb-2 font-medium">Kandang</th>
                                <th class="pb-2 text-right font-medium">Stok</th>
                                <th class="pb-2 text-right font-medium">Penggunaan/Hari</th>
                                <th class="pb-2 text-right font-medium">Est. Habis</th>
                                <th class="pb-2 text-center font-medium">Status</th>
                                <th class="pb-2 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse ($inventoryItems as $item)
                                @php
                                    $meta = $statusMeta[$item['status']] ?? $statusMeta['optimal'];
                                @endphp
                                <tr x-show="matchesRow($el)" data-category="{{ $item['category'] }}" data-status="{{ $item['status'] }}" data-barn="{{ $item['barn'] }}" data-search="{{ strtolower($item['id'] . ' ' . $item['name'] . ' ' . $item['category'] . ' ' . $item['barn']) }}" class="transition-colors hover:bg-slate-50/60">
                                    <td class="flex items-center gap-3 py-2.5">
                                        <div class="h-9 w-9 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                                            @if($item['photo'])
                                                <img src="{{ $item['photo'] }}" alt="{{ $item['name'] }}" class="h-full w-full object-cover">
                                            @else
                                                <div class="flex h-full w-full items-center justify-center text-[10px] font-bold text-slate-400">{{ substr($item['name'], 0, 1) }}</div>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-900">{{ $item['name'] }}</p>
                                            <div class="mt-0.5 flex items-center gap-2">
                                                <span class="font-mono text-[9px] text-slate-400">{{ $item['id'] }}</span>
                                                <span class="text-[10px] text-slate-500">{{ $item['category'] }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2.5 text-xs text-slate-500">{{ $item['barn'] }}</td>
                                    <td class="py-2.5 text-right">
                                        <span class="text-xs font-bold text-slate-900">{{ $item['stock'] }}</span>
                                        <span class="text-[10px] text-slate-500">{{ $item['unit'] }}</span>
                                    </td>
                                    <td class="py-2.5 text-right text-[11px] text-slate-600">{{ $item['daily_usage_label'] }}</td>
                                    <td class="py-2.5 text-right text-[11px] font-medium {{ ($item['days_left'] ?? 999) <= 5 ? 'text-rose-600' : 'text-slate-700' }}">{{ $item['days_left_label'] }}</td>
                                    <td class="py-2.5 text-center">
                                        <span class="rounded-full border px-2 py-0.5 text-[9px] font-semibold {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                                    </td>
                                    <td class="py-2.5 text-right">
                                        <div class="flex justify-end gap-1">
                                            <button @click="openDetail(@js($item))" class="rounded-md bg-slate-50 p-1.5 text-slate-500 transition hover:bg-slate-100" title="Detail">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </button>
                                            <button @click="openAdjust(@js($item))" class="rounded-md bg-emerald-50 p-1.5 text-emerald-600 transition hover:bg-emerald-100" title="Adjustment stok">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-10 text-center text-xs text-slate-400">Belum ada item inventaris. Gunakan tombol Tambah Inventaris.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="mb-5">
                    <h3 class="text-sm font-bold text-slate-800">Riwayat Pergerakan Stok</h3>
                    <p class="text-[11px] text-slate-400">Inflow, outflow, dan adjustment.</p>
                </div>

                <div class="custom-scrollbar max-h-[560px] space-y-4 overflow-y-auto border-l-2 border-slate-100 pl-4">
                    @forelse ($movementLog as $log)
                        @php
                            $dotColor = ['inflow' => 'bg-emerald-400', 'outflow' => 'bg-sky-400', 'adjustment' => 'bg-amber-400'][$log['type']] ?? 'bg-slate-400';
                            $qtyColor = ['inflow' => 'text-emerald-600', 'outflow' => 'text-sky-600', 'adjustment' => 'text-amber-600'][$log['type']] ?? 'text-slate-600';
                        @endphp
                        <div class="group relative">
                            <div class="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full border-2 border-white {{ $dotColor }} shadow-sm"></div>
                            <div class="mb-0.5 flex items-center justify-between gap-2">
                                <h4 class="text-xs font-bold text-slate-900">{{ $log['item'] }}</h4>
                                <span class="text-[10px] font-bold {{ $qtyColor }}">{{ $log['qty'] }}</span>
                            </div>
                            <p class="mb-0.5 text-[10px] text-slate-500">{{ $log['note'] }}</p>
                            <div class="flex flex-wrap items-center gap-1 text-[9px] text-slate-400">
                                <span>{{ $log['time'] }}</span>
                                <span>-</span>
                                <span>{{ $log['user'] }}</span>
                                <span>-</span>
                                <span>{{ $log['barn'] }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="py-8 text-center text-xs text-slate-400">Belum ada pergerakan stok.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div x-show="showCreateModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showCreateModal=false" style="display:none;">
            <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-bold text-slate-900">Tambah Inventaris</h3>
                    <button @click="showCreateModal=false" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('inventory.items.store') }}" enctype="multipart/form-data" class="space-y-4 px-6 py-5">
                    @csrf
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">SKU</label>
                            <input name="sku" value="{{ old('sku') }}" placeholder="Kosongkan untuk auto" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Nama Item <span class="text-rose-500">*</span></label>
                            <input name="name" value="{{ old('name') }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Kategori</label>
                            <input name="category" value="{{ old('category', 'Pakan') }}" list="categoryList" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                            <datalist id="categoryList">
                                @foreach($categoryOptions as $category)
                                    <option value="{{ $category }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Stok Awal</label>
                            <input type="number" step="0.01" min="0" name="stock" value="{{ old('stock', 0) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Satuan</label>
                            <input name="unit" value="{{ old('unit', 'Sak') }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Pakai/Hari</label>
                            <input type="number" step="0.01" min="0" name="daily_usage" value="{{ old('daily_usage', 0) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Minimum</label>
                            <input type="number" step="0.01" min="0" name="minimum_stock" value="{{ old('minimum_stock', 0) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Reorder Point</label>
                            <input type="number" step="0.01" min="0" name="reorder_point" value="{{ old('reorder_point', 0) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Lead Time</label>
                            <input type="number" min="0" name="lead_time_days" value="{{ old('lead_time_days', 1) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Supplier</label>
                            <select name="supplier_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                                <option value="">Belum dipilih</option>
                                @foreach($supplierOptions as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Kandang/Area</label>
                            <select name="unit_budidaya_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                                <option value="">Umum</option>
                                @foreach($barnOptions as $barn)
                                    <option value="{{ $barn->id }}">{{ $barn->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Foto Item</label>
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700 focus:border-emerald-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Catatan</label>
                        <textarea name="notes" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">{{ old('notes') }}</textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showCreateModal=false" class="rounded-lg bg-slate-100 px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-200">Batal</button>
                        <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-xs font-semibold text-white hover:bg-emerald-700">Simpan Item</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showAdjustModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showAdjustModal=false" style="display:none;">
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-bold text-slate-900">Adjustment Stok</h3>
                    <button @click="showAdjustModal=false" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form method="POST" :action="adjustAction" class="space-y-4 px-6 py-5">
                    @csrf
                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <p class="text-[10px] font-bold uppercase text-slate-400">Item</p>
                        <p class="text-sm font-semibold text-slate-900" x-text="adjustItem?.name ?? 'Pilih item dari tabel'"></p>
                        <p class="text-xs text-slate-500" x-text="adjustItem ? `Stok saat ini: ${adjustItem.stock} ${adjustItem.unit}` : ''"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Jenis Adjustment</label>
                        <select name="type" x-model="adjustType" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                            <option value="inflow">Stok Masuk</option>
                            <option value="outflow">Stok Keluar</option>
                            <option value="adjustment">Set Stok Final</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600" x-text="adjustType === 'adjustment' ? 'Stok Final' : 'Jumlah'"></label>
                        <input type="number" step="0.01" min="0" name="quantity" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Catatan</label>
                        <textarea name="note" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showAdjustModal=false" class="rounded-lg bg-slate-100 px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-200">Batal</button>
                        <button type="submit" :disabled="!adjustItem" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300">Simpan Adjustment</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showDetailModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDetailModal=false" style="display:none;">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-bold text-slate-900">Detail Inventaris</h3>
                    <button @click="showDetailModal=false" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="space-y-4 px-6 py-5" x-show="detailItem">
                    <div class="flex gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                            <template x-if="detailItem?.photo"><img :src="detailItem.photo" class="h-full w-full object-cover" alt=""></template>
                            <template x-if="!detailItem?.photo"><span class="text-lg font-bold text-slate-400" x-text="detailItem?.name?.slice(0,1)"></span></template>
                        </div>
                        <div>
                            <h4 class="text-base font-bold text-slate-900" x-text="detailItem?.name"></h4>
                            <p class="text-xs text-slate-500" x-text="`${detailItem?.id} - ${detailItem?.category} - ${detailItem?.barn}`"></p>
                            <p class="mt-2 text-xs leading-relaxed text-slate-600" x-text="detailItem?.notes || 'Tidak ada catatan.'"></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <template x-for="metric in detailMetrics()" :key="metric.label">
                            <div class="rounded-lg bg-slate-50 px-3 py-2">
                                <span class="block text-[10px] font-bold uppercase text-slate-400" x-text="metric.label"></span>
                                <span class="text-sm font-bold text-slate-900" x-text="metric.value"></span>
                            </div>
                        </template>
                    </div>
                    <div>
                        <h5 class="mb-2 text-xs font-bold text-slate-800">Riwayat Terakhir</h5>
                        <div class="space-y-2">
                            <template x-for="movement in detailMovements" :key="movement.time + movement.qty">
                                <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 text-xs">
                                    <div>
                                        <p class="font-semibold text-slate-800" x-text="movement.note"></p>
                                        <p class="text-[10px] text-slate-400" x-text="`${movement.time} - ${movement.user}`"></p>
                                    </div>
                                    <span class="font-bold text-slate-700" x-text="movement.qty"></span>
                                </div>
                            </template>
                            <p x-show="detailMovements.length === 0" class="rounded-lg border border-dashed border-slate-200 py-5 text-center text-xs text-slate-400">Belum ada riwayat movement.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="showPoModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showPoModal=false" style="display:none;">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-bold text-slate-900" x-text="poDraft?.po_number ? `Draft ${poDraft.po_number}` : 'Draft Purchase Order'"></h3>
                    <button @click="showPoModal=false" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="space-y-3 px-6 py-5">
                    <p class="text-xs text-slate-500" x-text="poDraft?.message"></p>
                    <template x-for="item in (poDraft?.items || [])" :key="item.sku">
                        <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2">
                            <div>
                                <p class="text-xs font-bold text-slate-900" x-text="item.name"></p>
                                <p class="text-[10px] text-slate-500" x-text="`${item.supplier} - ${item.days_left} - ${item.priority}`"></p>
                            </div>
                            <span class="text-xs font-bold text-emerald-700" x-text="`${item.qty} ${item.unit}`"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <style>
            [x-cloak] { display: none !important; }
            .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
            .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; border-radius: 4px; }
            .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
            .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        </style>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('inventoryDashboard', () => ({
                categoryFilter: 'all',
                barnFilter: 'all',
                searchQuery: '',
                tableStatusFilter: 'all',
                consumptionRange: '7d',
                usageFilter: 'all',
                chartReady: false,
                chartAttempts: 0,
                showCreateModal: false,
                showAdjustModal: false,
                showDetailModal: false,
                showPoModal: false,
                adjustItem: null,
                adjustType: 'inflow',
                detailItem: null,
                detailMovements: [],
                poDraft: null,
                _consumptionChart: null,
                _usageChart: null,

                get adjustAction() {
                    return this.adjustItem ? `{{ url('/inventory/items') }}/${this.adjustItem.raw_id}/adjust` : '#';
                },

                init() {
                    this.waitForCharts();
                },

                waitForCharts() {
                    if (typeof Chart === 'undefined') {
                        this.chartAttempts++;
                        if (this.chartAttempts < 30) setTimeout(() => this.waitForCharts(), 100);
                        return;
                    }
                    this.chartReady = true;
                    this.$nextTick(() => {
                        this.renderConsumption();
                        this.renderUsage();
                    });
                },

                matchesRow(row) {
                    const category = row.dataset.category;
                    const status = row.dataset.status;
                    const barn = row.dataset.barn;
                    const search = row.dataset.search || '';
                    return (this.categoryFilter === 'all' || this.categoryFilter === category)
                        && (this.tableStatusFilter === 'all' || this.tableStatusFilter === status)
                        && (this.barnFilter === 'all' || this.barnFilter === barn)
                        && (this.searchQuery === '' || search.includes(this.searchQuery.toLowerCase()));
                },

                openAdjust(item) {
                    this.adjustItem = item;
                    this.adjustType = item?.status === 'critical' ? 'inflow' : 'outflow';
                    this.showAdjustModal = true;
                },

                async openDetail(item) {
                    this.detailItem = item;
                    this.detailMovements = [];
                    this.showDetailModal = true;
                    try {
                        const response = await fetch(`{{ url('/inventory/items') }}/${item.raw_id}`, { headers: { 'Accept': 'application/json' } });
                        if (response.ok) {
                            const data = await response.json();
                            this.detailItem = data.item;
                            this.detailMovements = data.movements || [];
                        }
                    } catch (error) {}
                },

                detailMetrics() {
                    if (!this.detailItem) return [];
                    return [
                        { label: 'Stok', value: this.detailItem.stock_label },
                        { label: 'Pakai/Hari', value: this.detailItem.daily_usage_label },
                        { label: 'Sisa', value: this.detailItem.days_left_label },
                        { label: 'Supplier', value: this.detailItem.supplier || '-' },
                    ];
                },

                openRestockPlan(item) {
                    this.poDraft = {
                        po_number: null,
                        message: 'Action plan restock untuk item prioritas.',
                        items: [{
                            sku: item.id,
                            name: item.name,
                            supplier: item.supplier,
                            qty: Math.max(1, Math.ceil((item.lead_time || 1) * 2)),
                            unit: item.current_stock?.split(' ').slice(1).join(' ') || '',
                            priority: item.priority,
                            days_left: item.days_label,
                        }],
                    };
                    this.showPoModal = true;
                },

                async generatePurchaseOrder() {
                    try {
                        const response = await fetch(@js(route('inventory.purchase-order')), {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': @js(csrf_token()),
                            },
                        });
                        this.poDraft = await response.json();
                    } catch (error) {
                        this.poDraft = { message: 'Gagal membuat draft PO.', items: [] };
                    }
                    this.showPoModal = true;
                },

                async loadAnalysis() {
                    try {
                        const response = await fetch(@js(route('inventory.analysis')), { headers: { 'Accept': 'application/json' } });
                        const data = await response.json();
                        this.poDraft = {
                            message: `Analysis: ${data.critical} critical, ${data.warning} warning, ${data.optimal} optimal dari ${data.total_items} item.`,
                            items: (data.top_risk || []).map((item) => ({
                                sku: item.id,
                                name: item.name,
                                supplier: item.supplier || '-',
                                qty: item.stock,
                                unit: item.unit,
                                priority: item.priority,
                                days_left: item.days_left_label,
                            })),
                        };
                    } catch (error) {
                        this.poDraft = { message: 'Gagal mengambil analysis inventaris.', items: [] };
                    }
                    this.showPoModal = true;
                },

                renderConsumption() {
                    if (!this.chartReady) return;
                    const ctx = this.$refs.consumptionCanvas;
                    if (!ctx) return;
                    if (this._consumptionChart) this._consumptionChart.destroy();

                    const labels = @js($charts['consumptionTrend']['labels']);
                    const feedData = @js($charts['consumptionTrend']['layer']);
                    const vitaminData = @js($charts['consumptionTrend']['starter']);
                    const sliceN = this.consumptionRange === '3d' ? 3 : (this.consumptionRange === '5d' ? 5 : 7);

                    this._consumptionChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels.slice(-sliceN),
                            datasets: [
                                { label: 'Pakan', data: feedData.slice(-sliceN), borderColor: '#10B981', backgroundColor: '#10B98110', borderWidth: 2, fill: true, tension: 0.35, pointRadius: 0 },
                                { label: 'Vitamin', data: vitaminData.slice(-sliceN), borderColor: '#F59E0B', backgroundColor: '#F59E0B10', borderWidth: 2, fill: true, tension: 0.35, pointRadius: 0 },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 6, font: { size: 10 } } } },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#94A3B8' } },
                                y: { grid: { color: 'rgba(15,23,42,0.06)' }, ticks: { font: { size: 9 }, color: '#94A3B8' } },
                            },
                        },
                    });
                },

                renderUsage() {
                    if (!this.chartReady) return;
                    const ctx = this.$refs.usageCanvas;
                    if (!ctx) return;
                    if (this._usageChart) this._usageChart.destroy();

                    const datasets = [];
                    if (this.usageFilter === 'all' || this.usageFilter === 'pakan') {
                        datasets.push({ label: 'Pakan', data: @js($charts['usagePerBarn']['pakan']), backgroundColor: '#10B981', borderRadius: 4, yAxisID: 'y' });
                    }
                    if (this.usageFilter === 'all' || this.usageFilter === 'vitamin') {
                        datasets.push({ label: 'Vitamin', data: @js($charts['usagePerBarn']['vitamin']), backgroundColor: '#F59E0B', borderRadius: 4, yAxisID: 'y1' });
                    }

                    this._usageChart = new Chart(ctx, {
                        type: 'bar',
                        data: { labels: @js($charts['usagePerBarn']['labels']), datasets },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 6, font: { size: 10 } } } },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#94A3B8' } },
                                y: { position: 'left', grid: { color: 'rgba(15,23,42,0.06)' }, ticks: { font: { size: 9 }, color: '#94A3B8' } },
                                y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: { font: { size: 9 }, color: '#94A3B8' } },
                            },
                        },
                    });
                },
            }));
        });
    </script>
@endpush
