@extends('layouts.app')

@section('title', 'Dashboard DSS Supplier (SAW)')
@section('breadcrumb', 'Supplier > Dashboard SAW')


@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm">{{ session('success') }}</div>
    @endif

    @php $userResolved = isset($userId) && $userId !== null; @endphp

    @if(! $userResolved)
        <div class="rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 p-6 text-sm text-amber-950 shadow-sm mb-6">
            <p class="font-extrabold text-base flex items-center gap-2 mb-3">Akun Anda belum bisa dipakai sebagai pemilik konfig DSS</p>
            <p class="leading-relaxed text-amber-900/95 max-w-3xl">
                Session login tidak memetakan Anda ke primary key pada tabel <code class="bg-white px-1 rounded border border-amber-200">users</code>
                - dashboard SAW &amp; cache ranking tidak bisa diikat per pengguna sampai itu diperbaiki.
                <span class="block mt-2 text-xs opacity-85">Konfigurasikan backend auth agar menyimpan ID numerik Laravel yang sama dengan <strong>users.id</strong>, atau pastikan ada baris <strong>users</strong> dengan email yang sama persis seperti di session Anda.</span>
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('spk.suppliers.dss.config') }}" class="inline-flex items-center rounded-xl bg-amber-800 px-4 py-2 text-xs font-black text-white hover:bg-amber-900">Pelajari di halaman AHP</a>
                <a href="{{ route('settings.index') }}" class="inline-flex items-center rounded-xl border border-amber-300 bg-white px-4 py-2 text-xs font-bold text-amber-900 hover:bg-amber-100/80">&larr; Kembali ke pengaturan</a>
            </div>
        </div>
    @endif

    <div class="rounded-3xl bg-gradient-to-r from-purple-950 via-indigo-900 to-emerald-900 px-6 py-8 sm:px-10 text-white shadow-xl shadow-indigo-900/30 mb-6">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-200/95">Operational - DSS</p>
                <h1 class="mt-2 text-2xl sm:text-3xl font-black tracking-tight">Ranking supplier - SAW</h1>
                <p class="mt-3 text-sm text-indigo-100/95 leading-relaxed max-w-2xl">
                    Bobot dari langkah konfig AHP digunakan untuk menghitung skor gabungan setiap supplier secara real-time ketika Anda mengganti produk di bawah.
                </p>
            </div>
            <div class="flex flex-shrink-0 flex-wrap gap-2">
                <a href="{{ route('spk.suppliers.dss.config') }}" class="inline-flex items-center justify-center rounded-xl bg-emerald-400 px-5 py-2.5 text-sm font-black text-emerald-950 shadow-lg hover:bg-emerald-300 transition whitespace-nowrap">&larr; Edit bobot - AHP</a>
                <a href="{{ route('spk.suppliers.products') }}" class="inline-flex items-center justify-center rounded-xl bg-white/10 px-5 py-2.5 text-sm font-bold ring-1 ring-white/25 hover:bg-white/15 transition whitespace-nowrap">Komparasi produk</a>
            </div>
        </div>
    </div>
    <div x-data="{ 
        searchQuery: '', 
        get hasResults() { 
            if (this.searchQuery === '') return true;
            const q = this.searchQuery.toLowerCase();
            const names = {{ json_encode($produks->pluck('nama')->map(fn($n) => strtolower($n))) }};
            return names.some(n => n.includes(q));
        }
    }" class="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm mb-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h2 class="text-sm font-bold uppercase text-gray-600 tracking-wider">Katalog Produk</h2>
                <p class="text-xs text-gray-500 mt-1">Pilih produk untuk melihat perbandingan supplier terbaik berdasarkan bobot AHP Anda.</p>
            </div>
            <div class="relative w-full sm:w-72">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" /></svg>
                </div>
                <input x-model="searchQuery" type="text" placeholder="Cari nama produk..." class="block w-full pl-11 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-emerald-500 focus:border-emerald-500 bg-gray-50 hover:bg-white transition-colors">
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 max-h-[320px] overflow-y-auto pr-2 custom-scrollbar">
            @foreach($produks as $p)
            <a href="?produk_id={{ $p->id }}" 
               x-show="searchQuery === '' || '{{ strtolower($p->nama) }}'.includes(searchQuery.toLowerCase())"
               x-transition.opacity.duration.200ms
               class="group flex flex-col p-4 rounded-2xl border transition-all duration-200 {{ $produkId == $p->id ? 'border-emerald-500 bg-emerald-50/50 ring-1 ring-emerald-500 shadow-md scale-[1.02]' : 'border-gray-100 hover:border-emerald-200 hover:bg-emerald-50/30 hover:shadow-sm bg-white' }}">
                <div class="flex items-start justify-between mb-3">
                    <div class="p-2.5 rounded-xl transition-colors {{ $produkId == $p->id ? 'bg-emerald-100 text-emerald-600' : 'bg-gray-50 text-gray-400 group-hover:bg-emerald-100/50 group-hover:text-emerald-500' }}">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    @if($produkId == $p->id)
                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500 text-white shadow-sm">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    @endif
                </div>
                <h3 class="font-bold text-gray-900 text-sm line-clamp-2 leading-tight mb-1">{{ $p->nama }}</h3>
                <div class="mt-auto pt-2 flex items-center gap-1.5 text-xs font-medium {{ $produkId == $p->id ? 'text-emerald-700' : 'text-gray-500' }}">
                    <svg class="w-3.5 h-3.5 opacity-75" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    {{ $p->suppliers_count }} Mitra
                </div>
            </a>
            @endforeach
        </div>

        <div x-show="!hasResults" x-cloak class="py-12 text-center flex flex-col items-center justify-center border-2 border-dashed border-gray-100 rounded-2xl mt-4">
            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-sm font-bold text-gray-700">Produk tidak ditemukan</p>
            <p class="text-xs text-gray-500 mt-1 max-w-xs">Tidak ada produk yang cocok dengan pencarian "<span x-text="searchQuery" class="font-medium"></span>".</p>
        </div>
    </div>

    @if($bobots->isEmpty())
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-sm text-amber-900">
        Bobot AHP belum valid. <a href="{{ route('spk.suppliers.dss.config') }}" class="font-bold underline">Atur perbandingan kriteria</a> terlebih dahulu (CR harus <= 0.1).
    </div>
    @else
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
                <h2 class="text-sm font-bold uppercase text-gray-600 mb-2">Status Konsistensi AHP</h2>
                @if($latestConfig)
                <p class="text-3xl font-black {{ $latestConfig->cr <= 0.1 ? 'text-emerald-600' : 'text-red-600' }}">{{ number_format($latestConfig->cr, 4) }}</p>
                <p class="text-xs text-gray-500 mt-1">CR {{ $latestConfig->cr <= 0.1 ? '<= 0.1 (Valid)' : '> 0.1 (Invalid)' }} - v{{ $latestConfig->version }}</p>
                @else
                <p class="text-sm text-gray-500">Belum ada konfigurasi tersimpan.</p>
                @endif
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
                <h2 class="text-sm font-bold uppercase text-gray-600 mb-3">Bobot Kriteria</h2>
                <canvas id="weightsPie" height="200"></canvas>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
                <h2 class="text-sm font-bold uppercase text-gray-600 mb-3">Radar Kriteria</h2>
                <canvas id="weightsRadar" height="200"></canvas>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm overflow-hidden">
                <h2 class="text-sm font-bold uppercase text-gray-600 mb-4">Peringkat Supplier (SAW)</h2>
                @if($rankings->isEmpty())
                <p class="text-sm text-gray-500 py-6 text-center">Tidak ada data ranking untuk produk ini.</p>
                @else
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">Rank</th>
                            <th class="px-4 py-3 text-left">Supplier</th>
                            <th class="px-4 py-3 text-left">Skor Vi</th>
                            <th class="px-4 py-3 text-left">Visual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @php $maxScore = $rankings->max('final_score') ?: 1; @endphp
                        @foreach($rankings as $r)
                        <tr class="{{ $r->ranking === 1 ? 'bg-emerald-50/50' : '' }}">
                            <td class="px-4 py-3 font-black text-lg {{ $r->ranking === 1 ? 'text-emerald-600' : 'text-gray-400' }}">#{{ $r->ranking }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $r->supplier->nama ?? '-' }}</td>
                            <td class="px-4 py-3 font-mono">{{ number_format($r->final_score, 4) }}</td>
                            <td class="px-4 py-3 w-1/3">
                                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500 rounded-full" style="width: {{ ($r->final_score / $maxScore) * 100 }}%"></div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>

            @if($insights->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
                <h2 class="text-sm font-bold uppercase text-gray-600 mb-3">Insight Rekomendasi</h2>
                <ul class="space-y-2">
                    @foreach($insights as $insight)
                    <li class="text-sm px-3 py-2 rounded-lg border
                        @if($insight['severity'] === 'danger') bg-red-50 border-red-100 text-red-800
                        @elseif($insight['severity'] === 'warning') bg-amber-50 border-amber-100 text-amber-900
                        @elseif($insight['severity'] === 'success') bg-emerald-50 border-emerald-100 text-emerald-800
                        @else bg-blue-50 border-blue-100 text-blue-800 @endif">
                        {{ $insight['message'] }}
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>

@if($bobots->isNotEmpty())
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const labels = @json($bobots->map(fn($b) => $b->parameter->nama_parameter));
    const data = @json($bobots->map(fn($b) => round($b->bobot, 4)));
    const colors = ['#10b981','#8b5cf6','#f59e0b','#3b82f6','#ef4444'];

    new Chart(document.getElementById('weightsPie'), {
        type: 'pie',
        data: { labels, datasets: [{ data, backgroundColor: colors }] },
        options: { plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('weightsRadar'), {
        type: 'radar',
        data: {
            labels,
            datasets: [{ label: 'Bobot AHP', data, backgroundColor: 'rgba(16,185,129,0.2)', borderColor: '#10b981' }]
        },
        options: { scales: { r: { beginAtZero: true, max: Math.max(...data) * 1.2 || 1 } } }
    });
});
</script>
@endpush
@endif
@endsection
