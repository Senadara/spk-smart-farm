@extends('layouts.app')

@section('title', $barn['name'] . ' — Detail Kandang')
@section('breadcrumb', 'Peternakan › ' . $barn['name'])

@section('content')
    @php
        $detailKpiHints = [
            'HDP %' => ['body' => 'Hen Day Production kandang ini, yaitu persentase telur hari ini dibanding populasi ayam hidup.', 'formula' => '(telur hari ini / populasi hidup) x 100%', 'source' => 'panen, unitBudidaya'],
            'HHEP %' => ['body' => 'Hen Housed Egg Production, persentase telur dibanding populasi awal kandang.', 'formula' => '(telur hari ini / populasi awal) x 100%', 'source' => 'panen, unitBudidaya'],
            'Feed Intake' => ['body' => 'Rata-rata pakan yang dikonsumsi per ekor per hari.', 'formula' => '(pakan hari ini / populasi hidup) x 1000 gram', 'source' => 'harianTernak, unitBudidaya'],
            'FCR' => ['body' => 'Feed Conversion Ratio kandang ini. Semakin kecil biasanya semakin efisien.', 'formula' => 'pakan hari ini / egg mass', 'source' => 'harianTernak, panen'],
            'Mortalitas' => ['body' => 'Persentase kematian ayam pada kandang ini.', 'formula' => '(jumlah kematian / populasi awal) x 100%', 'source' => 'kematian, laporan'],
            'Reject Telur' => ['body' => 'Persentase telur rusak/reject dari total panen hari ini.', 'formula' => '(jumlah telur reject / total telur) x 100%', 'source' => 'panenRincianGrade, grade'],
        ];
        $barnOverviewHints = [
            'Lokasi' => ['body' => 'Lokasi fisik kandang untuk membantu identifikasi unit budidaya.', 'source' => 'unitBudidaya'],
            'Breed' => ['body' => 'Jenis/strain ayam yang dipelihara pada kandang.', 'source' => 'unitBudidaya / komoditas'],
            'Tanggal Masuk' => ['body' => 'Tanggal data kandang dibuat di sistem. Umur flock utama mengikuti input umur dari mobile.', 'source' => 'unitBudidaya.createdAt'],
            'Populasi' => ['body' => 'Jumlah ayam aktif yang dipakai sebagai pembagi HDP dan feed intake.', 'source' => 'unitBudidaya.jumlah'],
            'Kapasitas' => ['body' => 'Batas kapasitas kandang agar populasi dapat dibandingkan dengan daya tampung.', 'source' => 'unitBudidaya.kapasitas'],
            'Umur Flock' => ['body' => 'Umur flock/ayam di kandang dalam satuan minggu dari input mobile. Data lama fallback ke tanggal kandang dibuat.', 'formula' => 'unitBudidaya.umurMinggu', 'source' => 'unitBudidaya.umurMinggu'],
        ];
        $sensorHints = [
            'Suhu' => ['body' => 'Suhu kandang terbaru dari sensor. Nilai dibandingkan dengan rentang ideal komoditas.', 'source' => 'iot_sensor_data'],
            'Kelembapan' => ['body' => 'Kelembapan kandang terbaru dari sensor, berpengaruh pada kenyamanan ayam.', 'source' => 'iot_sensor_data'],
            'Amonia' => ['body' => 'Kadar amonia kandang. Nilai tinggi dapat menjadi sinyal ventilasi atau litter perlu dicek.', 'source' => 'iot_sensor_data'],
            'Cahaya' => ['body' => 'Intensitas cahaya kandang dari sensor lux.', 'source' => 'iot_sensor_data'],
        ];
        $eggMetricHints = [
            'Total Telur' => ['body' => 'Jumlah telur yang dipanen dari kandang ini pada laporan hari ini.', 'formula' => 'SUM(panen.jumlah)', 'source' => 'panen'],
            'Egg Mass' => ['body' => 'Berat total telur kandang hari ini dari laporan panen mobile.', 'formula' => 'SUM(panen.berat)', 'source' => 'panen'],
            'Berat Rata-rata' => ['body' => 'Rata-rata berat telur berdasarkan total berat dan jumlah telur.', 'formula' => 'egg mass / total telur', 'source' => 'panen'],
            'Reject/Rusak' => ['body' => 'Persentase telur reject, rusak, retak, kotor, pecah, atau afkir dari total panen.', 'formula' => '(jumlah telur reject / total telur) x 100%', 'source' => 'panenRincianGrade, grade'],
        ];
        $canExportProductivity = in_array(session('user.role'), ['pjawab', 'owner', 'admin'], true);
    @endphp

    <div x-data="{
        sensorFilter: 'all',
        sensorRange: '24h',
        prodFilter: 'all',
        prodRange: '30d',
        _trendChart: null,
        _prodChart: null,
        init() {
            this.$nextTick(() => {
                this.renderTrend();
                this.renderProd();
            });
        },
        renderTrend() {
            const ctx = this.$refs.trendCanvas;
            if (!ctx) return;
            if (this._trendChart) this._trendChart.destroy();
            const datasets = [];
            const colors = ['#EF4444','#3B82F6','#F59E0B','#8B5CF6'];
            const labels = ['Suhu (°C)','Kelembapan (%)','Amonia (ppm)','Cahaya (lux)'];
            const allData = @js([$sensorTrend['temperature'], $sensorTrend['humidity'], $sensorTrend['ammonia'] ?? [], $sensorTrend['light'] ?? []]);
            const allLabels = @js($sensorTrend['labels']);
            const range = this.sensorRange;
            const sliceN = range === '6h' ? 6 : range === '12h' ? 12 : 24;
            const slicedLabels = allLabels.slice(-sliceN);
            const filter = this.sensorFilter;
            allData.forEach((d, i) => {
                if (d.length === 0) return;
                if (filter !== 'all' && parseInt(filter) !== i) return;
                datasets.push({
                    label: labels[i], data: d.slice(-sliceN), borderColor: colors[i],
                    backgroundColor: colors[i] + '10', borderWidth: 2, fill: true,
                    tension: 0.4, pointRadius: 0, pointHoverRadius: 4,
                });
            });
            this._trendChart = new Chart(ctx, {
                type: 'line',
                data: { labels: slicedLabels, datasets },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'top', labels: { usePointStyle: true, pointStyle: 'circle', padding: 14, font: { size: 10, family: 'Inter' } } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 9, family: 'Inter' }, color: '#9CA3AF', maxTicksLimit: 12 } },
                        y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 9, family: 'Inter' }, color: '#9CA3AF' } },
                    },
                },
            });
        },
        renderProd() {
            const ctx = this.$refs.prodCanvas;
            if (!ctx) return;
            if (this._prodChart) this._prodChart.destroy();
            const datasets = [];
            const colors = ['#10B981','#3B82F6','#F59E0B','#8B5CF6','#EF4444'];
            const labels = ['HDP (%)','HHEP (%)','FCR','Feed Intake (g)','Mortalitas (%)'];
            const allData = @js([$productivityTrend['hdp'], $productivityTrend['hhep'], $productivityTrend['fcr'], $productivityTrend['feedIntake'], $productivityTrend['mortality']]);
            const allLabels = @js($productivityTrend['labels']);
            const range = this.prodRange;
            const sliceN = range === '7d' ? 7 : range === '14d' ? 14 : 30;
            const slicedLabels = allLabels.slice(-sliceN);
            const filter = this.prodFilter;
            allData.forEach((d, i) => {
                if (filter !== 'all' && parseInt(filter) !== i) return;
                datasets.push({
                    label: labels[i], data: d.slice(-sliceN), borderColor: colors[i],
                    backgroundColor: colors[i] + '10', borderWidth: 2, fill: false,
                    tension: 0.4, pointRadius: 0, pointHoverRadius: 4,
                    yAxisID: (i === 2 || i === 4) ? 'y1' : 'y',
                });
            });
            this._prodChart = new Chart(ctx, {
                type: 'line',
                data: { labels: slicedLabels, datasets },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'top', labels: { usePointStyle: true, pointStyle: 'circle', padding: 14, font: { size: 10, family: 'Inter' } } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 9, family: 'Inter' }, color: '#9CA3AF', maxTicksLimit: 15 } },
                        y: { type: 'linear', position: 'left', title: { display: true, text: '%  /  g', font: { size: 10 }, color: '#6B7280' }, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 9 }, color: '#9CA3AF' } },
                        y1: { type: 'linear', position: 'right', title: { display: true, text: 'FCR / Mort.%', font: { size: 10 }, color: '#6B7280' }, grid: { drawOnChartArea: false }, ticks: { font: { size: 9 }, color: '#9CA3AF' } },
                    },
                },
            });
        },
    }" class="max-w-full space-y-5">

        {{-- ═══ HEADER ═══ --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold text-gray-900">{{ $barn['name'] }}</h1>
                    @php
                        $sc = [
                            'normal'  => ['label'=>'Optimal','cls'=>'text-emerald-600 bg-emerald-50'],
                            'warning' => ['label'=>'Perhatian','cls'=>'text-amber-600 bg-amber-50'],
                            'danger'  => ['label'=>'Kritis','cls'=>'text-red-600 bg-red-50'],
                        ][$barn['status']] ?? ['label'=>'Normal','cls'=>'text-gray-500 bg-gray-50'];
                    @endphp
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold rounded-full {{ $sc['cls'] }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                        {{ $sc['label'] }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-0.5">
                    {{ $barn['breed'] }} · {{ $barn['location'] }} · Umur Flock: {{ $barn['flockAge'] }} · {{ $barn['totalBirds'] }} / {{ $barn['capacity'] }} ekor
                </p>
            </div>
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                <x-dashboard-hint-toggle />
                <a href="{{ route('peternakan', array_filter(['komoditas' => $activeKomoditasId])) }}" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-600 transition hover:bg-gray-50 no-underline sm:w-auto sm:px-4 sm:text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    Kembali
                </a>
                @if($canExportProductivity)
                    <a
                        href="{{ route('peternakan.settlement', array_filter(['id' => $barn['id'], 'komoditas' => $activeKomoditasId])) }}"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-500 px-3 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-emerald-600 no-underline sm:w-auto sm:px-4 sm:text-sm"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Settlement / PDF
                    </a>
                @endif
                <a
                    href="{{ route('peternakan.individual-productivity', array_filter(['id' => $barn['id'], 'komoditas' => $activeKomoditasId])) }}"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 transition hover:bg-amber-100 no-underline sm:w-auto sm:px-4 sm:text-sm"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 15l3-3 3 2 5-6"/></svg>
                    HDP Individu
                </a>
            </div>
        </div>

        {{-- ═══ BARN OVERVIEW — COMPACT ═══ --}}
        <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden flex flex-col lg:flex-row">
            <div class="lg:w-1/8 h-36 lg:h-auto relative bg-gray-100">
                <img src="{{ $barn['photo'] }}" alt="{{ $barn['name'] }}" class="w-full h-full object-cover">
            </div>
            <div class="lg:w-3/4 p-4 lg:p-5 flex items-center">
                <div class="grid grid-cols-3 md:grid-cols-6 gap-4 w-full">
                    @foreach ([
                        ['l'=>'Lokasi','v'=>$barn['location']],
                        ['l'=>'Breed','v'=>$barn['breed']],
                        ['l'=>'Tanggal Masuk','v'=> \Carbon\Carbon::parse($barn['startDate'])->format('d M Y')],
                        ['l'=>'Populasi','v'=>$barn['totalBirds']],
                        ['l'=>'Kapasitas','v'=>$barn['capacity']],
                        ['l'=>'Umur Flock','v'=>$barn['flockAge']],
                    ] as $f)
                        <div>
                            @php
                                $hint = $barnOverviewHints[$f['l']] ?? null;
                            @endphp
                            <div class="mb-0.5 flex items-center gap-1">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wider">{{ $f['l'] }}</p>
                                @if($hint)
                                    <x-metric-hint :title="$f['l']" :body="$hint['body']" :formula="$hint['formula'] ?? null" :source="$hint['source'] ?? null" />
                                @endif
                            </div>
                            <p class="text-sm font-bold text-gray-900">{{ $f['v'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ═══ KPI ROW — SAME AS DASHBOARD ═══ --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach ([
                ['label'=>'HDP %','value'=>$kpi['hdp'] ? $kpi['hdp'].'%' : '-','trend'=>['direction'=>'up','value'=>'+1.2%','status'=>'positive']],
                ['label'=>'HHEP %','value'=>$kpi['hhep'] ? $kpi['hhep'].'%' : '-','trend'=>['direction'=>'up','value'=>'+0.5%','status'=>'positive']],
                ['label'=>'Feed Intake','value'=>$kpi['feedIntake'].'g','trend'=>['direction'=>'stable','value'=>'Stable','status'=>'neutral']],
                ['label'=>'FCR','value'=>$kpi['fcr'] ?: '-','trend'=>['direction'=>'down','value'=>'-0.02','status'=>'positive']],
                ['label'=>'Mortalitas','value'=>$kpi['mortalitas'].'%','trend'=>['direction'=>$kpi['mortalitas']>0.05?'up':'stable','value'=>$kpi['mortalitas']>0.05?'+0.01%':'Stable','status'=>$kpi['mortalitas']>0.05?'warning':'neutral']],
                ['label'=>'Reject Telur','value'=>$kpi['afkir'].'%','trend'=>['direction'=>$kpi['afkir']>5?'up':'stable','value'=>$kpi['afkir']>5?'Perlu cek':'Stable','status'=>$kpi['afkir']>5?'warning':'neutral']],
            ] as $m)
                @php
                    $hint = $detailKpiHints[$m['label']] ?? null;
                @endphp
                <x-peternakan.kpi-card
                    :label="$m['label']"
                    :value="$m['value']"
                    :trend="$m['trend']"
                    :hint="$hint['body'] ?? null"
                    :formula="$hint['formula'] ?? null"
                    :source="$hint['source'] ?? null"
                />
            @endforeach
        </div>

        {{-- ═══ SENSOR TREND CHART + LIVE SENSORS ═══ --}}
        @php
            $healthStatus = $healthContext['status'] ?? 'normal';
            $healthTone = [
                'normal' => ['badge' => 'bg-emerald-50 text-emerald-700', 'border' => 'border-emerald-100'],
                'warning' => ['badge' => 'bg-amber-50 text-amber-700', 'border' => 'border-amber-100'],
                'danger' => ['badge' => 'bg-red-50 text-red-700', 'border' => 'border-red-100'],
            ][$healthStatus] ?? ['badge' => 'bg-gray-50 text-gray-600', 'border' => 'border-gray-100'];
            $metricTone = [
                'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                'amber' => 'bg-amber-50 text-amber-700 border-amber-100',
                'red' => 'bg-red-50 text-red-700 border-red-100',
                'sky' => 'bg-sky-50 text-sky-700 border-sky-100',
                'gray' => 'bg-gray-50 text-gray-600 border-gray-100',
            ];
            $signalTone = [
                'danger' => 'border-red-100 bg-red-50 text-red-700',
                'warning' => 'border-amber-100 bg-amber-50 text-amber-700',
                'info' => 'border-sky-100 bg-sky-50 text-sky-700',
            ];
            $canCreateHealthTask = data_get(session('user'), 'role') === 'pjawab';
            $eggDrop = data_get($healthContext, 'egg_production_drop');
        @endphp
        <div class="rounded-xl border {{ $healthTone['border'] }} bg-white p-4 shadow-sm sm:p-5">
            @if(session('health_indication_success') || session('health_indication_warning') || session('health_indication_error'))
                <div class="mb-4 rounded-xl border px-3 py-2 text-xs font-semibold {{
                    session('health_indication_success') ? 'border-emerald-100 bg-emerald-50 text-emerald-700' :
                    (session('health_indication_warning') ? 'border-amber-100 bg-amber-50 text-amber-700' : 'border-red-100 bg-red-50 text-red-700')
                }}">
                    {{ session('health_indication_success') ?? session('health_indication_warning') ?? session('health_indication_error') }}
                </div>
            @endif

            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base font-semibold text-gray-800">Konteks Kesehatan Kandang</h3>
                        <x-metric-hint title="Konteks Kesehatan" body="Bagian ini menggabungkan data existing dari laporan mobile, produktivitas, kematian, sakit, pakan, dan SPK web. Jumlah sakit ditampilkan sebagai jumlah laporan karena mobile belum menyimpan jumlah ayam sakit eksplisit." source="laporan, sakit, kematian, panen, harianTernak, spk_fuzzy_logs" />
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $healthTone['badge'] }}">
                            {{ $healthContext['status_label'] ?? 'Terkendali' }}
                        </span>
                    </div>
                    <p class="mt-1 max-w-3xl text-xs leading-relaxed text-gray-500">
                        {{ $healthContext['summary'] ?? 'Belum ada sinyal kesehatan penting dari data existing.' }}
                    </p>
                </div>
                @if($canCreateHealthTask && data_get($healthContext, 'task.recommended'))
                    <a href="{{ data_get($healthContext, 'task.url') }}" class="inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-700 no-underline sm:w-auto">
                        Buat Tugas Pemeriksaan
                    </a>
                @endif
            </div>

            @if($eggProductionDropError ?? null)
                <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-xs leading-relaxed text-slate-500">
                    Data ayam tidak bertelur belum bisa dibaca dari Node API: {{ $eggProductionDropError }}
                </div>
            @endif

            @if($eggDrop)
                <div class="mt-4 rounded-xl border {{ data_get($eggDrop, 'isIndication') ? 'border-amber-100 bg-amber-50' : 'border-sky-100 bg-sky-50' }} p-3">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wide {{ data_get($eggDrop, 'isIndication') ? 'text-amber-700' : 'text-sky-700' }}">
                                HDP individu
                            </p>
                            <h4 class="mt-1 text-sm font-black text-slate-900">Analisis per ayam tersedia di halaman khusus</h4>
                            <p class="mt-1 text-xs leading-relaxed text-slate-600">
                                Buka tabel HDP individu untuk membandingkan periode sekarang dan sebelumnya, mengurutkan ayam dengan penurunan terbesar, serta membuat laporan indikasi sakit otomatis bila diperlukan.
                            </p>
                        </div>
                        <a
                            href="{{ route('peternakan.individual-productivity', array_filter(['id' => $barn['id'], 'komoditas' => $activeKomoditasId])) }}"
                            class="inline-flex w-full shrink-0 items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white transition hover:bg-slate-700 no-underline sm:w-auto"
                        >
                            Buka Tabel HDP
                        </a>
                    </div>
                </div>
            @endif

            <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-5">
                @foreach (($healthContext['metrics'] ?? []) as $metric)
                    <div class="rounded-xl border px-3 py-3 {{ $metricTone[$metric['tone'] ?? 'gray'] ?? $metricTone['gray'] }}">
                        <p class="text-[10px] font-bold uppercase tracking-wide opacity-70">{{ $metric['label'] }}</p>
                        <p class="mt-1 text-base font-black">{{ $metric['value'] }}</p>
                        <p class="mt-1 text-[11px] leading-snug opacity-80">{{ $metric['caption'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-3">
                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Sinyal yang terbaca</p>
                    <div class="space-y-2">
                        @forelse (array_slice($healthContext['signals'] ?? [], 0, 4) as $signal)
                            <div class="rounded-lg border px-3 py-2 {{ $signalTone[$signal['level'] ?? 'info'] ?? $signalTone['info'] }}">
                                <p class="text-xs font-bold">{{ $signal['title'] }}</p>
                                <p class="mt-0.5 text-[11px] leading-relaxed opacity-90">{{ $signal['message'] }}</p>
                                <p class="mt-1 text-[10px] font-semibold opacity-70">Sumber: {{ $signal['source'] }}</p>
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-gray-200 bg-white px-3 py-4 text-center text-xs text-gray-400">
                                Belum ada sinyal sakit, mati, atau produktivitas yang perlu ditindaklanjuti.
                            </p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 bg-white p-3">
                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Instruksi untuk mobile</p>
                    <div class="space-y-2">
                        @foreach (($healthContext['mobile_checklist'] ?? []) as $item)
                            <div class="flex gap-2 rounded-lg bg-slate-50 px-3 py-2">
                                <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span>
                                <p class="text-xs leading-relaxed text-slate-600">{{ $item }}</p>
                            </div>
                        @endforeach
                    </div>
                    @if(!empty($healthContext['latest_sick']))
                        <div class="mt-3 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-amber-600">Laporan sakit terakhir</p>
                            <p class="mt-1 text-xs font-semibold text-slate-800">
                                {{ data_get($healthContext, 'latest_sick.diagnosis') ?: 'Diagnosis belum tersedia' }}
                            </p>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                Status {{ data_get($healthContext, 'latest_sick.status') }} - {{ data_get($healthContext, 'latest_sick.created_at') }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
            <div class="lg:col-span-3 bg-white border border-gray-100 rounded-xl p-4 sm:p-5 shadow-sm">
                <div class="flex flex-col gap-3 mb-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        <h3 class="text-base font-semibold text-gray-800">Tren Sensor</h3>
                        <x-metric-hint title="Tren Sensor" body="Grafik ini menampilkan histori sensor kandang berdasarkan rentang jam yang dipilih." formula="AVG(sensor value) per jam" source="iot_sensor_data, iot_parameter" />
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex bg-gray-100 rounded-lg p-0.5">
                            <template x-for="r in [{v:'6h',l:'6J'},{v:'12h',l:'12J'},{v:'24h',l:'24J'}]">
                                <button @click="sensorRange=r.v; renderTrend()" :class="sensorRange===r.v ? 'bg-white shadow-sm text-gray-900':'text-gray-500 hover:text-gray-700'" class="px-2.5 py-1 text-[10px] font-semibold rounded-md transition-all" x-text="r.l"></button>
                            </template>
                        </div>
                        <select x-model="sensorFilter" @change="renderTrend()" class="min-w-0 flex-1 text-xs border border-gray-200 rounded-lg px-2 py-1 bg-white text-gray-600 focus:outline-none focus:ring-1 focus:ring-emerald-300 sm:flex-none">
                            <option value="all">Semua</option>
                            <option value="0">Suhu</option>
                            <option value="1">Kelembapan</option>
                            <option value="2">Amonia</option>
                            <option value="3">Cahaya</option>
                        </select>
                    </div>
                </div>
                <div class="h-[220px] sm:h-[260px]">
                    <canvas x-ref="trendCanvas"></canvas>
                </div>
            </div>

            {{-- Live Sensors + IoT Device --}}
            <div class="lg:col-span-2 bg-white border border-gray-100 rounded-xl p-4 sm:p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-semibold text-gray-800">Sensor & Perangkat</h3>
                        <x-metric-hint title="Sensor & Perangkat" body="Card ini menampilkan nilai sensor terbaru dan status device. Device dapat dianggap offline jika tidak mengirim data melewati batas konfigurasi." source="iot_device, iot_sensor_data, iot_device_log" />
                    </div>
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    @php
                        $sIcons = [
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9V3m0 0a2 2 0 10-4 0v9.764a4 4 0 106.764 1.528A3.99 3.99 0 0012 13V3z"/>',
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a8 8 0 004-14.947L12 2l-4 4.053A8 8 0 0012 21z"/>',
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>',
                        ];
                        $statusMap = ['normal'=>'text-emerald-600 bg-emerald-50','warning'=>'text-amber-600 bg-amber-50','danger'=>'text-red-600 bg-red-50'];
                        $statusLabel = ['normal'=>'OK','warning'=>'⚠','danger'=>'!'];
                    @endphp
                    @foreach ($sensors as $i => $s)
                        <div class="rounded-xl border border-gray-100 p-3 hover:shadow-sm transition-all">
                            <div class="flex items-center justify-between mb-1">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">{!! $sIcons[$i] !!}</svg>
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full {{ $statusMap[$s['status']] ?? $statusMap['normal'] }}">{{ $statusLabel[$s['status']] ?? 'OK' }}</span>
                            </div>
                            @php
                                $hint = $sensorHints[$s['label']] ?? null;
                            @endphp
                            <div class="flex items-center gap-1">
                                <p class="text-xs text-gray-400 uppercase tracking-wider">{{ $s['label'] }}</p>
                                @if($hint)
                                    <x-metric-hint :title="$s['label']" :body="$hint['body']" :source="$hint['source']" />
                                @endif
                            </div>
                            <p class="text-lg font-bold text-gray-900">{{ $s['value'] }}<span class="text-xs font-normal text-gray-400">{{ $s['unit'] }}</span></p>
                        </div>
                    @endforeach
                </div>
                @if ($iotDevice)
                    <div class="rounded-xl border border-dashed border-gray-200 p-3 bg-gray-50/50">
                        <p class="text-[10px] text-gray-400 uppercase tracking-wider mb-1">Perangkat IoT</p>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-900 font-mono">{{ $iotDevice['code'] }}</p>
                                <p class="text-[10px] text-gray-500">{{ $iotDevice['name'] }} · {{ $iotDevice['protocol'] }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold {{ $iotDevice['status'] === 'active' ? 'text-emerald-600 bg-emerald-50' : 'text-red-600 bg-red-50' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                {{ ucfirst($iotDevice['status']) }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ═══ PRODUCTIVITY TREND CHART ═══ --}}
        <div class="bg-white border border-gray-100 rounded-xl p-4 sm:p-5 shadow-sm">
            <div class="flex flex-col gap-3 mb-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-semibold text-gray-800">Tren Produktivitas</h3>
                        <x-metric-hint title="Tren Produktivitas" body="Grafik ini memperlihatkan perkembangan HDP, HHEP, FCR, feed intake, dan mortalitas pada kandang ini." formula="agregasi laporan harian per tanggal" source="laporan, panen, harianTernak, kematian" />
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">HDP, HHEP, FCR, Feed Intake, Mortalitas</p>
                </div>
                <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                    <div class="flex bg-gray-100 rounded-lg p-0.5">
                        <template x-for="r in [{v:'7d',l:'7H'},{v:'14d',l:'14H'},{v:'30d',l:'30H'}]">
                            <button @click="prodRange=r.v; renderProd()" :class="prodRange===r.v ? 'bg-white shadow-sm text-gray-900':'text-gray-500 hover:text-gray-700'" class="px-2.5 py-1 text-[10px] font-semibold rounded-md transition-all" x-text="r.l"></button>
                        </template>
                    </div>
                    <select x-model="prodFilter" @change="renderProd()" class="min-w-0 flex-1 text-xs border border-gray-200 rounded-lg px-2 py-1 bg-white text-gray-600 focus:outline-none focus:ring-1 focus:ring-emerald-300 sm:flex-none">
                        <option value="all">Semua</option>
                        <option value="0">HDP</option>
                        <option value="1">HHEP</option>
                        <option value="2">FCR</option>
                        <option value="3">Feed Intake</option>
                        <option value="4">Mortalitas</option>
                    </select>
                </div>
            </div>
            <div class="h-[230px] sm:h-[280px]">
                <canvas x-ref="prodCanvas"></canvas>
            </div>
        </div>

        {{-- ═══ EGG QUALITY CARD ═══ --}}
        @php
            $hasEggReport = $eggQuality['hasReport'] ?? false;
            $gradeDistribution = $eggQuality['gradeDistribution'] ?? [];
            $hasGradeDetail = $eggQuality['hasGradeDetail'] ?? false;
            $rejectRate = $eggQuality['rejectRate'] ?? null;
            $hasMissingEggFields = !empty($eggQuality['missingFields'] ?? []);
        @endphp
        <div class="bg-white border border-gray-100 rounded-xl p-4 sm:p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-semibold text-gray-800">Detail Produksi Telur</h3>
                        <x-metric-hint title="Detail Produksi Telur" body="Bagian ini membaca laporan panen hari ini untuk menampilkan distribusi grade, total telur, egg mass, berat rata-rata, dan reject." source="laporan, panen, panenRincianGrade, grade" />
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">Sumber hari ini: laporan panen, panen, dan rincian grade - {{ $eggQuality['sourceDate'] }}</p>
                </div>
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $hasEggReport ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                    {{ $hasEggReport ? 'Data hari ini tersedia' : 'Belum ada panen hari ini' }}
                </span>
            </div>

            @if(!$hasEggReport)
                <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-900 mb-5">
                    <p class="font-semibold">Belum ada laporan panen untuk kandang ini pada {{ $eggQuality['sourceDate'] }}.</p>
                    <p class="mt-1 text-xs leading-relaxed opacity-90">
                        Distribusi grade, reject telur, egg mass, dan rata-rata berat telur akan tampil setelah laporan panen harian masuk.
                        @if(!empty($eggQuality['lastPanenAt']))
                            Laporan panen terakhir: {{ $eggQuality['lastPanenAt'] }}.
                        @endif
                    </p>
                </div>
            @endif

            <div class="grid grid-cols-1 {{ $hasMissingEggFields ? 'md:grid-cols-3' : 'md:grid-cols-2' }} gap-4 sm:gap-6">
                <div>
                    <div class="mb-3 flex items-center gap-1">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Distribusi Grade</p>
                        <x-metric-hint title="Distribusi Grade" body="Distribusi grade menunjukkan komposisi hasil panen berdasarkan grade telur yang dicatat pada laporan." formula="jumlah grade / total telur x 100% atau berat grade / total berat telur x 100%" source="panenRincianGrade, grade" />
                    </div>
                    @if($hasGradeDetail)
                        <div class="h-4 w-full rounded-full overflow-hidden flex mb-4 bg-gray-100">
                            @foreach ($gradeDistribution as $grade)
                                <div class="h-full {{ $grade['color'] }}" style="width: {{ max($grade['pct'], 1) }}%" title="{{ $grade['label'] }}"></div>
                            @endforeach
                        </div>
                        <div class="space-y-2">
                            @foreach ($gradeDistribution as $grade)
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <div class="w-2 h-2 rounded-full {{ $grade['color'] }}"></div>
                                        <span class="text-gray-600">{{ $grade['label'] }}</span>
                                    </div>
                                    @php
                                        $gradeUnit = $grade['unit'] ?? 'butir';
                                        $gradeDecimals = $gradeUnit === 'kg' ? 2 : 0;
                                    @endphp
                                    <span class="font-bold text-gray-900">
                                        {{ $grade['pct'] }}%
                                        <span class="font-normal text-gray-400">({{ number_format((float)$grade['count'], $gradeDecimals, ',', '.') }} {{ $gradeUnit }})</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-4 text-xs text-gray-500">
                            Rincian grade belum tersedia untuk laporan hari ini.
                        </div>
                    @endif
                </div>

                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Ringkasan Panen</p>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach([
                            ['label' => 'Total Telur', 'value' => number_format((float)($eggQuality['totalEggs'] ?? 0), 0, ',', '.'), 'class' => 'text-gray-900'],
                            ['label' => 'Egg Mass', 'value' => number_format((float)($eggQuality['totalWeightKg'] ?? 0), 2, ',', '.') . ' kg', 'class' => 'text-gray-900'],
                            ['label' => 'Berat Rata-rata', 'value' => $eggQuality['avgWeightGram'] !== null ? number_format((float)$eggQuality['avgWeightGram'], 1, ',', '.') . 'g' : '-', 'class' => 'text-gray-900'],
                            ['label' => 'Reject/Rusak', 'value' => $rejectRate !== null ? number_format((float)$rejectRate, 2, ',', '.') . '%' : '-', 'sub' => isset($eggQuality['rejectCount']) ? number_format((float)($eggQuality['rejectCount'] ?? 0), ($eggQuality['rejectUnit'] ?? 'butir') === 'kg' ? 2 : 0, ',', '.') . ' ' . ($eggQuality['rejectUnit'] ?? 'butir') : null, 'class' => $rejectRate !== null && $rejectRate > 5 ? 'text-amber-600' : 'text-gray-900'],
                        ] as $metric)
                            @php
                                $hint = $eggMetricHints[$metric['label']];
                            @endphp
                            <div class="rounded-xl border border-gray-100 p-3">
                                <div class="flex items-center gap-1">
                                    <p class="text-[10px] text-gray-400 uppercase font-semibold">{{ $metric['label'] }}</p>
                                    <x-metric-hint :title="$metric['label']" :body="$hint['body']" :formula="$hint['formula']" :source="$hint['source']" />
                                </div>
                                <p class="mt-1 text-lg sm:text-xl font-black {{ $metric['class'] }}">{{ $metric['value'] }}</p>
                                @if(!empty($metric['sub']))
                                    <p class="mt-0.5 text-[11px] font-medium text-gray-400">{{ $metric['sub'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($hasMissingEggFields)
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Data Belum Tersedia</p>
                        <div class="space-y-2">
                            @foreach (($eggQuality['missingFields'] ?? []) as $missing)
                                <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-3 py-2">
                                    <p class="text-xs font-semibold text-gray-700">{{ $missing['label'] }}</p>
                                    <p class="text-[11px] text-gray-500 mt-0.5">{{ $missing['description'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-5 rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <div>
                        <p class="text-xs font-bold text-gray-700">Status data harian</p>
                        <p class="text-[11px] text-gray-500 mt-0.5">Ringkasan kelengkapan data yang dipakai untuk membaca kondisi kandang hari ini.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-metric-hint title="Status data harian" body="Audit ini memberi tahu data mana yang tersedia atau masih perlu dilengkapi agar hasil dashboard tidak disalahartikan." source="laporan, panen, harianTernak, kematian, sensor" />
                        <span class="text-[11px] font-semibold text-gray-400">{{ $dailyDataAudit['date'] ?? '' }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach (($dailyDataAudit['available'] ?? []) as $item)
                        @php
                            $auditCls = [
                                'ready' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                'empty' => 'bg-amber-50 text-amber-700 border-amber-100',
                                'warning' => 'bg-orange-50 text-orange-700 border-orange-100',
                            ][$item['status']] ?? 'bg-gray-50 text-gray-600 border-gray-100';
                            $statusText = [
                                'ready' => 'tersedia',
                                'empty' => 'belum ada',
                                'warning' => 'perlu data',
                            ][$item['status']] ?? 'dicek';
                        @endphp
                        <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $auditCls }}">
                            {{ $item['label'] }}: {{ $statusText }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        @if(false)
        <div class="bg-white border border-gray-100 rounded-xl p-4 sm:p-5 shadow-sm">
            <h3 class="text-base font-semibold text-gray-800 mb-5">Legacy Egg Production Details (Disabled)</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                {{-- Size Distribution --}}
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Size Distribution</p>
                    {{-- Stacked bar --}}
                    <div class="h-4 w-full rounded-full overflow-hidden flex mb-4">
                        <div class="h-full bg-blue-600" style="width: {{ $eggQuality['small'] }}%" title="Small"></div>
                        <div class="h-full bg-emerald-500" style="width: {{ $eggQuality['medium'] }}%" title="Medium"></div>
                        <div class="h-full bg-emerald-700" style="width: {{ $eggQuality['large'] }}%" title="Large"></div>
                        <div class="h-full bg-emerald-900" style="width: {{ $eggQuality['xl'] }}%" title="XL"></div>
                    </div>
                    <div class="space-y-2">
                        @foreach ([
                            ['label'=>'Small (<53g)','pct'=>$eggQuality['small'],'color'=>'bg-blue-600'],
                            ['label'=>'Medium (53-63g)','pct'=>$eggQuality['medium'],'color'=>'bg-emerald-500'],
                            ['label'=>'Large (63-73g)','pct'=>$eggQuality['large'],'color'=>'bg-emerald-700'],
                            ['label'=>'XL (>73g)','pct'=>$eggQuality['xl'],'color'=>'bg-emerald-900'],
                        ] as $sz)
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full {{ $sz['color'] }}"></div>
                                    <span class="text-gray-600">{{ $sz['label'] }}</span>
                                </div>
                                <span class="font-bold text-gray-900">{{ $sz['pct'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>

        {{-- ═══ PRODUCTION LOG TABLE ═══ --}}
        @endif

        <div class="bg-white border border-gray-100 rounded-xl p-4 sm:p-5 shadow-sm">
            <div class="mb-4 flex items-center gap-2">
                <h3 class="text-base font-semibold text-gray-800">Log Produksi 7 Hari</h3>
                <x-metric-hint title="Log Produksi 7 Hari" body="Tabel ini menampilkan produksi tujuh hari terakhir. Reject khusus telur rusak/reject, sedangkan mortalitas menghitung ayam mati dari laporan kematian." source="laporan, panen, panenRincianGrade, harianTernak, kematian" />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            @foreach (['Tanggal','Telur','Reject','Pakan (kg)','Mortalitas','HDP'] as $th)
                                <th class="text-{{ $loop->first ? 'left' : 'right' }} py-2.5 px-3 text-xs font-medium text-gray-400 uppercase tracking-wider">{{ $th }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($productionLog as $log)
                            @php
                                $mortalityCount = (float) ($log['mortalityCount'] ?? (is_numeric($log['mortality']) ? $log['mortality'] : 0));
                            @endphp
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="py-2.5 px-3 font-medium text-gray-900">{{ $log['date'] }}</td>
                                <td class="py-2.5 px-3 text-right text-gray-700">{{ $log['eggs'] }}</td>
                                <td class="py-2.5 px-3 text-right text-gray-500">{{ $log['rejects'] }}</td>
                                <td class="py-2.5 px-3 text-right text-gray-500">{{ is_numeric($log['feedKg']) ? number_format($log['feedKg']) : $log['feedKg'] }}</td>
                                <td class="py-2.5 px-3 text-right">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $mortalityCount > 2 ? 'text-red-600 bg-red-50' : 'text-emerald-600 bg-emerald-50' }}">{{ $log['mortality'] }}</span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-bold text-gray-900">{{ $log['hdp'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══ SPK MESSAGES + ACTIVITY LOG ═══ --}}
        @php
            $spkItems = collect($spkMessages);
            $spkIssue = $spkItems->first(fn ($item) => ($item['status'] ?? 'normal') !== 'normal') ?? $spkItems->first();
            $spkDangerCount = $spkItems->where('status', 'danger')->count();
            $spkWarningCount = $spkItems->where('status', 'warning')->count();
            $spkStatus = $spkDangerCount > 0 ? 'danger' : ($spkWarningCount > 0 ? 'warning' : 'normal');
            $spkBadge = [
                'normal' => ['label' => 'Aman', 'cls' => 'bg-emerald-50 text-emerald-700'],
                'warning' => ['label' => 'Perlu cek', 'cls' => 'bg-amber-50 text-amber-700'],
                'danger' => ['label' => 'Kritis', 'cls' => 'bg-red-50 text-red-700'],
            ][$spkStatus];
            $spkLink = route('spk.dashboard', ['komoditas' => 'petelur', 'coop_id' => $barn['id']]);
            $taskLink = route('spk.tasks.index', ['coop_id' => $barn['id']]);
        @endphp
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white border border-gray-100 rounded-xl p-4 sm:p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800">Analisis SPK</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Ringkasan cepat untuk {{ $barn['name'] }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-metric-hint title="Analisis SPK Kandang" body="Ringkasan ini mengambil hasil SPK terbaru untuk kandang terpilih dan menampilkan mode analisis yang relevan." source="spk_fuzzy_logs" />
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $spkBadge['cls'] }}">{{ $spkBadge['label'] }}</span>
                    </div>
                </div>
                <p class="mt-4 text-sm text-gray-600 leading-relaxed">
                    {{ $spkIssue['message'] ?? 'Belum ada ringkasan SPK untuk kandang ini.' }}
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($spkItems->take(3) as $msg)
                        @php
                            $chipCls = [
                                'normal' => 'bg-gray-50 text-gray-600 border-gray-100',
                                'warning' => 'bg-amber-50 text-amber-700 border-amber-100',
                                'danger' => 'bg-red-50 text-red-700 border-red-100',
                            ][$msg['status'] ?? 'normal'] ?? 'bg-gray-50 text-gray-600 border-gray-100';
                        @endphp
                        <span class="rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $chipCls }}">{{ $msg['mode'] }}</span>
                    @endforeach
                </div>
                <a href="{{ $spkLink }}" class="mt-5 inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 transition no-underline sm:w-auto sm:px-4 sm:text-sm">
                    Buka Analisa SPK Kandang Ini
                </a>
            </div>

            <div class="bg-white border border-gray-100 rounded-xl p-4 sm:p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800">Aktivitas Petugas</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Laporan terbaru dari kandang ini</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-metric-hint title="Aktivitas Petugas" body="Aktivitas ini merangkum laporan atau tindakan terbaru yang berkaitan dengan kandang." source="laporan, spk_action_tasks, spk_action_reports" />
                        <a href="{{ $taskLink }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-50 px-2.5 py-1.5 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-100 no-underline sm:px-3 sm:text-xs">Penugasan</a>
                    </div>
                </div>
                <div class="space-y-2">
                    @foreach (array_slice($activityLog, 0, 4) as $act)
                        @php
                            $dotColor = ['success'=>'bg-emerald-500','info'=>'bg-sky-500','warning'=>'bg-amber-500'][$act['type']] ?? 'bg-gray-400';
                        @endphp
                        <div class="flex items-start gap-3 rounded-lg border border-gray-100 px-3 py-2.5">
                            <span class="mt-1.5 h-2 w-2 rounded-full {{ $dotColor }} shrink-0"></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $act['title'] }}</p>
                                    <span class="text-[10px] text-gray-400 shrink-0">{{ $act['time'] }}</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ $act['desc'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if(false)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- SPK Messages --}}
            <div class="bg-white border border-gray-100 rounded-xl p-4 sm:p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    <h3 class="text-base font-semibold text-gray-800">Analisis SPK</h3>
                </div>
                <div class="space-y-3">
                    @foreach ($spkMessages as $msg)
                        @php
                            $msgCfg = [
                                'normal'  => ['icon'=>'✅','border'=>'border-l-emerald-500','bg'=>'bg-emerald-50/50'],
                                'warning' => ['icon'=>'⚠️','border'=>'border-l-amber-500','bg'=>'bg-amber-50/50'],
                                'danger'  => ['icon'=>'🚨','border'=>'border-l-red-500','bg'=>'bg-red-50/50'],
                            ][$msg['status']] ?? ['icon'=>'ℹ️','border'=>'border-l-gray-300','bg'=>''];
                        @endphp
                        <div class="border-l-4 {{ $msgCfg['border'] }} {{ $msgCfg['bg'] }} rounded-r-lg pl-3 pr-4 py-2.5">
                            <p class="text-xs font-semibold text-gray-700 mb-0.5">{{ $msgCfg['icon'] }} {{ $msg['mode'] }}</p>
                            <p class="text-xs text-gray-600 leading-relaxed">{{ $msg['message'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Activity Log --}}
            <div class="bg-white border border-gray-100 rounded-xl p-4 sm:p-5 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Aktivitas Petugas Hari Ini</h3>
                <div class="relative pl-4 border-l-2 border-gray-200 space-y-5">
                    @foreach ($activityLog as $act)
                        @php
                            $dotColor = ['success'=>'bg-emerald-500','info'=>'bg-blue-400','warning'=>'bg-amber-500'][$act['type']] ?? 'bg-gray-400';
                        @endphp
                        <div class="relative">
                            <div class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full border-2 border-white {{ $dotColor }}"></div>
                            <div class="flex items-center justify-between gap-2 mb-0.5">
                                <h4 class="text-sm font-medium text-gray-900">{{ $act['title'] }}</h4>
                                <span class="text-[10px] text-gray-400 shrink-0">{{ $act['time'] }}</span>
                            </div>
                            <p class="text-xs text-gray-500">{{ $act['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @endif

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush
