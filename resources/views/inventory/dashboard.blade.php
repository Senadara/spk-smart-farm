@extends('layouts.app')

@section('title', 'Monitoring Inventaris')
@section('breadcrumb', 'Inventaris')

@section('content')
    @php
        $statusMeta = [
            'critical' => ['label' => 'Critical', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
            'warning' => ['label' => 'Warning', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
            'optimal' => ['label' => 'Optimal', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        ];
        $kpiHints = [
            'Total Item' => [
                'body' => 'Jumlah item inventaris aktif yang sedang dipantau dari sinkronisasi mobile/API.',
                'formula' => 'COUNT(inventory_items aktif)',
                'source' => 'inventory_items',
            ],
            'Low Stock' => [
                'body' => 'Item yang sudah melewati batas reorder point atau estimasi habis mendekati lead time.',
                'formula' => 'stok <= reorder_point atau sisa_hari <= lead_time + safety_stock',
                'source' => 'inventory_items',
            ],
            'Critical Stock' => [
                'body' => 'Item yang perlu segera diprioritaskan karena stok berada di bawah minimum atau akan habis sangat dekat.',
                'formula' => 'stok <= minimum_stock atau sisa_hari <= lead_time',
                'source' => 'inventory_items',
            ],
            'Avg. Sisa Hari' => [
                'body' => 'Rata-rata estimasi berapa hari stok masih cukup berdasarkan pemakaian harian.',
                'formula' => 'AVG(stok / pemakaian_harian)',
                'source' => 'inventory_items, inventory_movements',
            ],
        ];
        $canAccessSupplierOrders = in_array(session('user.role'), ['pjawab', 'owner', 'admin'], true);
    @endphp

    <div x-data="inventoryDashboard()" class="max-w-full space-y-5" x-cloak>
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        @if(isset($errors) && $errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="h-1 bg-gradient-to-r from-emerald-500 via-sky-500 to-amber-500"></div>
            <div class="flex flex-col gap-4 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Inventaris</p>
                    <h1 class="mt-1 text-xl font-bold text-slate-900">Monitoring Stok & Restock Supplier</h1>
                    <p class="mt-0.5 text-xs text-slate-500">Data stok berasal dari aplikasi mobile/API Node.js. Web ini fokus pada SPK restock dan pemesanan supplier.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <x-dashboard-hint-toggle />
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
                    @if($canAccessSupplierOrders)
                        <a href="{{ route('spk.suppliers.products') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700" style="text-decoration:none;">
                            Cari Barang Supplier
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <x-page-hint title="Alur inventaris yang dipakai" tone="amber" :open="false">
            Barang, stok awal, barang masuk, stok keluar, dan pemakaian harian tetap dicatat dari mobile/API Node.js. Laravel membaca proyeksi stok tersebut, menampilkan stok yang menipis, lalu membantu mencari supplier terbaik dengan SPK AHP-SAW sebelum barang dimasukkan ke keranjang pemesanan.
        </x-page-hint>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($kpi as $m)
                @php
                    $hint = $kpiHints[$m['label']] ?? null;
                @endphp
                <x-peternakan.kpi-card
                    :label="$m['label']"
                    :value="$m['value']"
                    :trend="$m['trend']"
                    :hint="$hint['body'] ?? null"
                    :formula="$hint['formula'] ?? null"
                    :source="$hint['source'] ?? null"
                />
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <div class="min-w-0 rounded-xl border border-slate-100 bg-white p-4 shadow-sm md:p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Tren Pemakaian Stok</h3>
                        <p class="text-[11px] text-slate-400">Outflow 7 hari terakhir dari sinkronisasi stok.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-metric-hint title="Tren Pemakaian Stok" body="Grafik ini menunjukkan stok keluar selama beberapa hari terakhir. Jika belum ada movement, sistem memakai estimasi pemakaian harian item." formula="SUM(inventory_movements outflow) per tanggal" source="inventory_movements, inventory_items" />
                        <div class="flex rounded-lg bg-slate-100 p-0.5">
                        <template x-for="r in [{v:'3d',l:'3H'},{v:'5d',l:'5H'},{v:'7d',l:'7H'}]" :key="r.v">
                            <button @click="consumptionRange=r.v; renderConsumption()" :class="consumptionRange===r.v ? 'bg-white shadow-sm text-slate-900':'text-slate-500 hover:text-slate-700'" class="rounded-md px-2.5 py-1 text-[10px] font-semibold transition-all" x-text="r.l"></button>
                        </template>
                        </div>
                    </div>
                </div>
                <div class="relative h-[220px]">
                    <canvas x-ref="consumptionCanvas"></canvas>
                    <div x-show="!chartReady" class="absolute inset-0 flex items-center justify-center rounded-lg bg-slate-50 text-xs font-semibold text-slate-500">
                        Grafik siap setelah Chart.js termuat.
                    </div>
                </div>
            </div>

            <div class="min-w-0 rounded-xl border border-slate-100 bg-white p-4 shadow-sm md:p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Distribusi Pemakaian</h3>
                        <p class="text-[11px] text-slate-400">Estimasi pemakaian harian per kandang.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-metric-hint title="Distribusi Pemakaian" body="Grafik ini membandingkan estimasi pemakaian harian pakan dan vitamin pada tiap kandang." formula="SUM(daily_usage) per kandang dan kategori" source="inventory_items, unitBudidaya" />
                        <select x-model="usageFilter" @change="renderUsage()" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600 focus:outline-none focus:ring-1 focus:ring-emerald-300">
                            <option value="all">Semua</option>
                            <option value="pakan">Pakan</option>
                            <option value="vitamin">Vitamin</option>
                        </select>
                    </div>
                </div>
                <div class="relative h-[220px]">
                    <canvas x-ref="usageCanvas"></canvas>
                    <div x-show="!chartReady" class="absolute inset-0 flex items-center justify-center rounded-lg bg-slate-50 text-xs font-semibold text-slate-500">
                        Grafik siap setelah Chart.js termuat.
                    </div>
                </div>
            </div>

            <div class="min-w-0 rounded-xl border border-slate-100 bg-white p-4 shadow-sm md:p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Estimasi Sisa Stok</h3>
                        <p class="text-[11px] text-slate-400">Item paling dekat habis berdasarkan pemakaian harian.</p>
                    </div>
                    <x-metric-hint title="Estimasi Sisa Stok" body="Grafik ini mengurutkan item dengan sisa hari terendah. Item tanpa pemakaian harian dianggap belum punya estimasi akurat." formula="stok / pemakaian_harian" source="inventory_items" />
                </div>
                <div class="relative h-[220px]">
                    <canvas x-ref="durationCanvas"></canvas>
                    <div x-show="!chartReady" class="absolute inset-0 flex items-center justify-center rounded-lg bg-slate-50 text-xs font-semibold text-slate-500">
                        Grafik siap setelah Chart.js termuat.
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-emerald-100 bg-white p-4 shadow-sm md:p-5">
            <div class="mb-4 flex flex-col gap-3 border-b border-slate-100 pb-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Rekomendasi Restock</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $restockView === 'all'
                            ? 'Mode audit menampilkan semua stok, diurutkan dari prioritas restock tertinggi.'
                            : 'Hanya menampilkan stok Critical dan Warning. Klik CTA untuk mencari supplier terbaik.' }}
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <div class="flex rounded-lg bg-slate-100 p-0.5">
                        <a href="{{ route('inventory', request()->except('restock_view')) }}"
                            class="rounded-md px-3 py-1.5 text-[11px] font-bold transition {{ $restockView === 'needs' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}"
                            style="text-decoration:none;">
                            Stok Menipis
                        </a>
                        <a href="{{ route('inventory', array_merge(request()->except('restock_view'), ['restock_view' => 'all'])) }}"
                            class="rounded-md px-3 py-1.5 text-[11px] font-bold transition {{ $restockView === 'all' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}"
                            style="text-decoration:none;">
                            Semua Stok
                        </a>
                    </div>
                    <x-metric-hint title="Rekomendasi Restock" body="Daftar ini diurutkan berdasarkan skor prioritas restock. Setelah dipilih, sistem mencari produk supplier yang cocok lalu meranking supplier dengan AHP-SAW." formula="status_weight + days_weight + lead_time_weight" source="inventory_items, produk/toko supplier, spk supplier" />
                    <span class="rounded border border-emerald-100 bg-emerald-50 px-2 py-1 text-xs font-bold tracking-wide text-emerald-600">{{ count($recommendedRestocks) }} item</span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($recommendedRestocks as $item)
                    @php
                        $pColor = ['Critical' => 'bg-rose-50 text-rose-700 border-rose-100', 'Warning' => 'bg-amber-50 text-amber-700 border-amber-100', 'Safe' => 'bg-slate-50 text-slate-600 border-slate-100'][$item['priority']] ?? 'bg-slate-50 text-slate-600 border-slate-100';
                        $barColor = ['Critical' => 'bg-rose-500', 'Warning' => 'bg-amber-500', 'Safe' => 'bg-emerald-500'][$item['priority']] ?? 'bg-slate-400';
                        $linked = $item['linked_product'];
                    @endphp
                    <div class="rounded-xl border border-slate-100 bg-slate-50/40 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h4 class="line-clamp-2 text-sm font-bold text-slate-900" title="{{ $item['name'] }}">{{ $item['name'] }}</h4>
                                <p class="mt-1 text-xs text-slate-500">{{ $item['category'] }} - {{ $item['current_stock'] }}</p>
                            </div>
                            <span class="shrink-0 whitespace-nowrap rounded border px-2 py-1 text-[10px] font-bold {{ $pColor }}">{{ $item['priority'] }}</span>
                        </div>

                        <div class="mt-4">
                            <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500">
                                <span>Sisa stok</span>
                                <span class="{{ ($item['days_remaining'] ?? 999) <= $item['lead_time'] ? 'text-rose-600' : 'text-slate-700' }}">{{ $item['days_label'] }}</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-white">
                                <div class="h-full rounded-full {{ $barColor }}" style="width: {{ $item['coverage_percent'] }}%"></div>
                            </div>
                            <p class="mt-2 text-[11px] text-slate-500">Lead time {{ $item['lead_time'] }} hari, safety stock {{ $item['safety_stock_days'] }} hari, reorder point {{ $item['reorder_point'] }} {{ $item['reorder_point_source'] }}.</p>
                        </div>

                        <div class="mt-4 rounded-lg {{ $linked ? 'bg-emerald-50 text-emerald-800' : 'bg-sky-50 text-sky-800' }} px-3 py-2 text-xs">
                            <p class="font-bold">{{ $linked ? 'Produk favorit tersimpan' : 'Supplier dicari otomatis' }}</p>
                            <p class="mt-0.5 line-clamp-2">
                                {{ $linked ? $linked['name'].' - '.$linked['store'] : 'Sistem akan mencari produk yang cocok dari katalog supplier, lalu memberi urutan rekomendasi.' }}
                            </p>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
                            <span class="rounded border border-slate-100 bg-white px-2 py-1 font-mono text-[11px] text-slate-600">Score {{ $item['score'] }}</span>
                            <div class="flex flex-wrap gap-1.5">
                                @if($canAccessSupplierOrders)
                                    <a href="{{ $item['recommend_url'] }}" class="rounded-md bg-slate-900 px-3 py-1.5 text-[11px] font-semibold text-white transition hover:bg-slate-700" style="text-decoration:none;">
                                        Cari Supplier Terbaik
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-xs text-slate-500 md:col-span-2 xl:col-span-3">
                        {{ $restockView === 'all'
                            ? 'Belum ada item stok tersinkron.'
                            : 'Tidak ada stok yang menipis saat ini. Gunakan mode Semua Stok jika ingin melihat seluruh inventaris.' }}
                    </div>
                @endforelse
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="flex flex-col rounded-xl border border-slate-100 bg-white p-5 shadow-sm lg:col-span-2">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Stok Tersinkron</h3>
                        <p class="text-[11px] text-slate-400">Read-only dari mobile/API Node.js. Tidak ada input stok manual di web.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-metric-hint title="Stok Tersinkron" body="Tabel ini menampilkan stok hasil sinkronisasi laporan dan movement. Web Laravel tidak mengubah stok utama secara manual." formula="stok sekarang, daily_usage, sisa_hari, status" source="inventory_items, inventory_movements" />
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
                    <table class="w-full min-w-[880px] text-left text-sm">
                        <thead class="sticky top-0 z-10 bg-white">
                            <tr class="border-b border-slate-100 text-[10px] uppercase tracking-wider text-slate-400">
                                <th class="pb-2 font-medium">Item & Kategori</th>
                                <th class="pb-2 font-medium">Kandang</th>
                                <th class="pb-2 text-right font-medium">Stok</th>
                                <th class="pb-2 text-right font-medium">Pakai/Hari</th>
                                <th class="pb-2 text-right font-medium">Est. Habis</th>
                                <th class="pb-2 font-medium">Alur Restock</th>
                                <th class="pb-2 text-center font-medium">Status</th>
                                <th class="pb-2 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse ($inventoryItems as $item)
                                @php
                                    $meta = $statusMeta[$item['status']] ?? $statusMeta['optimal'];
                                    $linked = $item['linked_product'];
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
                                    <td class="py-2.5 text-xs">
                                        @if($linked)
                                            <p class="font-semibold text-slate-800">Produk favorit tersedia</p>
                                            <p class="text-[10px] text-slate-500">{{ $linked['name'] }} - {{ $linked['store'] }}</p>
                                        @else
                                            <span class="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-700">Cari otomatis via SPK</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 text-center">
                                        <span class="rounded-full border px-2 py-0.5 text-[9px] font-semibold {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                                    </td>
                                    <td class="py-2.5 text-right">
                                        <div class="flex justify-end gap-1">
                                            <button @click="openDetail(@js($item))" class="rounded-md bg-slate-50 px-2.5 py-1 text-[10px] font-semibold text-slate-600 transition hover:bg-slate-100">
                                                Detail
                                            </button>
                                            @if($canAccessSupplierOrders)
                                                <button @click="openConfig(@js($item))" class="rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-semibold text-slate-600 transition hover:bg-slate-50">
                                                    Atur
                                                </button>
                                            @endif
                                            @if($canAccessSupplierOrders)
                                                <a href="{{ route('inventory.items.supplier-recommendations', $item['raw_id']) }}" class="rounded-md bg-slate-900 px-2.5 py-1 text-[10px] font-semibold text-white transition hover:bg-slate-700" style="text-decoration:none;">
                                                    Cari Supplier
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-10 text-center text-xs text-slate-400">Belum ada item stok tersinkron dari mobile/API Node.js.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-start justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Riwayat Sinkronisasi Stok</h3>
                    <p class="text-[11px] text-slate-400">Pergerakan stok dari sinkronisasi laporan dan barang masuk.</p>
                    </div>
                    <x-metric-hint title="Riwayat Sinkronisasi Stok" body="Riwayat ini membaca pergerakan stok masuk, keluar, dan adjustment yang berasal dari sinkronisasi laporan atau data stok." formula="inventory_movements terbaru" source="inventory_movements" />
                </div>
                <div class="custom-scrollbar max-h-[560px] space-y-3 overflow-y-auto pr-1">
                    @forelse ($movementLog as $log)
                        @php
                            $dotColor = ['inflow' => 'bg-emerald-400', 'outflow' => 'bg-sky-400', 'adjustment' => 'bg-amber-400'][$log['type']] ?? 'bg-slate-400';
                            $qtyColor = ['inflow' => 'text-emerald-600', 'outflow' => 'text-sky-600', 'adjustment' => 'text-amber-600'][$log['type']] ?? 'text-slate-600';
                        @endphp
                        <div class="flex gap-3 rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2.5">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $dotColor }}"></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="truncate text-xs font-bold text-slate-800">{{ $log['item'] }}</p>
                                    <span class="shrink-0 text-[10px] font-bold {{ $qtyColor }}">{{ $log['qty'] }}</span>
                                </div>
                                <p class="mt-0.5 text-[10px] text-slate-500">{{ $log['note'] }}</p>
                                <p class="mt-1 text-[10px] text-slate-400">{{ $log['time'] }} - {{ $log['barn'] }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-slate-200 py-8 text-center text-xs text-slate-400">Belum ada pergerakan stok.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div x-show="showConfigModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showConfigModal=false" style="display:none;">
            <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-slate-900">Atur Batas Restock</h3>
                            <x-metric-hint title="Atur Batas Restock" body="Konfigurasi ini hanya mengatur keputusan restock di Laravel. Stok utama tetap berasal dari mobile/API Node.js." formula="reorder point auto = max(stok minimum, pemakaian harian x (lead time + safety stock))" source="inventory_items" />
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500" x-text="configItem ? `${configItem.name} - stok ${configItem.stock_label}` : ''"></p>
                    </div>
                    <button @click="showConfigModal=false" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form method="POST" :action="configAction" class="space-y-5 px-6 py-5">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-bold text-slate-700">Lead time supplier</span>
                            <input type="number" min="1" max="60" name="lead_time_days" x-model="configForm.lead_time_days" required
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none">
                            <span class="mt-1 block text-[10px] text-slate-400">Hari dari pesan sampai barang tersedia.</span>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-bold text-slate-700">Safety stock</span>
                            <input type="number" min="0" max="60" name="safety_stock_days" x-model="configForm.safety_stock_days" required
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none">
                            <span class="mt-1 block text-[10px] text-slate-400">Cadangan hari agar stok tidak terlalu mepet.</span>
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-xs font-bold text-slate-700">Reorder point manual</span>
                            <input type="number" min="0" step="0.01" name="reorder_point_override" x-model="configForm.reorder_point_override"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none"
                                placeholder="Kosongkan untuk auto">
                            <span class="mt-1 block text-[10px] text-slate-400">Opsional, kosongkan agar sistem hitung otomatis.</span>
                        </label>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                        <p class="font-bold text-slate-800">Acuan saat ini</p>
                        <div class="mt-2 grid grid-cols-2 gap-2 md:grid-cols-4">
                            <span>Minimum: <b x-text="configItem?.minimum_stock ?? '-'"></b></span>
                            <span>Pakai/hari: <b x-text="configItem?.daily_usage_label ?? '-'"></b></span>
                            <span>Sisa: <b x-text="configItem?.days_left_label ?? '-'"></b></span>
                            <span>Reorder: <b x-text="configItem?.reorder_point ?? '-'"></b></span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-slate-500">Perubahan ini akan dipakai saat sinkronisasi mobile berikutnya dan saat rekomendasi restock dihitung.</p>
                        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700">
                            Simpan Batas
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showLinkModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showLinkModal=false" style="display:none;">
            <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-slate-900">Hubungkan Produk Supplier</h3>
                            <x-metric-hint title="Hubungkan Produk Supplier" body="Modal ini memetakan item inventaris dari mobile dengan produk supplier agar rekomendasi restock bisa langsung diarahkan ke produk yang benar." formula="inventory_item + supplier_product + conversion_qty" source="inventory_supplier_product_links, supplier_products" />
                        </div>
                        <p class="mt-0.5 text-xs text-slate-500" x-text="linkItem ? `${linkItem.name} - ${linkItem.unit}` : ''"></p>
                    </div>
                    <button @click="showLinkModal=false" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-4 px-6 py-5">
                    <div class="rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-800">
                        Pilih produk supplier yang paling sesuai dengan item dari mobile. Isi konversi berdasarkan satuan stok inventaris. Contoh: jika stok inventaris memakai kg dan produk supplier dijual per sak 50 kg, isi konversi 50 kg.
                    </div>

                    <template x-if="linkItem && linkItem.supplier_candidates.length > 0">
                        <div class="grid grid-cols-1 gap-3">
                            <template x-for="product in linkItem.supplier_candidates" :key="product.id">
                                <form method="POST" :action="linkAction" class="rounded-xl border border-slate-100 p-4">
                                    @csrf
                                    <input type="hidden" name="supplier_product_id" :value="product.id">
                                    <input type="hidden" name="conversion_unit" :value="linkItem?.unit">
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                        <div class="min-w-0">
                                            <p class="font-bold text-slate-900" x-text="product.name"></p>
                                            <p class="mt-1 text-xs text-slate-500" x-text="`${product.store} - stok ${product.stock} ${product.unit} - ${product.category}`"></p>
                                            <p class="mt-1 text-xs font-semibold text-emerald-700" x-text="`Rp ${Number(product.price || 0).toLocaleString('id-ID')} / ${product.unit}`"></p>
                                        </div>
                                        <div class="flex flex-wrap items-end gap-2">
                                            <label class="block">
                                                <span class="mb-1 block text-[10px] font-bold uppercase text-slate-400">1 produk =</span>
                                                <input type="number" step="0.0001" min="0.0001" name="conversion_qty" :value="product.default_conversion || 1" class="w-28 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none">
                                            </label>
                                            <div class="pb-2 text-xs font-semibold text-slate-500" x-text="linkItem?.unit"></div>
                                            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                                                Simpan Mapping
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </template>
                        </div>
                    </template>

                    <template x-if="linkItem && linkItem.supplier_candidates.length === 0">
                        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-5 py-8 text-center">
                            <p class="text-sm font-semibold text-slate-700">Belum ada kandidat produk yang cocok.</p>
                            @if($canAccessSupplierOrders)
                                <a :href="`{{ route('spk.suppliers.products') }}?search=${encodeURIComponent(linkItem.name)}`" class="mt-3 inline-flex rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white" style="text-decoration:none;">
                                    Cari di Marketplace Supplier
                                </a>
                            @endif
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div x-show="showDetailModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDetailModal=false" style="display:none;">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-900">Detail Stok Tersinkron</h3>
                        <x-metric-hint title="Detail Stok Tersinkron" body="Detail ini menjelaskan stok, estimasi pemakaian, sisa hari, supplier terhubung, dan riwayat movement terakhir untuk item yang dipilih." source="inventory_items, inventory_movements, supplier product link" />
                    </div>
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
                showConfigModal: false,
                showLinkModal: false,
                showDetailModal: false,
                configItem: null,
                linkItem: null,
                detailItem: null,
                detailMovements: [],
                configForm: {
                    lead_time_days: 1,
                    safety_stock_days: 5,
                    reorder_point_override: '',
                },
                _consumptionChart: null,
                _usageChart: null,
                _durationChart: null,

                get configAction() {
                    return this.configItem ? `{{ url('/inventory/items') }}/${this.configItem.raw_id}/restock-config` : '#';
                },

                get linkAction() {
                    return this.linkItem ? `{{ url('/inventory/items') }}/${this.linkItem.raw_id}/supplier-links` : '#';
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
                        this.renderDuration();
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

                openLink(item) {
                    this.linkItem = item;
                    this.showLinkModal = true;
                },

                openConfig(item) {
                    this.configItem = item;
                    this.configForm = {
                        lead_time_days: item.lead_time || 1,
                        safety_stock_days: item.safety_stock_days ?? 5,
                        reorder_point_override: item.reorder_point_override ?? '',
                    };
                    this.showConfigModal = true;
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
                        { label: 'Reorder', value: `${this.detailItem.reorder_point} (${this.detailItem.reorder_point_source})` },
                        { label: 'Supplier', value: this.detailItem.linked_product?.store || 'Belum mapping' },
                    ];
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

                renderDuration() {
                    if (!this.chartReady) return;
                    const ctx = this.$refs.durationCanvas;
                    if (!ctx) return;
                    if (this._durationChart) this._durationChart.destroy();

                    const labels = @js($charts['stockDuration']['labels']);
                    const statuses = @js($charts['stockDuration']['statuses']);
                    const colors = statuses.map((status) => {
                        if (status === 'critical') return '#E11D48';
                        if (status === 'warning') return '#F59E0B';
                        return '#10B981';
                    });

                    this._durationChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [{
                                label: 'Sisa hari',
                                data: @js($charts['stockDuration']['days']),
                                backgroundColor: colors,
                                borderRadius: 6,
                            }],
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        afterLabel(context) {
                                            const stocks = @js($charts['stockDuration']['stocks']);
                                            return `Stok: ${stocks[context.dataIndex] || '-'}`;
                                        },
                                    },
                                },
                            },
                            scales: {
                                x: { grid: { color: 'rgba(15,23,42,0.06)' }, ticks: { font: { size: 9 }, color: '#94A3B8' } },
                                y: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#64748B' } },
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
