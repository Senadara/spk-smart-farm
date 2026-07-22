@extends('layouts.app')

@section('title', 'Konfigurasi Fuzzy Mamdani')
@section('breadcrumb', 'Pengaturan / Fuzzy Mamdani')

@section('content')
@php
    $variablesByGroup = $variables->groupBy('group');
    $rulesByGroup = $rules->groupBy('group');
    $inputVariables = $variables->where('type', 'input')->where('group', '!=', 'kausalitas')->values();
    $configuredSourceIds = $inputSources->pluck('variable_id')->unique();
    $configuredInputCount = $inputVariables->whereIn('id', $configuredSourceIds)->count();
    $inputCount = $inputVariables->count();
    $sourceCompletion = $inputCount > 0 ? (int) round(($configuredInputCount / $inputCount) * 100) : 100;
    $unconfiguredInputCount = max($inputCount - $configuredInputCount, 0);
    $assignments = $templateAssignments ?? collect();
    $activeAssignmentCount = $assignments->whereNotNull('active_profile')->count();
    $activeTemplateAssignment = $assignments->firstWhere('jenis_budidaya_id', $activeJenisBudidayaId);
    $profilesForActiveJenis = $profiles->where('jenis_budidaya_id', $activeJenisBudidayaId)->values();
    $activeLivestockType = $livestockTypes->firstWhere('id', $activeJenisBudidayaId);
    $initialStageTab = in_array(request('tab'), ['variables', 'sources', 'rules'], true) ? request('tab') : 'variables';

    $engineMeta = [
        'lingkungan' => [
            'label' => 'Engine 1',
            'title' => 'Lingkungan',
            'hint' => 'Membaca kondisi kandang dari sensor.',
            'tone' => 'emerald',
        ],
        'kesehatan' => [
            'label' => 'Engine 2',
            'title' => 'Produktivitas',
            'hint' => 'Mengolah laporan harian, panen, pakan, dan mortalitas.',
            'tone' => 'sky',
        ],
        'kausalitas' => [
            'label' => 'Engine 3',
            'title' => 'Kausalitas',
            'hint' => 'Menggabungkan hasil engine 1 dan 2 menjadi diagnosis.',
            'tone' => 'gray',
        ],
    ];

    $stepCards = [
        [
            'number' => '1',
            'title' => 'Variabel',
            'body' => 'Input dari Data Master, output default.',
            'status' => $stats['totalVariables'] . ' variabel',
            'state' => $stats['totalVariables'] > 0 ? 'ok' : 'warn',
            'tone' => 'emerald',
        ],
        [
            'number' => '2',
            'title' => 'Sumber',
            'body' => 'Mapping IoT dan laporan sistem.',
            'status' => $configuredInputCount . '/' . $inputCount . ' sumber',
            'state' => $unconfiguredInputCount === 0 ? 'ok' : 'warn',
            'tone' => 'sky',
        ],
        [
            'number' => '3',
            'title' => 'Rule',
            'body' => 'IF-THEN pakar termasuk kausalitas.',
            'status' => $stats['totalRules'] . ' rule',
            'state' => $stats['totalRules'] > 0 ? 'ok' : 'warn',
            'tone' => 'gray',
        ],
    ];
@endphp

<div x-data="fuzzyConfig()" class="space-y-4">
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white" style="box-shadow: var(--shadow-sm);">
        <div class="h-1 bg-[var(--color-primary)]"></div>
        <div class="flex flex-wrap items-center justify-between gap-4 p-5">
        <div class="min-w-0">
            <div class="mb-1 flex items-center gap-2 text-sm">
                <a href="{{ route('settings.index') }}" class="text-gray-400 transition-colors hover:text-gray-600">Pengaturan</a>
                <span class="text-gray-300">/</span>
                <span class="font-medium text-gray-600">Fuzzy Mamdani</span>
            </div>
            <h1 class="text-2xl font-bold text-[var(--color-gray-900)]">Konfigurasi Fuzzy Mamdani</h1>
            <p class="mt-1 text-sm text-[var(--color-gray-500)]">Template mengikuti jenis ternak. Input utama ditarik dari Data Master, lalu dipetakan ke IoT atau laporan.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <form action="{{ route('settings.fuzzy.reset') }}" method="POST" onsubmit="return confirm('Semua konfigurasi fuzzy akan di-reset ke default. Lanjutkan?');">
                @csrf
                <button type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-amber-200 bg-white px-4 py-2.5 text-sm font-semibold text-amber-700 transition-colors hover:border-amber-300 hover:bg-amber-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reset
                </button>
            </form>
        </div>
        </div>
    </div>

    @if(session('success'))
        <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <div class="mb-1 flex items-center gap-2 font-semibold">
                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                Validasi gagal
            </div>
            <ul class="list-inside list-disc space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($activeProfile && !($masterConfigStatus['configured'] ?? false))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-bold">{{ $masterConfigStatus['title'] ?? 'Data Master belum lengkap' }}</h2>
                        <span class="rounded-full bg-white/80 px-2 py-0.5 text-[11px] font-bold text-amber-700">
                            {{ $masterConfigStatus['environment_count'] ?? 0 }} lingkungan / {{ $masterConfigStatus['function_count'] ?? 0 }} fungsi
                        </span>
                    </div>
                    <p class="mt-1 leading-6">{{ $masterConfigStatus['message'] ?? 'Lengkapi parameter lingkungan dan produktivitas Data Master sebelum mapping sumber data.' }}</p>
                </div>
                <a href="{{ $masterConfigStatus['data_master_url'] ?? route('data-master.index') }}"
                    class="inline-flex items-center justify-center rounded-lg bg-amber-600 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-700"
                    style="text-decoration:none;">
                    Buka Data Master
                </a>
            </div>
        </div>
    @endif

    @if(($syncSummary['variables_created'] ?? 0) > 0 || ($syncSummary['sets_created'] ?? 0) > 0 || ($syncSummary['sources_synced'] ?? 0) > 0)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            Konfigurasi template diselaraskan dari Data Master: {{ $syncSummary['variables_created'] ?? 0 }} variabel baru, {{ $syncSummary['sets_created'] ?? 0 }} set baru, {{ $syncSummary['sources_synced'] ?? 0 }} sumber data tersinkron.
        </div>
    @endif

    <div class="space-y-4">
        <section class="rounded-2xl border border-gray-100 bg-white p-4" style="box-shadow: var(--shadow-sm);">
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(260px,0.9fr)_minmax(0,1.4fr)] xl:items-start">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900">Jenis Ternak Aktif</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">
                        Pilih konteks jenis ternak terlebih dahulu. Variabel, sumber data, dan rule yang tampil mengikuti template aktif pada jenis ternak ini.
                    </p>
                    <form method="GET" action="{{ route('settings.fuzzy.index') }}" class="mt-3">
                        <input type="hidden" name="tab" value="{{ $initialStageTab }}">
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Pilih jenis ternak</label>
                        <select name="jenis_budidaya_id"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm transition-all focus:border-[var(--color-primary)] focus:outline-none"
                            onchange="this.form.submit()">
                            @forelse($livestockTypes as $type)
                                <option value="{{ $type->id }}" {{ $type->id === $activeJenisBudidayaId ? 'selected' : '' }}>
                                    {{ $type->nama }}
                                </option>
                            @empty
                                <option value="">Belum ada jenis ternak</option>
                            @endforelse
                        </select>
                    </form>
                </div>

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                        <div class="text-[11px] font-semibold uppercase text-gray-400">Jenis</div>
                        <div class="truncate text-sm font-semibold text-gray-800">{{ $activeLivestockType->nama ?? '-' }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                        <div class="text-[11px] font-semibold uppercase text-gray-400">Template Aktif</div>
                        <div class="truncate text-sm font-semibold text-gray-800">{{ $activeProfile?->name ?? 'Belum ada' }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                        <div class="text-[11px] font-semibold uppercase text-gray-400">Template Tersedia</div>
                        <div class="text-sm font-semibold text-gray-800">{{ $profilesForActiveJenis->count() }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                        <div class="text-[11px] font-semibold uppercase text-gray-400">Status</div>
                        @if($activeProfile)
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase
                                    {{ $activeProfile->status === 'active' ? 'bg-emerald-50 text-emerald-700' : ($activeProfile->status === 'review' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                                    {{ $activeProfile->status }}
                                </span>
                                @if($activeProfile->is_active)
                                    <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold uppercase text-white">Aktif</span>
                                @endif
                            </div>
                        @else
                            <div class="text-sm font-semibold text-amber-700">Belum aktif</div>
                        @endif
                    </div>

                    @if(! $activeProfile)
                        <div class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800 sm:col-span-2 xl:col-span-4">
                            Jenis ternak ini belum memiliki template aktif. Buat atau aktifkan template pada panel Manajemen Template di bawah.
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <aside class="rounded-2xl border border-emerald-100 bg-white p-4" style="box-shadow: var(--shadow-sm);">
            <div class="grid grid-cols-1 gap-3 xl:grid-cols-[220px_minmax(260px,1fr)_280px] xl:items-center">
                <div class="flex items-start justify-between gap-3 xl:block">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Kesiapan SPK</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Ringkasan template, mapping, dan rule aktif.</p>
                    </div>
                    <span class="rounded-full border px-2.5 py-1 text-xs font-bold {{ $unconfiguredInputCount === 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                        {{ $sourceCompletion }}%
                    </span>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between text-xs font-medium text-gray-500">
                        <span>Sumber data input</span>
                        <span>{{ $configuredInputCount }} dari {{ $inputCount }} terhubung</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full {{ $unconfiguredInputCount === 0 ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $sourceCompletion }}%"></div>
                    </div>
                    @if($unconfiguredInputCount > 0)
                        <p class="mt-2 text-xs leading-5 text-amber-700">{{ $unconfiguredInputCount }} input belum tersambung.</p>
                    @else
                        <p class="mt-2 text-xs leading-5 text-emerald-700">Semua input utama tersambung.</p>
                    @endif
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div class="rounded-lg border border-emerald-100 bg-white/80 px-2 py-2 text-center">
                        <div class="text-lg font-bold text-emerald-700">{{ $stats['totalVariables'] }}</div>
                        <div class="text-[11px] font-semibold text-gray-500">Variabel</div>
                    </div>
                    <div class="rounded-lg border border-sky-100 bg-white/80 px-2 py-2 text-center">
                        <div class="text-lg font-bold text-sky-700">{{ $stats['totalSources'] }}</div>
                        <div class="text-[11px] font-semibold text-gray-500">Mapping</div>
                    </div>
                    <div class="rounded-lg border border-amber-100 bg-white px-2 py-2 text-center">
                        <div class="text-lg font-bold text-amber-700">{{ $stats['totalRules'] }}</div>
                        <div class="text-[11px] font-semibold text-gray-500">Rule</div>
                    </div>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-3">
                @foreach($engineMeta as $groupKey => $meta)
                    @php
                        $engineToneClasses = [
                            'emerald' => 'border-emerald-100 bg-emerald-50 text-emerald-800',
                            'sky' => 'border-sky-100 bg-sky-50 text-sky-800',
                            'amber' => 'border-amber-100 bg-amber-50 text-amber-800',
                            'gray' => 'border-gray-200 bg-gray-50 text-gray-700',
                        ][$meta['tone']];
                    @endphp
                    <div class="rounded-lg border {{ $engineToneClasses }} px-3 py-2">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-[10px] font-black uppercase tracking-wide opacity-75">{{ $meta['label'] }}</div>
                                <div class="truncate text-xs font-bold">{{ $meta['title'] }}</div>
                            </div>
                            <div class="flex flex-shrink-0 items-center gap-1.5 text-[11px] font-bold">
                                <span class="rounded-md bg-white/75 px-1.5 py-0.5">{{ ($variablesByGroup[$groupKey] ?? collect())->count() }} var</span>
                                <span class="rounded-md bg-white/75 px-1.5 py-0.5">{{ ($rulesByGroup[$groupKey] ?? collect())->count() }} rule</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </aside>
    </div>

    <div>
        @include('settings.fuzzy-partials.templates')
    </div>

    <details class="rounded-2xl border border-gray-100 bg-white p-4" style="box-shadow: var(--shadow-sm);">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-gray-900">Alur konfigurasi</h2>
                <p class="text-xs text-gray-500">Buka saat perlu cek urutan kerja.</p>
            </div>
            <span class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm">Lihat alur</span>
        </summary>
        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
            @foreach($stepCards as $step)
                @php
                    $stepTone = [
                        'gray' => [
                            'card' => 'border-gray-200 bg-gray-50 hover:border-gray-300',
                            'number' => 'bg-gray-700 text-white shadow-sm',
                            'title' => 'text-gray-900',
                        ],
                        'emerald' => [
                            'card' => 'border-emerald-200 bg-emerald-50 hover:border-emerald-300',
                            'number' => 'bg-[var(--color-primary)] text-white shadow-sm',
                            'title' => 'text-emerald-900',
                        ],
                        'sky' => [
                            'card' => 'border-sky-200 bg-sky-50 hover:border-sky-300',
                            'number' => 'bg-sky-600 text-white shadow-sm',
                            'title' => 'text-sky-900',
                        ],
                        'amber' => [
                            'card' => 'border-amber-200 bg-amber-50 hover:border-amber-300',
                            'number' => 'bg-amber-500 text-white shadow-sm',
                            'title' => 'text-amber-900',
                        ],
                    ][$step['tone']];
                @endphp
                <div class="rounded-xl border {{ $stepTone['card'] }} p-3 transition-all hover:-translate-y-0.5 hover:shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg {{ $stepTone['number'] }} text-sm font-bold">
                            {{ $step['number'] }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-semibold {{ $stepTone['title'] }}">{{ $step['title'] }}</h3>
                                <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold {{ $step['state'] === 'ok' ? 'border-emerald-200 bg-white/80 text-emerald-700' : 'border-amber-200 bg-white/80 text-amber-700' }}">{{ $step['status'] }}</span>
                            </div>
                            <p class="mt-1 text-xs leading-5 text-gray-600">{{ $step['body'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </details>

    <div class="sticky top-0 z-20 rounded-2xl border border-gray-200 bg-white/95 p-2 backdrop-blur" style="box-shadow: var(--shadow-sm);">
        <div class="grid grid-cols-1 gap-2 md:grid-cols-3" role="tablist">
            <button type="button" @click="setTab('variables')"
                :aria-selected="tab === 'variables'"
                :class="tab === 'variables' ? 'border-emerald-500 bg-emerald-600 text-white shadow-md shadow-emerald-200 ring-2 ring-emerald-100' : 'border-gray-200 bg-white text-gray-700 hover:-translate-y-0.5 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 hover:shadow-md'"
                class="group cursor-pointer rounded-xl border px-4 py-3 text-left shadow-sm transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                role="tab">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-black transition-colors" :class="tab === 'variables' ? 'bg-white/20 text-white ring-1 ring-white/30' : 'bg-emerald-100 text-emerald-700 group-hover:bg-white'">1</span>
                    <div class="min-w-0">
                        <span class="block text-sm font-bold">Variabel & Set</span>
                        <span class="block text-xs opacity-80">{{ $stats['totalVariables'] }} variabel</span>
                    </div>
                    <svg class="ml-auto h-4 w-4 flex-shrink-0 transition-transform" :class="tab === 'variables' ? 'text-white' : 'text-emerald-500 group-hover:translate-x-0.5'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </div>
            </button>
            <button type="button" @click="setTab('sources')"
                :aria-selected="tab === 'sources'"
                :class="tab === 'sources' ? 'border-sky-500 bg-sky-600 text-white shadow-md shadow-sky-200 ring-2 ring-sky-100' : 'border-gray-200 bg-white text-gray-700 hover:-translate-y-0.5 hover:border-sky-300 hover:bg-sky-50 hover:text-sky-800 hover:shadow-md'"
                class="group cursor-pointer rounded-xl border px-4 py-3 text-left shadow-sm transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-sky-200"
                role="tab">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-black transition-colors" :class="tab === 'sources' ? 'bg-white/20 text-white ring-1 ring-white/30' : 'bg-sky-100 text-sky-700 group-hover:bg-white'">2</span>
                    <div class="min-w-0">
                        <span class="block text-sm font-bold">Sumber Data</span>
                        <span class="block text-xs opacity-80">{{ $stats['totalSources'] }} mapping</span>
                    </div>
                    <svg class="ml-auto h-4 w-4 flex-shrink-0 transition-transform" :class="tab === 'sources' ? 'text-white' : 'text-sky-500 group-hover:translate-x-0.5'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </div>
            </button>
            <button type="button" @click="setTab('rules')"
                :aria-selected="tab === 'rules'"
                :class="tab === 'rules' ? 'border-slate-300 bg-slate-100 text-slate-950 shadow-md shadow-slate-200 ring-2 ring-slate-100' : 'border-gray-200 bg-white text-gray-700 hover:-translate-y-0.5 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800 hover:shadow-md'"
                class="group cursor-pointer rounded-xl border px-4 py-3 text-left shadow-sm transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-slate-200"
                role="tab">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-black transition-colors" :class="tab === 'rules' ? 'bg-white text-slate-900 ring-1 ring-slate-200' : 'bg-slate-100 text-slate-700 group-hover:bg-white'">3</span>
                    <div class="min-w-0">
                        <span class="block text-sm font-bold">Rule IF-THEN</span>
                        <span class="block text-xs opacity-80">{{ $stats['totalRules'] }} rule</span>
                    </div>
                    <svg class="ml-auto h-4 w-4 flex-shrink-0 transition-transform" :class="tab === 'rules' ? 'text-slate-700' : 'text-slate-500 group-hover:translate-x-0.5'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </div>
            </button>
        </div>
    </div>

    <div x-show="tab === 'variables'" x-cloak>
        @include('settings.fuzzy-partials.variables')
    </div>

    <div x-show="tab === 'sources'" x-cloak>
        @include('settings.fuzzy-partials.sources')
    </div>

    <div x-show="tab === 'rules'" x-cloak>
        @include('settings.fuzzy-partials.rules')
    </div>

    @include('settings.fuzzy-partials.modals')
</div>

<script>
function fuzzyConfig() {
    return {
        tab: @json($initialStageTab),
        modal: null,
        editVar: {},
        editSet: {},
        editRule: {},
        editSource: {},
        sourceFormMode: 'create',
        ruleConditions: [{ variable_id: '', set_id: '' }],
        expandedVars: {},
        varFilterGroup: 'all',
        varFilterType: 'all',
        varSearch: '',
        ruleFilterGroup: 'all',
        ruleFilterOutput: 'all',
        ruleSearch: '',

        setTab(next) {
            this.tab = next;
            const url = new URL(window.location.href);
            url.searchParams.set('tab', next);
            window.history.replaceState({}, '', url);
        },

        toggleVar(id) {
            this.expandedVars[id] = !this.expandedVars[id];
        },

        expandAll() {
            document.querySelectorAll('[data-var-id]').forEach(el => {
                this.expandedVars[el.dataset.varId] = true;
            });
        },

        collapseAll() {
            this.expandedVars = {};
        },

        addCondition() {
            this.ruleConditions.push({ variable_id: '', set_id: '' });
        },

        removeCondition(idx) {
            if (this.ruleConditions.length > 1) {
                this.ruleConditions.splice(idx, 1);
            }
        },

        openEditVar(v) {
            this.editVar = { ...v };
            this.modal = 'editVariable';
        },

        openEditSet(s) {
            this.editSet = { ...s };
            this.modal = 'editSet';
        },

        openEditRule(r) {
            this.editRule = { ...r };
            this.ruleConditions = r.conditions.map(c => ({
                variable_id: c.variable_id,
                set_id: c.set_id,
            }));
            this.modal = 'editRule';
        },

        openAddSource() {
            this.sourceFormMode = 'create';
            this.editSource = {
                id: null,
                variable_id: '',
                source_type: 'iot',
                parameter_code: '',
                metric_code: '',
                function_name: '',
                source_name: '',
                field_name: '',
                aggregation: 'sum',
                date_scope: 'today',
                max_age_minutes: 30,
                offline_after_misses: 3,
            };
            this.modal = 'sourceForm';
        },

        openEditSource(source) {
            this.sourceFormMode = source.id ? 'edit' : 'create';
            this.editSource = {
                source_type: 'iot',
                aggregation: 'sum',
                date_scope: 'today',
                max_age_minutes: 30,
                offline_after_misses: 3,
                ...source,
            };
            this.modal = 'sourceForm';
        },

        dbFieldsForSource(sourceName) {
            const sources = @json($databaseSources);
            return sources[sourceName] || [];
        },

        getSetsForVariable(variableId) {
            const allSets = @json($allSets);
            return allSets[variableId] || [];
        },
    };
}
</script>
@endsection
