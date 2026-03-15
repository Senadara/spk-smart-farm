@props([
    'label',
    'value',
    'unit',
    'min' => 0,
    'max' => 100,
    'idealMin' => 0,
    'idealMax' => 100,
    'status' => 'normal',
    'icon' => '📊',
])

@php
    $percent = max(0, min(100, (($value - $min) / max($max - $min, 1)) * 100));
    $radius = 45;
    $circumference = 2 * M_PI * $radius;
    $dashOffset = $circumference - ($percent / 100) * $circumference;

    $statusConfig = [
        'normal'  => ['ring' => '#10B981', 'bg' => '#ECFDF5', 'text' => '#065F46', 'label' => 'Normal'],
        'warning' => ['ring' => '#F59E0B', 'bg' => '#FFFBEB', 'text' => '#92400E', 'label' => 'Perhatian'],
        'danger'  => ['ring' => '#EF4444', 'bg' => '#FEF2F2', 'text' => '#991B1B', 'label' => 'Kritis'],
    ];
    $cfg = $statusConfig[$status] ?? $statusConfig['normal'];
@endphp

<div class="bg-white rounded-2xl p-5 flex flex-col items-center gap-3 transition-all hover:shadow-md"
    style="box-shadow: var(--shadow-sm);">

    {{-- Gauge SVG --}}
    <div class="relative w-28 h-28">
        <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
            {{-- Background ring --}}
            <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="#F3F4F6" stroke-width="8"/>
            {{-- Ideal range arc (subtle) --}}
            <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="{{ $cfg['ring'] }}15"
                stroke-width="8"
                stroke-dasharray="{{ $circumference }}"
                stroke-dashoffset="{{ $circumference - (($idealMax - $idealMin) / max($max - $min, 1) * $circumference) }}"
                transform="rotate({{ ($idealMin - $min) / max($max - $min, 1) * 360 }}, 50, 50)"
                stroke-linecap="round"/>
            {{-- Value arc --}}
            <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="{{ $cfg['ring'] }}"
                stroke-width="8"
                stroke-dasharray="{{ $circumference }}"
                stroke-dashoffset="{{ $dashOffset }}"
                stroke-linecap="round"
                class="transition-all duration-700"/>
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span class="text-lg">{{ $icon }}</span>
            <span class="text-lg font-bold text-[var(--color-gray-900)]">{{ $value }}</span>
            <span class="text-[10px] text-[var(--color-gray-500)]">{{ $unit }}</span>
        </div>
    </div>

    {{-- Label + badge --}}
    <div class="text-center">
        <p class="text-sm font-semibold text-[var(--color-gray-800)]">{{ $label }}</p>
        <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold"
            style="background: {{ $cfg['bg'] }}; color: {{ $cfg['text'] }};">
            {{ $cfg['label'] }}
        </span>
    </div>

    {{-- Range info --}}
    <p class="text-[10px] text-[var(--color-gray-400)]">Ideal: {{ $idealMin }}–{{ $idealMax }} {{ $unit }}</p>
</div>
