{{-- Tab 2: Rules --}}
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-gray-900">Aturan Inferensi (Rules)</h2>
        <button @click="ruleConditions = [{ variable_id: '', set_id: '' }, { variable_id: '', set_id: '' }]; modal = 'addRule'"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90 transition-opacity">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah Rule
        </button>
    </div>

    {{-- Filter & Search --}}
    <div class="flex flex-wrap items-center gap-3 bg-white rounded-xl px-4 py-3" style="box-shadow: var(--shadow-sm);">
        <div class="flex items-center gap-2">
            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Engine:</label>
            <select x-model="ruleFilterGroup" class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                <option value="all">Semua Engine</option>
                <option value="lingkungan">Engine 1 — Lingkungan</option>
                <option value="kesehatan">Engine 2 — Kesehatan</option>
                <option value="kausalitas">Engine 3 — Kausalitas</option>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Output:</label>
            <select x-model="ruleFilterOutput" class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                <option value="all">Semua</option>
                <option value="Optimal">Optimal</option>
                <option value="Baik">Baik</option>
                <option value="Waspada">Waspada</option>
                <option value="Buruk">Buruk</option>
            </select>
        </div>
        <div class="flex items-center gap-2 flex-1 min-w-[200px]">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="ruleSearch" placeholder="Cari diagnosis..." class="w-full pl-9 pr-3 py-1.5 rounded-lg border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
            </div>
        </div>
    </div>

    @php
        $ruleGroups = $rules->groupBy('group');
        $groupLabels = ['lingkungan' => 'Engine 1 — Status Lingkungan', 'kesehatan' => 'Engine 2 — Indeks Kesehatan', 'kausalitas' => 'Engine 3 — Diagnosis Kausalitas'];
        $groupColors = ['lingkungan' => 'emerald', 'kesehatan' => 'blue', 'kausalitas' => 'purple'];
    @endphp

    @foreach($groupLabels as $groupKey => $groupLabel)
        @if(isset($ruleGroups[$groupKey]))
        <div x-show="ruleFilterGroup === 'all' || ruleFilterGroup === '{{ $groupKey }}'" class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
            <div class="flex items-center gap-3 mb-4">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide uppercase
                    {{ $groupColors[$groupKey] === 'emerald' ? 'bg-emerald-50 text-emerald-700' : '' }}
                    {{ $groupColors[$groupKey] === 'blue' ? 'bg-blue-50 text-blue-700' : '' }}
                    {{ $groupColors[$groupKey] === 'purple' ? 'bg-purple-50 text-purple-700' : '' }}">
                    {{ $groupKey }}
                </span>
                <h3 class="text-sm font-semibold text-gray-800">{{ $groupLabel }}</h3>
                <span class="text-xs text-gray-400 ml-auto">{{ $ruleGroups[$groupKey]->count() }} rules</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-8">#</th>
                            <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Kondisi (IF)</th>
                            <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Output (THEN)</th>
                            <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Diagnosis</th>
                            <th class="text-right py-2.5 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ruleGroups[$groupKey] as $idx => $rule)
                        <tr x-show="(ruleFilterOutput === 'all' || '{{ $rule->outputSet->name ?? '' }}'.includes(ruleFilterOutput)) && (ruleSearch === '' || '{{ strtolower($rule->diagnosis ?? '') }}'.includes(ruleSearch.toLowerCase()))"
                            class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                            <td class="py-3 px-3 text-xs text-gray-400 font-mono">{{ $idx + 1 }}</td>
                            <td class="py-3 px-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($rule->conditions as $ci => $cond)
                                        @if($ci > 0)
                                            <span class="text-[10px] font-bold text-gray-400 self-center">{{ $rule->operator }}</span>
                                        @endif
                                        <span class="inline-flex items-center gap-1 text-xs bg-gray-100 px-2 py-1 rounded-md">
                                            <span class="text-gray-500">{{ $cond->variable->name ?? '?' }}</span>
                                            <span class="font-semibold text-gray-800">{{ $cond->set->name ?? '?' }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                @php
                                    $outName = $rule->outputSet->name ?? '';
                                    $outColor = match(true) {
                                        str_contains($outName, 'Optimal') => 'bg-emerald-50 text-emerald-700',
                                        str_contains($outName, 'Baik')    => 'bg-blue-50 text-blue-700',
                                        str_contains($outName, 'Waspada') => 'bg-amber-50 text-amber-700',
                                        str_contains($outName, 'Buruk')   => 'bg-red-50 text-red-700',
                                        default => 'bg-purple-50 text-purple-700',
                                    };
                                @endphp
                                <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-md {{ $outColor }}">{{ $outName }}</span>
                            </td>
                            <td class="py-3 px-3 text-xs text-gray-600 max-w-[200px]">{{ $rule->diagnosis ?? '-' }}</td>
                            <td class="py-3 px-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="openEditRule({{ $rule->toJson() }})" class="w-7 h-7 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="Edit rule">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <form action="{{ route('settings.fuzzy.rules.destroy', $rule->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Hapus rule ini?\n\nDiagnosis: {{ $rule->diagnosis }}');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="w-7 h-7 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Hapus rule">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    @endforeach
</div>
