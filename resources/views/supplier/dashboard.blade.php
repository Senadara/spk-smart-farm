@extends('layouts.app')

@section('title', 'Dashboard Supplier')
@section('breadcrumb', 'Dashboard Toko')

@section('content')
<div class="max-w-[1600px] mx-auto space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-medium text-emerald-700 mb-1">Panel Supplier</p>
            <h1 class="text-2xl font-bold text-gray-900">{{ $store?->nama ?? 'Toko belum dibuat' }}</h1>
            <p class="text-sm text-gray-500 mt-1">Kelola katalog, pesanan, dan performa penjualan dalam satu tempat.</p>
        </div>
        <a href="{{ route('supplier.store.edit') }}"
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-white border border-gray-200 text-sm font-semibold text-gray-700 hover:border-emerald-300 hover:text-emerald-700 transition-colors no-underline">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L8 18l-4 1 1-4L16.5 3.5z"/>
            </svg>
            Atur Profil Toko
        </a>
    </div>

    @if(!$store)
        <section class="bg-white border border-gray-200 rounded-lg p-8 md:p-12 text-center">
            <div class="w-14 h-14 mx-auto rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center mb-4">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9l2-5h14l2 5M5 13v7h14v-7M3 9a3 3 0 006 0 3 3 0 006 0 3 3 0 006 0"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold text-gray-900">Mulai dengan profil toko</h2>
            <p class="text-sm text-gray-500 max-w-lg mx-auto mt-2">Identitas toko diperlukan sebelum produk dan pesanan dapat dikelola.</p>
            <a href="{{ route('supplier.store.edit') }}"
                class="inline-flex mt-5 px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 no-underline">
                Buat Profil Toko
            </a>
        </section>
    @else
        @php
            $statusStyles = [
                'active' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'request' => 'bg-amber-50 text-amber-700 border-amber-200',
                'reject' => 'bg-red-50 text-red-700 border-red-200',
            ];
            $statusLabels = ['active' => 'Aktif', 'request' => 'Menunggu Aktivasi', 'reject' => 'Ditolak'];
        @endphp

        @if($store->tokoStatus !== 'active')
            <div class="flex gap-3 rounded-lg border p-4 {{ $statusStyles[$store->tokoStatus] ?? 'bg-gray-50 text-gray-700 border-gray-200' }}">
                <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.6L2.6 17a2 2 0 001.7 3h15.4a2 2 0 001.7-3L13.7 3.6a2 2 0 00-3.4 0z"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold">Status toko: {{ $statusLabels[$store->tokoStatus] ?? ucfirst($store->tokoStatus) }}</p>
                    <p class="text-xs mt-1">Produk dapat disiapkan, tetapi toko baru tampil untuk pembeli setelah disetujui admin.</p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach([
                ['label' => 'Produk Aktif', 'value' => number_format($metrics['products']), 'accent' => 'bg-emerald-500'],
                ['label' => 'Stok Menipis', 'value' => number_format($metrics['low_stock']), 'accent' => 'bg-amber-500'],
                ['label' => 'Pesanan Baru', 'value' => number_format($metrics['pending_orders']), 'accent' => 'bg-blue-500'],
                ['label' => 'Omzet Bulan Ini', 'value' => 'Rp '.number_format($metrics['monthly_revenue'], 0, ',', '.'), 'accent' => 'bg-violet-500'],
            ] as $metric)
                <section class="bg-white border border-gray-200 rounded-lg p-5">
                    <div class="w-8 h-1 rounded-full {{ $metric['accent'] }} mb-4"></div>
                    <p class="text-xs font-semibold uppercase text-gray-400">{{ $metric['label'] }}</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2">{{ $metric['value'] }}</p>
                </section>
            @endforeach
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-[1.3fr_1fr] gap-6">
            <section class="bg-white border border-gray-200 rounded-lg p-5">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="font-bold text-gray-900">Tren Omzet</h2>
                        <p class="text-xs text-gray-500 mt-1">Pesanan selesai dalam enam bulan terakhir</p>
                    </div>
                    <a href="{{ route('supplier.finance') }}" class="text-xs font-semibold text-emerald-700 no-underline">Detail</a>
                </div>
                @php
                    $maxSales = max(max($salesChart['values']), 1);
                @endphp
                <div class="h-56 flex items-end gap-3 border-b border-gray-200 px-1">
                    @foreach($salesChart['values'] as $index => $value)
                        <div class="flex-1 h-full flex flex-col justify-end items-center gap-2 min-w-0">
                            <span class="text-[10px] text-gray-500 truncate w-full text-center">
                                {{ $value > 0 ? number_format($value / 1000, 0).'k' : '0' }}
                            </span>
                            <div class="w-full max-w-12 bg-emerald-500 rounded-t-sm hover:bg-emerald-600 transition-colors"
                                style="height: {{ max(4, ($value / $maxSales) * 160) }}px"></div>
                            <span class="text-xs text-gray-500 pb-2">{{ $salesChart['labels'][$index] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="p-5 flex items-center justify-between border-b border-gray-100">
                    <div>
                        <h2 class="font-bold text-gray-900">Stok Perlu Perhatian</h2>
                        <p class="text-xs text-gray-500 mt-1">Produk dengan stok terendah</p>
                    </div>
                    <a href="{{ route('supplier.products.index', ['stock' => 'low']) }}" class="text-xs font-semibold text-emerald-700 no-underline">Kelola</a>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($products as $product)
                        @php
                            $minimumStock = (int) ($product->minimum_stock ?? 10);
                            $isLowStock = (int) $product->stok <= $minimumStock;
                        @endphp
                        <div class="px-5 py-3 flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate">{{ $product->nama }}</p>
                                <p class="text-xs text-gray-500">Rp {{ number_format($product->harga, 0, ',', '.') }} / min {{ $minimumStock }} {{ $product->satuan }}</p>
                            </div>
                            <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-semibold {{ $isLowStock ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $product->stok }} {{ $product->satuan }}
                            </span>
                        </div>
                    @empty
                        <div class="p-8 text-center text-sm text-gray-500">Belum ada produk.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="p-5 flex items-center justify-between border-b border-gray-100">
                <div>
                    <h2 class="font-bold text-gray-900">Pesanan Terbaru</h2>
                    <p class="text-xs text-gray-500 mt-1">Aktivitas transaksi toko terkini</p>
                </div>
                <a href="{{ route('supplier.orders.index') }}" class="text-xs font-semibold text-emerald-700 no-underline">Lihat Semua</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="text-left px-5 py-3">Pesanan</th>
                            <th class="text-left px-5 py-3">Pembeli</th>
                            <th class="text-left px-5 py-3">Total</th>
                            <th class="text-left px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($recentOrders as $order)
                            <tr>
                                <td class="px-5 py-3 font-mono text-xs text-gray-600">#{{ strtoupper(substr($order->id, 0, 8)) }}</td>
                                <td class="px-5 py-3 text-gray-700">{{ $order->customer?->name ?? 'Pembeli' }}</td>
                                <td class="px-5 py-3 font-semibold text-gray-900">Rp {{ number_format($order->totalHarga, 0, ',', '.') }}</td>
                                <td class="px-5 py-3"><span class="text-xs font-semibold capitalize text-gray-600">{{ $order->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-gray-500">Belum ada pesanan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
