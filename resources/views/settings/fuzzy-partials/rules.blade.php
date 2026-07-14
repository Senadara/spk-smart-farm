@php
    $ruleGroups = $rules->groupBy('group');
    $groupLabels = [
        'lingkungan' => 'Engine 1 - Status Lingkungan',
        'kesehatan' => 'Engine 2 - Indeks Produktivitas',
        'kausalitas' => 'Engine 3 - Diagnosis Kausalitas',
    ];
    $groupHints = [
        'lingkungan' => 'Rule ini menilai kondisi sensor kandang.',
        'kesehatan' => 'Rule ini menilai performa laporan harian dan panen.',
        'kausalitas' => 'Rule ini membaca kombinasi hasil engine lingkungan dan produktivitas.',
    ];
    $groupStyles = [
        'lingkungan' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
        'kesehatan' => 'bg-sky-50 text-sky-700 border-sky-100',
        'kausalitas' => 'bg-violet-50 text-violet-700 border-violet-100',
    ];
@endphp

<div class="space-y-4">
    <div class="rounded-2xl border border-gray-100 bg-white p-4" style="box-shadow: var(--shadow-sm);">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-gray-900">Rule Inferensi IF-THEN</h2>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                    Rule membaca kombinasi membership dari variabel input, lalu menghasilkan output dan diagnosis. Susun dari engine awal ke engine berikutnya.
                </p>
            </div>
            <button type="button" @click="ruleConditions = [{ variable_id: '', set_id: '' }, { variable_id: '', set_id: '' }]; modal = 'addRule'"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah Rule
            </button>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-[auto_auto_minmax(220px,1fr)]">
            <label class="flex flex-col gap-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Engine</span>
                <select x-model="ruleFilterGroup" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm transition-all focus:border-[var(--color-primary)] focus:outline-none">
                    <option value="all">Semua engine</option>
                    <option value="lingkungan">Engine 1 - Lingkungan</option>
                    <option value="kesehatan">Engine 2 - Produktivitas</option>
                    <option value="kausalitas">Engine 3 - Kausalitas</option>
                </select>
            </label>
            <label class="flex flex-col gap-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Output</span>
                <select x-model="ruleFilterOutput" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm transition-all focus:border-[var(--color-primary)] focus:outline-none">
                    <option value="all">Semua output</option>
                    <option value="Optimal">Optimal</option>
                    <option value="Baik">Baik</option>
                    <option value="Waspada">Waspada</option>
                    <option value="Buruk">Buruk</option>
                </select>
            </label>
            <label class="flex flex-col gap-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Cari diagnosis</span>
                <span class="relative">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="ruleSearch" placeholder="Cari diagnosis atau rekomendasi..." class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm transition-all focus:border-[var(--color-primary)] focus:outline-none">
                </span>
            </label>
        </div>
    </div>

    @if($rules->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-200 bg-white p-8 text-center">
            <h3 class="text-sm font-semibold text-gray-900">Belum ada rule pada profil ini</h3>
            <p class="mt-1 text-sm text-gray-500">Buat minimal satu rule setelah variabel input, output, dan membership function tersedia.</p>
            <button type="button" @click="ruleConditions = [{ variable_id: '', set_id: '' }, { variable_id: '', set_id: '' }]; modal = 'addRule'" class="mt-4 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white">Tambah Rule</button>
        </div>
    @endif

    @foreach($groupLabels as $groupKey => $groupLabel)
        @if(isset($ruleGroups[$groupKey]))
            <section x-show="ruleFilterGroup === 'all' || ruleFilterGroup === '{{ $groupKey }}'" class="rounded-2xl border border-gray-100 bg-white p-4 md:p-5" style="box-shadow: var(--shadow-sm);">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $groupStyles[$groupKey] }}">
                                {{ $groupKey }}
                            </span>
                            <h3 class="text-sm font-semibold text-gray-900">{{ $groupLabel }}</h3>
                        </div>
                        <p class="mt-1 text-sm leading-6 text-gray-500">{{ $groupHints[$groupKey] }}</p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500">{{ $ruleGroups[$groupKey]->count() }} rule</span>
                </div>

                <div class="space-y-3">
                    @foreach($ruleGroups[$groupKey] as $idx => $rule)
                        @php
                            $outName = $rule->outputSet->name ?? 'Output belum dipilih';
                            $outputVariableName = $rule->outputSet->variable->name ?? 'output';
                            $outColor = match(true) {
                                str_contains($outName, 'Optimal') => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                str_contains($outName, 'Baik') => 'bg-sky-50 text-sky-700 border-sky-100',
                                str_contains($outName, 'Waspada') => 'bg-amber-50 text-amber-700 border-amber-100',
                                str_contains($outName, 'Buruk') => 'bg-red-50 text-red-700 border-red-100',
                                default => 'bg-violet-50 text-violet-700 border-violet-100',
                            };
                        @endphp

                        <article
                            x-show="(ruleFilterOutput === 'all' || @js($outName).includes(ruleFilterOutput)) && (ruleSearch === '' || @js(strtolower($rule->diagnosis ?? '')).includes(ruleSearch.toLowerCase()))"
                            class="rounded-xl border border-gray-100 bg-white p-4 transition-colors hover:border-gray-200 hover:bg-gray-50/40">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-gray-500">Rule {{ $idx + 1 }}</span>
                                        <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-gray-500">Operator {{ $rule->operator }}</span>
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-bold {{ $outColor }}">{{ $outName }}</span>
                                    </div>
                                    <h4 class="mt-2 text-sm font-semibold text-gray-900">{{ $rule->diagnosis ?? 'Belum ada diagnosis' }}</h4>
                                </div>

                                <div class="flex items-center gap-1">
                                    <button type="button" @click="openEditRule(@js($rule))" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-blue-50 hover:text-blue-600" title="Edit rule">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <form action="{{ route('settings.fuzzy.rules.destroy', $rule->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Hapus rule ini? Diagnosis: {{ $rule->diagnosis }}');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600" title="Hapus rule">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_auto_minmax(260px,0.7fr)] lg:items-center">
                                <div class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                                    <div class="mb-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">IF kondisi</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($rule->conditions as $ci => $cond)
                                            @if($ci > 0)
                                                <span class="self-center rounded bg-white px-1.5 py-1 text-[10px] font-bold text-gray-400">{{ $rule->operator }}</span>
                                            @endif
                                            <span class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs">
                                                <span class="font-mono text-gray-500">{{ $cond->variable->name ?? '?' }}</span>
                                                <span class="text-gray-300">is</span>
                                                <span class="font-semibold text-gray-800">{{ $cond->set->name ?? '?' }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="hidden text-lg font-bold text-gray-300 lg:block">-></div>

                                <div class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                                    <div class="mb-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">THEN output</div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-xs text-gray-500">{{ $outputVariableName }}</span>
                                        <span class="text-xs text-gray-300">=</span>
                                        <span class="inline-flex items-center rounded-lg border px-2 py-1 text-xs font-bold {{ $outColor }}">{{ $outName }}</span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
</div>
