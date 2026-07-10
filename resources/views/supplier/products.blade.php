@extends('layouts.app')

@section('title', 'Produk Supplier')
@section('breadcrumb', 'Produk')

@section('content')
<div class="max-w-[1600px] mx-auto space-y-5" x-data="{ showCreate: {{ $errors->any() ? 'true' : 'false' }} }">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm font-medium text-emerald-700 mb-1">{{ $store->nama }}</p>
            <h1 class="text-2xl font-bold text-gray-900">Katalog Produk</h1>
            <p class="text-sm text-gray-500 mt-1">Atur informasi, harga, dan ketersediaan stok produk.</p>
        </div>
        <button type="button" @click="showCreate = true"
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
            </svg>
            Tambah Produk
        </button>
    </div>

    <form method="GET" class="bg-white border border-gray-200 rounded-lg p-3 flex flex-col sm:flex-row gap-3">
        <input name="search" value="{{ request('search') }}" placeholder="Cari nama produk..."
            class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">
        <select name="stock" class="rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white">
            <option value="">Semua stok</option>
            <option value="available" @selected(request('stock') === 'available')>Stok tersedia</option>
            <option value="low" @selected(request('stock') === 'low')>Stok menipis</option>
        </select>
        <button class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm font-semibold">Terapkan</button>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($products as $product)
            @php
                $imageUrl = $product->gambar
                    ? (\Illuminate\Support\Str::startsWith($product->gambar, ['http://', 'https://']) ? $product->gambar : asset('storage/'.$product->gambar))
                    : null;
            @endphp
            <article class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="h-40 bg-gray-100 flex items-center justify-center overflow-hidden">
                    @if($imageUrl)
                        <img src="{{ $imageUrl }}" alt="{{ $product->nama }}" class="w-full h-full object-cover">
                    @else
                        <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    @endif
                </div>
                <div class="p-4">
                    <div class="flex justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-bold text-gray-900 truncate">{{ $product->nama }}</h2>
                            <p class="text-sm font-semibold text-emerald-700 mt-1">Rp {{ number_format($product->harga, 0, ',', '.') }}</p>
                        </div>
                        <span class="shrink-0 h-fit px-2 py-1 rounded-full text-xs font-semibold {{ $product->stok <= 10 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                            {{ $product->stok }} {{ $product->satuan }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-3 line-clamp-2 min-h-8">{{ $product->deskripsi }}</p>

                    <details class="mt-4 border-t border-gray-100 pt-3">
                        <summary class="cursor-pointer text-sm font-semibold text-gray-700">Edit produk</summary>
                        <form method="POST" action="{{ route('supplier.products.update', $product) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                            @csrf
                            @method('PUT')
                            <input name="nama" value="{{ $product->nama }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" aria-label="Nama produk">
                            <textarea name="deskripsi" rows="3" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" aria-label="Deskripsi">{{ $product->deskripsi }}</textarea>
                            <div class="grid grid-cols-2 gap-2">
                                <input name="harga" type="number" min="0" value="{{ $product->harga }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" aria-label="Harga">
                                <input name="stok" type="number" min="0" value="{{ $product->stok }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" aria-label="Stok">
                            </div>
                            <input name="satuan" value="{{ $product->satuan }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" aria-label="Satuan">
                            <input name="gambar" type="file" accept=".jpg,.jpeg,.png,.webp" class="w-full text-xs text-gray-500">
                            <div class="flex justify-between gap-2">
                                <button class="px-3 py-2 rounded-lg bg-gray-900 text-white text-xs font-semibold">Simpan</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('supplier.products.destroy', $product) }}" class="mt-2"
                            onsubmit="return confirm('Nonaktifkan produk ini dari toko?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs font-semibold text-red-600">Nonaktifkan Produk</button>
                        </form>
                    </details>
                </div>
            </article>
        @empty
            <div class="md:col-span-2 xl:col-span-3 bg-white border border-gray-200 rounded-lg p-10 text-center">
                <p class="font-semibold text-gray-800">Belum ada produk</p>
                <p class="text-sm text-gray-500 mt-1">Tambahkan produk pertama agar katalog toko mulai terisi.</p>
            </div>
        @endforelse
    </div>

    {{ $products->links() }}

    <div x-show="showCreate" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/45">
        <div @click.outside="showCreate = false" class="bg-white w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-lg shadow-xl">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-bold text-gray-900">Tambah Produk</h2>
                <button type="button" @click="showCreate = false" class="p-1 text-gray-400" aria-label="Tutup">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('supplier.products.store') }}" enctype="multipart/form-data" class="p-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nama produk</label>
                    <input name="nama" value="{{ old('nama') }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Deskripsi</label>
                    <textarea name="deskripsi" rows="4" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">{{ old('deskripsi') }}</textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Harga</label>
                        <input name="harga" type="number" min="0" value="{{ old('harga') }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Stok</label>
                        <input name="stok" type="number" min="0" value="{{ old('stok', 0) }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Satuan</label>
                        <input name="satuan" value="{{ old('satuan', 'Pcs') }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Foto produk</label>
                    <input name="gambar" type="file" accept=".jpg,.jpeg,.png,.webp" class="w-full text-sm text-gray-500">
                </div>
                @if($errors->any())
                    <div class="rounded-lg bg-red-50 text-red-700 p-3 text-xs">{{ $errors->first() }}</div>
                @endif
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showCreate = false" class="px-4 py-2.5 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700">Batal</button>
                    <button class="px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold">Simpan Produk</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
