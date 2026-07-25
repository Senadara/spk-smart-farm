@extends('layouts.app')

@section('title', 'Produk Supplier')
@section('breadcrumb', 'Produk')

@section('content')
<div class="mx-auto max-w-[1600px] space-y-5">
    @if(session('success') || session('error'))
        <div class="rounded-lg border px-4 py-3 text-sm font-semibold {{ session('success') ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
            {{ session('success') ?? session('error') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="mb-1 text-sm font-medium text-emerald-700">{{ $store->nama }}</p>
            <h1 class="text-2xl font-bold text-slate-900">Katalog Produk</h1>
            <p class="mt-1 text-sm text-slate-500">Pantau harga dan stok produk tanpa form yang menumpuk di halaman utama.</p>
        </div>
        <a href="{{ route('supplier.products.create') }}"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white no-underline transition hover:bg-emerald-700">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
            </svg>
            Tambah Produk
        </a>
    </div>

    <form method="GET" class="rounded-lg border border-slate-200 bg-white p-2.5">
        <div class="flex flex-wrap items-center gap-2">
            <input name="search" value="{{ request('search') }}" placeholder="Cari nama produk..."
                class="h-10 min-w-[220px] flex-1 rounded-lg border border-slate-200 px-3 text-sm focus:border-emerald-400 focus:outline-none">
            <select name="stock" class="h-10 min-w-[150px] flex-1 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-emerald-400 focus:outline-none sm:flex-none">
                <option value="">Semua stok</option>
                <option value="available" @selected(request('stock') === 'available')>Stok tersedia</option>
                <option value="low" @selected(request('stock') === 'low')>Stok menipis</option>
            </select>
            <select name="category" class="h-10 min-w-[180px] flex-1 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-emerald-400 focus:outline-none sm:flex-none">
                <option value="">Semua kategori</option>
                @foreach($categoryOptions ?? [] as $category)
                    <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                @endforeach
            </select>
            <button class="h-10 flex-1 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 sm:flex-none">Terapkan</button>
        </div>
    </form>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-[980px] w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Produk</th>
                        <th class="px-5 py-3">Kategori</th>
                        <th class="px-5 py-3">Harga</th>
                        <th class="px-5 py-3">Stok</th>
                        <th class="px-5 py-3">Batas</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($products as $product)
                        @php
                            $imageUrl = $product->gambar
                                ? (\Illuminate\Support\Str::startsWith($product->gambar, ['http://', 'https://']) ? $product->gambar : asset('storage/'.$product->gambar))
                                : null;
                            $minimumStock = (int) ($product->minimum_stock ?? 10);
                            $restockQty = (int) ($product->restock_quantity ?? 0);
                            $isLowStock = (int) $product->stok <= $minimumStock;
                        @endphp
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
                                        @if($imageUrl)
                                            <img src="{{ $imageUrl }}" alt="{{ $product->nama }}" class="h-full w-full object-cover">
                                        @else
                                            <svg class="h-5 w-5 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-slate-900">{{ $product->nama }}</p>
                                        <p class="mt-0.5 line-clamp-1 text-xs text-slate-500">{{ $product->deskripsi }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $product->kategori ?: '-' }}</td>
                            <td class="px-5 py-3 font-semibold text-slate-900">Rp {{ number_format($product->harga, 0, ',', '.') }}</td>
                            <td class="px-5 py-3">
                                <span class="font-semibold {{ $isLowStock ? 'text-amber-700' : 'text-slate-800' }}">
                                    {{ number_format((int) $product->stok, 0, ',', '.') }} {{ $product->satuan }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500">
                                Min {{ number_format($minimumStock, 0, ',', '.') }} {{ $product->satuan }}
                                <span class="block">Restok {{ number_format($restockQty, 0, ',', '.') }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $isLowStock ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                                    {{ $isLowStock ? 'Perlu restok' : 'Aman' }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('supplier.products.stock.edit', $product) }}"
                                        class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 no-underline transition hover:bg-emerald-100">
                                        Restok
                                    </a>
                                    <a href="{{ route('supplier.products.edit', $product) }}"
                                        class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 no-underline transition hover:bg-slate-50">
                                        Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center">
                                <p class="font-semibold text-slate-800">Belum ada produk</p>
                                <p class="mt-1 text-sm text-slate-500">Tambahkan produk pertama agar katalog toko mulai terisi.</p>
                                <a href="{{ route('supplier.products.create') }}" class="mt-4 inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white no-underline">
                                    Tambah Produk
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{ $products->links() }}
</div>
@endsection
