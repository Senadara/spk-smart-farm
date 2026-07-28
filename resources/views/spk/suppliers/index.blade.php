@extends('layouts.app')

@section('title', 'Belanja Supplier')
@section('breadcrumb', 'Supplier')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <section class="rounded-lg border border-gray-200 bg-white p-5 md:p-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Belanja Supplier</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Cari toko, pilih barang, pantau pesanan</h1>
                <p class="mt-2 text-sm text-gray-500">Alur dibuat sederhana untuk pembelian kebutuhan peternakan. Pembayaran tetap dikonfirmasi langsung dengan supplier.</p>
            </div>

            <div class="space-y-2 lg:w-[560px]">
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <a href="{{ route('spk.suppliers.index') }}" class="rounded-lg bg-emerald-600 px-4 py-3 text-center text-sm font-bold text-white hover:bg-emerald-700">
                        Cari Toko
                    </a>
                    <a href="{{ route('spk.suppliers.products') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-center text-sm font-bold text-gray-700 hover:bg-gray-50">
                        Bandingkan Barang
                    </a>
                    <a href="{{ route('spk.suppliers.orders.index') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-center text-sm font-bold text-gray-700 hover:bg-gray-50">
                        Pesanan Saya
                        @if(($orderSummary['active'] ?? 0) > 0)
                            <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">{{ $orderSummary['active'] }}</span>
                        @endif
                    </a>
                </div>
            </div>
        </div>

        <ol class="mt-5 grid grid-cols-1 gap-4 border-t border-gray-100 pt-4 md:grid-cols-3">
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">1</span>
                <div>
                    <p class="text-sm font-bold text-gray-900">Pilih toko</p>
                    <p class="mt-1 text-xs text-gray-500">Cari toko dari nama, kota, atau kategori kebutuhan.</p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">2</span>
                <div>
                    <p class="text-sm font-bold text-gray-900">Pesan barang</p>
                    <p class="mt-1 text-xs text-gray-500">Pilih jumlah. Pembayaran dikonfirmasi langsung dengan supplier.</p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">3</span>
                <div>
                    <p class="text-sm font-bold text-gray-900">Pantau status</p>
                    <p class="mt-1 text-xs text-gray-500">Cek histori dan batalkan pesanan yang masih menunggu.</p>
                </div>
            </li>
        </ol>
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-4 md:p-5">
        <form action="{{ route('spk.suppliers.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-[240px] flex-1">
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Cari toko, kota, pakan, vaksin..."
                    class="h-11 w-full rounded-lg border border-gray-300 pl-9 pr-3 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
            </div>
            <button class="h-11 flex-1 rounded-lg bg-gray-900 px-5 text-sm font-bold text-white hover:bg-gray-800 sm:flex-none">Cari</button>
        </form>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            @foreach([
                'all' => 'Semua',
                'pakan' => 'Pakan',
                'obat' => 'Obat & Vaksin',
                'alat' => 'Peralatan',
            ] as $key => $label)
                <a href="{{ $key === 'all' ? route('spk.suppliers.index') : route('spk.suppliers.index', ['category' => $key]) }}"
                    class="rounded-full px-4 py-2 text-sm font-semibold {{ $category === $key ? 'bg-emerald-600 text-white' : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach

            <div class="ml-auto flex flex-wrap gap-2 text-xs">
                <a href="{{ route('spk.suppliers.dss.config') }}" class="text-gray-400 hover:text-emerald-700">Atur bobot SPK</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('spk.suppliers.dss.dashboard') }}" class="text-gray-400 hover:text-emerald-700">Lihat ranking SAW</a>
            </div>
        </div>
    </section>

    @if(count($suppliers) === 0)
        <div class="rounded-lg border border-dashed border-gray-200 bg-white p-10 text-center">
            <h2 class="font-bold text-gray-900">Tidak ada toko ditemukan</h2>
            <p class="mt-1 text-sm text-gray-500">Coba kata kunci lain atau pilih kategori Semua.</p>
        </div>
    @else
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($suppliers as $supplier)
                <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex gap-4">
                        <img src="{{ $supplier['logo'] }}" alt="Logo {{ $supplier['name'] }}" class="h-14 w-14 shrink-0 rounded-lg object-cover ring-1 ring-gray-100">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h2 class="truncate text-base font-bold text-gray-900">{{ $supplier['name'] }}</h2>
                                    <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ $supplier['location'] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-gray-100 pt-3">
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Jarak Info</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">{{ $supplier['distance'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Estimasi</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-900">{{ $supplier['delivery_estimate'] }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($supplier['categories'] as $cat)
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-semibold text-gray-600">{{ $cat }}</span>
                        @endforeach
                    </div>

                    <div class="mt-4 border-t border-gray-100 pt-3">
                        @if($supplier['has_store'] && $supplier['store_product_count'] > 0)
                            <p class="text-sm font-bold text-gray-900">{{ number_format($supplier['store_product_count']) }} barang bisa dipesan</p>
                            <p class="mt-1 text-xs text-gray-500">Buka toko untuk memilih barang dan jumlah pesanan.</p>
                        @elseif($supplier['has_store'])
                            <p class="text-sm font-bold text-gray-900">Toko sudah terdaftar</p>
                            <p class="mt-1 text-xs text-gray-500">Barang belum tersedia, hubungi supplier untuk pemesanan manual.</p>
                        @else
                            <p class="text-sm font-bold text-gray-900">Pemesanan manual</p>
                            <p class="mt-1 text-xs text-gray-500">Supplier belum mengaktifkan katalog web.</p>
                        @endif
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('spk.suppliers.show', $supplier['id']) }}" class="flex-1 rounded-lg bg-emerald-600 px-4 py-2.5 text-center text-sm font-bold text-white hover:bg-emerald-700">
                            Lihat Barang
                        </a>
                        @if(!empty($supplier['phone']))
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $supplier['phone']) }}" target="_blank" class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50">
                                WhatsApp
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>
    @endif
</div>
@endsection
