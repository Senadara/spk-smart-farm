@props([
    'label' => '',
    'value' => '',
    'subtitle' => '',
    'icon' => 'activity',
    'iconBg' => 'bg-emerald-50',
    'iconColor' => 'text-emerald-600',
    'valueColor' => 'text-gray-900',
    'trend' => null,
])

@php
    $trendColors = [
        'positive' => 'text-emerald-700 bg-emerald-100',
        'warning' => 'text-amber-700 bg-amber-100',
        'negative' => 'text-red-700 bg-red-100',
        'neutral' => 'text-gray-600 bg-gray-100',
    ];

    $arrows = [
        'up' => 'arrow-up',
        'down' => 'arrow-down',
        'stable' => 'minus',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'h-full bg-white rounded-xl p-5 shadow-sm border border-gray-100 flex flex-col justify-center transition-all hover:shadow-md hover:-translate-y-0.5']) }}>
    <div class="flex items-center gap-4">
        {{-- Icon --}}
        <div class="flex items-center justify-center w-12 h-12 rounded-xl shrink-0 {{ $iconBg }} shadow-sm">
            <i data-lucide="{{ $icon }}" class="w-6 h-6 {{ $iconColor }}"></i>
        </div>

        {{-- Content --}}
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold tracking-wide text-gray-500 mb-1">
                {{ $label }}
            </p>
            <div class="flex items-center gap-2">
                <p class="text-2xl font-bold leading-none {{ $valueColor }}">{{ $value }}</p>

                @if ($trend)
                    @php
                        $badgeClass = $trendColors[$trend['status'] ?? 'neutral'] ?? $trendColors['neutral'];
                        $arrowIcon = $arrows[$trend['direction'] ?? 'stable'] ?? $arrows['stable'];
                    @endphp
                    <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold rounded-md uppercase tracking-wider {{ $badgeClass }}">
                        <i data-lucide="{{ $arrowIcon }}" class="w-3 h-3 {{ !empty($trend['value']) ? 'mr-1' : '' }}"></i>
                        @if (!empty($trend['value']))
                            <span>{{ $trend['value'] }}</span>
                        @endif
                    </span>
                @endif
            </div>

            @if ($subtitle)
                <p class="text-xs text-gray-400 mt-1.5 font-medium truncate">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
</div>
