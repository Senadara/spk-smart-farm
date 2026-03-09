{{--
Sensor Card — Menampilkan data sensor terkini untuk satu blok kebun.
Uses Tailwind v4 @container queries for responsive grid sizing based on card width.
Alpine.js hover tooltips for sensor detail info.

Props:
- $block : array — { name, lastUpdate, status, sensors[] }
  sensors[0..3] = Lingkungan (pH, EC, Suhu, Kelembapan)
  sensors[4..6] = Nutrisi (Nitrogen, Fosfor, Kalium)
--}}

@props([
    'block' => [],
])

@php
    $statusColors = [
        'normal' => 'green',
        'warning' => 'amber',
        'critical' => 'red',
    ];

    $statusLabels = [
        'normal' => 'Normal',
        'warning' => 'Peringatan',
        'critical' => 'Kritis',
    ];

    $valueColors = [
        'normal' => 'text-[var(--color-gray-900)]',
        'warning' => 'text-amber-600',
        'critical' => 'text-red-600',
    ];

    $dotColors = [
        'normal' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'critical' => 'bg-red-500',
    ];

    $sensorMeta = [
        'pH Tanah' => [
            'desc' => 'Tingkat keasaman tanah untuk pertumbuhan akar melon',
            'unitLabel' => 'Skala pH (0–14)',
            'idealRange' => '6.0 – 7.0',
        ],
        'EC' => [
            'desc' => 'Electrical Conductivity — konduktivitas listrik larutan nutrisi',
            'unitLabel' => 'miliSiemens per cm',
            'idealRange' => '2.0 – 3.5 mS/cm',
        ],
        'Suhu' => [
            'desc' => 'Suhu udara di dalam greenhouse',
            'unitLabel' => 'Derajat Celsius',
            'idealRange' => '25 – 30 °C',
        ],
        'Kelembaban' => [
            'desc' => 'Kelembaban relatif udara di dalam greenhouse',
            'unitLabel' => 'Persen relatif',
            'idealRange' => '60 – 80 %',
        ],
        'Nitrogen' => [
            'desc' => 'Kadar nitrogen (N) dalam tanah — unsur hara makro untuk pertumbuhan daun',
            'unitLabel' => 'Parts per million',
            'idealRange' => '40 – 60 ppm',
        ],
        'Fosfor' => [
            'desc' => 'Kadar fosfor (P) dalam tanah — unsur hara untuk perakaran & pembungaan',
            'unitLabel' => 'Parts per million',
            'idealRange' => '20 – 40 ppm',
        ],
        'Kalium' => [
            'desc' => 'Kadar kalium (K) dalam tanah — unsur hara untuk kualitas buah',
            'unitLabel' => 'Parts per million',
            'idealRange' => '100 – 200 ppm',
        ],
    ];

    $badgeColor = $statusColors[$block['status'] ?? 'normal'] ?? 'green';

    $allSensors = $block['sensors'] ?? [];
    $lingkungan = array_slice($allSensors, 0, 4);
    $nutrisi = array_slice($allSensors, 4);
@endphp

<x-card>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <h4 class="text-sm font-semibold text-[var(--color-gray-900)]">{{ $block['name'] ?? '-' }}</h4>
            <x-badge :color="$badgeColor">{{ ucfirst($block['status'] ?? 'normal') }}</x-badge>
        </div>
        <span class="text-xs text-[var(--color-gray-400)]">{{ $block['lastUpdate'] ?? '-' }}</span>
    </div>

    {{-- Sensor Groups — uses @container queries for card-width-aware responsiveness --}}
    <div class="@container space-y-3">
        {{-- Row 1: Lingkungan (pH, EC, Suhu, Kelembapan) --}}
        @if (!empty($lingkungan))
            <div>
                <p class="text-[10px] font-medium uppercase tracking-wider text-[var(--color-gray-400)] mb-1.5">
                    Lingkungan</p>
                <div class="grid grid-cols-2 @md:grid-cols-4 gap-2">
                    @foreach ($lingkungan as $sensor)
                        @php
                            $sensorStatus = $sensor['status'] ?? 'normal';
                            $vColor = $valueColors[$sensorStatus] ?? $valueColors['normal'];
                            $dColor = $dotColors[$sensorStatus] ?? $dotColors['normal'];
                            $meta = $sensorMeta[$sensor['label']] ?? [
                                'desc' => '-',
                                'unitLabel' => $sensor['unit'],
                                'idealRange' => '-',
                            ];
                        @endphp
                        <div x-data="{ show: false, align: 'center' }"
                            @mouseenter="
                                show = true;
                                $nextTick(() => {
                                    const r = $el.getBoundingClientRect();
                                    const tw = 224;
                                    const m = $el.closest('main');
                                    const ml = m ? m.getBoundingClientRect().left : 0;
                                    const mr = m ? m.getBoundingClientRect().right : window.innerWidth;
                                    const cl = r.left + (r.width / 2) - (tw / 2);
                                    align = cl < ml ? 'start' : (cl + tw > mr ? 'end' : 'center');
                                })
                            "
                            @mouseleave="show = false"
                            class="relative px-2.5 py-2 rounded-lg bg-[var(--color-gray-50)] cursor-default transition-colors duration-150 hover:bg-[var(--color-gray-100)] hover:shadow-sm">
                            <p class="text-[11px] text-[var(--color-gray-400)] mb-1">{{ $sensor['label'] }}</p>
                            <div class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $dColor }}"></span>
                                <span
                                    class="text-sm font-bold leading-none {{ $vColor }}">{{ $sensor['value'] }}</span>
                                @if ($sensor['unit'])
                                    <span class="text-[11px] text-[var(--color-gray-400)]">{{ $sensor['unit'] }}</span>
                                @endif
                            </div>

                            {{-- Hover Tooltip — dynamic alignment: start | center | end --}}
                            <div x-show="show" x-cloak x-transition:enter="transition ease-out duration-150"
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
                                }"
                                    class="absolute top-full">
                                    <div
                                        class="w-2.5 h-2.5 bg-white border-b border-r border-[var(--color-gray-100)] rotate-45 -translate-y-1.5">
                                    </div>
                                </div>
                                {{-- Content --}}
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <span
                                            class="text-xs font-semibold text-[var(--color-gray-900)]">{{ $sensor['label'] }}</span>
                                        <x-badge :color="$statusColors[$sensorStatus]">{{ $statusLabels[$sensorStatus] }}</x-badge>
                                    </div>
                                    <p class="text-[11px] text-[var(--color-gray-500)] leading-relaxed">
                                        {{ $meta['desc'] }}</p>
                                    <div class="pt-2 border-t border-[var(--color-gray-100)] space-y-1.5">
                                        <div class="flex justify-between text-[11px]">
                                            <span class="text-[var(--color-gray-400)]">Satuan</span>
                                            <span
                                                class="text-[var(--color-gray-700)] font-medium">{{ $meta['unitLabel'] }}</span>
                                        </div>
                                        <div class="flex justify-between text-[11px]">
                                            <span class="text-[var(--color-gray-400)]">Rentang Ideal</span>
                                            <span
                                                class="text-[var(--color-gray-700)] font-medium">{{ $meta['idealRange'] }}</span>
                                        </div>
                                        <div class="flex justify-between text-[11px]">
                                            <span class="text-[var(--color-gray-400)]">Nilai Saat Ini</span>
                                            <span class="font-bold {{ $vColor }}">{{ $sensor['value'] }}
                                                {{ $sensor['unit'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Row 2: Nutrisi (Nitrogen, Fosfor, Kalium) --}}
        @if (!empty($nutrisi))
            <div>
                <p class="text-[10px] font-medium uppercase tracking-wider text-[var(--color-gray-400)] mb-1.5">Nutrisi
                </p>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($nutrisi as $sensor)
                        @php
                            $sensorStatus = $sensor['status'] ?? 'normal';
                            $vColor = $valueColors[$sensorStatus] ?? $valueColors['normal'];
                            $dColor = $dotColors[$sensorStatus] ?? $dotColors['normal'];
                            $meta = $sensorMeta[$sensor['label']] ?? [
                                'desc' => '-',
                                'unitLabel' => $sensor['unit'],
                                'idealRange' => '-',
                            ];
                        @endphp
                        <div x-data="{ show: false, align: 'center' }"
                            @mouseenter="
                                show = true;
                                $nextTick(() => {
                                    const r = $el.getBoundingClientRect();
                                    const tw = 224;
                                    const m = $el.closest('main');
                                    const ml = m ? m.getBoundingClientRect().left : 0;
                                    const mr = m ? m.getBoundingClientRect().right : window.innerWidth;
                                    const cl = r.left + (r.width / 2) - (tw / 2);
                                    align = cl < ml ? 'start' : (cl + tw > mr ? 'end' : 'center');
                                })
                            "
                            @mouseleave="show = false"
                            class="relative px-2.5 py-2 rounded-lg bg-[var(--color-gray-50)] cursor-default transition-colors duration-150 hover:bg-[var(--color-gray-100)] hover:shadow-sm">
                            <p class="text-[11px] text-[var(--color-gray-400)] mb-1">{{ $sensor['label'] }}</p>
                            <div class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $dColor }}"></span>
                                <span
                                    class="text-sm font-bold leading-none {{ $vColor }}">{{ $sensor['value'] }}</span>
                                @if ($sensor['unit'])
                                    <span class="text-[11px] text-[var(--color-gray-400)]">{{ $sensor['unit'] }}</span>
                                @endif
                            </div>

                            {{-- Hover Tooltip — dynamic alignment: start | center | end --}}
                            <div x-show="show" x-cloak x-transition:enter="transition ease-out duration-150"
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
                                }"
                                    class="absolute top-full">
                                    <div
                                        class="w-2.5 h-2.5 bg-white border-b border-r border-[var(--color-gray-100)] rotate-45 -translate-y-1.5">
                                    </div>
                                </div>
                                {{-- Content --}}
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <span
                                            class="text-xs font-semibold text-[var(--color-gray-900)]">{{ $sensor['label'] }}</span>
                                        <x-badge :color="$statusColors[$sensorStatus]">{{ $statusLabels[$sensorStatus] }}</x-badge>
                                    </div>
                                    <p class="text-[11px] text-[var(--color-gray-500)] leading-relaxed">
                                        {{ $meta['desc'] }}</p>
                                    <div class="pt-2 border-t border-[var(--color-gray-100)] space-y-1.5">
                                        <div class="flex justify-between text-[11px]">
                                            <span class="text-[var(--color-gray-400)]">Satuan</span>
                                            <span
                                                class="text-[var(--color-gray-700)] font-medium">{{ $meta['unitLabel'] }}</span>
                                        </div>
                                        <div class="flex justify-between text-[11px]">
                                            <span class="text-[var(--color-gray-400)]">Rentang Ideal</span>
                                            <span
                                                class="text-[var(--color-gray-700)] font-medium">{{ $meta['idealRange'] }}</span>
                                        </div>
                                        <div class="flex justify-between text-[11px]">
                                            <span class="text-[var(--color-gray-400)]">Nilai Saat Ini</span>
                                            <span class="font-bold {{ $vColor }}">{{ $sensor['value'] }}
                                                {{ $sensor['unit'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-card>
