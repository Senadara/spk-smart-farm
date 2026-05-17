@props([
    'listKandang' => [],
    'activeKomoditasId' => null,
    'activeKomoditasNama' => '',
])

@php
    $statusConfig = [
        'normal'  => ['label' => 'Aman', 'dot' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'border' => 'border-gray-100 hover:border-emerald-300'],
        'warning' => ['label' => 'Perhatian', 'dot' => 'bg-amber-500', 'badge' => 'bg-amber-50 text-amber-700 border-amber-200', 'border' => 'border-amber-200 hover:border-amber-400 bg-amber-50/20'],
        'danger'  => ['label' => 'Kritis', 'dot' => 'bg-red-500', 'badge' => 'bg-red-50 text-red-700 border-red-200', 'border' => 'border-red-200 hover:border-red-400 bg-red-50/30'],
    ];
    $needsAttention = collect($listKandang)->whereIn('status', ['warning', 'danger'])->count();
@endphp

<section id="daftar-kandang" class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-50 bg-gradient-to-r from-emerald-50/80 to-white">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-gray-900">Kandang Anda</h2>
                    <p class="text-sm text-gray-500 mt-0.5 max-w-xl">
                        {{ count($listKandang) }} kandang untuk <span class="font-medium text-gray-700">{{ $activeKomoditasNama }}</span>.
                        Pilih salah satu untuk membuka halaman detail lengkap.
                    </p>
                </div>
            </div>
            @if($needsAttention > 0)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                {{ $needsAttention }} perlu perhatian
            </span>
            @else
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                Semua kandang aman
            </span>
            @endif
        </div>
    </div>

    @if(count($listKandang) > 0)
    <div class="px-5 py-3 bg-gray-50/60 border-b border-gray-50 flex items-center gap-2 text-xs text-gray-500">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Klik kartu kandang → lihat sensor IoT, tren produksi, dan rekomendasi SPK</span>
    </div>
    @endif

    <div class="p-4">
        @forelse($listKandang as $index => $kandang)
            @php $cfg = $statusConfig[$kandang['status']] ?? $statusConfig['normal']; @endphp
            <a
                href="{{ route('peternakan.show', $kandang['id']) }}?komoditas={{ $activeKomoditasId }}"
                class="group flex items-center gap-4 rounded-xl border-2 px-4 py-3.5 mb-3 last:mb-0 transition-all {{ $cfg['border'] }}"
                style="text-decoration: none;"
            >
                <div class="w-11 h-11 rounded-xl bg-white border border-gray-100 flex flex-col items-center justify-center shrink-0 shadow-sm group-hover:border-emerald-200">
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-emerald-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1"/></svg>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-gray-900 text-sm group-hover:text-emerald-700 transition-colors">{{ $kandang['nama'] }}</h3>
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded-full border {{ $cfg['badge'] }}">{{ $cfg['label'] }}</span>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1.5 text-xs text-gray-500">
                        <span><span class="text-gray-400">Populasi</span> <strong class="text-gray-700">{{ number_format((float)($kandang['jumlah'] ?? 0), 0, ',', '.') }}</strong> ekor</span>
                        <span><span class="text-gray-400">HDP hari ini</span> <strong class="text-gray-700">{{ $kandang['hdp'] }}%</strong></span>
                        <span><span class="text-gray-400">Suhu</span> <strong class="text-gray-700">{{ $kandang['temp'] }}°C</strong></span>
                    </div>
                </div>

                <div class="shrink-0 flex items-center gap-1 text-xs font-semibold text-emerald-600 group-hover:text-emerald-700">
                    <span class="hidden sm:inline">Buka detail</span>
                    <svg class="w-5 h-5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </div>
            </a>
        @empty
            <div class="py-12 text-center">
                <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                </div>
                <p class="text-sm font-medium text-gray-600">Belum ada kandang aktif</p>
                <p class="text-xs text-gray-400 mt-1">Tambahkan unit budidaya untuk komoditas ini di Data Master.</p>
            </div>
        @endforelse
    </div>
</section>

