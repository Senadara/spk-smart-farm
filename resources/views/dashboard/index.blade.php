@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
@php
    $toneClass = [
        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'sky' => 'border-sky-200 bg-sky-50 text-sky-700',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
        'red' => 'border-red-200 bg-red-50 text-red-700',
        'gray' => 'border-gray-200 bg-gray-50 text-gray-700',
    ];
    $maxHarvest = max(max($trend['eggs'] ?? [0]), max($trend['crop'] ?? [0]), 1);
    $spkPanelClass = [
        'emerald' => 'border-emerald-200 bg-emerald-50/40',
        'amber' => 'border-amber-200 bg-amber-50/40',
        'red' => 'border-red-200 bg-red-50/40',
        'gray' => 'border-gray-200 bg-gray-50',
    ][$spk['tone'] ?? 'gray'] ?? 'border-gray-200 bg-gray-50';
    $spkBadgeClass = [
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'red' => 'bg-red-100 text-red-700',
        'gray' => 'bg-gray-100 text-gray-700',
    ][$spk['tone'] ?? 'gray'] ?? 'bg-gray-100 text-gray-700';
    $dashboardCardHints = [
        'Telur Hari Ini' => [
            'body' => 'Total telur yang dipanen dari seluruh kandang aktif pada tanggal hari ini.',
            'formula' => 'SUM(panen.jumlah) hari ini',
            'source' => 'laporan, panen, unitBudidaya',
        ],
        'HDP Rata-rata' => [
            'body' => 'Hen Day Production, yaitu persentase produktivitas telur dibanding populasi ayam aktif.',
            'formula' => '(total telur / populasi aktif) x 100%',
            'source' => 'panen, unitBudidaya',
        ],
        'Pakan Hari Ini' => [
            'body' => 'Total konsumsi pakan seluruh kandang aktif pada tanggal hari ini.',
            'formula' => 'SUM(harianTernak.pakan) hari ini',
            'source' => 'laporan, harianTernak',
        ],
        'Panen Kebun' => [
            'body' => 'Total hasil panen dari unit budidaya bertipe tumbuhan.',
            'formula' => 'SUM(panen.berat) atau SUM(panen.jumlah)',
            'source' => 'laporan, panen',
        ],
        'Kelengkapan Laporan' => [
            'body' => 'Jumlah unit aktif yang sudah memiliki minimal satu laporan hari ini dibanding total unit aktif.',
            'formula' => 'unit sudah lapor / total unit aktif',
            'source' => 'laporan, unitBudidaya',
        ],
        'Status SPK' => [
            'body' => 'Ringkasan kondisi SPK berdasarkan hasil fuzzy terbaru dan penugasan yang masih aktif.',
            'formula' => 'warning SPK atau output < 70',
            'source' => 'spk_fuzzy_logs, spk_action_tasks',
        ],
    ];
    $metricHints = [
        'Populasi' => ['body' => 'Jumlah ayam aktif pada seluruh kandang ternak yang dihitung sebagai pembagi beberapa KPI.', 'formula' => 'SUM(unitBudidaya.jumlah)', 'source' => 'unitBudidaya'],
        'Egg mass' => ['body' => 'Estimasi berat total telur yang dipanen.', 'formula' => 'SUM(panen.berat) atau panen.jumlah x 0.06 kg', 'source' => 'panen'],
        'FCR' => ['body' => 'Feed Conversion Ratio, indikator efisiensi pakan terhadap berat telur.', 'formula' => 'total pakan / egg mass', 'source' => 'harianTernak, panen'],
        'Mortalitas' => ['body' => 'Persentase kematian ayam terhadap populasi.', 'formula' => '(jumlah kematian / populasi) x 100%', 'source' => 'kematian, laporan'],
        'Blok aktif' => ['body' => 'Jumlah unit budidaya bertipe tumbuhan yang masih aktif.', 'formula' => 'COUNT(unit aktif)', 'source' => 'unitBudidaya, jenisBudidaya'],
        'Tanaman' => ['body' => 'Total populasi tanaman pada blok kebun aktif.', 'formula' => 'SUM(unitBudidaya.jumlah)', 'source' => 'unitBudidaya'],
        'Laporan' => ['body' => 'Cakupan blok kebun yang sudah mengirim laporan hari ini.', 'formula' => 'blok sudah lapor / total blok aktif', 'source' => 'laporan, unitBudidaya'],
        'Sensor risiko' => ['body' => 'Jumlah log sensor warning/error dalam 24 jam terakhir.', 'formula' => 'COUNT(logType WARNING/ERROR)', 'source' => 'iot_device_log'],
    ];
@endphp

<div class="mx-auto flex min-h-[calc(100vh-96px)] max-w-7xl flex-col gap-4">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Smart Farm</p>
            <h1 class="mt-1 text-2xl font-bold text-gray-900">Produktivitas Farm Hari Ini</h1>
            <p class="mt-1 text-sm text-gray-500">Ringkasan umum produktivitas peternakan, perkebunan, dan rekomendasi SPK.</p>
        </div>
        <x-dashboard-hint-toggle />
    </div>

    <x-page-hint title="Cara membaca dashboard" tone="sky" :open="false">
        Fokus dashboard ini adalah produktivitas. Cek Kelengkapan Laporan terlebih dahulu, lalu baca HDP, panen kebun, dan risiko SPK untuk menentukan tindakan lanjutan.
    </x-page-hint>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach($productivityCards as $item)
            @php
                $hint = $dashboardCardHints[$item['label']] ?? null;
            @endphp
            <article class="rounded-lg border bg-white p-4 {{ $toneClass[$item['tone']] ?? $toneClass['gray'] }}">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-xs font-semibold text-gray-500">{{ $item['label'] }}</p>
                    @if($hint)
                        <x-metric-hint :title="$item['label']" :body="$hint['body']" :formula="$hint['formula']" :source="$hint['source']" />
                    @endif
                </div>
                <p class="mt-2 text-2xl font-black text-gray-900">{{ $item['value'] }}</p>
                <p class="mt-1 text-xs">{{ $item['caption'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="grid flex-1 grid-cols-1 gap-4 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="rounded-lg border border-gray-200 bg-white p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-gray-900">Tren Produktivitas 7 Hari</h2>
                    <p class="text-sm text-gray-500">Indikator utama panen: telur peternakan dan hasil panen kebun.</p>
                </div>
                <div class="flex items-center gap-2">
                    <x-metric-hint title="Tren Produktivitas 7 Hari" body="Grafik membandingkan panen telur dan panen kebun selama tujuh hari terakhir." formula="Agregasi panen per tanggal, skala grafik relatif" source="laporan, panen" />
                    <a href="{{ route('peternakan') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">Detail</a>
                </div>
            </div>

            <div class="grid min-h-[260px] grid-cols-7 items-end gap-2 rounded-lg bg-gray-50 px-3 pb-3 pt-5">
                @foreach($trend['labels'] as $index => $label)
                    @php
                        $eggHeight = max(8, min(100, (($trend['eggs'][$index] ?? 0) / $maxHarvest) * 100));
                        $cropHeight = max(8, min(100, (($trend['crop'][$index] ?? 0) / $maxHarvest) * 100));
                    @endphp
                    <div class="flex h-56 flex-col items-center justify-end gap-2">
                        <div class="flex h-40 w-full items-end justify-center gap-1.5">
                            <span class="w-3 rounded-t bg-emerald-500" style="height: {{ $eggHeight }}%"></span>
                            <span class="w-3 rounded-t bg-amber-500" style="height: {{ $cropHeight }}%"></span>
                        </div>
                        <p class="text-[11px] font-semibold text-gray-500">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500">
                <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded bg-emerald-500"></span>Panen telur</span>
                <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded bg-amber-500"></span>Panen kebun</span>
                <span>Nilai grafik memakai skala relatif agar dua jenis panen mudah dibandingkan.</span>
            </div>
        </div>

        <div class="rounded-lg border bg-white p-5 {{ $spkPanelClass }}">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="font-bold text-gray-900">Peringatan SPK Hari Ini</h2>
                    <p class="mt-1 text-sm text-gray-500">Informasi penting yang perlu ditindaklanjuti dari hasil analisa SPK.</p>
                </div>
                <div class="flex items-center gap-2">
                    <x-metric-hint title="Peringatan SPK" body="Panel ini menampilkan analisis fuzzy yang berstatus Waspada/Buruk, skor rendah, atau tugas aktif yang belum selesai." formula="status Waspada/Buruk atau output < 70" source="spk_fuzzy_logs, spk_action_tasks" />
                    <span class="rounded-full {{ $spkBadgeClass }} px-3 py-1 text-xs font-bold">
                        {{ $spk['badge_label'] }}
                    </span>
                </div>
            </div>

            <p class="mt-5 text-sm font-medium leading-relaxed text-gray-800">{{ $spk['message'] }}</p>

            <div class="mt-4 space-y-2">
                @forelse($spk['warnings'] as $warning)
                    <a href="{{ $warning['url'] }}" class="block rounded-lg border border-white/70 bg-white px-4 py-3 text-sm shadow-sm hover:border-gray-200 hover:bg-gray-50" style="text-decoration:none;">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-semibold text-gray-900">{{ $warning['title'] }}</p>
                            <span class="shrink-0 text-[11px] text-gray-400">{{ $warning['time'] }}</span>
                        </div>
                        <p class="mt-1 text-xs leading-relaxed text-gray-600">{{ $warning['message'] }}</p>
                    </a>
                @empty
                    <div class="rounded-lg border border-white/70 bg-white px-4 py-3 text-sm text-gray-600 shadow-sm">
                        {{ $spk['action_hint'] }}
                    </div>
                @endforelse
            </div>

            <div class="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600">
                <p class="font-semibold text-gray-800">Update terakhir</p>
                <p class="mt-1">{{ $spk['latest_label'] }} @if($spk['latest_human']) <span class="text-gray-400">({{ $spk['latest_human'] }})</span> @endif</p>
            </div>

            <a href="{{ route('spk.dashboard') }}" class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700" style="text-decoration:none;">
                Buka Analisa SPK
            </a>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="rounded-lg border border-gray-200 bg-white p-5">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-bold text-gray-900">Peternakan</h2>
                <x-metric-hint title="Ringkasan Peternakan" body="Ringkasan ini membaca populasi, egg mass, FCR, dan mortalitas dari laporan harian dan data unit ternak." source="unitBudidaya, laporan, panen, harianTernak, kematian" />
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                @foreach([
                    ['label' => 'Populasi', 'value' => $livestock['population_label']],
                    ['label' => 'Egg mass', 'value' => $livestock['egg_mass_label']],
                    ['label' => 'FCR', 'value' => $livestock['fcr_label']],
                    ['label' => 'Mortalitas', 'value' => $livestock['mortality_rate_label']],
                ] as $metric)
                    @php
                        $hint = $metricHints[$metric['label']];
                    @endphp
                    <div>
                        <div class="flex items-center gap-1">
                            <p class="text-xs text-gray-400">{{ $metric['label'] }}</p>
                            <x-metric-hint :title="$metric['label']" :body="$hint['body']" :formula="$hint['formula']" :source="$hint['source']" />
                        </div>
                        <p class="font-bold text-gray-900">{{ $metric['value'] }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">{{ $livestock['report_message'] }}</p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-bold text-gray-900">Perkebunan</h2>
                <x-metric-hint title="Ringkasan Perkebunan" body="Ringkasan ini membaca blok aktif, populasi tanaman, kelengkapan laporan, dan risiko sensor kebun." source="unitBudidaya, laporan, iot_device_log" />
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                @foreach([
                    ['label' => 'Blok aktif', 'value' => $crop['active_units'], 'class' => 'text-gray-900'],
                    ['label' => 'Tanaman', 'value' => $crop['plants_label'], 'class' => 'text-gray-900'],
                    ['label' => 'Laporan', 'value' => $crop['report_coverage_label'], 'class' => 'text-gray-900'],
                    ['label' => 'Sensor risiko', 'value' => $crop['sensor_risks'], 'class' => $crop['sensor_risks'] > 0 ? 'text-red-600' : 'text-gray-900'],
                ] as $metric)
                    @php
                        $hint = $metricHints[$metric['label']];
                    @endphp
                    <div>
                        <div class="flex items-center gap-1">
                            <p class="text-xs text-gray-400">{{ $metric['label'] }}</p>
                            <x-metric-hint :title="$metric['label']" :body="$hint['body']" :formula="$hint['formula']" :source="$hint['source']" />
                        </div>
                        <p class="font-bold {{ $metric['class'] }}">{{ $metric['value'] }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-700">{{ $crop['note'] }}</p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="font-bold text-gray-900">Prioritas</h2>
                <div class="flex items-center gap-2">
                    <x-metric-hint title="Prioritas" body="Daftar prioritas berisi gabungan peringatan IoT dan hasil SPK terbaru yang perlu ditinjau." formula="IoT WARNING/ERROR + SPK Waspada/Buruk" source="iot_device_log, spk_fuzzy_logs" />
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">{{ count($alerts) }}</span>
                </div>
            </div>
            <div class="max-h-56 space-y-2 overflow-y-auto pr-1">
                @forelse($alerts as $alert)
                    <a href="{{ $alert['url'] }}" class="block rounded-lg border px-3 py-2 text-sm {{ $alert['type'] === 'danger' ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }}" style="text-decoration:none;">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-semibold text-gray-900">{{ $alert['title'] }}</p>
                            <span class="shrink-0 text-[11px] text-gray-500">{{ $alert['time'] }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-600">{{ $alert['message'] }}</p>
                    </a>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
                        Belum ada prioritas SPK atau sensor.
                    </div>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection
