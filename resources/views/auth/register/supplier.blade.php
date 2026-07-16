@extends('layouts.guest')

@section('title', 'Daftar Supplier')

@section('content')
@php
    $selectedCategories = old('kategori', []);
@endphp

<div class="w-full max-w-5xl">
    <x-card class="!p-6 md:!p-8">
        <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-sky-700">Registrasi Supplier</p>
                <h1 class="mt-1 text-2xl font-black text-slate-900">Ajukan toko supplier</h1>
                <p class="mt-2 text-sm text-slate-500">Akun supplier bisa login setelah dibuat. Toko tampil di rekomendasi owner setelah disetujui super admin.</p>
            </div>
            <a href="{{ route('register') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50 no-underline">Kembali</a>
        </div>

        <form method="POST" action="{{ route('register.supplier.store') }}" class="space-y-5">
            @csrf
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-form.input name="name" label="Nama pemilik/admin toko" :required="true" placeholder="Nama lengkap" />
                <x-form.input name="email" type="email" label="Email login" :required="true" placeholder="supplier@email.com" />
                <x-form.input name="phone" label="Nomor WhatsApp" :required="true" placeholder="081234567890" />
                <x-form.input name="store_name" label="Nama toko" :required="true" placeholder="CV Sumber Ternak Malang" />
                <x-form.input name="password" type="password" label="Password" :required="true" autocomplete="new-password" />
                <x-form.input name="password_confirmation" type="password" label="Konfirmasi password" :required="true" autocomplete="new-password" />
            </div>

            <div>
                <span class="mb-2 block text-sm font-semibold text-slate-700">Kategori kebutuhan <span class="text-red-500">*</span></span>
                <label class="mb-3 inline-flex cursor-pointer items-center gap-2 rounded-lg border border-sky-100 bg-sky-50 px-3 py-2 text-sm font-bold text-sky-700">
                    <input id="register-supplier-category-all" type="checkbox" class="rounded border-sky-300 text-sky-600 focus:ring-sky-500">
                    <span>Pilih semua kategori</span>
                </label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach($categoryOptions as $option)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700 hover:bg-sky-50">
                            <input type="checkbox" name="kategori[]" value="{{ $option }}" @checked(in_array($option, $selectedCategories, true))
                                data-register-supplier-category
                                class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                            <span class="font-semibold">{{ $option }}</span>
                        </label>
                    @endforeach
                </div>
                @error('kategori')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                @error('kategori.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="supplier-register-address" class="mb-2 block text-sm font-semibold text-slate-700">Alamat toko <span class="text-red-500">*</span></label>
                <textarea id="supplier-register-address" name="alamat" rows="3" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-100">{{ old('alamat') }}</textarea>
                @error('alamat')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <x-location-picker
                id="supplier-register-location-picker"
                lat-input-id="supplier-register-latitude"
                lng-input-id="supplier-register-longitude"
                address-input-id="supplier-register-address"
                initial-query="{{ old('alamat') }}"
                title="Pilih titik lokasi toko"
                help="Cari wilayah toko atau klik peta. Titik ini dipakai untuk estimasi jarak pengiriman."
            />

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="supplier-register-latitude" class="mb-2 block text-sm font-semibold text-slate-700">Latitude</label>
                    <input id="supplier-register-latitude" name="latitude" type="number" step="0.0000001" value="{{ old('latitude') }}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                    @error('latitude')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="supplier-register-longitude" class="mb-2 block text-sm font-semibold text-slate-700">Longitude</label>
                    <input id="supplier-register-longitude" name="longitude" type="number" step="0.0000001" value="{{ old('longitude') }}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
                    @error('longitude')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="supplier-register-description" class="mb-2 block text-sm font-semibold text-slate-700">Deskripsi toko</label>
                <textarea id="supplier-register-description" name="deskripsi" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-100">{{ old('deskripsi') }}</textarea>
                @error('deskripsi')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">Setelah daftar, status toko menjadi menunggu persetujuan super admin.</p>
                <button type="submit" class="rounded-lg bg-sky-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-sky-700">Ajukan Supplier</button>
            </div>
        </form>
    </x-card>
</div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleAll = document.getElementById('register-supplier-category-all');
            const options = Array.from(document.querySelectorAll('[data-register-supplier-category]'));
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
