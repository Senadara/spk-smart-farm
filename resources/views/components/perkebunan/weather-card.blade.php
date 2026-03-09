{{--
Weather Card — Menampilkan data cuaca real-time dari BMKG.

Props:
- $weather : array — Data dari WeatherService::getForecast()
--}}

@props([
    'weather' => [],
])

@php
    $hasError = ($weather['error'] ?? false) === true;
    $current  = $weather['current'] ?? [];
    $forecast = $weather['forecast'] ?? [];

    // SVG icon mapping berdasarkan BMKG weather icon identifiers
    $weatherIcons = [
        'cerah'          => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
        'cerah-berawan'  => '<path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/><circle cx="12" cy="10" r="4"/><path d="M10 15.5A4 4 0 1 0 17 17H7a3 3 0 0 1-.5-5.95"/>',
        'berawan'        => '<path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>',
        'berawan-tebal'  => '<path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>',
        'hujan-ringan'   => '<path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M16 14v6m-4-4v6m-4-4v6"/>',
        'hujan-sedang'   => '<path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M16 14v6m-4-4v6m-4-4v6m12-4v6"/>',
        'hujan-lebat'    => '<path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M16 14v6m-4-4v6m-4-4v6m12-4v6m-16-2v6"/>',
        'hujan-lokal'    => '<path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M16 14v6m-4-4v6"/>',
        'petir'          => '<path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M13 11l-4 6h6l-4 6"/>',
        'kabut'          => '<path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><line x1="3" y1="20" x2="21" y2="20"/><line x1="5" y1="23" x2="19" y2="23"/>',
        'asap'           => '<path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><line x1="3" y1="20" x2="21" y2="20"/>',
    ];

    $currentIconKey = $current['icon'] ?? 'cerah';
    $currentIconSvg = $weatherIcons[$currentIconKey] ?? $weatherIcons['cerah'];
@endphp

<x-card>
    @if($hasError)
        {{-- Error State --}}
        <div class="p-6 text-center">
            <svg class="w-12 h-12 mx-auto text-[var(--color-gray-300)] mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2 12a10 10 0 0 1 18-6m2 6a10 10 0 0 1-18 6"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>
                <line x1="4" y1="4" x2="20" y2="20" stroke-width="2"/>
            </svg>
            <p class="text-sm text-[var(--color-gray-400)]">Data cuaca tidak tersedia</p>
            <p class="text-xs text-[var(--color-gray-300)] mt-1">Gagal terhubung ke layanan BMKG</p>
        </div>
    @else
        <div class="p-5">
            {{-- Header --}}
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-[var(--color-gray-900)]">Cuaca RFC {{ $weather['location'] ?? 'Sarirogo' }}</h3>
                <span class="inline-flex items-center gap-1 text-xs text-[var(--color-gray-400)]">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                    {{ $weather['last_update'] ?? '-' }}
                </span>
            </div>

            {{-- Current Weather --}}
            <div class="flex items-center gap-3 sm:gap-4 mb-4">
                <svg class="w-10 h-10 sm:w-14 sm:h-14 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    {!! $currentIconSvg !!}
                </svg>
                <div>
                    <p class="text-3xl sm:text-4xl font-bold text-[var(--color-gray-900)] leading-none">{{ $current['temperature'] ?? '-' }}°</p>
                    <p class="text-sm text-[var(--color-gray-500)] mt-1">{{ $current['description'] ?? '-' }}</p>
                </div>
            </div>

            {{-- Weather Details --}}
            <div class="flex flex-wrap items-center gap-2 sm:gap-4 text-sm text-[var(--color-gray-500)] mb-4 pb-4 border-b border-[var(--color-gray-100)]">
                <span class="inline-flex items-center gap-1.5" title="Kelembaban">
                    <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>
                    </svg>
                    {{ $current['humidity'] ?? '-' }}%
                </span>
                <span class="inline-flex items-center gap-1.5" title="Kecepatan Angin">
                    <svg class="w-4 h-4 text-teal-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.7 7.7A7.1 7.1 0 0 0 6.3 19M9.4 4.6A7.1 7.1 0 0 1 20.8 16.2"/>
                        <path stroke-linecap="round" d="M2 12h10"/>
                    </svg>
                    {{ $current['wind_speed'] ?? '-' }} km/j
                </span>
                <span class="inline-flex items-center gap-1.5" title="Arah Angin">
                    <svg class="w-4 h-4 text-[var(--color-gray-400)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m-7 7l7-7 7 7"/>
                    </svg>
                    {{ $current['wind_direction'] ?? '-' }}
                </span>
            </div>

            {{-- Forecast Mini --}}
            @if(count($forecast) > 0)
                <div>
                    <p class="text-xs font-medium text-[var(--color-gray-400)] uppercase tracking-wider mb-2">Prakiraan</p>
                    <div class="flex gap-3 overflow-x-auto pb-1">
                        @foreach(array_slice($forecast, 0, 4) as $fc)
                            @php
                                $fcIconKey = $fc['icon'] ?? 'cerah';
                                $fcIconSvg = $weatherIcons[$fcIconKey] ?? $weatherIcons['cerah'];
                            @endphp
                            <div class="flex flex-col items-center gap-1 min-w-[52px] p-2 rounded-lg bg-[var(--color-gray-50)]">
                                <span class="text-xs text-[var(--color-gray-400)]">{{ $fc['time'] ?? '-' }}</span>
                                <svg class="w-6 h-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    {!! $fcIconSvg !!}
                                </svg>
                                <span class="text-sm font-semibold text-[var(--color-gray-700)]">{{ $fc['temperature'] ?? '-' }}°</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</x-card>
