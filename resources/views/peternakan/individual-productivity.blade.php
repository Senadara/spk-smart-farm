@extends('layouts.app')

@section('title', 'HDP Individu - '.$barn['name'])
@section('breadcrumb', 'Peternakan > '.$barn['name'].' > HDP Individu')

@section('content')
    @php
        $currentPeriod = data_get($productivity, 'currentPeriod', []);
        $previousPeriod = data_get($productivity, 'previousPeriod', []);
        $isReady = (bool) data_get($productivity, 'isIndividualHarvestReady', false);
        $isIndication = (bool) data_get($productivity, 'isIndication', false);
        $activeCount = (int) data_get($productivity, 'activeChickenCount', 0);
        $indicationCount = (int) data_get($productivity, 'indicationChickenCount', 0);
        $indicationPercent = (float) data_get($productivity, 'indicationChickenPercent', 0);
        $avgCurrent = (float) data_get($productivity, 'averageCurrentLayingPercent', 0);
        $avgPrevious = (float) data_get($productivity, 'averagePreviousLayingPercent', 0);
        $avgDrop = (float) data_get($productivity, 'averageDropPercent', 0);
        $canCreateHealthIndication = in_array(data_get(session('user'), 'role'), ['pjawab', 'owner', 'admin'], true);
        $afkirLabel = data_get($afkirConfig ?? [], 'label', 'Afkir');
        $sortLabels = [
            'drop' => 'Penurunan terbesar',
            'drop_points' => 'Selisih persen terbesar',
            'current' => 'HDP sekarang',
            'previous' => 'HDP sebelumnya',
            'non_laying' => 'Hari tidak bertelur',
            'name' => 'Nama ayam',
        ];
    @endphp

    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('peternakan.show', array_filter(['id' => $barn['id'], 'jenis_ternak' => $activeJenisTernakId ?? null, 'komoditas' => $activeKomoditasId])) }}" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 no-underline">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        Detail Kandang
                    </a>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $isIndication ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                        {{ $isIndication ? 'Ada indikasi' : 'Terkendali' }}
                    </span>
                </div>
                <h1 class="mt-3 text-xl font-black text-slate-950">Tabel HDP Individu Ayam</h1>
                <p class="mt-1 max-w-3xl text-sm leading-relaxed text-slate-500">
                    Tabel ini membandingkan persentase hari bertelur setiap ayam pada periode sekarang dengan periode sebelumnya. Ayam ditandai jika HDP individunya turun minimal {{ number_format((float) $filters['threshold'], 0, ',', '.') }}%.
                </p>
            </div>

            @if($canCreateHealthIndication && $isIndication)
                <form method="POST" action="{{ route('peternakan.health-indication.store', $barn['id']) }}" class="shrink-0">
                    @csrf
                    <input type="hidden" name="analysis_mode" value="individual_productivity_drop">
                    <input type="hidden" name="days" value="{{ $filters['days'] }}">
                    <input type="hidden" name="threshold" value="{{ $filters['threshold'] }}">
                    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                    <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-amber-600 sm:w-auto">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
                        Kirim Indikasi Pemeriksaan
                    </button>
                </form>
            @endif
        </div>

        @if(session('health_indication_success') || session('health_indication_warning') || session('health_indication_error'))
            <div class="rounded-xl border px-4 py-3 text-sm font-semibold {{
                session('health_indication_success') ? 'border-emerald-100 bg-emerald-50 text-emerald-700' :
                (session('health_indication_warning') ? 'border-amber-100 bg-amber-50 text-amber-700' : 'border-red-100 bg-red-50 text-red-700')
            }}">
                {{ session('health_indication_success') ?? session('health_indication_warning') ?? session('health_indication_error') }}
            </div>
        @endif

        @if($error)
            <div class="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $error }}
            </div>
        @elseif(! $isReady)
            <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                Kandang ini belum bertipe individu. Analisis per ayam membutuhkan data panen individu dari mobile pada field <span class="font-mono font-semibold">detailPanen</span>.
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            @foreach([
                ['label' => 'Ayam aktif', 'value' => number_format($activeCount, 0, ',', '.'), 'tone' => 'slate'],
                ['label' => 'Ayam indikasi', 'value' => number_format($indicationCount, 0, ',', '.'), 'sub' => number_format($indicationPercent, 1, ',', '.').'%', 'tone' => $indicationCount > 0 ? 'amber' : 'emerald'],
                ['label' => 'HDP sekarang', 'value' => number_format($avgCurrent, 1, ',', '.').'%', 'tone' => 'sky'],
                ['label' => 'HDP sebelumnya', 'value' => number_format($avgPrevious, 1, ',', '.').'%', 'tone' => 'slate'],
                ['label' => 'Rata-rata turun', 'value' => number_format($avgDrop, 1, ',', '.').'%', 'tone' => $avgDrop >= (float) $filters['threshold'] ? 'amber' : 'slate'],
            ] as $metric)
                @php
                    $toneClass = [
                        'emerald' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
                        'amber' => 'border-amber-100 bg-amber-50 text-amber-700',
                        'sky' => 'border-sky-100 bg-sky-50 text-sky-700',
                        'slate' => 'border-slate-100 bg-white text-slate-800',
                    ][$metric['tone']];
                @endphp
                <div class="rounded-xl border p-4 shadow-sm {{ $toneClass }}">
                    <p class="text-[11px] font-bold uppercase tracking-wide opacity-70">{{ $metric['label'] }}</p>
                    <p class="mt-1 text-2xl font-black">{{ $metric['value'] }}</p>
                    @if(!empty($metric['sub']))
                        <p class="mt-0.5 text-xs font-semibold opacity-70">{{ $metric['sub'] }} dari populasi</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('peternakan.individual-productivity', ['id' => $barn['id']]) }}" class="grid gap-3 md:grid-cols-6 md:items-end">
                @if($activeKomoditasId)
                    <input type="hidden" name="komoditas" value="{{ $activeKomoditasId }}">
                @endif
                @if($activeJenisTernakId ?? null)
                    <input type="hidden" name="jenis_ternak" value="{{ $activeJenisTernakId }}">
                @endif

                <label class="block">
                    <span class="text-xs font-bold text-slate-500">Periode</span>
                    <select name="days" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        @foreach([7 => '7 hari', 14 => '14 hari', 30 => '30 hari', 90 => '3 bulan', 180 => '6 bulan', 365 => '1 tahun'] as $day => $label)
                            <option value="{{ $day }}" @selected((int) $filters['days'] === $day)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold text-slate-500">Ambang turun</span>
                    <input type="number" min="1" max="100" step="1" name="threshold" value="{{ $filters['threshold'] }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </label>

                <label class="block">
                    <span class="text-xs font-bold text-slate-500">Urutkan</span>
                    <select name="sort" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        @foreach($sortLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold text-slate-500">Arah</span>
                    <select name="direction" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="desc" @selected($filters['direction'] === 'desc')>Terbesar dulu</option>
                        <option value="asc" @selected($filters['direction'] === 'asc')>Terkecil dulu</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold text-slate-500">Tampilan</span>
                    <select name="filter" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="all" @selected($filters['filter'] === 'all')>Semua ayam</option>
                        <option value="indication" @selected($filters['filter'] === 'indication')>Indikasi saja</option>
                    </select>
                </label>

                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white transition hover:bg-slate-700">
                    Terapkan
                </button>
            </form>

            <div class="mt-4 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-xs leading-relaxed text-slate-500">
                Periode sekarang: {{ data_get($currentPeriod, 'start', '-') }} sampai {{ data_get($currentPeriod, 'end', '-') }}. Periode pembanding: {{ data_get($previousPeriod, 'start', '-') }} sampai {{ data_get($previousPeriod, 'end', '-') }}.
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm">
            <div class="flex flex-col gap-1 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-black text-slate-900">Tabel HDP Individu</h2>
                    <p class="text-xs text-slate-500">HDP individu dihitung dari jumlah hari ayam muncul di detail panen.</p>
                </div>
                <span class="text-xs font-semibold text-slate-400">{{ count($rows) }} baris ditampilkan</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1120px] text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-[11px] font-bold uppercase tracking-wide text-slate-400">
                            <th class="px-4 py-3">Ayam</th>
                            <th class="px-4 py-3">Batch / Umur</th>
                            <th class="px-4 py-3">Target {{ $afkirLabel }}</th>
                            <th class="px-4 py-3 text-right">HDP Sekarang</th>
                            <th class="px-4 py-3 text-right">HDP Sebelumnya</th>
                            <th class="px-4 py-3 text-right">Hari Tidak Bertelur</th>
                            <th class="px-4 py-3 text-right">Penurunan</th>
                            <th class="px-4 py-3 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse($rows as $row)
                            @php
                                $status = data_get($row, 'status');
                                $statusClass = match ($status) {
                                    'warning' => 'bg-amber-50 text-amber-700',
                                    'attention' => 'bg-sky-50 text-sky-700',
                                    default => 'bg-emerald-50 text-emerald-700',
                                };
                                $statusLabel = match ($status) {
                                    'warning' => 'Perlu cek',
                                    'attention' => 'Pantau',
                                    default => 'Normal',
                                };
                                $afkirStatus = data_get($row, 'lifecycle.afkirStatus', 'normal');
                                $afkirClass = match ($afkirStatus) {
                                    'overdue' => 'bg-red-50 text-red-700',
                                    'due_soon' => 'bg-amber-50 text-amber-700',
                                    default => 'bg-emerald-50 text-emerald-700',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-4 py-3">
                                    <p class="font-bold text-slate-900">{{ data_get($row, 'namaId') }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-400 font-mono">{{ data_get($row, 'id') }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-bold text-slate-800">{{ data_get($row, 'lifecycle.batchCode', '-') }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">{{ data_get($row, 'lifecycle.ageLabel', '-') }} - {{ data_get($row, 'lifecycle.phase', '-') }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-400">Masuk: {{ data_get($row, 'lifecycle.entryDateLabel', data_get($row, 'lifecycle.entryDate', '-')) ?: '-' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-800">{{ data_get($row, 'lifecycle.targetAfkirLabel', data_get($row, 'lifecycle.targetAfkirDate', '-')) ?: '-' }}</p>
                                    <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold {{ $afkirClass }}">{{ data_get($row, 'lifecycle.afkirStatusLabel', '-') }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <p class="font-black text-slate-900">{{ number_format((float) data_get($row, 'current.layingPercent', 0), 1, ',', '.') }}%</p>
                                    <p class="text-[11px] text-slate-400">{{ data_get($row, 'current.layingDays', 0) }}/{{ $filters['days'] }} hari</p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <p class="font-black text-slate-900">{{ number_format((float) data_get($row, 'previous.layingPercent', 0), 1, ',', '.') }}%</p>
                                    <p class="text-[11px] text-slate-400">{{ data_get($row, 'previous.layingDays', 0) }}/{{ $filters['days'] }} hari</p>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-600">
                                    {{ data_get($row, 'current.nonLayingDays', 0) }} hari
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <p class="font-black {{ data_get($row, 'isDropIndication') ? 'text-amber-600' : 'text-slate-700' }}">
                                        {{ number_format((float) data_get($row, 'dropPercent', 0), 1, ',', '.') }}%
                                    </p>
                                    <p class="text-[11px] text-slate-400">{{ number_format((float) data_get($row, 'dropPoints', 0), 1, ',', '.') }} poin</p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Tidak ada data sesuai filter saat ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
