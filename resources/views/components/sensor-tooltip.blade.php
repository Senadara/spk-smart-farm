@props([
    'title',
    'desc',
    'satuan',
    'idealRange' => null,
    'valueLabel' => 'Nilai Saat Ini',
    'valueColor' => 'text-emerald-600',
    'value',
    'unit' => '',
    'status' => null,
])

<div x-show="show" x-cloak
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0 translate-y-1"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-100"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-1"
     :class="{
         'left-1/2 -translate-x-1/2': align === 'center',
         'left-0': align === 'start',
         'right-0': align === 'end'
     }"
     class="absolute z-50 bottom-full mb-2 w-56 p-3 bg-white rounded-xl border border-[var(--color-gray-100)] pointer-events-none"
     style="box-shadow: var(--shadow-lg);">
    {{-- Arrow --}}
    <div :class="{
             'left-1/2 -translate-x-1/2': align === 'center',
             'left-5': align === 'start',
             'right-5': align === 'end'
         }" class="absolute top-full">
        <div class="w-2.5 h-2.5 bg-white border-b border-r border-[var(--color-gray-100)] rotate-45 -translate-y-1.5"></div>
    </div>
    <div class="space-y-2 text-left">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-[var(--color-gray-900)]">{{ $title }}</span>
            @if($status)
                @php
                    $badgeColors = [
                        'normal'   => 'bg-emerald-100 text-emerald-800',
                        'warning'  => 'bg-amber-100 text-amber-800',
                        'critical' => 'bg-red-100 text-red-800',
                    ];
                    $badgeText = [
                        'normal'   => 'Normal',
                        'warning'  => 'Warning',
                        'critical' => 'Critical',
                    ];
                    $statusKey = (string) $status;
                    $bc = $badgeColors[$statusKey] ?? $badgeColors['normal'];
                    $bt = $badgeText[$statusKey] ?? ucfirst($statusKey);
                @endphp
                <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $bc }}">
                    {{ $bt }}
                </span>
            @endif
        </div>
        <p class="text-xs text-[var(--color-gray-500)] leading-relaxed">{{ $desc }}</p>
        <div class="pt-2 border-t border-[var(--color-gray-100)] space-y-1.5">
            <div class="flex justify-between text-xs">
                <span class="text-[var(--color-gray-400)]">Satuan</span>
                <span class="text-[var(--color-gray-700)] font-medium">{{ $satuan }}</span>
            </div>
            @if($idealRange)
            <div class="flex justify-between text-xs">
                <span class="text-[var(--color-gray-400)]">Rentang Ideal</span>
                <span class="text-[var(--color-gray-700)] font-medium">{!! $idealRange !!}</span>
            </div>
            @endif
            <div class="flex justify-between text-xs">
                <span class="text-[var(--color-gray-400)]">{{ $valueLabel }}</span>
                <span class="font-bold {{ $valueColor }}">{{ $value }}{{ $unit ? ' ' . $unit : '' }}</span>
            </div>
        </div>
    </div>
</div>
