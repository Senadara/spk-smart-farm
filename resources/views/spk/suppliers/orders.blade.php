@extends('layouts.app')

@section('title', 'Pesanan Saya')
@section('breadcrumb', 'Supplier > Pesanan Saya')

@section('content')
<div class="mx-auto max-w-7xl space-y-5">
    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="rounded-lg border border-gray-200 bg-white p-5">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Belanja Supplier</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Pesanan Saya</h1>
                <p class="mt-1 text-sm text-gray-500">Pantau status pemesanan, lihat rincian barang, dan batalkan pesanan yang belum dikonfirmasi supplier.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('spk.suppliers.index') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cari Toko</a>
                <a href="{{ route('spk.suppliers.products') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Bandingkan Barang</a>
            </div>
        </div>
    </div>

    <div class="flex gap-2 overflow-x-auto pb-1">
        <a href="{{ route('spk.suppliers.orders.index') }}"
            class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold {{ $status === 'all' ? 'bg-gray-900 text-white' : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
            Semua
        </a>
        @foreach($statusLabels as $key => $label)
            <a href="{{ route('spk.suppliers.orders.index', ['status' => $key]) }}"
                class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold {{ $status === $key ? 'bg-gray-900 text-white' : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50' }}">
                {{ $label }} {{ ($statusCounts[$key] ?? 0) > 0 ? '(' . number_format($statusCounts[$key]) . ')' : '' }}
            </a>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse($orders as $order)
            @php
                $statusClass = match($order->status) {
                    'menunggu' => 'bg-amber-50 text-amber-700 border-amber-100',
                    'diterima' => 'bg-blue-50 text-blue-700 border-blue-100',
                    'selesai' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                    'ditolak' => 'bg-red-50 text-red-700 border-red-100',
                    'dibatalkan' => 'bg-gray-100 text-gray-600 border-gray-200',
                    default => 'bg-gray-50 text-gray-600 border-gray-200',
                };
            @endphp
            <article class="rounded-lg border border-gray-200 bg-white p-4 md:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-gray-500">#{{ strtoupper(substr($order->id, 0, 12)) }}</span>
                            <span class="rounded-full border px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ $statusLabels[$order->status] ?? ucfirst($order->status) }}</span>
                            <span class="text-xs text-gray-400">{{ $order->createdAt?->format('d M Y, H:i') }}</span>
                        </div>
                        <h2 class="mt-3 font-bold text-gray-900">{{ $order->store?->nama ?? 'Toko supplier' }}</h2>
                        <p class="mt-1 text-xs text-gray-500">{{ $order->store?->alamat ?? 'Alamat toko belum tersedia' }}</p>

                        <div class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2">
                            @forelse($order->details as $detail)
                                <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                    <p class="text-sm font-semibold text-gray-900">{{ $detail->product?->nama ?? 'Produk' }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">Jumlah: {{ number_format($detail->jumlah) }} {{ $detail->product?->satuan ?? 'unit' }}</p>
                                </div>
                            @empty
                                <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-sm text-gray-500">Rincian barang belum tersedia.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="shrink-0 rounded-lg bg-gray-50 p-4 lg:w-64">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Pesanan</p>
                        <p class="mt-1 text-xl font-black text-gray-900">Rp {{ number_format($order->totalHarga, 0, ',', '.') }}</p>
                        <p class="mt-2 text-xs text-gray-500">Pembayaran dilakukan langsung dengan supplier di luar sistem.</p>

                        @if($order->status === 'menunggu')
                            <form method="POST" action="{{ route('spk.suppliers.orders.cancel', $order) }}" class="mt-4" onsubmit="return confirm('Batalkan pesanan ini?');">
                                @csrf
                                @method('PATCH')
                                <button class="w-full rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Batalkan Pesanan</button>
                            </form>
                        @elseif($order->status === 'diterima')
                            <p class="mt-4 rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700">Pesanan sedang diproses supplier.</p>
                        @elseif($order->status === 'selesai')
                            @php
                                $currentRating = old('rating', $order->rating?->rating ?? 3);
                            @endphp
                            <p class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">Pesanan sudah selesai.</p>

                            <form method="POST" action="{{ route('spk.suppliers.orders.rating', $order) }}" class="mt-4 space-y-3">
                                @csrf
                                @method('PATCH')

                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wider text-gray-400">Rating Kualitas</label>
                                    <select name="rating" class="mt-1 w-full rounded-lg border-gray-200 text-sm font-semibold text-gray-700 focus:border-emerald-500 focus:ring-emerald-500">
                                        <option value="5" @selected((int) $currentRating === 5)>5 - Sangat baik</option>
                                        <option value="4" @selected((int) $currentRating === 4)>4 - Baik</option>
                                        <option value="3" @selected((int) $currentRating === 3)>3 - Netral</option>
                                        <option value="2" @selected((int) $currentRating === 2)>2 - Kurang</option>
                                        <option value="1" @selected((int) $currentRating === 1)>1 - Buruk</option>
                                    </select>
                                    @error('rating')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wider text-gray-400">Catatan</label>
                                    <textarea name="note" rows="2" maxlength="500" placeholder="Opsional"
                                        class="mt-1 w-full rounded-lg border-gray-200 text-sm text-gray-700 focus:border-emerald-500 focus:ring-emerald-500">{{ old('note', $order->rating?->note) }}</textarea>
                                    @error('note')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                    {{ $order->rating ? 'Perbarui Rating' : 'Simpan Rating' }}
                                </button>

                                <p class="text-xs text-gray-500">
                                    @if($order->rating)
                                        Rating Anda saat ini: <span class="font-semibold text-gray-700">{{ $order->rating->rating }}/5</span>.
                                    @else
                                        Belum ada rating. SPK memakai nilai netral <span class="font-semibold text-gray-700">3/5</span> sampai rating disimpan.
                                    @endif
                                </p>
                            </form>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-lg border border-dashed border-gray-200 bg-white p-10 text-center">
                <h2 class="font-bold text-gray-900">Belum ada pesanan</h2>
                <p class="mt-1 text-sm text-gray-500">Buka toko supplier, pilih barang, lalu buat pesanan sederhana.</p>
                <a href="{{ route('spk.suppliers.index') }}" class="mt-4 inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Cari Toko Supplier</a>
            </div>
        @endforelse
    </div>

    {{ $orders->links() }}
</div>
@endsection
