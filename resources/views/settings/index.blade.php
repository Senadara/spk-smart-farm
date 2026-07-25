@extends('layouts.app')

@section('title', 'Daftar Pengaturan')
@section('breadcrumb', 'Pengaturan')

@section('content')
<div class="mx-auto max-w-6xl space-y-5">
    <div>
        <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Pengaturan</p>
        <h1 class="mt-1 text-2xl font-bold text-gray-900">Pengaturan Sistem</h1>
        <p class="mt-1 text-sm text-gray-500">Pusat konfigurasi data, IoT, SPK, dan supplier.</p>
    </div>

    <x-page-hint title="Urutan singkat" tone="sky" :open="false">
        Mulai dari Data Master, lanjut IoT, lalu aturan SPK. DSS Supplier dipakai saat farm perlu membandingkan rekomendasi pembelian.
    </x-page-hint>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <a href="{{ route('data-master.index') }}" class="rounded-lg border border-emerald-100 bg-emerald-50/40 p-5 transition-all hover:-translate-y-0.5 hover:border-emerald-200 hover:bg-emerald-50 hover:shadow-sm" style="text-decoration:none;">
            <p class="text-sm font-bold text-gray-900">Data Master</p>
            <p class="mt-1 text-sm text-gray-500">Data dasar ternak, stok, dan satuan.</p>
            <p class="mt-3 text-xs font-semibold text-emerald-700">Buka Data Master</p>
        </a>

        <a id="iot-settings" href="{{ route('iot.devices') }}" class="rounded-lg border border-sky-100 bg-sky-50/40 p-5 transition-all hover:-translate-y-0.5 hover:border-sky-200 hover:bg-sky-50 hover:shadow-sm" style="text-decoration:none;">
            <p class="text-sm font-bold text-gray-900">IoT</p>
            <p class="mt-1 text-sm text-gray-500">Device, koneksi, mapping payload, dan sensor.</p>
            <p class="mt-3 text-xs font-semibold text-sky-700">Buka Setup IoT</p>
        </a>

        @if(session('user') && isset(session('user')['role']) && session('user')['role'] === 'pjawab')
            <a href="{{ route('settings.fuzzy.index') }}" class="rounded-lg border border-emerald-100 bg-white p-5 transition-all hover:-translate-y-0.5 hover:border-emerald-200 hover:bg-emerald-50/60 hover:shadow-sm" style="text-decoration:none;">
                <p class="text-sm font-bold text-gray-900">Aturan SPK Kandang</p>
                <p class="mt-1 text-sm text-gray-500">Variabel, sumber input, dan rule Mamdani.</p>
                <p class="mt-3 text-xs font-semibold text-emerald-700">Buka Aturan SPK</p>
            </a>
        @endif

        <a href="{{ route('settings.notifications.index') }}" class="rounded-lg border border-sky-100 bg-white p-5 transition-all hover:-translate-y-0.5 hover:border-sky-200 hover:bg-sky-50/60 hover:shadow-sm" style="text-decoration:none;">
            <p class="text-sm font-bold text-gray-900">Notifikasi</p>
            <p class="mt-1 text-sm text-gray-500">Jadwal panen mobile dan indikasi kesehatan SPK.</p>
            <p class="mt-3 text-xs font-semibold text-sky-700">Buka Notifikasi</p>
        </a>

        <div class="rounded-lg border border-gray-200 bg-white p-5 transition-all hover:border-gray-300 hover:shadow-sm">
            <p class="text-sm font-bold text-gray-900">DSS Supplier AHP-SAW</p>
            <p class="mt-1 text-sm text-gray-500">Bobot AHP, ranking SAW, dan barang supplier.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('spk.suppliers.dss.config') }}" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700" style="text-decoration:none;">Atur Bobot</a>
                <a href="{{ route('spk.suppliers.dss.dashboard') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">Ranking SAW</a>
                <a href="{{ route('spk.suppliers.products') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">Cari Barang</a>
            </div>
        </div>
    </div>
</div>
@endsection
