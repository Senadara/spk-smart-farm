@extends('layouts.app')

@section('title', 'Pesanan Supplier')
@section('breadcrumb', 'Pesanan')

@section('content')
<div class="max-w-[1600px] mx-auto space-y-5">
    <div>
        <p class="text-sm font-medium text-emerald-700 mb-1">{{ $store->nama }}</p>
        <h1 class="text-2xl font-bold text-gray-900">Manajemen Pesanan</h1>
        <p class="text-sm text-gray-500 mt-1">Pesanan dicatat di sistem, sedangkan pembayaran dan bukti transfer dikonfirmasi langsung di luar sistem.</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
        @foreach(['menunggu' => 'Menunggu', 'diterima' => 'Diproses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan', 'expired' => 'Kedaluwarsa'] as $key => $label)
            <a href="{{ route('supplier.orders.index', ['status' => $key]) }}"
                class="bg-white border rounded-lg p-4 no-underline {{ request('status') === $key ? 'border-emerald-500 ring-2 ring-emerald-100' : 'border-gray-200' }}">
                <p class="text-xs text-gray-500">{{ $label }}</p>
                <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($statusCounts[$key] ?? 0) }}</p>
            </a>
        @endforeach
    </div>

    <form method="GET" class="bg-white border border-gray-200 rounded-lg p-3 flex flex-col sm:flex-row gap-3">
        <input name="search" value="{{ request('search') }}" placeholder="Cari ID pesanan atau pembeli..."
            class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm">
        <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white">
            <option value="">Semua status</option>
            @foreach(['menunggu', 'diterima', 'selesai', 'ditolak', 'dibatalkan', 'expired'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm font-semibold">Cari</button>
    </form>

    <div class="space-y-3">
        @forelse($orders as $order)
            @php
                $statusClass = match($order->status) {
                    'menunggu' => 'bg-amber-50 text-amber-700',
                    'diterima' => 'bg-blue-50 text-blue-700',
                    'selesai' => 'bg-emerald-50 text-emerald-700',
                    'ditolak' => 'bg-red-50 text-red-700',
                    'dibatalkan' => 'bg-gray-100 text-gray-600',
                    default => 'bg-gray-100 text-gray-600',
                };
            @endphp
            <article class="bg-white border border-gray-200 rounded-lg p-4 md:p-5">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-gray-500">#{{ strtoupper(substr($order->id, 0, 12)) }}</span>
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold capitalize {{ $statusClass }}">{{ $order->status }}</span>
                            <span class="text-xs text-gray-400">{{ $order->createdAt->format('d M Y, H:i') }}</span>
                        </div>
                        <p class="font-semibold text-gray-900 mt-3">{{ $order->customer?->name ?? 'Pembeli' }}</p>
                        <p class="text-xs text-gray-500">{{ $order->customer?->email ?? '-' }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @forelse($order->details as $detail)
                                <span class="text-xs bg-gray-50 border border-gray-200 rounded px-2 py-1">
                                    {{ $detail->product?->nama ?? 'Produk' }} x{{ $detail->jumlah }}
                                </span>
                            @empty
                                <span class="text-xs text-gray-400">Rincian produk tidak tersedia.</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="lg:text-right shrink-0">
                        <p class="text-xs text-gray-500">Total transaksi</p>
                        <p class="text-lg font-bold text-gray-900 mt-1">Rp {{ number_format($order->totalHarga, 0, ',', '.') }}</p>
                        @if($order->status === 'menunggu')
                            <div class="flex lg:justify-end gap-2 mt-3">
                                <form method="POST" action="{{ route('supplier.orders.status', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="diterima">
                                    <button class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold">Terima</button>
                                </form>
                            <form method="POST" action="{{ route('supplier.orders.status', $order) }}" onsubmit="return confirm('Tolak pesanan ini?')">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="ditolak">
                                    <button class="px-3 py-2 rounded-lg border border-red-200 text-red-700 text-xs font-semibold">Tolak</button>
                                </form>
                            </div>
                        @elseif($order->status === 'diterima')
                            <form method="POST" action="{{ route('supplier.orders.status', $order) }}" class="mt-3">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="selesai">
                                <button class="px-3 py-2 rounded-lg bg-blue-600 text-white text-xs font-semibold">Tandai Selesai</button>
                            </form>
                            <p class="mt-2 text-[11px] text-gray-400">Gunakan setelah pembayaran luar sistem dan pengiriman/ambil barang selesai.</p>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="bg-white border border-gray-200 rounded-lg p-10 text-center">
                <p class="font-semibold text-gray-800">Tidak ada pesanan pada filter ini</p>
                <p class="text-sm text-gray-500 mt-1">Pesanan baru akan muncul setelah pembeli membuat pesanan dari halaman supplier.</p>
            </div>
        @endforelse
    </div>

    {{ $orders->links() }}
</div>
@endsection
