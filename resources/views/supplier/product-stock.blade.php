@extends('layouts.app')

@php
    $minimumStock = (int) ($product->minimum_stock ?? 10);
    $restockQty = (int) ($product->restock_quantity ?? 0);
    $defaultQty = $restockQty > 0 ? $restockQty : max($minimumStock * 2, 1);
    $isLowStock = (int) $product->stok <= $minimumStock;
    $typeLabels = [
        'restock' => 'Restok masuk',
        'correction_in' => 'Koreksi tambah',
        'correction_out' => 'Koreksi kurang',
        'order_accepted' => 'Pesanan diterima',
    ];
@endphp

@section('title', 'Restok Produk')
@section('breadcrumb', 'Restok Produk')

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    @if(session('success') || session('error'))
        <div class="rounded-lg border px-4 py-3 text-sm font-semibold {{ session('success') ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
            {{ session('success') ?? session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="mb-1 text-sm font-medium text-emerald-700">{{ $store->nama }}</p>
            <h1 class="text-2xl font-bold text-slate-900">Restok Produk</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $product->nama }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('supplier.products.edit', $product) }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 no-underline transition hover:bg-slate-50">Edit Produk</a>
            <a href="{{ route('supplier.products.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 no-underline transition hover:bg-slate-50">Kembali</a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[320px_1fr]">
        <aside class="space-y-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Stok saat ini</p>
                <p class="mt-1 text-3xl font-black {{ $isLowStock ? 'text-amber-700' : 'text-slate-900' }}">
                    {{ number_format((int) $product->stok, 0, ',', '.') }}
                    <span class="text-base font-bold text-slate-500">{{ $product->satuan }}</span>
                </p>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="rounded-lg bg-slate-50 px-3 py-2">
                    <p class="font-semibold text-slate-500">Minimum</p>
                    <p class="mt-1 font-bold text-slate-900">{{ number_format($minimumStock, 0, ',', '.') }}</p>
                </div>
                <div class="rounded-lg {{ $isLowStock ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }} px-3 py-2">
                    <p class="font-semibold">Status</p>
                    <p class="mt-1 font-bold">{{ $isLowStock ? 'Perlu restok' : 'Aman' }}</p>
                </div>
            </div>
            <p class="text-xs text-slate-500">Koreksi keluar akan ditolak otomatis jika membuat stok menjadi minus.</p>
        </aside>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-900">Catat Pergerakan Stok</h2>
            <form method="POST" action="{{ route('supplier.products.stock', $product) }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Jenis</label>
                        <select name="type" required class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                            <option value="restock" @selected(old('type') === 'restock')>Restok masuk</option>
                            <option value="correction_in" @selected(old('type') === 'correction_in')>Koreksi tambah</option>
                            <option value="correction_out" @selected(old('type') === 'correction_out')>Koreksi kurang</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Jumlah</label>
                        <input name="quantity" type="number" min="1" value="{{ old('quantity', $defaultQty) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                    </div>
                    <div class="flex items-end">
                        <button class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                            Simpan Stok
                        </button>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Catatan</label>
                    <input name="note" value="{{ old('note') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none" placeholder="Opsional, contoh: restok dari gudang utama">
                </div>
            </form>
        </section>
    </div>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="font-bold text-slate-900">Riwayat Stok</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[760px] w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Waktu</th>
                        <th class="px-5 py-3">Jenis</th>
                        <th class="px-5 py-3">Jumlah</th>
                        <th class="px-5 py-3">Sebelum</th>
                        <th class="px-5 py-3">Sesudah</th>
                        <th class="px-5 py-3">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($movements as $movement)
                        <tr>
                            <td class="px-5 py-3 text-slate-500">{{ $movement->createdAt?->format('d M Y, H:i') ?? '-' }}</td>
                            <td class="px-5 py-3 font-semibold text-slate-800">{{ $typeLabels[$movement->type] ?? $movement->type }}</td>
                            <td class="px-5 py-3 font-semibold {{ $movement->quantity < 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                                {{ $movement->quantity > 0 ? '+' : '' }}{{ number_format($movement->quantity, 0, ',', '.') }} {{ $product->satuan }}
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ number_format($movement->stock_before, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-slate-900 font-semibold">{{ number_format($movement->stock_after, 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $movement->note ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">Belum ada riwayat stok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
