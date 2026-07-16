@extends('layouts.app')

@section('title', 'Super Admin Supplier')
@section('breadcrumb', 'Super Admin > Supplier')

@section('content')
@php
    $tabs = [
        'request' => ['label' => 'Menunggu', 'class' => 'amber'],
        'active' => ['label' => 'Aktif', 'class' => 'emerald'],
        'reject' => ['label' => 'Ditolak', 'class' => 'rose'],
    ];
    $statusBadge = [
        'request' => 'bg-amber-50 text-amber-700 border-amber-200',
        'active' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'reject' => 'bg-rose-50 text-rose-700 border-rose-200',
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-5">
    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ session('error') }}</div>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Super Admin</p>
                <h1 class="mt-1 text-2xl font-black text-slate-900">Manajemen Mitra Supplier</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-500">Kelola supplier global untuk seluruh owner. Supplier baru dari registrasi masuk ke status menunggu dan tidak tampil di rekomendasi sampai disetujui.</p>
            </div>
            <a href="{{ route('superadmin.suppliers.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700" style="text-decoration:none;">
                Tambah Supplier Manual
            </a>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-3 md:grid-cols-3">
        @foreach($tabs as $key => $tab)
            <a href="{{ route('superadmin.suppliers.index', ['status' => $key]) }}"
                class="rounded-xl border p-4 transition {{ $status === $key ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white hover:bg-slate-50' }}"
                style="text-decoration:none;">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $tab['label'] }}</p>
                <p class="mt-2 text-2xl font-black text-slate-900">{{ number_format($counts[$key] ?? 0) }}</p>
            </a>
        @endforeach
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
        <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-bold text-slate-900">Daftar toko supplier</h2>
                <p class="mt-1 text-xs text-slate-500">Status saat ini: {{ $tabs[$status]['label'] ?? 'Menunggu' }}.</p>
            </div>
            <form method="GET" action="{{ route('superadmin.suppliers.index') }}" class="flex flex-col gap-2 sm:flex-row">
                <input type="hidden" name="status" value="{{ $status }}">
                <input name="search" value="{{ request('search') }}" placeholder="Cari toko, alamat, kategori..."
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:w-80">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Cari</button>
            </form>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            @forelse($stores as $store)
                <article class="rounded-xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-base font-black text-slate-900">{{ $store->nama }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $store->alamat ?: 'Alamat belum tersedia' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full border px-3 py-1 text-xs font-bold {{ $statusBadge[$store->tokoStatus] ?? 'bg-slate-50 text-slate-600 border-slate-200' }}">
                            {{ $tabs[$store->tokoStatus]['label'] ?? ucfirst($store->tokoStatus) }}
                        </span>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg bg-slate-50 px-3 py-2">
                            <dt class="text-[10px] font-bold uppercase text-slate-400">WhatsApp</dt>
                            <dd class="mt-1 font-semibold text-slate-900">{{ $store->phone ?: '-' }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 px-3 py-2">
                            <dt class="text-[10px] font-bold uppercase text-slate-400">Kategori</dt>
                            <dd class="mt-1 line-clamp-1 font-semibold text-slate-900">{{ $store->kategori ?: '-' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                        @if($store->tokoStatus !== 'active')
                            <form method="POST" action="{{ route('superadmin.supplier-stores.approve', $store) }}">
                                @csrf
                                @method('PATCH')
                                <button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Setujui</button>
                            </form>
                        @endif
                        @if($store->tokoStatus !== 'reject')
                            <form method="POST" action="{{ route('superadmin.supplier-stores.reject', $store) }}" onsubmit="return confirm('Tolak supplier ini? Toko tidak akan tampil untuk owner.');">
                                @csrf
                                @method('PATCH')
                                <button class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100">Tolak</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500 lg:col-span-2">
                    Tidak ada toko pada status ini.
                </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $stores->links() }}
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-slate-900">Master Supplier Manual</h2>
                <p class="mt-1 text-xs text-slate-500">Data supplier global yang dipakai SPK AHP-SAW.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            @forelse($manualSuppliers as $supplier)
                <div class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-3">
                    <p class="line-clamp-1 text-sm font-bold text-slate-900">{{ $supplier->nama }}</p>
                    <p class="mt-1 line-clamp-1 text-xs text-slate-500">{{ $supplier->kategori ?: 'Tanpa kategori' }}</p>
                    <a href="{{ route('superadmin.suppliers.edit', $supplier) }}" class="mt-3 inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50" style="text-decoration:none;">Edit</a>
                </div>
            @empty
                <p class="text-sm text-slate-500">Belum ada master supplier.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
