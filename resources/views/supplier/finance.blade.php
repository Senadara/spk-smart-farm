@extends('layouts.app')

@section('title', 'Keuangan Supplier')
@section('breadcrumb', 'Keuangan')

@section('content')
<div class="max-w-[1600px] mx-auto space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-medium text-emerald-700 mb-1">{{ $store->nama }}</p>
            <h1 class="text-2xl font-bold text-gray-900">Ringkasan Keuangan</h1>
            <p class="text-sm text-gray-500 mt-1">Omzet dihitung dari pesanan yang telah berstatus selesai.</p>
        </div>
        <form method="GET">
            <select name="year" onchange="this.form.submit()" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm">
                @foreach(range(now()->year, now()->year - 4) as $optionYear)
                    <option value="{{ $optionYear }}" @selected($year === $optionYear)>{{ $optionYear }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <section class="bg-white border border-gray-200 rounded-lg p-5">
            <p class="text-xs font-semibold uppercase text-gray-400">Total Omzet {{ $year }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-2">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</p>
        </section>
        <section class="bg-white border border-gray-200 rounded-lg p-5">
            <p class="text-xs font-semibold uppercase text-gray-400">Transaksi Selesai</p>
            <p class="text-2xl font-bold text-gray-900 mt-2">{{ number_format($completedOrders->count()) }}</p>
        </section>
        <section class="bg-white border border-gray-200 rounded-lg p-5">
            <p class="text-xs font-semibold uppercase text-gray-400">Rata-rata Pesanan</p>
            <p class="text-2xl font-bold text-gray-900 mt-2">Rp {{ number_format($averageOrder, 0, ',', '.') }}</p>
        </section>
    </div>

    <section class="bg-white border border-gray-200 rounded-lg p-5">
        <div class="mb-5">
            <h2 class="font-bold text-gray-900">Omzet Bulanan</h2>
            <p class="text-xs text-gray-500 mt-1">Perbandingan pendapatan sepanjang tahun {{ $year }}</p>
        </div>
        @php
            $maxRevenue = max($monthly->max('revenue'), 1);
        @endphp
        <div class="h-64 flex items-end gap-2 border-b border-gray-200 overflow-x-auto">
            @foreach($monthly as $month)
                <div class="flex-1 min-w-10 h-full flex flex-col justify-end items-center gap-2">
                    <div class="w-full max-w-10 bg-emerald-500 rounded-t-sm"
                        title="Rp {{ number_format($month['revenue'], 0, ',', '.') }}"
                        style="height: {{ max(4, ($month['revenue'] / $maxRevenue) * 190) }}px"></div>
                    <span class="text-[11px] text-gray-500 pb-2">{{ $month['month'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <div class="p-5 border-b border-gray-100">
            <h2 class="font-bold text-gray-900">Transaksi Selesai Terbaru</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-5 py-3">Pesanan</th>
                        <th class="text-left px-5 py-3">Tanggal</th>
                        <th class="text-right px-5 py-3">Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($completedOrders as $order)
                        <tr>
                            <td class="px-5 py-3 font-mono text-xs text-gray-600">#{{ strtoupper(substr($order->id, 0, 12)) }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $order->createdAt->format('d M Y, H:i') }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-900">Rp {{ number_format($order->totalHarga, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-10 text-center text-gray-500">Belum ada transaksi selesai pada tahun {{ $year }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
