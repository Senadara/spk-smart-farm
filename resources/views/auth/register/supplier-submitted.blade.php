@extends('layouts.guest')

@section('title', 'Pengajuan Supplier Terkirim')

@section('content')
<div class="w-full max-w-xl">
    <x-card class="!p-6 text-center md:!p-8">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h1 class="mt-5 text-2xl font-black text-slate-900">Pengajuan supplier terkirim</h1>
        <p class="mt-3 text-sm leading-6 text-slate-500">
            Akun dan toko sudah masuk antrean verifikasi super admin. Informasi disetujui atau ditolak akan dikirim melalui email yang digunakan saat registrasi.
        </p>

        @if(session('success'))
            <div class="mt-5 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4 text-left text-sm text-slate-600">
            <p class="font-bold text-slate-900">Langkah berikutnya</p>
            <p class="mt-1">Tunggu email approval dari admin. Setelah toko disetujui, supplier dapat login dan mengelola produk serta pesanan.</p>
        </div>

        <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
            <a href="{{ route('register') }}" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50 no-underline">Kembali</a>
            <a href="{{ route('login') }}" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700 no-underline">Ke Login</a>
        </div>
    </x-card>
</div>
@endsection
