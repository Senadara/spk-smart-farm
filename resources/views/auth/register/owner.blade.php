@extends('layouts.guest')

@section('title', 'Daftar Owner')

@section('content')
<div class="w-full max-w-4xl">
    <x-card class="!p-6 md:!p-8">
        <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Registrasi Owner</p>
                <h1 class="mt-1 text-2xl font-black text-slate-900">Buat akun pengelola farm</h1>
                <p class="mt-2 text-sm text-slate-500">Lokasi farm dipakai untuk menghitung jarak supplier dan estimasi pengiriman.</p>
            </div>
            <a href="{{ route('register') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50 no-underline">Kembali</a>
        </div>

        <form method="POST" action="{{ route('register.owner.store') }}" class="space-y-5">
            @csrf
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-form.input name="name" label="Nama owner" :required="true" placeholder="Nama lengkap" />
                <x-form.input name="email" type="email" label="Email" :required="true" placeholder="owner@email.com" />
                <x-form.input name="phone" label="Nomor WhatsApp" :required="true" placeholder="081234567890" />
                <x-form.input name="farm_name" label="Nama farm" :required="true" placeholder="Smart Farm Ngantang" />
                <x-form.input name="password" type="password" label="Password" :required="true" autocomplete="new-password" />
                <x-form.input name="password_confirmation" type="password" label="Konfirmasi password" :required="true" autocomplete="new-password" />
            </div>

            <div>
                <label for="owner-address" class="mb-2 block text-sm font-semibold text-slate-700">Alamat farm <span class="text-red-500">*</span></label>
                <textarea id="owner-address" name="address" rows="3" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">{{ old('address') }}</textarea>
                @error('address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <x-location-picker
                id="owner-register-location-picker"
                lat-input-id="owner-latitude"
                lng-input-id="owner-longitude"
                address-input-id="owner-address"
                initial-query="{{ old('address') }}"
                title="Pilih titik lokasi farm"
                help="Cari wilayah atau klik peta. Titik ini menjadi lokasi utama untuk perhitungan jarak supplier."
            />

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="owner-latitude" class="mb-2 block text-sm font-semibold text-slate-700">Latitude</label>
                    <input id="owner-latitude" name="latitude" type="number" step="0.0000001" value="{{ old('latitude') }}" placeholder="-7.9666204"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    @error('latitude')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="owner-longitude" class="mb-2 block text-sm font-semibold text-slate-700">Longitude</label>
                    <input id="owner-longitude" name="longitude" type="number" step="0.0000001" value="{{ old('longitude') }}" placeholder="112.6326321"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    @error('longitude')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">Akun owner akan langsung aktif dan bisa login setelah registrasi berhasil.</p>
                <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Daftar Owner</button>
            </div>
        </form>
    </x-card>
</div>
@endsection
