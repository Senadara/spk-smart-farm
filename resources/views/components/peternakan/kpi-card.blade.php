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

<div {{ $attributes->merge(['class' => 'bg-white border border-gray-100 rounded-xl p-3.5 xl:p-4 min-w-0 min-h-[94px] hover:shadow-md transition-all flex flex-col justify-between']) }}>
    <p class="text-[11px] font-medium text-gray-400 mb-2 uppercase tracking-wider truncate" title="{{ $label }}">{{ $label }}</p>
    <div class="flex items-end justify-between gap-2 min-w-0">
        <p class="text-xl 2xl:text-2xl font-bold text-gray-900 leading-none truncate" title="{{ $value }}">{{ $value }}</p>
        <span class="inline-flex shrink-0 items-center gap-1 px-1.5 py-0.5 text-[11px] font-semibold rounded-full whitespace-nowrap {{ $badgeClass }}">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $arrowPath !!}</svg>
            {{ $trend['value'] }}
        </span>
    </div>
</div>
