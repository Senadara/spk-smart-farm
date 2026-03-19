{{-- resources/views/components/plant-monitoring/daily-report-card.blade.php --}}
@props(['report'])

@php
    // Mapping kondisiDaun ke warna badge
    $daunColors = [
        'sehat'    => 'bg-green-100 text-green-700',
        'kering'   => 'bg-red-100 text-red-700',
        'layu'     => 'bg-orange-100 text-orange-700',
        'kuning'   => 'bg-yellow-100 text-yellow-700',
        'keriting' => 'bg-orange-100 text-orange-700',
        'bercak'   => 'bg-red-100 text-red-700',
        'rusak'    => 'bg-red-100 text-red-700',
    ];

    // Mapping statusTumbuh ke label yang lebih readable
    $statusLabels = [
        'bibit'            => 'Bibit',
        'perkecambahan'    => 'Perkecambahan',
        'vegetatifAwal'    => 'Vegetatif Awal',
        'vegetatifLanjut'  => 'Vegetatif Lanjut',
        'generatifAwal'    => 'Generatif Awal',
        'generatifLanjut'  => 'Generatif Lanjut',
        'panen'            => 'Panen',
        'dormansi'         => 'Dormansi',
    ];

    $badgeClass = $daunColors[$report->kondisiDaun] ?? 'bg-gray-100 text-gray-700';
    $statusLabel = $statusLabels[$report->statusTumbuh] ?? $report->statusTumbuh;
@endphp

<div class="bg-white rounded-2xl p-5 shadow-sm">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 20h10M10 20c5.5-2.5.8-6.4 3-10 2.2 3.6-2.5 7.5 3 10M12 10c-1-1-2-3.5-1-6 4 0 6 3 6 6-3 0-5-1-5-3"/>
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-gray-800">{{ $report->namaBlok }}</h3>
                <p class="text-xs text-gray-400">
                    Laporan: {{ \Carbon\Carbon::parse($report->tanggalLaporan)->translatedFormat('d M Y, H:i') }}
                </p>
            </div>
        </div>
        {{-- Status Tumbuh Badge --}}
        <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
            {{ $statusLabel }}
        </span>
    </div>

    {{-- Info Grid --}}
    <div class="grid grid-cols-2 gap-3 mb-3">
        {{-- Tinggi Tanaman --}}
        <div class="bg-gray-50 rounded-xl p-3">
            <p class="text-xs text-gray-500 mb-1">Tinggi Tanaman</p>
            <p class="text-gray-800 text-lg font-semibold">
                {{ $report->tinggiTanaman !== null ? number_format($report->tinggiTanaman, 1) . ' cm' : '-' }}
            </p>
        </div>

        {{-- Kondisi Daun --}}
        <div class="bg-gray-50 rounded-xl p-3">
            <p class="text-xs text-gray-500 mb-1">Kondisi Daun</p>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                {{ ucfirst($report->kondisiDaun ?? '-') }}
            </span>
        </div>
    </div>

    {{-- Aktivitas Harian (checkmarks) --}}
    <div class="flex items-center gap-4 text-sm text-gray-600 mb-3">
        <span class="{{ $report->penyiraman ? 'text-green-600' : 'text-gray-400' }}">
            {{ $report->penyiraman ? "\u2713" : "\u2717" }} Penyiraman
        </span>
        <span class="{{ $report->pruning ? 'text-green-600' : 'text-gray-400' }}">
            {{ $report->pruning ? "\u2713" : "\u2717" }} Pruning
        </span>
        <span class="{{ $report->repotting ? 'text-green-600' : 'text-gray-400' }}">
            {{ $report->repotting ? "\u2713" : "\u2717" }} Repotting
        </span>
    </div>

    {{-- Catatan --}}
    @if($report->catatan)
        <div class="bg-amber-50 rounded-xl p-3">
            <p class="text-xs text-gray-500 mb-1">Catatan Petugas</p>
            <p class="text-sm text-gray-700">{{ Str::limit($report->catatan, 120) }}</p>
        </div>
    @endif
</div>
