@props([
    'indicators' => [],
    'spider' => ['labels' => [], 'values' => []],
    'spkResults' => [],
    'evaluationTime' => '-',
    'masterConfigStatus' => [],
    'inputQuality' => [],
    'calculationDetail' => [],
    'environmentSensors' => [],
    'selectedLog' => null,
    'canCreateTask' => false,
    'taskUrl' => null,
])

@php
    $toneClasses = [
        'emerald' => [
            'soft' => 'border-emerald-100 bg-emerald-50 text-emerald-900',
            'badge' => 'border-emerald-200 bg-white text-emerald-700',
            'solid' => 'bg-emerald-600 hover:bg-emerald-700 text-white',
            'bar' => 'bg-emerald-500',
        ],
        'blue' => [
            'soft' => 'border-blue-100 bg-blue-50 text-blue-900',
            'badge' => 'border-blue-200 bg-white text-blue-700',
            'solid' => 'bg-blue-600 hover:bg-blue-700 text-white',
            'bar' => 'bg-blue-500',
        ],
        'amber' => [
            'soft' => 'border-amber-100 bg-amber-50 text-amber-900',
            'badge' => 'border-amber-200 bg-white text-amber-700',
            'solid' => 'bg-amber-600 hover:bg-amber-700 text-white',
            'bar' => 'bg-amber-500',
        ],
        'red' => [
            'soft' => 'border-red-100 bg-red-50 text-red-900',
            'badge' => 'border-red-200 bg-white text-red-700',
            'solid' => 'bg-red-600 hover:bg-red-700 text-white',
            'bar' => 'bg-red-500',
        ],
        'gray' => [
            'soft' => 'border-gray-100 bg-gray-50 text-gray-800',
            'badge' => 'border-gray-200 bg-white text-gray-600',
            'solid' => 'bg-gray-800 hover:bg-gray-700 text-white',
            'bar' => 'bg-gray-400',
        ],
    ];

    $result = $spkResults['gabungan'] ?? [];
    $environment = $spkResults['lingkungan'] ?? [];
    $productivity = $spkResults['produktivitas'] ?? [];
    $tone = $result['scoreColor'] ?? $result['statusColor'] ?? 'gray';
    $tone = array_key_exists($tone, $toneClasses) ? $tone : 'gray';
    $qualityTone = $inputQuality['tone'] ?? 'gray';
    $qualityTone = array_key_exists($qualityTone, $toneClasses) ? $qualityTone : 'gray';
@endphp

<section class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-emerald-100 bg-emerald-50 text-emerald-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2zM9 9h6v6H9V9z" />
                    </svg>
                </span>
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Hasil Inferensi Fuzzy Mamdani</h2>
                    <p class="text-xs text-gray-500">Status, diagnosis, rekomendasi, dan bukti input dari log SPK terpilih.</p>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-600">
                {{ $evaluationTime }}
            </span>
            @if(!empty(data_get($masterConfigStatus, 'spk_config_url')))
                <a href="{{ data_get($masterConfigStatus, 'spk_config_url') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">
                    Pengaturan SPK
                </a>
            @endif
        </div>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-12">
        <div class="xl:col-span-5">
            <div class="h-full rounded-xl border p-4 {{ $toneClasses[$tone]['soft'] }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $toneClasses[$tone]['badge'] }}">
                            {{ $result['status'] ?? 'MENUNGGU' }}
                        </span>
                        <h3 class="mt-3 text-xl font-semibold leading-tight text-gray-950">{{ $result['title'] ?? 'Diagnosis Kausalitas' }}</h3>
                    </div>
                    <div class="text-right">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Skor</p>
                        <p class="text-3xl font-semibold text-gray-950">{{ number_format((float) ($result['score'] ?? 0), 1) }}</p>
                    </div>
                </div>
                <p class="mt-4 text-sm leading-6 text-gray-700">{{ $result['description'] ?? 'Belum ada hasil SPK.' }}</p>
                @if(!empty($result['recommendation']) && $result['recommendation'] !== '-')
                    <div class="mt-4 rounded-lg border border-white/80 bg-white/75 px-3 py-3 text-sm leading-6 text-gray-700">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Rekomendasi</p>
                        <p class="mt-1">{{ $result['recommendation'] }}</p>
                    </div>
                @endif
                @if($canCreateTask && $taskUrl)
                    <a href="{{ $taskUrl }}" class="mt-4 inline-flex w-full items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold transition {{ $toneClasses[$tone]['solid'] }}" style="text-decoration:none;">
                        Buat Tugas SPK
                    </a>
                @endif
            </div>
        </div>

        <div class="xl:col-span-7">
            <div class="grid h-full grid-cols-1 gap-3 lg:grid-cols-5">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3 lg:col-span-2 lg:grid-cols-1">
                    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium text-gray-500">Lingkungan</p>
                        <div class="mt-2 flex items-end justify-between gap-3">
                            <p class="text-2xl font-semibold text-gray-900">{{ number_format((float) ($environment['score'] ?? 0), 1) }}</p>
                            <span class="rounded-full border px-2 py-1 text-[10px] font-semibold {{ $toneClasses[$environment['scoreColor'] ?? 'gray']['badge'] ?? $toneClasses['gray']['badge'] }}">
                                {{ $environment['status'] ?? '-' }}
                            </span>
                        </div>
                        <p class="mt-2 line-clamp-2 text-xs leading-5 text-gray-500">{{ $environment['title'] ?? 'Belum tersedia' }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p class="text-xs font-medium text-gray-500">Produktivitas</p>
                        <div class="mt-2 flex items-end justify-between gap-3">
                            <p class="text-2xl font-semibold text-gray-900">{{ number_format((float) ($productivity['score'] ?? 0), 1) }}</p>
                            <span class="rounded-full border px-2 py-1 text-[10px] font-semibold {{ $toneClasses[$productivity['scoreColor'] ?? 'gray']['badge'] ?? $toneClasses['gray']['badge'] }}">
                                {{ $productivity['status'] ?? '-' }}
                            </span>
                        </div>
                        <p class="mt-2 line-clamp-2 text-xs leading-5 text-gray-500">{{ $productivity['title'] ?? 'Belum tersedia' }}</p>
                    </div>
                    <div class="rounded-xl border p-4 {{ $toneClasses[$qualityTone]['soft'] }}">
                        <p class="text-xs font-medium text-gray-500">Kualitas Input</p>
                        <div class="mt-2 flex items-end justify-between gap-3">
                            <p class="text-2xl font-semibold text-gray-900">{{ $inputQuality['ok'] ?? 0 }}/{{ $inputQuality['total'] ?? 0 }}</p>
                            <span class="rounded-full border px-2 py-1 text-[10px] font-semibold {{ $toneClasses[$qualityTone]['badge'] }}">
                                {{ ($inputQuality['fallback'] ?? 0) > 0 ? 'Fallback' : 'Data' }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs leading-5 text-gray-600">{{ $inputQuality['message'] ?? 'Belum ada metadata input.' }}</p>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm lg:col-span-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Grafik Fuzzy</p>
                            <p class="text-xs text-gray-500">Profil parameter aktif</p>
                        </div>
                        <span class="rounded-full border px-2 py-1 text-[10px] font-semibold {{ $toneClasses[$tone]['badge'] }}">
                            {{ count($spider['labels'] ?? []) }} titik
                        </span>
                    </div>
                    @if(!empty($spider['labels'] ?? []))
                        <div class="mx-auto mt-3 aspect-square max-w-[310px]">
                            <canvas x-ref="spiderCanvas"></canvas>
                        </div>
                    @else
                        <div class="mt-3 flex aspect-square max-h-[280px] items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 text-center text-sm text-gray-500">
                            Belum ada parameter untuk grafik fuzzy.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-100 bg-white p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-gray-900">Parameter Lingkungan</h3>
                <span class="text-xs font-medium text-gray-400">{{ count($environmentSensors) }} input</span>
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @forelse($environmentSensors as $sensor)
                    @php
                        $sensorTone = ['normal' => 'emerald', 'warning' => 'amber', 'danger' => 'red'][$sensor['status'] ?? 'warning'] ?? 'gray';
                    @endphp
                    <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate text-sm font-medium text-gray-800" title="{{ $sensor['label'] ?? '-' }}">{{ $sensor['label'] ?? '-' }}</p>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $toneClasses[$sensorTone]['badge'] }}">{{ $sensor['set'] ?? '-' }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ $sensor['statusLabel'] ?? '-' }}</p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white">
                            <div class="h-full rounded-full {{ $toneClasses[$sensorTone]['bar'] }}" style="width: {{ max(0, min(100, (int) ($sensor['percent'] ?? 0))) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-4 text-sm text-gray-500">Belum ada parameter lingkungan aktif.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-gray-100 bg-white p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-gray-900">Produktivitas & Kesehatan</h3>
                <span class="text-xs font-medium text-gray-400">{{ count($indicators) }} input</span>
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @forelse($indicators as $item)
                    @php
                        $indicatorTone = $item['color'] ?? 'gray';
                        $indicatorTone = array_key_exists($indicatorTone, $toneClasses) ? $indicatorTone : 'gray';
                    @endphp
                    <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate text-sm font-medium text-gray-800" title="{{ $item['label'] ?? '-' }}">{{ $item['label'] ?? '-' }}</p>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $toneClasses[$indicatorTone]['badge'] }}">{{ $item['detail'] ?? '-' }}</span>
                        </div>
                        <p class="mt-1 text-base font-semibold text-gray-950">{{ $item['value'] ?? '-' }}</p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white">
                            <div class="h-full rounded-full {{ $toneClasses[$indicatorTone]['bar'] }}" style="width: {{ max(0, min(100, (int) ($item['score'] ?? 0))) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-4 text-sm text-gray-500">Belum ada parameter produktivitas aktif.</p>
                @endforelse
            </div>
        </div>
    </div>

    <details class="mt-5 rounded-xl border border-gray-100 bg-gray-50/60">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-800">Bukti Perhitungan & Sumber Input</summary>
        <div class="border-t border-gray-100 bg-white px-4 py-4">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                @forelse($calculationDetail['groups'] ?? [] as $group)
                    <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $group['label'] ?? '-' }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $group['status'] ?? '-' }}</p>
                            </div>
                            @if(isset($group['score']))
                                <span class="rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ number_format((float) $group['score'], 1) }}</span>
                            @endif
                        </div>
                        <p class="mt-3 text-xs leading-5 text-gray-600">{{ $group['dominant_rule'] ?? ($group['recommendation'] ?? '-') }}</p>
                        @if(isset($group['alpha']) && $group['alpha'] !== null)
                            <p class="mt-2 text-[11px] font-medium text-gray-500">Alpha dominan: {{ number_format((float) $group['alpha'], 3) }}</p>
                        @endif
                        @if(!empty($group['fuzzified']))
                            <div class="mt-3 space-y-1.5">
                                @foreach($group['fuzzified'] as $variable => $sets)
                                    <p class="text-[11px] text-gray-500">
                                        <span class="font-semibold text-gray-700">{{ $variable }}</span>:
                                        @foreach($sets as $setName => $mu)
                                            {{ $setName }} {{ number_format((float) $mu, 2) }}@if(!$loop->last), @endif
                                        @endforeach
                                    </p>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-4 text-sm text-gray-500">Belum ada log SPK yang bisa ditampilkan.</p>
                @endforelse
            </div>

            <div class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
                @forelse($inputQuality['items'] ?? [] as $item)
                    @php
                        $inputTone = $item['tone'] ?? 'gray';
                        $inputTone = array_key_exists($inputTone, $toneClasses) ? $inputTone : 'gray';
                    @endphp
                    <div class="rounded-lg border border-gray-100 bg-white px-3 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium text-gray-900">{{ $item['name'] ?? '-' }}</p>
                            <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $toneClasses[$inputTone]['badge'] }}">{{ $item['statusLabel'] ?? '-' }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ $item['source'] ?? '-' }} - {{ $item['value'] ?? '-' }}</p>
                        @if(!empty($item['message']))
                            <p class="mt-2 text-[11px] leading-4 text-gray-500">{{ $item['message'] }}</p>
                        @endif
                    </div>
                @empty
                    <p class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-4 text-sm text-gray-500">Metadata input belum tersedia.</p>
                @endforelse
            </div>
        </div>
    </details>
</section>
