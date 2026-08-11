@extends('layouts.app')

@section('title', 'Settlement Produktivitas - '.$barn['name'])
@section('breadcrumb', 'Peternakan > '.$barn['name'].' > Settlement')

@push('styles')
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm;
        }

        @media print {
            html,
            body {
                height: auto !important;
                overflow: visible !important;
                background: #ffffff !important;
            }

            body > div,
            body > div > div {
                display: block !important;
                width: 100% !important;
                height: auto !important;
                min-height: auto !important;
                overflow: visible !important;
            }

            #sidebar,
            #sidebarOverlay,
            body > div > div > header,
            .no-print {
                display: none !important;
            }

            main {
                display: block !important;
                width: 100% !important;
                overflow: visible !important;
                padding: 0 !important;
                background: #ffffff !important;
            }

            .print-report {
                border: 0 !important;
                box-shadow: none !important;
                padding: 0 !important;
            }

            .print-table th,
            .print-table td {
                padding: 6px 7px !important;
                font-size: 10px !important;
            }

            .print-break-inside-avoid {
                break-inside: avoid;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $summary = $report['summary'] ?? [];
        $rows = $report['rows'] ?? [];
        $reportBarn = $report['barn'] ?? [];
        $individualReport = $individualReport ?? null;
        $afkirLabel = data_get($individualReport, 'afkir_config.label', 'Afkir');
        $afkirWarningWeeks = (int) data_get($individualReport, 'afkir_config.warning_weeks', 8);
        $settlementFilters = $settlementFilters ?? ['performance' => 'all', 'afkir' => 'all'];
        $performanceOptions = [
            'all' => 'Semua performa',
            'warning' => 'Perlu cek',
            'attention' => 'Pantau',
            'normal' => 'Normal',
            'low_hdp' => 'HDP < 70%',
            'high_hdp' => 'HDP >= 85%',
        ];
        $afkirOptions = [
            'all' => 'Semua '.$afkirLabel,
            'normal' => $afkirLabel.' masih aman',
            'due_soon' => $afkirLabel.' <= '.$afkirWarningWeeks.' minggu',
            'overdue' => 'Lewat target '.$afkirLabel,
        ];
        $dateLabel = \Carbon\Carbon::parse($startDate)->locale('id')->translatedFormat('d M Y')
            .' - '.\Carbon\Carbon::parse($endDate)->locale('id')->translatedFormat('d M Y');
        $printFilename = 'Settlement Produktivitas '.$barn['name'].' '.$startDate.' sd '.$endDate;
        $metricCards = [
            ['label' => 'Total telur', 'value' => number_format((float) ($summary['total_eggs'] ?? 0), 0, ',', '.'), 'sub' => 'butir'],
            ['label' => 'Egg mass', 'value' => number_format((float) ($summary['total_egg_mass_kg'] ?? 0), 2, ',', '.'), 'sub' => 'kg'],
            ['label' => 'Total pakan', 'value' => number_format((float) ($summary['total_feed_kg'] ?? 0), 2, ',', '.'), 'sub' => 'kg'],
            ['label' => 'Rata-rata HDP', 'value' => number_format((float) ($summary['avg_hdp'] ?? 0), 1, ',', '.').'%', 'sub' => 'periode'],
            ['label' => 'Rata-rata FCR', 'value' => number_format((float) ($summary['avg_fcr'] ?? 0), 2, ',', '.'), 'sub' => 'pakan / egg mass'],
            ['label' => 'Mortalitas', 'value' => number_format((float) ($summary['total_mortality'] ?? 0), 0, ',', '.'), 'sub' => 'ekor'],
        ];
    @endphp

    <div class="space-y-5">
        <div class="no-print flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Settlement Produktivitas</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $barn['name'] }} - {{ $dateLabel }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('peternakan.show', array_filter(['id' => $barn['id'], 'jenis_ternak' => $activeJenisTernakId ?? null, 'komoditas' => $activeKomoditasId])) }}"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 no-underline transition hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    Detail Kandang
                </a>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                    onclick="document.title = @js($printFilename); window.print();"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                    Print / Simpan PDF
                </button>
                <a href="{{ route('peternakan.settlement', array_filter(['id' => $barn['id'], 'jenis_ternak' => $activeJenisTernakId ?? null, 'komoditas' => $activeKomoditasId, 'start_date' => $startDate, 'end_date' => $endDate, 'performance' => $settlementFilters['performance'] ?? 'all', 'afkir' => $settlementFilters['afkir'] ?? 'all', 'format' => 'csv'])) }}"
                    class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 no-underline transition hover:bg-emerald-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 19h16"/></svg>
                    Export Excel
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('peternakan.settlement', ['id' => $barn['id']]) }}"
            class="no-print grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_auto]">
            @if($activeKomoditasId)
                <input type="hidden" name="komoditas" value="{{ $activeKomoditasId }}">
            @endif
            @if($activeJenisTernakId ?? null)
                <input type="hidden" name="jenis_ternak" value="{{ $activeJenisTernakId }}">
            @endif
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal mulai</span>
                <input type="date" name="start_date" value="{{ $startDate }}"
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-400 focus:bg-white">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Tanggal akhir</span>
                <input type="date" name="end_date" value="{{ $endDate }}"
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-400 focus:bg-white">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Performa</span>
                <select name="performance"
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-400 focus:bg-white">
                    @foreach($performanceOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($settlementFilters['performance'] ?? 'all') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $afkirLabel }}</span>
                <select name="afkir"
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-emerald-400 focus:bg-white">
                    @foreach($afkirOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($settlementFilters['afkir'] ?? 'all') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end">
                <button type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 md:w-auto">
                    Tampilkan
                </button>
            </div>
        </form>

        <section class="print-report rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="print-break-inside-avoid flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                <div class="flex items-start gap-3">
                    <x-brand-logo :show-text="false" class="shrink-0" logo-class="h-12 w-12" />
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-600">SPK Smart Farm</p>
                        <h2 class="mt-1 text-2xl font-black text-slate-950">Settlement Produktivitas Kandang</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $dateLabel }}</p>
                    </div>
                </div>
                <div class="text-right text-sm text-slate-500">
                    <p class="font-bold text-slate-900">{{ $reportBarn['name'] ?? $barn['name'] }}</p>
                    <p>{{ $reportBarn['location'] ?? '-' }}</p>
                    <p>Dibuat: {{ ($report['generated_at'] ?? now())->locale('id')->translatedFormat('d M Y, H:i') }}</p>
                </div>
            </div>

            <div class="print-break-inside-avoid mt-4 grid gap-3 md:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold text-slate-400">Breed</p>
                    <p class="mt-1 text-sm font-bold text-slate-900">{{ $reportBarn['breed'] ?? '-' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold text-slate-400">Populasi saat ini</p>
                    <p class="mt-1 text-sm font-bold text-slate-900">{{ number_format((float) ($reportBarn['current_population'] ?? 0), 0, ',', '.') }} ekor</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold text-slate-400">Tanggal masuk</p>
                    <p class="mt-1 text-sm font-bold text-slate-900">{{ $reportBarn['start_date'] ?? '-' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold text-slate-400">Umur flock</p>
                    <p class="mt-1 text-sm font-bold text-slate-900">{{ $reportBarn['flock_age'] ?? '-' }}</p>
                </div>
            </div>

            <div class="print-break-inside-avoid mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                @foreach($metricCards as $metric)
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50/50 p-3">
                        <p class="text-xs font-semibold text-emerald-700">{{ $metric['label'] }}</p>
                        <p class="mt-1 text-lg font-black text-slate-950">{{ $metric['value'] }}</p>
                        <p class="text-xs text-slate-500">{{ $metric['sub'] }}</p>
                    </div>
                @endforeach
            </div>

            @if(!empty($report['warnings']))
                <div class="print-break-inside-avoid mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <p class="font-bold">Catatan data</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5">
                        @foreach($report['warnings'] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-5 overflow-x-auto">
                <table class="print-table w-full min-w-[980px] border-collapse text-left text-sm">
                    <thead>
                        <tr class="bg-slate-100 text-xs uppercase tracking-wide text-slate-500">
                            <th class="border border-slate-200 px-3 py-2">Tanggal</th>
                            <th class="border border-slate-200 px-3 py-2 text-right">Telur</th>
                            <th class="border border-slate-200 px-3 py-2 text-right">Egg kg</th>
                            <th class="border border-slate-200 px-3 py-2 text-right">Pakan kg</th>
                            <th class="border border-slate-200 px-3 py-2 text-right">FI g/ekor</th>
                            <th class="border border-slate-200 px-3 py-2 text-right">Mati</th>
                            <th class="border border-slate-200 px-3 py-2 text-right">HDP %</th>
                            <th class="border border-slate-200 px-3 py-2 text-right">HHEP %</th>
                            <th class="border border-slate-200 px-3 py-2 text-right">FCR</th>
                            <th class="border border-slate-200 px-3 py-2">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="align-top">
                                <td class="border border-slate-200 px-3 py-2 font-semibold text-slate-800">{{ $row['date_label'] }}</td>
                                <td class="border border-slate-200 px-3 py-2 text-right">{{ number_format((float) $row['eggs'], 0, ',', '.') }}</td>
                                <td class="border border-slate-200 px-3 py-2 text-right">{{ number_format((float) $row['egg_mass_kg'], 2, ',', '.') }}</td>
                                <td class="border border-slate-200 px-3 py-2 text-right">{{ number_format((float) $row['feed_kg'], 2, ',', '.') }}</td>
                                <td class="border border-slate-200 px-3 py-2 text-right">{{ number_format((float) $row['feed_intake'], 1, ',', '.') }}</td>
                                <td class="border border-slate-200 px-3 py-2 text-right">{{ number_format((float) $row['mortality'], 0, ',', '.') }}</td>
                                <td class="border border-slate-200 px-3 py-2 text-right">{{ number_format((float) $row['hdp'], 1, ',', '.') }}</td>
                                <td class="border border-slate-200 px-3 py-2 text-right">{{ number_format((float) $row['hhep'], 1, ',', '.') }}</td>
                                <td class="border border-slate-200 px-3 py-2 text-right">{{ number_format((float) $row['fcr'], 2, ',', '.') }}</td>
                                <td class="border border-slate-200 px-3 py-2 text-slate-600">{{ $row['note'] ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="border border-slate-200 px-3 py-8 text-center text-slate-500">
                                    Tidak ada data produktivitas pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($individualReport !== null)
                <div class="print-break-inside-avoid mt-6 border-t border-slate-200 pt-5">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Performa Individu Ternak</h3>
                            <p class="text-xs text-slate-500">Ditampilkan karena kandang bertipe individu. Data membaca detailPanen per ayam.</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                            {{ count($individualReport['rows'] ?? []) }} dari {{ $individualReport['total_before_filter'] ?? count($individualReport['rows'] ?? []) }} individu
                        </span>
                    </div>

                    @if($individualReport['error'] ?? null)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            {{ $individualReport['error'] }}
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="print-table w-full min-w-[1180px] border-collapse text-left text-sm">
                                <thead>
                                    <tr class="bg-slate-100 text-xs uppercase tracking-wide text-slate-500">
                                        <th class="border border-slate-200 px-3 py-2">Individu</th>
                                        <th class="border border-slate-200 px-3 py-2">Batch / Umur</th>
                                        <th class="border border-slate-200 px-3 py-2">Target {{ $afkirLabel }}</th>
                                        <th class="border border-slate-200 px-3 py-2 text-right">HDP Periode</th>
                                        <th class="border border-slate-200 px-3 py-2 text-right">HDP Pembanding</th>
                                        <th class="border border-slate-200 px-3 py-2 text-right">Tidak Bertelur</th>
                                        <th class="border border-slate-200 px-3 py-2 text-right">Turun</th>
                                        <th class="border border-slate-200 px-3 py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($individualReport['rows'] ?? [] as $row)
                                        @php
                                            $afkirStatus = data_get($row, 'lifecycle.afkirStatus', 'normal');
                                            $afkirClass = match ($afkirStatus) {
                                                'overdue' => 'bg-red-50 text-red-700',
                                                'due_soon' => 'bg-amber-50 text-amber-700',
                                                default => 'bg-emerald-50 text-emerald-700',
                                            };
                                        @endphp
                                        <tr>
                                            <td class="border border-slate-200 px-3 py-2">
                                                <p class="font-bold text-slate-800">{{ data_get($row, 'namaId') }}</p>
                                                <p class="text-[10px] text-slate-400">{{ data_get($row, 'id') }}</p>
                                            </td>
                                            <td class="border border-slate-200 px-3 py-2">
                                                <p class="font-semibold text-slate-800">{{ data_get($row, 'lifecycle.batchCode', '-') }}</p>
                                                <p class="text-[10px] text-slate-500">{{ data_get($row, 'lifecycle.ageLabel', '-') }} - {{ data_get($row, 'lifecycle.phase', '-') }}</p>
                                                <p class="text-[10px] text-slate-400">Masuk: {{ data_get($row, 'lifecycle.entryDateLabel', data_get($row, 'lifecycle.entryDate', '-')) ?: '-' }}</p>
                                            </td>
                                            <td class="border border-slate-200 px-3 py-2">
                                                <p class="font-semibold text-slate-800">{{ data_get($row, 'lifecycle.targetAfkirLabel', data_get($row, 'lifecycle.targetAfkirDate', '-')) ?: '-' }}</p>
                                                <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold {{ $afkirClass }}">{{ data_get($row, 'lifecycle.afkirStatusLabel', '-') }}</span>
                                            </td>
                                            <td class="border border-slate-200 px-3 py-2 text-right">{{ number_format((float) data_get($row, 'current.layingPercent', 0), 1, ',', '.') }}%</td>
                                            <td class="border border-slate-200 px-3 py-2 text-right">{{ number_format((float) data_get($row, 'previous.layingPercent', 0), 1, ',', '.') }}%</td>
                                            <td class="border border-slate-200 px-3 py-2 text-right">{{ data_get($row, 'current.nonLayingDays', 0) }} hari</td>
                                            <td class="border border-slate-200 px-3 py-2 text-right">{{ number_format((float) data_get($row, 'dropPercent', 0), 1, ',', '.') }}%</td>
                                            <td class="border border-slate-200 px-3 py-2">{{ data_get($row, 'status') === 'warning' ? 'Perlu cek' : (data_get($row, 'status') === 'attention' ? 'Pantau' : 'Normal') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="border border-slate-200 px-3 py-8 text-center text-slate-500">
                                                Tidak ada data individu pada periode ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </section>
    </div>
@endsection
