{{--
KPI Metric Card - Horizontal card with trend indicator.

Props:
- $label : string - KPI name
- $value : string - Formatted value
- $trend : array - { direction, value, status }
--}}

@props([
    'label' => '',
    'value' => '',
    'trend' => ['direction' => 'stable', 'value' => '', 'status' => 'neutral'],
    'hint' => null,
    'formula' => null,
    'source' => null,
])
@php
    $statusColors = [
        'positive' => 'text-emerald-600 bg-emerald-50',
        'warning' => 'text-amber-600 bg-amber-50',
        'negative' => 'text-red-600 bg-red-50',
        'neutral' => 'text-gray-500 bg-gray-50',
    ];
    $badgeClass = $statusColors[$trend['status']] ?? $statusColors['neutral'];

    $arrows = [
        'up' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>',
        'down' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>',
        'stable' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>',
    ];
    $arrowPath = $arrows[$trend['direction']] ?? $arrows['stable'];
@endphp

<div {{ $attributes->merge(['class' => 'bg-white border border-gray-100 rounded-xl p-3 sm:p-3.5 xl:p-4 min-w-0 min-h-[96px] hover:shadow-md transition-all flex flex-col justify-between']) }}>
    <div class="mb-2 flex items-start justify-between gap-2">
        <p class="min-w-0 break-words text-[11px] font-medium uppercase leading-tight tracking-wider text-gray-400" title="{{ $label }}">{{ $label }}</p>
        @if($hint)
            <x-metric-hint :title="$label" :body="$hint" :formula="$formula" :source="$source" />
        @endif
    </div>
    <div class="flex min-w-0 flex-wrap items-end justify-between gap-2">
        <p class="min-w-0 break-words text-base font-bold leading-tight text-gray-900 min-[420px]:text-lg sm:text-xl 2xl:text-2xl" title="{{ $value }}">{{ $value }}</p>
        <span class="inline-flex max-w-full shrink-0 items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-semibold sm:text-[11px] {{ $badgeClass }}">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $arrowPath !!}</svg>
            <span class="truncate">{{ $trend['value'] }}</span>
        </span>
    </div>
</div>
