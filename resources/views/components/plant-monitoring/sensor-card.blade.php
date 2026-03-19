{{--
Sensor Card (DASH-03 Redesign v2.1) — Menampilkan data sensor terkini untuk satu blok kebun.
Supports trend arrows (↑↓→) per sensor parameter.

Props:
- $sensor : array — { namaBlok, ph_tanah, ec, suhu, kelembaban, nitrogen, fosfor, kalium, dicatatPada, trend{} }
  trend: { ph_tanah: 'up'|'down'|'stable', ec: ..., suhu: ..., kelembaban: ... }
--}}
@props(['sensor'])

@php
    // Support both array and object
    $s = is_array($sensor) ? (object)$sensor : $sensor;

    // Threshold untuk indikator warna
    // TODO: [ALERT-01] Ambil threshold dari database
    $thresholds = [
        'ph_tanah'    => ['min' => 5.5, 'max' => 7.5],
        'ec'          => ['min' => 1.0, 'max' => 3.5],
        'suhu'        => ['min' => 20.0, 'max' => 30.0],
        'kelembaban'  => ['min' => 60.0, 'max' => 85.0],
        'nitrogen'    => ['min' => 40.0, 'max' => 60.0],
        'fosfor'      => ['min' => 20.0, 'max' => 50.0],
        'kalium'      => ['min' => 100.0, 'max' => 200.0],
    ];

    $getStatus = function($value, $param) use ($thresholds) {
        if (!isset($thresholds[$param]) || $value === null) return 'normal';
        $t = $thresholds[$param];
        if ($value < $t['min'] || $value > $t['max']) return 'critical';
        $warnMin = $t['min'] + ($t['max'] - $t['min']) * 0.1;
        $warnMax = $t['max'] - ($t['max'] - $t['min']) * 0.1;
        if ($value < $warnMin || $value > $warnMax) return 'warning';
        return 'normal';
    };

    $statusColors = [
        'normal'   => ['text' => 'text-[var(--color-gray-900)]',   'dot' => 'bg-emerald-500', 'stroke' => 'stroke-emerald-400'],
        'warning'  => ['text' => 'text-amber-600',                  'dot' => 'bg-amber-500',  'stroke' => 'stroke-amber-400'],
        'critical' => ['text' => 'text-red-600',                    'dot' => 'bg-red-500',    'stroke' => 'stroke-red-400'],
    ];

    $trendArrows = [
        'up'     => ['icon' => 'arrow-up',   'color' => 'text-red-500'],
        'down'   => ['icon' => 'arrow-down', 'color' => 'text-emerald-500'],
        'stable' => ['icon' => 'minus',      'color' => 'text-[var(--color-gray-400)]'],
    ];

    $trends = (array)($s->trend ?? []);

    $isRecent = \Carbon\Carbon::parse($s->dicatatPada)->diffInHours(now()) < 1;

    $params = [
        ['key' => 'ph_tanah',   'label' => 'pH Tanah',   'desc' => 'Tingkat keasaman tanah untuk pertumbuhan akar melon', 'satuan' => 'Skala pH (0-14)', 'value' => $s->ph_tanah,   'unit' => '',      'format' => fn($v) => number_format($v, 1)],
        ['key' => 'ec',         'label' => 'EC',          'desc' => 'Electrical Conductivity — konduktivitas listrik larutan nutrisi', 'satuan' => 'miliSiemens per cm', 'value' => $s->ec,         'unit' => 'mS/cm', 'format' => fn($v) => number_format($v, 1)],
        ['key' => 'suhu',       'label' => 'Suhu',        'desc' => 'Suhu rata-rata udara di dalam greenhouse', 'satuan' => 'Derajat Celsius', 'value' => $s->suhu,       'unit' => '°C',    'format' => fn($v) => number_format($v, 1)],
        ['key' => 'kelembaban', 'label' => 'Kelembaban',  'desc' => 'Tingkat kelembapan udara rata-rata di dalam greenhouse', 'satuan' => 'Persentase (%)', 'value' => $s->kelembaban, 'unit' => '%',     'format' => fn($v) => number_format($v, 0)],
    ];
@endphp

<div class="bg-white rounded-xl shadow-sm border border-[var(--color-gray-100)] p-5 hover:shadow-lg transition-shadow duration-300">
    @php
        // Determine overall status for the Greenhouse based on its worst parameter
        $overallStatus = current(array_unique(array_filter(
            array_map(fn($p) => $getStatus($p['value'], $p['key']), $params),
            fn($st) => $st !== 'normal'
        ))) ?: 'normal';
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-y-2 gap-x-1 mb-4">
        <div class="flex items-center gap-2 shrink-0">
            <h4 class="text-[15px] font-bold text-[var(--color-gray-900)] whitespace-nowrap">{{ $s->namaBlok }}</h4>
            <div class="shrink-0">
                <x-badge :color="$overallStatus === 'normal' ? 'green' : ($overallStatus === 'warning' ? 'amber' : 'red')">
                    {{ ucfirst($overallStatus) }}
                </x-badge>
            </div>
        </div>
        <div class="flex items-center justify-end shrink-0">
            <span class="text-[11px] text-[var(--color-gray-400)] whitespace-nowrap">
                {{ \Carbon\Carbon::parse($s->dicatatPada)->diffForHumans() }}
            </span>
        </div>
    </div>

    {{-- LINGKUNGAN Section --}}
    <p class="text-[10px] font-semibold tracking-wider text-[var(--color-gray-400)] mb-2 uppercase">Lingkungan</p>
    <div class="grid grid-cols-2 gap-2 mb-4">
        @foreach($params as $param)
            @php
                $st = $getStatus($param['value'], $param['key']);
                $sc = $statusColors[$st];
                $tr = $trendArrows[$trends[$param['key']] ?? 'stable'] ?? $trendArrows['stable'];
            @endphp
            <div class="relative px-3 py-2.5 rounded-xl bg-[var(--color-gray-50)] cursor-help"
                 x-data="{ show: false, align: 'center' }"
                 :class="show ? 'z-50' : 'z-10'"
                 @mouseenter="
                    show = true;
                    $nextTick(() => {
                        const r = $el.getBoundingClientRect();
                        const tw = 224;
                        const wrapper = document.querySelector('main') || document.body;
                        const wr = wrapper.getBoundingClientRect();
                        const cl = r.left + (r.width / 2) - (tw / 2);
                        align = cl < wr.left + 16 ? 'start' : (cl + tw > wr.right - 16 ? 'end' : 'center');
                    })
                 "
                 @mouseleave="show = false">
                <div class="flex items-center justify-between mb-1.5 gap-2">
                    <p class="text-[11px] font-medium text-[var(--color-gray-500)] truncate">{{ $param['label'] }}</p>
                    <span class="font-bold {{ $tr['color'] }} shrink-0" title="Trend">
                        <i data-lucide="{{ $tr['icon'] }}" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="flex items-end justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $sc['dot'] }}"></span>
                        <span class="text-sm font-bold leading-none {{ $sc['text'] }}">
                            {{ $param['value'] !== null ? ($param['format'])($param['value']) : '-' }}
                        </span>
                        @if($param['unit'])
                            <span class="text-[10px] font-semibold tracking-wide text-[var(--color-gray-400)]">{{ $param['unit'] }}</span>
                        @endif
                    </div>
                    

                </div>

                {{-- Tooltip Hover --}}
                <x-sensor-tooltip
                    :title="$param['label']"
                    :desc="$param['desc']"
                    :satuan="$param['satuan']"
                    :idealRange="isset($thresholds[$param['key']]) ? $thresholds[$param['key']]['min'] . ' – ' . $thresholds[$param['key']]['max'] . ' ' . $param['unit'] : null"
                    :value="$param['value'] !== null ? ($param['format'])($param['value']) : '-'"
                    :unit="$param['unit']"
                    :valueColor="$sc['text']"
                    :status="$st"
                />
            </div>
        @endforeach
    </div>

    {{-- NUTRISI Section --}}
    <p class="text-[10px] font-semibold tracking-wider text-[var(--color-gray-400)] mb-2 uppercase">Nutrisi</p>
    <div class="grid grid-cols-3 gap-2">
        @php
            $npkParams = [
                ['key' => 'nitrogen', 'label' => 'Nitrogen', 'desc' => 'Kadar makronutrien utama (Nitrogen) di media', 'satuan' => 'ppm (Part per million)', 'value' => $s->nitrogen, 'unit' => 'ppm'],
                ['key' => 'fosfor',   'label' => 'Fosfor',   'desc' => 'Kadar makronutrien pembentuk akar (Fosfor)',    'satuan' => 'ppm (Part per million)', 'value' => $s->fosfor,   'unit' => 'ppm'],
                ['key' => 'kalium',   'label' => 'Kalium',   'desc' => 'Kadar makronutrien pemanis buah (Kalium)',      'satuan' => 'ppm (Part per million)', 'value' => $s->kalium,   'unit' => 'ppm'],
            ];
        @endphp
        @foreach($npkParams as $npk)
            @php
                $npkStatus = $getStatus($npk['value'], $npk['key']);
                $npkColor = $statusColors[$npkStatus];
                $npkTr = $trendArrows[$trends[$npk['key']] ?? 'stable'] ?? $trendArrows['stable'];
            @endphp
            <div class="relative px-3 py-2.5 rounded-xl bg-[var(--color-gray-50)] cursor-help"
                 x-data="{ show: false, align: 'center' }"
                 :class="show ? 'z-50' : 'z-10'"
                 @mouseenter="
                    show = true;
                    $nextTick(() => {
                        const r = $el.getBoundingClientRect();
                        const tw = 224;
                        const wrapper = document.querySelector('main') || document.body;
                        const wr = wrapper.getBoundingClientRect();
                        const cl = r.left + (r.width / 2) - (tw / 2);
                        align = cl < wr.left + 16 ? 'start' : (cl + tw > wr.right - 16 ? 'end' : 'center');
                    })
                 "
                 @mouseleave="show = false">
                <div class="flex items-center justify-between mb-1.5 gap-2">
                    <p class="text-[11px] font-medium text-[var(--color-gray-500)] truncate">{{ $npk['label'] }}</p>
                    <span class="font-bold {{ $npkTr['color'] }} shrink-0" title="Trend">
                        <i data-lucide="{{ $npkTr['icon'] }}" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="flex items-end justify-between">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $npkColor['dot'] }}"></span>
                        <p class="text-sm font-bold leading-none {{ $npkColor['text'] }}">
                            {{ $npk['value'] !== null ? number_format($npk['value'], 0) : '-' }}
                        </p>
                        <span class="text-[10px] font-semibold tracking-wide text-[var(--color-gray-400)]">{{ $npk['unit'] }}</span>
                    </div>


                </div>

                {{-- Tooltip Hover (NPK specific) --}}
                <x-sensor-tooltip
                    :title="'Kadar ' . $npk['label']"
                    :desc="$npk['desc']"
                    :satuan="$npk['satuan']"
                    :idealRange="isset($thresholds[$npk['key']]) ? $thresholds[$npk['key']]['min'] . ' – ' . $thresholds[$npk['key']]['max'] . ' ' . $npk['unit'] : null"
                    :value="$npk['value'] !== null ? number_format($npk['value'], 0) : '-'"
                    :unit="$npk['unit']"
                    :valueColor="$npkColor['text']"
                    :status="$npkStatus"
                />
            </div>
        @endforeach
    </div>
</div>
