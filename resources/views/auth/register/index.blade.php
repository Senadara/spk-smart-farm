@extends('layouts.guest')

@section('title', 'Daftar Akun')

@section('content')
<div class="w-full max-w-4xl">
    <x-card class="!p-6 md:!p-8">
        <div class="text-center">
            <x-brand-logo
                :show-text="false"
                class="mb-3 justify-center"
                logo-class="h-20 w-20"
            />
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Registrasi SmartFarm</p>
            <h1 class="mt-2 text-2xl font-black text-slate-900">Pilih jenis akun</h1>
            <p class="mx-auto mt-2 max-w-2xl text-sm text-slate-500">Owner memakai aplikasi untuk mengelola farm. Supplier memakai aplikasi untuk mengelola toko dan menerima pesanan.</p>
        </div>

        <div class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2">
            <a href="{{ route('register.owner') }}" class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-left no-underline transition hover:border-emerald-300 hover:bg-emerald-100">
                <span class="inline-flex rounded-lg bg-emerald-600 px-3 py-1 text-xs font-bold text-white">Owner</span>
                <h2 class="mt-4 text-lg font-black text-slate-900">Daftar sebagai owner farm</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">Untuk pemilik/penanggung jawab peternakan atau perkebunan. Cocok untuk mengelola dashboard, laporan, inventaris, IoT, SPK, dan petugas.</p>
            </a>

            <a href="{{ route('register.supplier') }}" class="rounded-xl border border-sky-200 bg-sky-50 p-5 text-left no-underline transition hover:border-sky-300 hover:bg-sky-100">
                <span class="inline-flex rounded-lg bg-sky-600 px-3 py-1 text-xs font-bold text-white">Supplier</span>
                <h2 class="mt-4 text-lg font-black text-slate-900">Daftar sebagai supplier</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">Untuk mitra toko yang ingin mengelola profil toko, produk, stok, dan pesanan. Toko baru tampil setelah disetujui super admin.</p>
            </a>
        </div>

        <div class="mt-6 text-center text-sm text-slate-500">
            Sudah punya akun?
            <a href="{{ route('login') }}" class="font-bold text-emerald-700 hover:text-emerald-800">Masuk</a>
        </div>
    </x-card>
</div>
@endsection
