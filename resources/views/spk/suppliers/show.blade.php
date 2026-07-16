@extends('layouts.app')

@section('title', 'Detail Supplier')
@section('breadcrumb', 'Supplier > Detail')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-10">
    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <section class="rounded-lg border border-gray-200 bg-white p-5 md:p-6">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex min-w-0 gap-4">
                <img src="{{ $supplier['logo'] }}" alt="Logo {{ $supplier['name'] }}" class="h-16 w-16 shrink-0 rounded-lg object-cover ring-1 ring-gray-100">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Toko Supplier</p>
                    <h1 class="mt-1 truncate text-2xl font-bold text-gray-900">{{ $supplier['name'] }}</h1>
                    <p class="mt-2 max-w-3xl text-sm text-gray-500">{{ $supplier['location'] }}</p>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-700">{{ $supplier['distance'] }}</span>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">{{ $supplier['delivery_estimate'] }}</span>
                    </div>
                </div>
            </div>

            <div class="space-y-2 lg:w-[560px]">
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <a href="{{ route('spk.suppliers.index') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-center text-sm font-bold text-gray-700 hover:bg-gray-50">
                        Cari Toko
                    </a>
                    <a href="{{ route('spk.suppliers.products') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-center text-sm font-bold text-gray-700 hover:bg-gray-50">
                        Bandingkan Barang
                    </a>
                    <a href="{{ route('spk.suppliers.orders.index') }}" class="rounded-lg bg-emerald-600 px-4 py-3 text-center text-sm font-bold text-white hover:bg-emerald-700">
                        Pesanan Saya
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">
        <section class="rounded-lg border border-gray-200 bg-white p-5 md:p-6">
            <div class="flex flex-col gap-2 border-b border-gray-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Barang Siap Dipesan</h2>
                    <p class="mt-1 text-sm text-gray-500">Pilih jumlah barang, lalu pesanan akan masuk ke histori dengan status Menunggu.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Pembayaran di luar sistem</span>
            </div>

            @if($store && $storeProducts->isNotEmpty())
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($storeProducts as $product)
                        @php
                            $productImage = $product->gambar
                                ? (\Illuminate\Support\Str::startsWith($product->gambar, ['http://', 'https://']) ? $product->gambar : asset('storage/'.$product->gambar))
                                : null;
                        @endphp
                        <form method="POST" action="{{ route('spk.suppliers.orders.store', $supplier['id']) }}" class="flex h-full flex-col rounded-lg border border-gray-200 p-4">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <div class="mb-4 h-32 overflow-hidden rounded-lg bg-gray-100">
                                @if($productImage)
                                    <img src="{{ $productImage }}" alt="{{ $product->nama }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-lg font-black text-gray-300">{{ strtoupper(substr($product->nama, 0, 2)) }}</div>
                                @endif
                            </div>
                            <div class="flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="font-bold text-gray-900">{{ $product->nama }}</h3>
                                    <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ number_format($product->stok) }} {{ $product->satuan }}</span>
                                </div>
                                <p class="mt-2 line-clamp-2 text-xs text-gray-500">{{ $product->deskripsi ?: 'Deskripsi produk belum tersedia.' }}</p>
                                <p class="mt-4 text-lg font-black text-gray-900">Rp {{ number_format($product->harga, 0, ',', '.') }}</p>
                            </div>

                            <div class="mt-4 flex gap-2">
                                <label class="sr-only" for="quantity-{{ $product->id }}">Jumlah</label>
                                <input id="quantity-{{ $product->id }}" name="quantity" type="number" min="1" max="{{ $product->stok }}" value="1" required
                                    class="w-24 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                <button class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700">
                                    Pesan
                                </button>
                            </div>
                        </form>
                    @endforeach
                </div>
            @else
                <div class="mt-5 rounded-lg border border-dashed border-gray-200 bg-gray-50 p-8 text-center">
                    <p class="font-semibold text-gray-700">Katalog web toko ini belum tersedia.</p>
                    <p class="mt-1 text-sm text-gray-500">Gunakan kontak supplier untuk pemesanan manual.</p>
                </div>
            @endif
        </section>

        <aside class="space-y-4">
            <section class="rounded-lg border border-gray-200 bg-white p-5">
                <h2 class="font-bold text-gray-900">Kontak Toko</h2>
                <p class="mt-1 text-sm text-gray-500">Gunakan kontak ini untuk konfirmasi pembayaran dan pengiriman.</p>
                <div class="mt-4 grid grid-cols-1 gap-2">
                    @if(!empty($supplier['phone']))
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $supplier['phone']) }}" target="_blank" class="rounded-lg bg-[#25D366] px-4 py-2.5 text-center text-sm font-bold text-white hover:bg-[#1DA851]">
                            WhatsApp Supplier
                        </a>
                    @endif
                    @if($supplier['maps_url'])
                        <a href="{{ $supplier['maps_url'] }}" target="_blank" class="rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-center text-sm font-bold text-gray-700 hover:bg-gray-50">
                            Buka Google Maps
                        </a>
                    @endif
                    @if(empty($supplier['phone']) && ! $supplier['maps_url'])
                        <p class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500">Kontak supplier belum tersedia.</p>
                    @endif
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5">
                <h2 class="font-bold text-gray-900">Ringkasan SPK</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500">Skor rekomendasi</dt>
                        <dd class="font-bold text-emerald-700">{{ $supplier['score'] }}%</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500">Estimasi</dt>
                        <dd class="text-right font-bold text-gray-900">{{ $supplier['delivery_estimate'] }}</dd>
                    </div>
                </dl>
            </section>
        </aside>
    </div>

    <section class="rounded-lg border border-gray-200 bg-white p-5 md:p-6">
        <div class="flex flex-col gap-2 border-b border-gray-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Referensi Data SPK</h2>
                <p class="mt-1 text-sm text-gray-500">Data ini dipakai untuk pembanding AHP/SAW, bukan stok toko real-time.</p>
            </div>
            <a href="{{ route('spk.suppliers.products') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">Bandingkan barang</a>
        </div>

        @if(empty($inventories))
            <div class="py-8 text-center text-sm text-gray-500">Belum ada referensi barang dari data SPK.</div>
        @else
            <div class="mt-4 overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Barang</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500">Kategori</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-gray-500">Estimasi Harga</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach($inventories as $inv)
                            <tr>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $inv['name'] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $inv['type'] }}</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900">Rp {{ number_format($inv['price'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
