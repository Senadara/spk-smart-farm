@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
@php
    $overviewCards = $overviewCards ?? $productivityCards ?? [];
    $workItems = $workSummary['items'] ?? [];
    $trendSeries = $trend['series'] ?? [];
    $trendLabels = $trend['labels'] ?? [];
    $chartWidth = $trend['chart_width'] ?? 320;
    $chartHeight = $trend['chart_height'] ?? 124;

    $toneAccentClass = [
        'emerald' => 'bg-emerald-500',
        'sky' => 'bg-sky-500',
        'amber' => 'bg-amber-500',
        'red' => 'bg-red-500',
        'gray' => 'bg-gray-400',
    ];
    $toneBorderClass = [
        'emerald' => 'border-l-emerald-400',
        'sky' => 'border-l-sky-400',
        'amber' => 'border-l-amber-400',
        'red' => 'border-l-red-400',
        'gray' => 'border-l-gray-300',
    ];
    $toneBadgeClass = [
        'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
        'sky' => 'bg-sky-50 text-sky-700 border-sky-100',
        'amber' => 'bg-amber-50 text-amber-700 border-amber-100',
        'red' => 'bg-red-50 text-red-700 border-red-100',
        'gray' => 'bg-gray-50 text-gray-600 border-gray-100',
    ];
    $toneTextClass = [
        'emerald' => 'text-emerald-700',
        'sky' => 'text-sky-700',
        'amber' => 'text-amber-700',
        'red' => 'text-red-700',
        'gray' => 'text-gray-600',
    ];
    $dashboardCardHints = [
        'Prioritas Hari Ini' => [
            'body' => 'Jumlah hal yang perlu dibereskan hari ini dari laporan, jadwal, SPK, data master, dan stok.',
            'formula' => 'setup + laporan tertinggal + panen lewat jam + tugas SPK + restock',
            'source' => 'unitBudidaya, laporan, scheduledUnitNotification, spk_action_tasks, inventory_items',
        ],
        'Data Master' => [
            'body' => 'Kesiapan konfigurasi terpusat untuk jenis ternak atau tanaman yang sudah terbaca dari mobile.',
            'formula' => 'jenis aktif yang punya konfigurasi / total jenis aktif',
            'source' => 'jenisBudidaya, livestock_master_configs',
        ],
        'Laporan Unit' => [
            'body' => 'Cakupan unit aktif yang sudah mengirim minimal satu laporan hari ini.',
            'formula' => 'unit sudah lapor / total unit aktif',
            'source' => 'laporan, unitBudidaya',
        ],
        'Jadwal Panen' => [
            'body' => 'Status slot panen hari ini. Jika lewat jam dan belum tercatat, petugas perlu diingatkan.',
            'formula' => 'jadwal hari ini dibanding laporan panen',
            'source' => 'scheduledUnitNotification, laporan, panen',
        ],
        'Penugasan SPK' => [
            'body' => 'Jumlah penugasan SPK yang masih aktif dan perlu dipantau hingga selesai.',
            'formula' => 'COUNT(status todo/in_progress)',
            'source' => 'spk_action_tasks',
        ],
    ];
    $productivitySections = [
        [
            'title' => 'Peternakan',
            'url' => route('peternakan'),
            'tone' => 'emerald',
            'caption' => $livestock['active_units'].' unit aktif',
            'metrics' => [
                ['label' => 'Unit', 'value' => $livestock['active_units']],
                ['label' => 'Populasi', 'value' => $livestock['population_label']],
                ['label' => 'Laporan', 'value' => $livestock['report_coverage_label']],
            ],
            'note' => $livestock['report_message'],
        ],
        [
            'title' => 'Perkebunan',
            'url' => route('perkebunan.index'),
            'tone' => 'sky',
            'caption' => $crop['active_units'].' unit aktif',
            'metrics' => [
                ['label' => 'Unit', 'value' => $crop['active_units']],
                ['label' => 'Populasi', 'value' => $crop['plants_label']],
                ['label' => 'Laporan', 'value' => $crop['report_coverage_label']],
            ],
            'note' => $crop['note'],
        ],
    ];
@endphp

@php
    $primaryCard = $overviewCards[0] ?? null;
    $supportCards = array_slice($overviewCards, 1);
@endphp

<div class="mx-auto max-w-full space-y-4 pb-4">
    <section class="grid gap-3 xl:grid-cols-[minmax(320px,0.9fr)_minmax(0,1.4fr)]">
        <article class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="absolute inset-x-0 top-0 h-1 {{ $toneAccentClass[$primaryCard['tone'] ?? 'gray'] ?? $toneAccentClass['gray'] }}"></div>
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full {{ $toneAccentClass[$primaryCard['tone'] ?? 'gray'] ?? $toneAccentClass['gray'] }}"></span>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Prioritas Hari Ini</p>
                    </div>
                    <p class="mt-2 text-lg font-semibold tracking-tight text-gray-900">{{ $primaryCard['value'] ?? 0 }} <span class="text-xs font-medium text-gray-400">item pending</span></p>
                    <p class="mt-1 max-w-lg text-sm leading-relaxed text-gray-600">{{ $primaryCard['caption'] ?? 'Tidak ada prioritas utama.' }}</p>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if(!empty($primaryCard['url']))
                    <a href="{{ $primaryCard['url'] }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-xs font-medium text-white hover:bg-emerald-700" style="text-decoration:none;">Buka prioritas</a>
                @endif
                @if($primaryCard && ($dashboardCardHints[$primaryCard['label']] ?? null))
                    @php
                        $hint = $dashboardCardHints[$primaryCard['label']];
                    @endphp
                    <x-metric-hint :title="$primaryCard['label']" :body="$hint['body']" :formula="$hint['formula']" :source="$hint['source']" />
                @endif
            </div>
        </article>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($supportCards as $item)
                @php
                    $hint = $dashboardCardHints[$item['label']] ?? null;
                    $tone = $item['tone'] ?? 'gray';
                @endphp
                <article class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-md">
                    <div class="flex items-start justify-between gap-2">
                        <span class="h-2 w-2 rounded-full {{ $toneAccentClass[$tone] ?? $toneAccentClass['gray'] }}"></span>
                        @if($hint)
                            <x-metric-hint :title="$item['label']" :body="$hint['body']" :formula="$hint['formula']" :source="$hint['source']" />
                        @endif
                    </div>
                    <p class="mt-3 text-xs font-medium text-gray-500">{{ $item['label'] }}</p>
                    <p class="mt-1 text-base font-semibold text-gray-900">{{ $item['value'] }}</p>
                    <p class="mt-1 line-clamp-2 min-h-[32px] text-[11px] leading-4 text-gray-500">{{ $item['caption'] }}</p>
                    @if(!empty($item['url']))
                        <a href="{{ $item['url'] }}" class="mt-3 inline-flex text-[11px] font-medium {{ $toneTextClass[$tone] ?? $toneTextClass['gray'] }} hover:underline" style="text-decoration:none;">Buka menu</a>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <section class="grid items-stretch gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(320px,0.78fr)]">
        <div class="flex max-h-[calc(100vh-260px)] min-h-[520px] flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex shrink-0 items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Langkah 1</p>
                    <h2 class="mt-1 text-base font-semibold text-gray-900">Kegiatan Wajib Petugas</h2>
                    <p class="text-xs text-gray-500">Mulai dari item yang membutuhkan penyelesaian.</p>
                </div>
                <span class="rounded-full border {{ ($workSummary['pending_count'] ?? 0) > 0 ? $toneBadgeClass['amber'] : $toneBadgeClass['emerald'] }} px-2.5 py-1 text-xs font-medium">
                    {{ $workSummary['pending_count'] ?? 0 }} pending
                </span>
            </div>

            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto pr-1">
                @forelse($workItems as $item)
                    @php
                        $tone = $item['tone'] ?? 'gray';
                    @endphp
                    <a href="{{ $item['url'] }}" class="group block rounded-xl border border-l-4 border-gray-200 bg-white p-3 transition hover:border-gray-300 hover:bg-gray-50 {{ $toneBorderClass[$tone] ?? $toneBorderClass['gray'] }}" style="text-decoration:none;">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 group-hover:text-emerald-700">{{ $item['title'] }}</p>
                                <p class="mt-1 line-clamp-2 text-xs leading-5 text-gray-500">{{ $item['caption'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-full border {{ $toneBadgeClass[$tone] ?? $toneBadgeClass['gray'] }} px-2 py-1 text-[11px] font-medium">
                                {{ $item['status_label'] }}
                            </span>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 border-t border-gray-100 pt-2">
                            <p class="text-sm font-semibold text-gray-800">{{ $item['value'] }}</p>
                            <p class="line-clamp-1 text-right text-xs text-gray-400">{{ $item['detail'] }}</p>
                        </div>
                    </a>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-10 text-center text-sm text-gray-500">
                        Belum ada daftar kegiatan wajib.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="flex max-h-[calc(100vh-260px)] min-h-[520px] flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex shrink-0 items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Langkah 2</p>
                    <h2 class="mt-1 text-base font-semibold text-gray-900">Riwayat Aktivitas Petugas</h2>
                    <p class="text-xs text-gray-500">Jejak terbaru setelah pekerjaan dijalankan.</p>
                </div>
                <span class="rounded-full border border-gray-100 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-600">{{ count($staffActivities) }}</span>
            </div>

            <div class="min-h-0 flex-1 space-y-1 overflow-y-auto pr-1">
                @forelse($staffActivities as $activity)
                    @php
                        $tone = $activity['tone'] ?? 'gray';
                    @endphp
                    <a href="{{ $activity['url'] }}" class="group relative block rounded-lg px-2 py-2.5 transition hover:bg-gray-50" style="text-decoration:none;">
                        <div class="absolute left-3 top-4 h-2 w-2 rounded-full {{ $toneAccentClass[$tone] ?? $toneAccentClass['gray'] }}"></div>
                        <div class="ml-5 min-w-0 border-l border-gray-100 pl-4">
                            <div class="flex items-start justify-between gap-3">
                                <p class="line-clamp-1 text-sm font-medium text-gray-900 group-hover:text-emerald-700">{{ $activity['title'] }}</p>
                                <span class="shrink-0 text-[11px] font-medium text-gray-400">{{ $activity['time'] }}</span>
                            </div>
                            <p class="mt-1 line-clamp-1 text-xs text-gray-500">{{ $activity['actor'] }} - {{ $activity['meta'] }}</p>
                        </div>
                    </a>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-10 text-center text-sm text-gray-500">
                        Belum ada aktivitas petugas yang tercatat.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="grid min-h-[520px] gap-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Catatan</p>
                        <h2 class="mt-1 text-base font-semibold text-gray-900">Penyelesaian Sistem</h2>
                        <p class="text-xs text-gray-500">Masalah konfigurasi diarahkan ke menu terkait.</p>
                    </div>
                    <x-metric-hint title="Catatan Penyelesaian" body="Daftar ini berisi konfigurasi atau kondisi sistem yang perlu diselesaikan agar alur kerja harian tidak macet." source="data master, iot_device, spk_fuzzy_logs, inventory_items" />
                </div>
                <div class="space-y-2.5">
                    @foreach($systemNotices as $notice)
                        @php
                            $tone = $notice['tone'] ?? 'gray';
                        @endphp
                        <a href="{{ $notice['url'] }}" class="block rounded-xl border border-gray-200 bg-gray-50/60 p-3 transition hover:border-gray-300 hover:bg-white hover:shadow-sm" style="text-decoration:none;">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="h-2 w-2 rounded-full {{ $toneAccentClass[$tone] ?? $toneAccentClass['gray'] }}"></span>
                                        <p class="line-clamp-1 text-sm font-medium text-gray-900">{{ $notice['title'] }}</p>
                                    </div>
                                    <p class="mt-1.5 line-clamp-2 text-xs leading-5 text-gray-500">{{ $notice['message'] }}</p>
                                </div>
                                <span class="shrink-0 rounded-full border {{ $toneBadgeClass[$tone] ?? $toneBadgeClass['gray'] }} px-2 py-0.5 text-[11px] font-medium">
                                    {{ $notice['status_label'] }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-4">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Kondisi Unit</p>
                    <h2 class="mt-1 text-base font-semibold text-gray-900">Ringkasan Unit</h2>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    @foreach($productivitySections as $section)
                        @php
                            $tone = $section['tone'] ?? 'gray';
                        @endphp
                        <a href="{{ $section['url'] }}" class="rounded-xl border border-gray-200 bg-white p-3 transition hover:border-gray-300 hover:bg-gray-50" style="text-decoration:none;">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $section['title'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $section['caption'] }}</p>
                                </div>
                                <span class="h-2 w-2 rounded-full {{ $toneAccentClass[$tone] ?? $toneAccentClass['gray'] }}"></span>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                @foreach($section['metrics'] as $metric)
                                    <div>
                                        <p class="text-[11px] text-gray-400">{{ $metric['label'] }}</p>
                                        <p class="mt-1 truncate text-sm font-semibold text-gray-800">{{ $metric['value'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-3 line-clamp-2 text-xs leading-5 text-gray-500">{{ $section['note'] }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">Pantauan</p>
                <h2 class="mt-1 text-base font-semibold text-gray-900">Tren 7 Hari</h2>
                <p class="text-xs text-gray-500">Garis per jenis budidaya terdaftar, tidak digabung menjadi satu angka panen.</p>
            </div>
            <x-metric-hint title="Tren 7 Hari" body="Grafik menampilkan panen per jenis budidaya, sehingga jenis hewan atau tanaman tidak digabung menjadi satu angka." formula="Agregasi panen per tanggal per jenis budidaya" source="jenisBudidaya, unitBudidaya, laporan, panen" />
        </div>

        <div class="rounded-xl bg-gray-50 p-3">
            @if($trendSeries)
                <div class="overflow-x-auto">
                    <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="h-40 min-w-[560px] w-full text-gray-300" role="img" aria-label="Tren panen per jenis budidaya">
                        <line x1="14" y1="94" x2="306" y2="94" stroke="currentColor" stroke-width="1" />
                        <line x1="14" y1="53" x2="306" y2="53" stroke="currentColor" stroke-width="1" stroke-dasharray="3 4" />
                        <line x1="14" y1="12" x2="306" y2="12" stroke="currentColor" stroke-width="1" stroke-dasharray="3 4" />
                        @foreach($trendSeries as $serie)
                            <polyline points="{{ $serie['points'] }}" fill="none" stroke="{{ $serie['color'] }}" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                            @foreach($serie['values'] as $pointIndex => $value)
                                @php
                                    $point = explode(',', explode(' ', $serie['points'])[$pointIndex] ?? '0,0');
                                @endphp
                                <circle cx="{{ $point[0] ?? 0 }}" cy="{{ $point[1] ?? 0 }}" r="2.3" fill="{{ $serie['color'] }}" />
                            @endforeach
                        @endforeach
                        @foreach($trendLabels as $index => $label)
                            @php
                                $x = 14 + ((count($trendLabels) <= 1 ? 0 : $index / (count($trendLabels) - 1)) * 292);
                            @endphp
                            <text x="{{ $x }}" y="116" text-anchor="middle" class="fill-gray-500 text-[9px]">{{ $label }}</text>
                        @endforeach
                    </svg>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-[11px] text-gray-500">
                    @foreach($trendSeries as $serie)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-100 bg-white px-2 py-1">
                            <span class="h-2 w-2 rounded-full" style="background-color: {{ $serie['color'] }}"></span>
                            {{ $serie['label'] }} <span class="text-gray-400">({{ $serie['unit_count'] }})</span>
                        </span>
                    @endforeach
                </div>
            @else
                <div class="flex h-32 items-center justify-center rounded-xl border border-dashed border-gray-200 bg-white text-center text-xs text-gray-500">
                    Belum ada jenis budidaya aktif untuk ditampilkan.
                </div>
            @endif
        </div>
    </section>
</div>
@endsection
