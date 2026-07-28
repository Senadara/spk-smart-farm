@extends('layouts.app')

@php
    $isEdit = $product !== null;
    $imageUrl = $isEdit && $product->gambar
        ? (\Illuminate\Support\Str::startsWith($product->gambar, ['http://', 'https://']) ? $product->gambar : asset('storage/'.$product->gambar))
        : null;
    $selectedCategory = old('kategori', $product->kategori ?? '');
    $selectedUnit = old('satuan', $product->satuan ?? (($unitOptions ?? collect())->first() ?? 'Pcs'));
@endphp

@section('title', $isEdit ? 'Edit Produk Supplier' : 'Tambah Produk Supplier')
@section('breadcrumb', $isEdit ? 'Edit Produk' : 'Tambah Produk')

@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="mb-1 text-sm font-medium text-emerald-700">{{ $store->nama }}</p>
            <h1 class="text-2xl font-bold text-slate-900">{{ $isEdit ? 'Edit Produk' : 'Tambah Produk' }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $isEdit ? 'Ubah informasi produk. Stok dikelola lewat halaman restok.' : 'Lengkapi produk baru yang akan tampil di katalog.' }}</p>
        </div>
        <a href="{{ route('supplier.products.index') }}"
            class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 no-underline transition hover:bg-slate-50">
            Kembali
        </a>
    </div>

    @if($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('supplier.products.update', $product) : route('supplier.products.store') }}" enctype="multipart/form-data" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="mb-5 rounded-lg border border-slate-100 bg-slate-50/70 p-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white">
                    @if($imageUrl)
                        <img src="{{ $imageUrl }}" alt="{{ $product->nama }}" class="max-h-28 max-w-28 object-cover">
                    @else
                        <svg class="h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    @endif
                </div>
                <div class="min-w-0 flex-1 space-y-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Foto produk</label>
                        <input name="gambar" type="file" accept=".jpg,.jpeg,.png,.webp" class="w-full max-w-xl rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-500 file:mr-3 file:rounded-md file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700">
                    </div>
                    <div class="flex flex-col gap-2 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                        <p>{{ $isEdit ? 'Upload file baru hanya jika ingin mengganti foto produk.' : 'Foto bersifat opsional, tetapi membantu pembeli mengenali produk.' }}</p>
                        @if($isEdit)
                            <a href="{{ route('supplier.products.stock.edit', $product) }}" class="inline-flex w-fit rounded-lg border border-emerald-200 bg-white px-3 py-2 font-semibold text-emerald-700 no-underline transition hover:bg-emerald-50">
                                Kelola Restok
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Nama produk</label>
                <input name="nama" value="{{ old('nama', $product->nama ?? '') }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Deskripsi</label>
                <textarea name="deskripsi" rows="4" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">{{ old('deskripsi', $product->deskripsi ?? '') }}</textarea>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Kategori produk</label>
                    <select name="kategori" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                        <option value="" disabled @selected($selectedCategory === '')>Pilih kategori</option>
                        @foreach($categoryOptions ?? [] as $category)
                            <option value="{{ $category }}" @selected($selectedCategory === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Satuan</label>
                    <select name="satuan" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                        @foreach($unitOptions ?? [] as $unit)
                            <option value="{{ $unit }}" @selected($selectedUnit === $unit)>{{ $unit }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 {{ $isEdit ? 'sm:grid-cols-3' : 'sm:grid-cols-4' }}">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Harga</label>
                    <input name="harga" type="number" min="0" value="{{ old('harga', $product->harga ?? '') }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                </div>
                @unless($isEdit)
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Stok awal</label>
                        <input name="stok" type="number" min="0" value="{{ old('stok', 0) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                    </div>
                @endunless
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Minimum stok</label>
                    <input name="minimum_stock" type="number" min="0" value="{{ old('minimum_stock', $product->minimum_stock ?? 10) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Restok default</label>
                    <input name="restock_quantity" type="number" min="0" value="{{ old('restock_quantity', $product->restock_quantity ?? 0) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                </div>
            </div>

            @if($isEdit)
                <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700">
                    Stok saat ini: {{ number_format((int) $product->stok, 0, ',', '.') }} {{ $product->satuan }}
                </div>
            @endif
        </div>

        <div class="mt-5 flex flex-col gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <div></div>
            <div class="flex justify-end gap-2">
                <a href="{{ route('supplier.products.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 no-underline transition hover:bg-slate-50">Batal</a>
                <button class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                    {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Produk' }}
                </button>
            </div>
        </div>
    </form>

    @if($isEdit)
        <form method="POST" action="{{ route('supplier.products.destroy', $product) }}" onsubmit="return confirm('Nonaktifkan produk ini dari toko?')">
            @csrf
            @method('DELETE')
            <button class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">
                Nonaktifkan Produk
            </button>
        </form>
    @endif
</div>
@endsection
