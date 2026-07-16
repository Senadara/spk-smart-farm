@extends('layouts.app')

@section('title', 'Rekomendasi Restock Supplier')
@section('breadcrumb', 'Inventaris > Cari Supplier Restock')

@section('content')
    @php
        $statusClass = [
            'critical' => 'bg-rose-50 text-rose-700 border-rose-200',
            'warning' => 'bg-amber-50 text-amber-700 border-amber-200',
            'optimal' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        ][$restockNeed['status']] ?? 'bg-slate-50 text-slate-700 border-slate-200';
        $scoreClass = fn ($score) => $score >= 80 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($score >= 65 ? 'bg-sky-50 text-sky-700 border-sky-200' : 'bg-amber-50 text-amber-700 border-amber-200');
    @endphp

    <div class="mx-auto max-w-7xl space-y-5">
        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ session('error') }}</div>
        @endif

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="h-1 bg-gradient-to-r from-emerald-500 via-sky-500 to-amber-500"></div>
            <div class="grid grid-cols-1 gap-5 p-5 lg:grid-cols-[1fr_340px] lg:p-6">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('inventory') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50" style="text-decoration:none;">Kembali</a>
                        <span class="rounded-lg border px-3 py-2 text-xs font-bold {{ $statusClass }}">{{ $restockNeed['priority'] }}</span>
                        <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">{{ $item['barn'] }}</span>
                    </div>
                    <p class="mt-5 text-xs font-bold uppercase tracking-wider text-emerald-700">Cari Supplier Terbaik</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-900 md:text-3xl">{{ $item['name'] }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-600">{{ $restockNeed['message'] }}</p>
                </div>

                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-slate-500">Keranjang supplier</p>
                            <p class="mt-1 text-xl font-black text-slate-900">{{ $cart['total_quantity'] }} barang</p>
                        </div>
                        <a href="{{ route('spk.suppliers.products') }}" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-700" style="text-decoration:none;">Lihat Keranjang</a>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Subtotal sementara: <b class="text-slate-900">Rp {{ number_format($cart['subtotal'], 0, ',', '.') }}</b></p>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase text-slate-400">Stok Saat Ini</p>
                <p class="mt-2 text-lg font-black text-slate-900">{{ $restockNeed['current_label'] }}</p>
                <p class="text-xs text-slate-500">Minimum {{ $restockNeed['minimum_label'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase text-slate-400">Estimasi Habis</p>
                <p class="mt-2 text-lg font-black text-slate-900">{{ $restockNeed['days_label'] }}</p>
                <p class="text-xs text-slate-500">{{ $restockNeed['daily_usage_label'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase text-slate-400">Target Aman</p>
                <p class="mt-2 text-lg font-black text-slate-900">{{ $restockNeed['target_label'] }}</p>
                <p class="text-xs text-slate-500">Reorder {{ $restockNeed['reorder_point_label'] }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase text-slate-400">Saran Pembelian</p>
                <p class="mt-2 text-lg font-black text-slate-900">{{ $restockNeed['needed_label'] }}</p>
                <p class="text-xs text-slate-500">Lead time {{ $restockNeed['lead_time_days'] }} hari</p>
            </div>
        </section>

        <x-page-hint title="Cara kerja rekomendasi restock" tone="sky" :open="true">
            Stok menipis diambil dari inventaris mobile. Sistem mencari produk supplier yang namanya atau kategorinya cocok, menghitung estimasi jumlah beli, lalu mengurutkan supplier menggunakan AHP-SAW jika bobot tersedia. Jika bobot belum ada, sistem memakai estimasi awal dari harga, jarak, dan stok tersedia.
        </x-page-hint>

        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Urutan Supplier yang Disarankan</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ $spkSummary['description'] }}</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-semibold text-slate-600">{{ $spkSummary['title'] }}</span>
                    <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 font-semibold text-slate-600">Update {{ $spkSummary['updated_at'] }}</span>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
                @forelse($recommendations as $recommendation)
                    <article class="flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-start gap-3 border-b border-slate-100 p-4">
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
                                @if($recommendation['image'])
                                    <img src="{{ $recommendation['image'] }}" alt="{{ $recommendation['product_name'] }}" class="h-full w-full object-cover">
                                @else
                                    <span class="text-lg font-black text-slate-300">PR</span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="rounded-full bg-slate-900 px-2.5 py-1 text-[10px] font-bold text-white">#{{ $recommendation['rank'] }}</span>
                                    <span class="rounded-full border px-2.5 py-1 text-[10px] font-bold {{ $scoreClass($recommendation['score_percent']) }}">{{ $recommendation['score_percent'] }}%</span>
                                </div>
                                <h3 class="mt-2 line-clamp-2 text-sm font-black text-slate-900">{{ $recommendation['product_name'] }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ $recommendation['category'] }}</p>
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col p-4">
                            <p class="text-xs font-bold text-slate-900">{{ $recommendation['store_name'] }}</p>
                            <p class="mt-1 line-clamp-1 text-xs text-slate-500">{{ $recommendation['store_location'] }}</p>
                            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                <div class="rounded-lg bg-slate-50 px-3 py-2">
                                    <span class="block text-[10px] font-bold uppercase text-slate-400">Harga</span>
                                    <b class="text-slate-900">{{ $recommendation['price_label'] }}</b>
                                </div>
                                <div class="rounded-lg bg-slate-50 px-3 py-2">
                                    <span class="block text-[10px] font-bold uppercase text-slate-400">Stok Toko</span>
                                    <b class="text-slate-900">{{ $recommendation['stock'] }} {{ $recommendation['unit'] }}</b>
                                </div>
                                <div class="rounded-lg bg-slate-50 px-3 py-2">
                                    <span class="block text-[10px] font-bold uppercase text-slate-400">Jarak</span>
                                    <b class="text-slate-900">{{ $recommendation['distance_label'] }}</b>
                                </div>
                                <div class="rounded-lg bg-slate-50 px-3 py-2">
                                    <span class="block text-[10px] font-bold uppercase text-slate-400">Estimasi</span>
                                    <b class="text-slate-900">{{ $recommendation['delivery_label'] }}</b>
                                </div>
                            </div>

                            <div class="mt-4 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                                <p class="font-bold">Saran beli {{ $recommendation['quantity_label'] }}</p>
                                <p class="mt-0.5">Perkiraan biaya {{ $recommendation['subtotal_label'] }}. {{ $recommendation['conversion_label'] }}.</p>
                            </div>

                            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                                <form method="POST" action="{{ route('spk.suppliers.cart.add') }}" class="flex-1">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $recommendation['product_id'] }}">
                                    <input type="hidden" name="quantity" value="{{ $recommendation['quantity'] }}">
                                    <button class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Tambah ke Keranjang</button>
                                </form>
                                @if($recommendation['whatsapp_url'])
                                    <a href="{{ $recommendation['whatsapp_url'] }}" target="_blank" rel="noopener" class="rounded-lg border border-slate-200 px-3 py-2 text-center text-xs font-bold text-slate-700 hover:bg-slate-50" style="text-decoration:none;">Chat WA</a>
                                @endif
                            </div>
                            <a href="{{ $recommendation['search_url'] }}" class="mt-2 rounded-lg border border-slate-200 px-3 py-2 text-center text-xs font-semibold text-slate-600 hover:bg-slate-50" style="text-decoration:none;">Lihat di Katalog</a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center lg:col-span-2 xl:col-span-3">
                        <p class="text-base font-bold text-slate-900">Belum ada produk supplier yang cocok.</p>
                        <p class="mx-auto mt-2 max-w-xl text-sm text-slate-500">Coba cari manual di katalog supplier. Nanti kalau katalog supplier sudah lengkap, item seperti ini akan otomatis muncul di rekomendasi.</p>
                        <a href="{{ $fallbackSearchUrl }}" class="mt-4 inline-flex rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-700" style="text-decoration:none;">Cari Manual</a>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
