@extends('layouts.app')

@section('title', 'Pesanan Supplier')
@section('breadcrumb', 'Pesanan')

@section('content')
<div class="max-w-[1600px] mx-auto space-y-5" x-data="{ rejectOpen: false, rejectAction: '', rejectOrder: '', openReject(action, orderId) { this.rejectAction = action; this.rejectOrder = orderId; this.rejectOpen = true; } }">
    @if(session('success') || session('error'))
        <div class="rounded-lg border px-4 py-3 text-sm font-semibold {{ session('success') ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700' }}">
            {{ session('success') ?? session('error') }}
        </div>
    @endif

    <div>
        <p class="text-sm font-medium text-emerald-700 mb-1">{{ $store->nama }}</p>
        <h1 class="text-2xl font-bold text-gray-900">Manajemen Pesanan</h1>
        <p class="text-sm text-gray-500 mt-1">Pesanan dicatat di sistem, sedangkan pembayaran dan bukti transfer dikonfirmasi langsung di luar sistem.</p>
    </div>

    <div class="flex gap-2 overflow-x-auto pb-1">
        @foreach(['menunggu' => 'Menunggu', 'diterima' => 'Diproses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan', 'expired' => 'Kedaluwarsa'] as $key => $label)
            <a href="{{ route('supplier.orders.index', ['status' => $key]) }}"
                class="inline-flex shrink-0 items-center gap-2 rounded-full border px-3.5 py-2 text-sm font-semibold no-underline transition {{ request('status') === $key ? 'border-emerald-500 bg-emerald-50 text-emerald-700 ring-2 ring-emerald-100' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
                <span>{{ $label }}</span>
                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-bold text-gray-900">{{ number_format($statusCounts[$key] ?? 0) }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-white p-2.5">
        <input name="search" value="{{ request('search') }}" placeholder="Cari ID pesanan atau pembeli..."
            class="h-10 min-w-[220px] flex-1 rounded-lg border border-gray-300 px-3 text-sm focus:border-emerald-400 focus:outline-none">
        <select name="status" class="h-10 min-w-[170px] flex-1 rounded-lg border border-gray-300 bg-white px-3 text-sm focus:border-emerald-400 focus:outline-none sm:flex-none">
            <option value="">Semua status</option>
            @foreach(['menunggu', 'diterima', 'selesai', 'ditolak', 'dibatalkan', 'expired'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button class="h-10 flex-1 rounded-lg bg-gray-900 px-4 text-sm font-semibold text-white sm:flex-none">Cari</button>
    </form>

    <div class="space-y-3">
        @forelse($orders as $order)
            @php
                $statusClass = match($order->status) {
                    'menunggu' => 'bg-amber-50 text-amber-700',
                    'diterima' => 'bg-sky-50 text-sky-700',
                    'selesai' => 'bg-emerald-50 text-emerald-700',
                    'ditolak' => 'bg-rose-50 text-rose-700',
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
                        @if($order->statusReason)
                            <div class="mt-3 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                <span class="font-bold">Catatan status:</span> {{ $order->statusReason }}
                            </div>
                        @endif
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
                            <div class="mt-3 flex flex-wrap justify-start gap-2 lg:justify-end">
                                <form method="POST" action="{{ route('supplier.orders.status', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="diterima">
                                    <button class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        Terima
                                    </button>
                                </form>
                                <button type="button" @click="openReject(@js(route('supplier.orders.status', $order)), @js('#'.strtoupper(substr($order->id, 0, 12))))"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    Tolak
                                </button>
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

    <div x-show="rejectOpen" x-cloak x-transition.opacity class="fixed inset-0 z-[70] flex items-center justify-center bg-black/45 p-4" @click.self="rejectOpen = false">
        <div class="w-full max-w-md rounded-xl bg-white shadow-xl" @click.stop>
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Tolak Pesanan</h2>
                    <p class="mt-0.5 text-xs text-slate-500" x-text="rejectOrder"></p>
                </div>
                <button type="button" @click="rejectOpen = false" class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" :action="rejectAction" class="space-y-4 px-5 py-5" onsubmit="return confirm('Tolak pesanan ini?')">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="ditolak">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Alasan penolakan</label>
                    <textarea name="reason" rows="4" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-rose-400 focus:outline-none" placeholder="Contoh: Stok produk sedang kosong atau jadwal pengiriman belum tersedia."></textarea>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" @click="rejectOpen = false" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Batal</button>
                    <button class="rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700">Tolak & Kirim Email</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
