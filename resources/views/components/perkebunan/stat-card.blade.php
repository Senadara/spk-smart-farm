{{--
Stat Card — Kartu statistik ringkas untuk satu metrik.

Props:
- $label     : string  — Judul metrik
- $value     : string  — Nilai utama
- $subtitle  : string  — Teks pendukung opsional
- $icon      : string  — SVG path(s) untuk ikon
- $iconBg    : string  — Background class ikon
- $iconColor : string  — Text color class ikon
- $trend     : ?array  — { direction, value, status }
--}}

@props([
    'label' => '',
    'value' => '',
    'subtitle' => '',
    'icon' => '',
    'iconBg' => 'bg-[var(--color-primary-lighter)]',
    'iconColor' => 'text-[var(--color-primary)]',
    'valueColor' => 'text-[var(--color-gray-900)]',
    'trend' => null,
])

@php
    $trendColors = [
        'positive' => 'text-emerald-600 bg-emerald-50',
        'warning' => 'text-amber-600 bg-amber-50',
        'negative' => 'text-red-600 bg-red-50',
        'neutral' => 'text-gray-500 bg-gray-50',
    ];

    $arrows = [
        'up' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>',
        'down' =>
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>',
        'stable' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'card']) }}>
    {{-- No inner padding — .card already provides p-5 --}}
    <div class="flex items-start gap-3">
        {{-- Icon --}}
        <div class="flex items-center justify-center w-10 h-10 rounded-xl shrink-0 {{ $iconBg }}">
            <svg class="w-5 h-5 {{ $iconColor }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="1.5">
                {!! $icon !!}
            </svg>
        </div>

        {{-- Content --}}
        <div class="flex-1 min-w-0">
            <p class="text-[11px] font-medium uppercase tracking-wider text-[var(--color-gray-400)] mb-0.5">
                {{ $label }}</p>
            <p class="text-xl font-bold leading-tight {{ $valueColor }}">{{ $value }}</p>

            @if ($subtitle)
                <p class="text-xs text-[var(--color-gray-400)] mt-0.5 leading-snug">{{ $subtitle }}</p>
            @endif

            @if ($trend)
                @php
                    $badgeClass = $trendColors[$trend['status'] ?? 'neutral'] ?? $trendColors['neutral'];
                    $arrowPath = $arrows[$trend['direction'] ?? 'stable'] ?? $arrows['stable'];
                @endphp
                <span
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 text-xs font-semibold rounded-full mt-1.5 {{ $badgeClass }}">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">{!! $arrowPath !!}</svg>
                    {{ $trend['value'] }}
                </span>
            @endif
        </div>
    </div>
</div>
