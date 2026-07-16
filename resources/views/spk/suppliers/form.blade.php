@extends('layouts.app')

@section('title', $supplier ? 'Edit Mitra Supplier' : 'Tambah Mitra Supplier')
@section('breadcrumb', 'Supplier > '.($supplier ? 'Edit Mitra' : 'Tambah Mitra'))

@section('content')
@php
    $isEdit = $supplier !== null;
    $selectedCategories = old('kategori', $selectedCategories ?? []);
    if (is_string($selectedCategories)) {
        $selectedCategories = array_values(array_filter(array_map('trim', explode(',', $selectedCategories))));
    }

    $latitude = old('latitude', $supplier?->latitude ?? $store?->latitude);
    $longitude = old('longitude', $supplier?->longitude ?? $store?->longitude);
    $alamat = old('alamat', $supplier?->alamat ?? $store?->alamat);
    $formAction = $formAction
        ?? ($isEdit && $supplier ? route('superadmin.suppliers.update', $supplier) : route('superadmin.suppliers.store'));
    $indexRoute = $indexRoute ?? route('superadmin.suppliers.index');
    $formEyebrow = $formEyebrow ?? 'Mitra Supplier';
@endphp

<div class="mx-auto max-w-5xl space-y-6 pb-10">
    <section class="rounded-lg border border-gray-200 bg-white p-5 md:p-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">{{ $formEyebrow }}</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ $isEdit ? 'Edit data mitra supplier' : 'Tambah mitra supplier baru' }}</h1>
                <p class="mt-2 text-sm text-gray-500">
                    Data ini dipakai untuk daftar toko, WhatsApp, estimasi jarak pengiriman, dan alternatif supplier pada SPK.
                </p>
            </div>
            <a href="{{ $indexRoute }}" class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50">
                Kembali
            </a>
        </div>
    </section>

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            Periksa kembali data yang belum sesuai.
        </div>
    @endif

    <form method="POST" action="{{ $formAction }}" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-5 p-5 md:grid-cols-2 md:p-7">
            <div>
                <label for="supplier-nama" class="mb-2 block text-sm font-semibold text-gray-700">Nama toko supplier</label>
                <input id="supplier-nama" name="nama" value="{{ old('nama', $supplier?->nama ?? $store?->nama) }}" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    placeholder="Contoh: CV Sumber Ternak Malang">
                @error('nama')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="supplier-whatsapp" class="mb-2 block text-sm font-semibold text-gray-700">Nomor WhatsApp</label>
                <input id="supplier-whatsapp" name="whatsapp" value="{{ old('whatsapp', $supplier?->kontak ?? $store?->phone) }}" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    placeholder="Contoh: 6281234567890">
                <p class="mt-1 text-xs text-gray-400">Nomor akan dinormalisasi untuk tombol WhatsApp.</p>
                @error('whatsapp')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="md:col-span-2">
                <span class="mb-2 block text-sm font-semibold text-gray-700">Kategori kebutuhan</span>
                <p class="mb-3 text-xs text-gray-500">Pilih semua kategori yang benar-benar dilayani supplier. Kategori harus berasal dari master kategori agar pencarian dan rekomendasi tetap konsisten.</p>
                <label class="mb-3 inline-flex cursor-pointer items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700">
                    <input id="supplier-category-all" type="checkbox"
                        class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
                    <span>Pilih semua kategori</span>
                </label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach($categoryOptions as $option)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-2.5 text-sm text-gray-700 hover:bg-emerald-50">
                            <input type="checkbox" name="kategori[]" value="{{ $option }}" @checked(in_array($option, $selectedCategories, true))
                                data-supplier-category-option
                                class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="font-semibold">{{ $option }}</span>
                        </label>
                    @endforeach
                </div>
                @error('kategori')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                @error('kategori.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="md:col-span-2">
                <label for="supplier-alamat" class="mb-2 block text-sm font-semibold text-gray-700">Alamat toko</label>
                <textarea id="supplier-alamat" name="alamat" rows="3" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    placeholder="Tulis alamat toko atau pilih dari peta">{{ $alamat }}</textarea>
                @error('alamat')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="md:col-span-2">
                <x-location-picker
                    id="managed-supplier-location-picker"
                    lat-input-id="supplier-latitude"
                    lng-input-id="supplier-longitude"
                    address-input-id="supplier-alamat"
                    initial-query="{{ $alamat }}"
                    title="Pilih titik toko supplier"
                    help="Cari wilayah toko atau klik titik pada peta. Titik ini dipakai untuk menghitung jarak dari lokasi peternak."
                />
            </div>

            <div>
                <label for="supplier-latitude" class="mb-2 block text-sm font-semibold text-gray-700">Latitude</label>
                <input id="supplier-latitude" name="latitude" type="number" step="0.0000001" value="{{ $latitude }}"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    placeholder="-7.9666204">
                @error('latitude')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="supplier-longitude" class="mb-2 block text-sm font-semibold text-gray-700">Longitude</label>
                <input id="supplier-longitude" name="longitude" type="number" step="0.0000001" value="{{ $longitude }}"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    placeholder="112.6326321">
                @error('longitude')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="md:col-span-2">
                <label for="supplier-deskripsi" class="mb-2 block text-sm font-semibold text-gray-700">Catatan singkat</label>
                <textarea id="supplier-deskripsi" name="deskripsi" rows="4"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                    placeholder="Contoh: Menyediakan pakan layer, vitamin, vaksin, dan pengiriman area Malang Raya.">{{ old('deskripsi', $supplier?->deskripsi ?? $store?->deskripsi) }}</textarea>
                @error('deskripsi')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex flex-col gap-3 border-t border-gray-100 bg-gray-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between md:px-7">
            <p class="text-xs text-gray-500">
                {{ $isEdit ? 'Perubahan lokasi akan membuat rekomendasi supplier dihitung ulang.' : 'Supplier baru langsung aktif sebagai mitra manual.' }}
            </p>
            <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">
                {{ $isEdit ? 'Simpan Perubahan' : 'Tambah Mitra' }}
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleAll = document.getElementById('supplier-category-all');
            const options = Array.from(document.querySelectorAll('[data-supplier-category-option]'));

            if (!toggleAll || options.length === 0) return;

            const syncToggleState = () => {
                const checkedCount = options.filter((option) => option.checked).length;
                toggleAll.checked = checkedCount === options.length;
                toggleAll.indeterminate = checkedCount > 0 && checkedCount < options.length;
            };

            toggleAll.addEventListener('change', () => {
                options.forEach((option) => {
                    option.checked = toggleAll.checked;
                });
                syncToggleState();
            });

            options.forEach((option) => option.addEventListener('change', syncToggleState));
            syncToggleState();
        });
    </script>
@endpush
