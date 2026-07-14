@extends('layouts.app')

@section('title', 'Daftar Pengaturan')
@section('breadcrumb', 'Pengaturan')

@section('content')
<div class="mx-auto max-w-6xl space-y-5">
    <div>
        <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Pengaturan</p>
        <h1 class="mt-1 text-2xl font-bold text-gray-900">Pengaturan Sistem</h1>
        <p class="mt-1 text-sm text-gray-500">Gunakan halaman ini untuk mengatur data dasar, IoT, aturan SPK, dan supplier DSS.</p>
    </div>

    <x-page-hint title="Alur pengaturan yang disarankan" tone="sky" :open="false">
        Mulai dari Data Master, lalu atur IoT, setelah itu sesuaikan aturan SPK. Pengaturan supplier DSS dipakai saat farm ingin membandingkan supplier dan membuat rekomendasi pembelian.
    </x-page-hint>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <a href="{{ route('data-master.index') }}" class="rounded-lg border border-gray-200 bg-white p-5 hover:bg-gray-50" style="text-decoration:none;">
            <p class="text-sm font-bold text-gray-900">Data Master</p>
            <p class="mt-1 text-sm text-gray-500">Kelola komoditas, kandang, jenis budidaya, dan data dasar yang dipakai semua modul.</p>
            <p class="mt-3 text-xs font-semibold text-emerald-700">Buka Data Master</p>
        </a>

        <a id="iot-settings" href="{{ route('iot.devices') }}" class="rounded-lg border border-gray-200 bg-white p-5 hover:bg-gray-50" style="text-decoration:none;">
            <p class="text-sm font-bold text-gray-900">IoT</p>
            <p class="mt-1 text-sm text-gray-500">Mulai dari setup terpadu: koneksi, device per kandang, mapping payload, parameter sensor, threshold, lalu monitoring.</p>
            <p class="mt-3 text-xs font-semibold text-emerald-700">Buka Setup IoT</p>
        </a>

        @if(session('user') && isset(session('user')['role']) && session('user')['role'] === 'pjawab')
            <a href="{{ route('settings.fuzzy.index') }}" class="rounded-lg border border-gray-200 bg-white p-5 hover:bg-gray-50" style="text-decoration:none;">
                <p class="text-sm font-bold text-gray-900">Aturan SPK Kandang</p>
                <p class="mt-1 text-sm text-gray-500">Kelola profil, variabel, himpunan fuzzy, sumber input, dan rule Mamdani.</p>
                <p class="mt-3 text-xs font-semibold text-emerald-700">Buka Aturan SPK</p>
            </a>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-5">
            <p class="text-sm font-bold text-gray-900">DSS Supplier AHP-SAW</p>
            <p class="mt-1 text-sm text-gray-500">Atur bobot AHP, cek ranking SAW, dan bandingkan supplier untuk kebutuhan restock.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('spk.suppliers.dss.config') }}" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700" style="text-decoration:none;">Atur Bobot</a>
                <a href="{{ route('spk.suppliers.dss.dashboard') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">Ranking SAW</a>
                <a href="{{ route('spk.suppliers.products') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">Cari Barang</a>
            </div>
        </div>
    </div>
</div>
@endsection
