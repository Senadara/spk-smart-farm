@extends('layouts.app')

@section('title', 'Dashboard DSS Supplier (SAW)')
@section('breadcrumb', 'Supplier DSS > Ranking (SAW)')

@section('content')
@php
    $selectedProduct = $produks->firstWhere('id', $produkId);
    $userResolved = isset($userId) && $userId !== null;
    $hasValidAhp = $bobots->isNotEmpty() && $latestConfig;
    $bestRanking = $rankings->first();
    $runnerUpRanking = $rankings->skip(1)->first();
    $maxScore = max((float) ($rankings->max('final_score') ?? 0), 1);
    $lastCalculatedAt = $rankings->pluck('last_calculated_at')->filter()->max();
    $evaluationBySupplier = collect($evaluation)->keyBy('id');

    $parameterKeyFor = function (?string $name): string {
        $lower = strtolower((string) $name);

        if (str_contains($lower, 'harga')) {
            return 'price';
        }

        if (str_contains($lower, 'kualitas')) {
            return 'quality';
        }

        if (str_contains($lower, 'waktu') || str_contains($lower, 'kecepatan')) {
            return 'delivery_time';
        }

        if (str_contains($lower, 'jarak')) {
            return 'distance';
        }

        return str_replace(' ', '_', $lower);
    };

    $formatAttribute = function (string $key, mixed $value): string {
        if ($value === null || $value === '') {
            return '-';
        }

        if (! is_numeric($value)) {
            return (string) $value;
        }

        $number = (float) $value;

        return match ($key) {
            'price' => 'Rp '.number_format($number, 0, ',', '.'),
            'quality' => number_format($number, 1, ',', '.').'/5',
            'delivery_time' => number_format($number, 1, ',', '.').' hari',
            'distance' => number_format($number, 1, ',', '.').' km',
            default => number_format($number, 2, ',', '.'),
        };
    };

    $formatScore = fn ($score): string => number_format((float) $score, 4, ',', '.');
    $formatPercent = fn ($value): string => number_format(min(100, max(0, (float) $value * 100)), 1, ',', '.');
    $formatDateTime = fn ($date): string => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y H:i') : 'Belum dihitung';

    $weightRows = $bobots
        ->map(fn ($b) => [
            'name' => $b->parameter?->nama_parameter ?? 'Parameter',
            'type' => $b->parameter?->tipe ?? 'benefit',
            'key' => $parameterKeyFor($b->parameter?->nama_parameter),
            'weight' => (float) $b->bobot,
        ])
        ->sortByDesc('weight')
        ->values();

    $statusSteps = [
        [
            'label' => 'Bobot AHP',
            'done' => $hasValidAhp,
            'note' => $hasValidAhp ? 'Valid dan siap dipakai SAW' : 'Belum valid',
        ],
        [
            'label' => 'Produk',
            'done' => (bool) $selectedProduct,
            'note' => $selectedProduct ? $selectedProduct->nama : 'Belum dipilih',
        ],
        [
            'label' => 'Data Supplier',
            'done' => (int) ($selectedProduct?->suppliers_count ?? 0) > 0,
            'note' => number_format((int) ($selectedProduct?->suppliers_count ?? 0)).' supplier terhubung',
        ],
        [
            'label' => 'Ranking SAW',
            'done' => $rankings->isNotEmpty(),
            'note' => $rankings->isNotEmpty() ? $rankings->count().' supplier dibandingkan' : 'Belum ada hasil',
        ],
    ];

    $quickProducts = $produks->where('suppliers_count', '>', 0)->take(12);
@endphp

<div class="mx-auto max-w-7xl space-y-5">
    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if(! $userResolved)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-bold">Akun belum bisa dipakai untuk konfigurasi DSS.</p>
            <p class="mt-1 leading-relaxed">Session login belum terhubung ke data pengguna Laravel. Ranking SAW belum bisa disimpan per pengguna sampai ID atau email akun sesuai dengan tabel pengguna.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('spk.suppliers.dss.config') }}" class="rounded-lg bg-amber-700 px-4 py-2 text-xs font-bold text-white hover:bg-amber-800">Buka Konfigurasi AHP</a>
                <a href="{{ route('settings.index') }}" class="rounded-lg border border-amber-300 bg-white px-4 py-2 text-xs font-bold text-amber-900 hover:bg-amber-100">Buka Pengaturan</a>
            </div>
        </div>
    @endif

    <section class="rounded-lg border border-gray-200 bg-white p-5 md:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Supplier DSS</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Ranking Supplier dengan SAW</h1>
                <p class="mt-1 max-w-3xl text-sm leading-relaxed text-gray-500">
                    SAW memakai bobot AHP yang valid untuk memilih supplier terbaik berdasarkan harga, kualitas, waktu pengiriman, jarak, dan parameter lain yang tersedia.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('spk.suppliers.dss.config') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">Atur AHP</a>
                <a href="{{ route('spk.suppliers.products') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700" style="text-decoration:none;">Cari Barang</a>
                <a href="{{ route('spk.suppliers.orders.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">Histori Pesanan</a>
            </div>
        </div>
    </section>

    <x-page-hint title="Cara membaca rekomendasi SAW" tone="sky" :open="false">
        Pilih produk yang ingin direstock. Sistem mengambil supplier yang menjual produk tersebut, menormalisasi nilai tiap parameter, lalu mengalikan nilai normalisasi dengan bobot AHP. Supplier dengan skor tertinggi menjadi rekomendasi utama. Jika hasil kosong, periksa bobot AHP, relasi produk-supplier, dan nilai parameter supplier.
    </x-page-hint>

    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Produk Aktif</p>
            <p class="mt-2 line-clamp-1 text-lg font-black text-gray-900">{{ $selectedProduct?->nama ?? 'Belum ada produk' }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ number_format((int) ($selectedProduct?->suppliers_count ?? 0)) }} supplier tersedia</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Rekomendasi</p>
            <p class="mt-2 line-clamp-1 text-lg font-black text-gray-900">{{ $bestRanking?->supplier?->nama ?? 'Belum tersedia' }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $bestRanking ? 'Skor '.$formatScore($bestRanking->final_score) : 'Jalankan SAW dengan data lengkap' }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Konsistensi AHP</p>
            <p class="mt-2 text-lg font-black {{ $latestConfig && $latestConfig->cr <= 0.1 ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ $latestConfig ? number_format((float) $latestConfig->cr, 4, ',', '.') : 'Belum ada' }}
            </p>
            <p class="mt-1 text-xs text-gray-500">{{ $latestConfig ? ($latestConfig->cr <= 0.1 ? 'CR valid, versi '.$latestConfig->version : 'CR belum valid') : 'Atur AHP terlebih dahulu' }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Terakhir Dihitung</p>
            <p class="mt-2 text-lg font-black text-gray-900">{{ $formatDateTime($lastCalculatedAt) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $rankings->count() }} hasil ranking aktif</p>
        </div>
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-4 md:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Pilih Produk</p>
                <h2 class="mt-1 text-lg font-bold text-gray-900">Produk yang akan dibandingkan suppliernya</h2>
                <p class="mt-1 text-sm text-gray-500">Pilih satu produk untuk menghitung ranking supplier berdasarkan bobot AHP saat ini.</p>
            </div>
            <form method="GET" action="{{ route('spk.suppliers.dss.dashboard') }}" class="grid w-full grid-cols-1 gap-2 sm:grid-cols-[1fr_auto] lg:max-w-xl">
                <select name="produk_id" class="rounded-lg border border-gray-300 px-3 py-3 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                    @forelse($produks as $product)
                        <option value="{{ $product->id }}" @selected((int) $produkId === (int) $product->id)>
                            {{ $product->nama }} ({{ $product->suppliers_count }} supplier)
                        </option>
                    @empty
                        <option value="">Belum ada produk</option>
                    @endforelse
                </select>
                <button class="rounded-lg bg-gray-900 px-5 py-3 text-sm font-bold text-white hover:bg-gray-800" @disabled($produks->isEmpty())>Tampilkan</button>
            </form>
        </div>

        @if($quickProducts->isNotEmpty())
            <div class="mt-4 flex gap-2 overflow-x-auto pb-1">
                @foreach($quickProducts as $product)
                    <a href="{{ route('spk.suppliers.dss.dashboard', ['produk_id' => $product->id]) }}"
                        class="shrink-0 rounded-full px-4 py-2 text-xs font-bold {{ (int) $produkId === (int) $product->id ? 'bg-emerald-600 text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50' }}"
                        style="text-decoration:none;">
                        {{ $product->nama }}
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    @if(! $hasValidAhp)
        <section class="rounded-lg border border-amber-200 bg-amber-50 p-6 text-amber-900">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-lg font-black">Bobot AHP belum siap untuk SAW.</p>
                    <p class="mt-1 max-w-2xl text-sm leading-relaxed">Lengkapi perbandingan kriteria AHP sampai nilai CR tidak lebih dari 0,1. Setelah valid, halaman ini akan menampilkan ranking supplier untuk produk yang dipilih.</p>
                </div>
                <a href="{{ route('spk.suppliers.dss.config') }}" class="rounded-lg bg-amber-700 px-5 py-3 text-center text-sm font-bold text-white hover:bg-amber-800" style="text-decoration:none;">Atur Bobot AHP</a>
            </div>
        </section>
    @else
        <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-5">
                @if($bestRanking)
                    @php
                        $bestScorePercent = $formatPercent($bestRanking->final_score);
                        $gapToRunner = $runnerUpRanking
                            ? max(0, (float) $bestRanking->final_score - (float) $runnerUpRanking->final_score)
                            : null;
                    @endphp
                    <section class="rounded-lg border border-emerald-200 bg-emerald-50 p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Rekomendasi Utama</p>
                                <h2 class="mt-1 text-2xl font-black text-gray-900">{{ $bestRanking->supplier?->nama ?? 'Supplier' }}</h2>
                                <p class="mt-1 text-sm text-emerald-900">
                                    Peringkat #1 untuk {{ $selectedProduct?->nama ?? 'produk aktif' }} dengan skor SAW {{ $formatScore($bestRanking->final_score) }}.
                                    @if($gapToRunner !== null)
                                        Selisih dari peringkat #2 adalah {{ $formatScore($gapToRunner) }}.
                                    @endif
                                </p>
                            </div>
                            <div class="w-full rounded-lg border border-emerald-200 bg-white p-4 lg:w-64">
                                <div class="flex items-end justify-between gap-3">
                                    <span class="text-sm font-semibold text-gray-600">Kelayakan SAW</span>
                                    <span class="text-2xl font-black text-emerald-700">{{ $bestScorePercent }}%</span>
                                </div>
                                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-emerald-100">
                                    <div class="h-full rounded-full bg-emerald-600" style="width: {{ $bestScorePercent }}%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if($bestRanking->supplier_id)
                                <a href="{{ route('spk.suppliers.show', $bestRanking->supplier_id) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700" style="text-decoration:none;">Buka Toko</a>
                            @endif
                            <a href="{{ route('spk.suppliers.products', ['search' => $selectedProduct?->nama]) }}" class="rounded-lg border border-emerald-300 bg-white px-4 py-2 text-sm font-bold text-emerald-800 hover:bg-emerald-100" style="text-decoration:none;">Cari Barang Ini</a>
                        </div>
                    </section>
                @endif

                <section class="rounded-lg border border-gray-200 bg-white p-4 md:p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Ranking Supplier</h2>
                            <p class="text-sm text-gray-500">Urutan supplier berdasarkan skor akhir SAW. Nilai kontribusi menunjukkan parameter yang paling mempengaruhi ranking.</p>
                        </div>
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold text-gray-600">{{ $rankings->count() }} supplier</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($rankings as $ranking)
                            @php
                                $evaluationRow = $evaluationBySupplier->get($ranking->supplier_id, []);
                                $attributes = collect(data_get($evaluationRow, 'attributes', []));
                                $normalized = collect(data_get($evaluationRow, 'normalized', []));
                                $scorePercent = $formatPercent($ranking->final_score);
                                $relativePercent = number_format(min(100, max(0, ((float) $ranking->final_score / $maxScore) * 100)), 1, '.', '');
                                $contributions = $weightRows
                                    ->map(function ($weight) use ($attributes, $normalized) {
                                        $norm = (float) ($normalized->get($weight['key']) ?? 0);

                                        return [
                                            'name' => $weight['name'],
                                            'key' => $weight['key'],
                                            'type' => $weight['type'],
                                            'weight' => $weight['weight'],
                                            'raw' => $attributes->get($weight['key']),
                                            'normalized' => $norm,
                                            'contribution' => $norm * $weight['weight'],
                                        ];
                                    })
                                    ->sortByDesc('contribution')
                                    ->take(4)
                                    ->values();
                            @endphp
                            <article class="rounded-lg border {{ $ranking->ranking === 1 ? 'border-emerald-200 bg-emerald-50/60' : 'border-gray-200 bg-white' }} p-4">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="flex min-w-0 gap-3">
                                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg {{ $ranking->ranking === 1 ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700' }} text-sm font-black">
                                            #{{ $ranking->ranking }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="line-clamp-1 text-base font-black text-gray-900">{{ $ranking->supplier?->nama ?? 'Supplier' }}</h3>
                                                @if($ranking->ranking === 1)
                                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700">Rekomendasi</span>
                                                @elseif($ranking->ranking === 2)
                                                    <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-bold text-sky-700">Alternatif</span>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500">Skor Vi {{ $formatScore($ranking->final_score) }} - {{ $scorePercent }}% dari skala SAW.</p>
                                        </div>
                                    </div>

                                    <div class="w-full lg:w-64">
                                        <div class="flex items-center justify-between text-xs font-semibold text-gray-500">
                                            <span>Perbandingan skor</span>
                                            <span>{{ $relativePercent }}%</span>
                                        </div>
                                        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-gray-100">
                                            <div class="h-full rounded-full {{ $ranking->ranking === 1 ? 'bg-emerald-600' : 'bg-sky-500' }}" style="width: {{ $relativePercent }}%"></div>
                                        </div>
                                    </div>
                                </div>

                                @if($contributions->isNotEmpty())
                                    <div class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-4">
                                        @foreach($contributions as $item)
                                            <div class="rounded-lg border border-gray-100 bg-white px-3 py-2">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="min-w-0">
                                                        <p class="line-clamp-1 text-xs font-bold text-gray-900">{{ $item['name'] }}</p>
                                                        <p class="mt-0.5 text-[11px] text-gray-500">{{ $item['type'] === 'cost' ? 'Lebih kecil lebih baik' : 'Lebih besar lebih baik' }}</p>
                                                    </div>
                                                    <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-gray-600">{{ number_format($item['weight'] * 100, 0, ',', '.') }}%</span>
                                                </div>
                                                <div class="mt-2 flex items-end justify-between gap-3 text-xs">
                                                    <span class="font-semibold text-gray-700">{{ $formatAttribute($item['key'], $item['raw']) }}</span>
                                                    <span class="text-gray-500">N {{ number_format($item['normalized'], 2, ',', '.') }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="mt-4 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-500">
                                        Detail kontribusi belum tersedia. Pastikan nilai parameter supplier untuk produk ini sudah lengkap.
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-6 py-10 text-center">
                                <p class="font-bold text-gray-900">Ranking belum tersedia untuk produk ini.</p>
                                <p class="mt-1 text-sm text-gray-500">Pastikan produk sudah memiliki supplier dan setiap supplier memiliki nilai parameter yang dibutuhkan SAW.</p>
                                <div class="mt-4 flex flex-wrap justify-center gap-2">
                                    <a href="{{ route('spk.suppliers.products', ['search' => $selectedProduct?->nama]) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700" style="text-decoration:none;">Cek Barang Supplier</a>
                                    <a href="{{ route('spk.suppliers.dss.config') }}" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">Cek Bobot AHP</a>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </section>

                @if(! empty($evaluation) && $weightRows->isNotEmpty())
                    <details class="rounded-lg border border-gray-200 bg-white p-4 md:p-5">
                        <summary class="cursor-pointer text-sm font-bold text-gray-900">Detail matriks nilai dan normalisasi SAW</summary>
                        <p class="mt-2 text-sm text-gray-500">Bagian ini membantu pengecekan blackbox: nilai asli berasal dari parameter supplier, sedangkan nilai N adalah hasil normalisasi yang dipakai dalam skor SAW.</p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                                    <tr>
                                        <th class="px-3 py-3">Supplier</th>
                                        @foreach($weightRows as $weight)
                                            <th class="px-3 py-3">{{ $weight['name'] }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($evaluation as $row)
                                        @php
                                            $rowAttributes = collect(data_get($row, 'attributes', []));
                                            $rowNormalized = collect(data_get($row, 'normalized', []));
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-3 font-bold text-gray-900">{{ data_get($row, 'name', 'Supplier') }}</td>
                                            @foreach($weightRows as $weight)
                                                <td class="px-3 py-3 text-gray-700">
                                                    <div class="font-semibold">{{ $formatAttribute($weight['key'], $rowAttributes->get($weight['key'])) }}</div>
                                                    <div class="text-xs text-gray-500">N {{ number_format((float) ($rowNormalized->get($weight['key']) ?? 0), 3, ',', '.') }}</div>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </div>

            <aside class="space-y-5">
                <section class="rounded-lg border border-gray-200 bg-white p-5">
                    <h2 class="text-base font-bold text-gray-900">Kesiapan Perhitungan</h2>
                    <div class="mt-4 space-y-3">
                        @foreach($statusSteps as $step)
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full {{ $step['done'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    @if($step['done'])
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4m0 4h.01"/></svg>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-gray-900">{{ $step['label'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $step['note'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Bobot AHP Aktif</h2>
                            <p class="text-xs text-gray-500">Semakin besar bobot, semakin besar pengaruhnya ke SAW.</p>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">v{{ $latestConfig?->version ?? '-' }}</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @foreach($weightRows as $weight)
                            @php $weightPercent = number_format(min(100, max(0, $weight['weight'] * 100)), 1, '.', ''); @endphp
                            <div>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="font-semibold text-gray-800">{{ $weight['name'] }}</span>
                                    <span class="font-black text-gray-900">{{ number_format($weight['weight'] * 100, 1, ',', '.') }}%</span>
                                </div>
                                <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full bg-emerald-600" style="width: {{ $weightPercent }}%"></div>
                                </div>
                                <p class="mt-1 text-[11px] text-gray-500">{{ $weight['type'] === 'cost' ? 'Cost: nilai lebih kecil lebih baik' : 'Benefit: nilai lebih besar lebih baik' }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>

                @if($insights->isNotEmpty())
                    <section class="rounded-lg border border-gray-200 bg-white p-5">
                        <h2 class="text-base font-bold text-gray-900">Insight Rekomendasi</h2>
                        <div class="mt-4 space-y-2">
                            @foreach($insights as $insight)
                                <div class="rounded-lg border px-3 py-2 text-sm leading-relaxed
                                    @if($insight['severity'] === 'danger') border-red-100 bg-red-50 text-red-800
                                    @elseif($insight['severity'] === 'warning') border-amber-100 bg-amber-50 text-amber-900
                                    @elseif($insight['severity'] === 'success') border-emerald-100 bg-emerald-50 text-emerald-800
                                    @else border-sky-100 bg-sky-50 text-sky-800 @endif">
                                    {{ $insight['message'] }}
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="rounded-lg border border-gray-200 bg-white p-5">
                    <h2 class="text-base font-bold text-gray-900">Riwayat AHP</h2>
                    <div class="mt-4 space-y-2">
                        @forelse($configHistory as $config)
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2">
                                <div>
                                    <p class="text-sm font-bold text-gray-900">Versi {{ $config->version }}</p>
                                    <p class="text-xs text-gray-500">CR {{ number_format((float) $config->cr, 4, ',', '.') }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $config->is_valid ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $config->is_valid ? 'Valid' : 'Invalid' }}
                                </span>
                            </div>
                        @empty
                            <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                                Belum ada riwayat konfigurasi.
                            </div>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    @endif
</div>
@endsection
