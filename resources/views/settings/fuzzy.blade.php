@extends('layouts.app')

@section('title', 'Konfigurasi Fuzzy Mamdani')
@section('breadcrumb', 'Pengaturan / Fuzzy Mamdani')

@section('content')
<div x-data="fuzzyConfig()" class="space-y-6">
    {{-- Page Header --}}
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('settings.index') }}" class="text-sm text-gray-400 hover:text-gray-600 transition-colors">Pengaturan</a>
                <svg class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                <span class="text-sm text-gray-600 font-medium">Fuzzy Mamdani</span>
            </div>
            <h1 class="text-2xl font-bold text-[var(--color-gray-900)]">Konfigurasi Fuzzy Mamdani</h1>
            <p class="text-sm text-[var(--color-gray-500)] mt-1">Kelola variabel, membership function, dan aturan inferensi</p>
        </div>
        <div class="flex gap-3">
            <form action="{{ route('settings.fuzzy.reset') }}" method="POST" onsubmit="return confirm('PERINGATAN: Semua konfigurasi fuzzy akan di-reset ke default.\nData yang sudah diubah akan hilang.\n\nLanjutkan?');">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 cursor-pointer hover:bg-amber-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Reset ke Default
                </button>
            </form>
        </div>
    </div>

    {{-- Active Profile --}}
    <div class="bg-white rounded-2xl p-5 space-y-4" style="box-shadow: var(--shadow-sm);">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-gray-900">Profile Fuzzy Komoditas</h2>
                    @if($activeProfile)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase
                            {{ $activeProfile->status === 'active' ? 'bg-emerald-50 text-emerald-700' : ($activeProfile->status === 'review' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ $activeProfile->status }}
                        </span>
                    @endif
                </div>
                <p class="text-sm text-gray-500 mt-1">
                    Konfigurasi parameter, membership function, sumber data, dan rule dipisahkan per komoditas.
                </p>
            </div>
            <button type="button" @click="modal = 'addProfile'"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90 transition-opacity">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah Profile
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-4 items-end">
            <form method="GET" action="{{ route('settings.fuzzy.index') }}" class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Profile aktif di halaman</label>
                    <select name="profile_id" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all" onchange="this.form.submit()">
                        @foreach($profiles as $profile)
                            <option value="{{ $profile->id }}" {{ $profile->id === $activeProfileId ? 'selected' : '' }}>
                                {{ $profile->name }} {{ $profile->version ? '(' . $profile->version . ')' : '' }} - {{ $profile->commodity->nama ?? 'Tanpa komoditas' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <noscript>
                    <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-medium bg-gray-900 text-white">Pilih</button>
                </noscript>
            </form>

            @if($activeProfile && ! $activeProfile->is_active)
                <form action="{{ route('settings.fuzzy.profiles.activate', $activeProfile->id) }}" method="POST" onsubmit="return confirm('Jadikan profile ini sebagai konfigurasi aktif untuk komoditas terkait?');">
                    @csrf @method('PATCH')
                    <button type="submit" class="w-full lg:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 transition-colors">
                        Aktifkan Profile
                    </button>
                </form>
            @endif
        </div>

        @if($activeProfile)
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-2">
                <div class="rounded-xl bg-gray-50 px-3 py-2">
                    <div class="text-[11px] text-gray-400 uppercase font-semibold">Nama</div>
                    <div class="text-sm font-semibold text-gray-800 truncate">{{ $activeProfile->name }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 px-3 py-2">
                    <div class="text-[11px] text-gray-400 uppercase font-semibold">Komoditas</div>
                    <div class="text-sm font-semibold text-gray-800 truncate">{{ $activeProfile->commodity->nama ?? '-' }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 px-3 py-2">
                    <div class="text-[11px] text-gray-400 uppercase font-semibold">Versi</div>
                    <div class="text-sm font-semibold text-gray-800">{{ $activeProfile->version }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 px-3 py-2">
                    <div class="text-[11px] text-gray-400 uppercase font-semibold">Review</div>
                    <div class="text-sm font-semibold text-gray-800 truncate">{{ $activeProfile->reviewed_by ?: 'Belum ditetapkan' }}</div>
                </div>
            </div>
        @endif
    </div>

    {{-- Summary Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-4 flex items-center gap-3" style="box-shadow: var(--shadow-sm);">
            <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
            </div>
            <div><div class="text-xl font-bold text-gray-900">{{ $stats['totalVariables'] }}</div><div class="text-xs text-gray-500">Variabel</div></div>
        </div>
        <div class="bg-white rounded-xl p-4 flex items-center gap-3" style="box-shadow: var(--shadow-sm);">
            <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
            <div><div class="text-xl font-bold text-gray-900">{{ $stats['totalSets'] }}</div><div class="text-xs text-gray-500">Membership Functions</div></div>
        </div>
        <div class="bg-white rounded-xl p-4 flex items-center gap-3" style="box-shadow: var(--shadow-sm);">
            <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            </div>
            <div><div class="text-xl font-bold text-gray-900">{{ $stats['totalRules'] }}</div><div class="text-xs text-gray-500">Aturan (Rules)</div></div>
        </div>
        <div class="bg-white rounded-xl p-4 flex items-center gap-3" style="box-shadow: var(--shadow-sm);">
            <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
            </div>
            <div><div class="text-xl font-bold text-gray-900">{{ $stats['totalSources'] }}</div><div class="text-xs text-gray-500">Sumber Data</div></div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
    </div>
    @endif

    {{-- Validation Errors --}}
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
        <div class="flex items-center gap-2 font-semibold mb-1">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
            Validasi Gagal
        </div>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Tab Navigation --}}
    <div class="flex gap-2 border-b border-gray-200">
        <button @click="tab = 'variables'" :class="tab === 'variables' ? 'border-b-2 border-[var(--color-primary)] text-[var(--color-primary)] font-semibold' : 'text-gray-500 hover:text-gray-700'" class="px-5 py-3 text-sm transition-colors bg-transparent border-none cursor-pointer">
            Variabel & MF
        </button>
        <button @click="tab = 'rules'" :class="tab === 'rules' ? 'border-b-2 border-[var(--color-primary)] text-[var(--color-primary)] font-semibold' : 'text-gray-500 hover:text-gray-700'" class="px-5 py-3 text-sm transition-colors bg-transparent border-none cursor-pointer">
            Aturan (Rules)
        </button>
        <button @click="tab = 'sources'" :class="tab === 'sources' ? 'border-b-2 border-[var(--color-primary)] text-[var(--color-primary)] font-semibold' : 'text-gray-500 hover:text-gray-700'" class="px-5 py-3 text-sm transition-colors bg-transparent border-none cursor-pointer">
            Sumber Data
        </button>
    </div>

    {{-- Tab 1: Variables & Membership Functions --}}
    <div x-show="tab === 'variables'" x-cloak>
        @include('settings.fuzzy-partials.variables')
    </div>

    {{-- Tab 2: Rules --}}
    <div x-show="tab === 'rules'" x-cloak>
        @include('settings.fuzzy-partials.rules')
    </div>

    {{-- Tab 3: Input Sources --}}
    <div x-show="tab === 'sources'" x-cloak>
        @include('settings.fuzzy-partials.sources')
    </div>

    {{-- Modals --}}
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
        // Filters
        varFilterGroup: 'all',
        varFilterType: 'all',
        varSearch: '',
        ruleFilterGroup: 'all',
        ruleFilterOutput: 'all',
        ruleSearch: '',

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
