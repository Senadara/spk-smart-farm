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
            'tone' => 'violet',
        ],
    ];

    $stepCards = [
        [
            'number' => '1',
            'title' => 'Pilih Profil',
            'body' => 'Pilih konfigurasi sesuai komoditas. Aktifkan profil jika sudah siap dipakai.',
            'status' => $activeProfile ? 'Siap' : 'Kosong',
            'state' => $activeProfile ? 'ok' : 'warn',
        ],
        [
            'number' => '2',
            'title' => 'Atur Variabel',
            'body' => 'Buat input, output, dan membership function untuk tiap engine.',
            'status' => $stats['totalVariables'] . ' variabel',
            'state' => $stats['totalVariables'] > 0 ? 'ok' : 'warn',
        ],
        [
            'number' => '3',
            'title' => 'Hubungkan Data',
            'body' => 'Pastikan variabel input membaca data IoT, laporan, function, atau database.',
            'status' => $configuredInputCount . '/' . $inputCount . ' sumber',
            'state' => $unconfiguredInputCount === 0 ? 'ok' : 'warn',
        ],
        [
            'number' => '4',
            'title' => 'Susun Rule',
            'body' => 'Buat aturan IF-THEN untuk menghasilkan output dan rekomendasi SPK.',
            'status' => $stats['totalRules'] . ' rule',
            'state' => $stats['totalRules'] > 0 ? 'ok' : 'warn',
        ],
    ];
@endphp

<div x-data="fuzzyConfig()" class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="mb-1 flex items-center gap-2 text-sm">
                <a href="{{ route('settings.index') }}" class="text-gray-400 transition-colors hover:text-gray-600">Pengaturan</a>
                <span class="text-gray-300">/</span>
                <span class="font-medium text-gray-600">Fuzzy Mamdani</span>
            </div>
            <h1 class="text-2xl font-bold text-[var(--color-gray-900)]">Konfigurasi Fuzzy Mamdani</h1>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-[var(--color-gray-500)]">
                Kelola alur fuzzy dari profil komoditas, variabel, membership function, sumber data, sampai rule inferensi.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" @click="modal = 'addProfile'"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-100">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah Profil
            </button>
            <form action="{{ route('settings.fuzzy.reset') }}" method="POST" onsubmit="return confirm('Semua konfigurasi fuzzy akan di-reset ke default. Lanjutkan?');">
                @csrf
                <button type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-amber-200 bg-white px-4 py-2.5 text-sm font-semibold text-amber-700 transition-colors hover:bg-amber-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reset Default
                </button>
            </form>
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

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,0.65fr)]">
        <section class="rounded-2xl border border-gray-100 bg-white p-5" style="box-shadow: var(--shadow-sm);">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold text-gray-900">Profil Konfigurasi</h2>
                        @if($activeProfile)
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase
                                {{ $activeProfile->status === 'active' ? 'bg-emerald-50 text-emerald-700' : ($activeProfile->status === 'review' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                                {{ $activeProfile->status }}
                            </span>
                            @if($activeProfile->is_active)
                                <span class="inline-flex items-center rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold uppercase text-white">Aktif</span>
                            @endif
                        @endif
                    </div>
                    <p class="mt-1 text-sm leading-6 text-gray-500">
                        Satu profil mewakili konfigurasi fuzzy untuk satu komoditas atau versi aturan tertentu.
                    </p>
                </div>

                @if($activeProfile && ! $activeProfile->is_active)
                    <form action="{{ route('settings.fuzzy.profiles.activate', $activeProfile->id) }}" method="POST" onsubmit="return confirm('Jadikan profil ini sebagai konfigurasi aktif untuk komoditas terkait?');">
                        @csrf @method('PATCH')
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-100 sm:w-auto">
                            Aktifkan Profil
                        </button>
                    </form>
                @endif
            </div>

            <form method="GET" action="{{ route('settings.fuzzy.index') }}" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Profil yang sedang diedit</label>
                    <select name="profile_id" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm transition-all focus:border-[var(--color-primary)] focus:outline-none" onchange="this.form.submit()">
                        @foreach($profiles as $profile)
                            <option value="{{ $profile->id }}" {{ $profile->id === $activeProfileId ? 'selected' : '' }}>
                                {{ $profile->name }} {{ $profile->version ? '(' . $profile->version . ')' : '' }} - {{ $profile->commodity->nama ?? 'Tanpa komoditas' }}
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="tab" :value="tab">
                </div>
                <noscript>
                    <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white">Pilih</button>
                </noscript>
            </form>

            @if($activeProfile)
                <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-3">
                        <div class="text-[11px] font-semibold uppercase text-gray-400">Nama</div>
                        <div class="mt-1 truncate text-sm font-semibold text-gray-800">{{ $activeProfile->name }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-3">
                        <div class="text-[11px] font-semibold uppercase text-gray-400">Komoditas</div>
                        <div class="mt-1 truncate text-sm font-semibold text-gray-800">{{ $activeProfile->commodity->nama ?? '-' }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-3">
                        <div class="text-[11px] font-semibold uppercase text-gray-400">Versi</div>
                        <div class="mt-1 text-sm font-semibold text-gray-800">{{ $activeProfile->version ?: '-' }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-3">
                        <div class="text-[11px] font-semibold uppercase text-gray-400">Reviewer</div>
                        <div class="mt-1 truncate text-sm font-semibold text-gray-800">{{ $activeProfile->reviewed_by ?: 'Belum ditetapkan' }}</div>
                    </div>
                </div>
            @endif
        </section>

        <aside class="rounded-2xl border border-gray-100 bg-white p-5" style="box-shadow: var(--shadow-sm);">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Kesiapan SPK</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">Ringkasan cepat sebelum konfigurasi dipakai untuk evaluasi.</p>
                </div>
                <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $unconfiguredInputCount === 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                    {{ $sourceCompletion }}%
                </span>
            </div>

            <div class="mt-4">
                <div class="mb-2 flex items-center justify-between text-xs font-medium text-gray-500">
                    <span>Sumber data input</span>
                    <span>{{ $configuredInputCount }} dari {{ $inputCount }} terhubung</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full {{ $unconfiguredInputCount === 0 ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ $sourceCompletion }}%"></div>
                </div>
                @if($unconfiguredInputCount > 0)
                    <p class="mt-2 text-xs leading-5 text-amber-700">{{ $unconfiguredInputCount }} variabel input belum punya sumber data. Nilai dapat terbaca 0 saat evaluasi.</p>
                @else
                    <p class="mt-2 text-xs leading-5 text-emerald-700">Semua variabel input non-kausalitas sudah terhubung ke sumber data.</p>
                @endif
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2">
                <div class="rounded-lg bg-gray-50 p-3 text-center">
                    <div class="text-lg font-bold text-gray-900">{{ $stats['totalVariables'] }}</div>
                    <div class="text-[11px] font-medium text-gray-500">Variabel</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 text-center">
                    <div class="text-lg font-bold text-gray-900">{{ $stats['totalSets'] }}</div>
                    <div class="text-[11px] font-medium text-gray-500">MF</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-3 text-center">
                    <div class="text-lg font-bold text-gray-900">{{ $stats['totalRules'] }}</div>
                    <div class="text-[11px] font-medium text-gray-500">Rule</div>
                </div>
            </div>
        </aside>
    </div>

    <section class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        @foreach($stepCards as $step)
            <div class="rounded-2xl border border-gray-100 bg-white p-4" style="box-shadow: var(--shadow-sm);">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg {{ $step['state'] === 'ok' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} text-sm font-bold">
                        {{ $step['number'] }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-semibold text-gray-900">{{ $step['title'] }}</h3>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $step['state'] === 'ok' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $step['status'] }}</span>
                        </div>
                        <p class="mt-1 text-xs leading-5 text-gray-500">{{ $step['body'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </section>

    <section class="grid grid-cols-1 gap-3 lg:grid-cols-3">
        @foreach($engineMeta as $groupKey => $meta)
            @php
                $toneClasses = [
                    'emerald' => 'border-emerald-100 bg-emerald-50/50 text-emerald-700',
                    'sky' => 'border-sky-100 bg-sky-50/50 text-sky-700',
                    'violet' => 'border-violet-100 bg-violet-50/50 text-violet-700',
                ][$meta['tone']];
            @endphp
            <div class="rounded-2xl border {{ $toneClasses }} p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide opacity-80">{{ $meta['label'] }}</div>
                        <h3 class="mt-1 text-sm font-bold">{{ $meta['title'] }}</h3>
                        <p class="mt-1 text-xs leading-5 opacity-80">{{ $meta['hint'] }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-bold">{{ ($variablesByGroup[$groupKey] ?? collect())->count() }}</div>
                        <div class="text-[11px] font-medium opacity-75">variabel</div>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2 text-xs font-medium opacity-80">
                    <span>{{ ($rulesByGroup[$groupKey] ?? collect())->count() }} rule</span>
                    <span class="opacity-40">-</span>
                    <span>{{ ($variablesByGroup[$groupKey] ?? collect())->sum(fn ($v) => $v->sets->count()) }} membership</span>
                </div>
            </div>
        @endforeach
    </section>

    <div class="rounded-2xl border border-gray-100 bg-white p-2" style="box-shadow: var(--shadow-sm);">
        <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
            <button type="button" @click="setTab('variables')"
                :class="tab === 'variables' ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
                class="rounded-xl px-4 py-3 text-left transition-colors">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-bold">Variabel & MF</span>
                    <span class="text-xs font-semibold">{{ $stats['totalVariables'] }}</span>
                </div>
                <p class="mt-1 text-xs leading-5 opacity-80">Parameter input/output dan bentuk membership.</p>
            </button>
            <button type="button" @click="setTab('sources')"
                :class="tab === 'sources' ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
                class="rounded-xl px-4 py-3 text-left transition-colors">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-bold">Sumber Data</span>
                    <span class="text-xs font-semibold">{{ $stats['totalSources'] }}</span>
                </div>
                <p class="mt-1 text-xs leading-5 opacity-80">Mapping data IoT, laporan, function, dan database.</p>
            </button>
            <button type="button" @click="setTab('rules')"
                :class="tab === 'rules' ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
                class="rounded-xl px-4 py-3 text-left transition-colors">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-bold">Rule IF-THEN</span>
                    <span class="text-xs font-semibold">{{ $stats['totalRules'] }}</span>
                </div>
                <p class="mt-1 text-xs leading-5 opacity-80">Aturan inferensi Mamdani yang menentukan output.</p>
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
        tab: @json(request('tab', 'variables')),
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
