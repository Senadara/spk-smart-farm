@php
    $sourceByVariable = $inputSources->keyBy('variable_id');
    $inputVariables = $variables
        ->where('type', 'input')
        ->where('group', '!=', 'kausalitas')
        ->values();

    $sourceLabels = [
        'iot' => 'IoT Sensor',
        'report_metric' => 'Metric Laporan',
        'function' => 'Function',
        'database' => 'Database',
    ];

    $sourceColors = [
        'iot' => 'bg-emerald-50 text-emerald-700',
        'report_metric' => 'bg-sky-50 text-sky-700',
        'function' => 'bg-violet-50 text-violet-700',
        'database' => 'bg-amber-50 text-amber-700',
    ];
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Sumber Data Input</h2>
            <p class="text-sm text-gray-500 mt-1">Hubungkan variabel SPK ke IoT, metric laporan dinamis, function kalkulasi, atau sumber database aman.</p>
        </div>
        <button type="button" @click="openAddSource()"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90 transition-opacity">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Atur Sumber
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-5 lg:col-span-2" style="box-shadow: var(--shadow-sm);">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Variabel</th>
                            <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Sumber</th>
                            <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Detail</th>
                            <th class="text-right py-2.5 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inputVariables as $var)
                            @php
                                $src = $sourceByVariable->get($var->id);
                                $config = is_array($src?->extra_config) ? $src->extra_config : [];
                                $editPayload = $src ? [
                                    'id' => $src->id,
                                    'variable_id' => $var->id,
                                    'variable_name' => $var->name,
                                    'source_type' => $src->source_type,
                                    'parameter_code' => $config['parameterCode'] ?? '',
                                    'metric_code' => $config['metricCode'] ?? '',
                                    'function_name' => $src->function_name ?? '',
                                    'source_name' => $src->source_name ?? '',
                                    'field_name' => $src->field_name ?? '',
                                    'aggregation' => $config['aggregation'] ?? 'sum',
                                    'date_scope' => $config['dateScope'] ?? 'today',
                                    'max_age_minutes' => $config['maxAgeMinutes'] ?? 30,
                                    'offline_after_misses' => $config['offlineAfterMisses'] ?? 3,
                                ] : [
                                    'id' => null,
                                    'variable_id' => $var->id,
                                    'variable_name' => $var->name,
                                    'source_type' => 'iot',
                                    'parameter_code' => '',
                                    'metric_code' => '',
                                    'function_name' => '',
                                    'source_name' => '',
                                    'field_name' => '',
                                    'aggregation' => 'sum',
                                    'date_scope' => 'today',
                                    'max_age_minutes' => 30,
                                    'offline_after_misses' => 3,
                                ];
                            @endphp
                            <tr class="border-b border-gray-50 hover:bg-gray-50/60 transition-colors">
                                <td class="py-3 px-3">
                                    <div class="font-semibold text-gray-900">{{ $var->name }}</div>
                                    <div class="text-xs text-gray-400">{{ $var->group }}{{ $var->unit ? ' - ' . $var->unit : '' }}</div>
                                </td>
                                <td class="py-3 px-3">
                                    @if($src)
                                        <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full {{ $sourceColors[$src->source_type] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ $sourceLabels[$src->source_type] ?? $src->source_type }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full bg-red-50 text-red-700">
                                            Belum diatur
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-gray-600">
                                    @if(! $src)
                                        <span class="text-xs text-red-500">Variabel ini akan bernilai 0 saat evaluasi SPK.</span>
                                    @elseif($src->source_type === 'iot')
                                        <div><code class="text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $config['parameterCode'] ?? '-' }}</code></div>
                                        <div class="text-xs text-gray-400 mt-1">Segar maks. {{ $config['maxAgeMinutes'] ?? 30 }} menit, offline setelah {{ $config['offlineAfterMisses'] ?? 3 }} miss</div>
                                    @elseif($src->source_type === 'report_metric')
                                        <div><code class="text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $config['metricCode'] ?? '-' }}</code></div>
                                        <div class="text-xs text-gray-400 mt-1">{{ $config['aggregation'] ?? 'sum' }} / {{ $config['dateScope'] ?? 'today' }}</div>
                                    @elseif($src->source_type === 'function')
                                        <code class="text-xs bg-gray-100 px-2 py-0.5 rounded">{{ class_basename($src->function_name) }}</code>
                                    @else
                                        <code class="text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $src->source_name }}.{{ $src->field_name }}</code>
                                    @endif
                                </td>
                                <td class="py-3 px-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" @click='openEditSource(@json($editPayload))'
                                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                            title="{{ $src ? 'Edit sumber' : 'Atur sumber' }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        @if($src)
                                            <form action="{{ route('settings.fuzzy.sources.destroy', $src->id) }}" method="POST" onsubmit="return confirm('Hapus sumber data untuk variabel {{ $var->name }}?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Hapus sumber">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-sm text-gray-500">Belum ada variabel input pada profile ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 space-y-4" style="box-shadow: var(--shadow-sm);">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Alur Konfigurasi</h3>
                <p class="text-sm text-gray-500 mt-1">Urutan ini membuat SPK tetap dinamis saat parameter bertambah.</p>
            </div>
            <div class="space-y-3 text-sm">
                <div class="rounded-xl bg-gray-50 p-3">
                    <div class="font-semibold text-gray-800">1. Buat variabel input</div>
                    <div class="text-xs text-gray-500 mt-1">Contoh: suhu, bobot_rata_rata, konsumsi_air.</div>
                </div>
                <div class="rounded-xl bg-gray-50 p-3">
                    <div class="font-semibold text-gray-800">2. Pilih sumber data</div>
                    <div class="text-xs text-gray-500 mt-1">IoT untuk sensor, Metric Laporan untuk data harian fleksibel.</div>
                </div>
                <div class="rounded-xl bg-gray-50 p-3">
                    <div class="font-semibold text-gray-800">3. Lengkapi membership dan rule</div>
                    <div class="text-xs text-gray-500 mt-1">Variabel tanpa sumber tetap tampil, tetapi hasil inputnya 0.</div>
                </div>
            </div>
        </div>
    </div>

    <div x-show="modal === 'sourceForm'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/40" @click="modal = null"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 z-10 max-h-[90vh] overflow-y-auto" @click.stop>
            <h3 class="text-lg font-bold text-gray-900 mb-1" x-text="sourceFormMode === 'edit' ? 'Edit Sumber Data' : 'Atur Sumber Data'"></h3>
            <p class="text-sm text-gray-500 mb-5">Pilih asal nilai yang akan dibaca otomatis oleh SPK.</p>

            <form :action="sourceFormMode === 'edit' ? `{{ url('/settings/fuzzy/sources') }}/${editSource.id}` : `{{ route('settings.fuzzy.sources.store') }}`" method="POST">
                @csrf
                <template x-if="sourceFormMode === 'edit'">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <input type="hidden" name="profile_id" value="{{ $activeProfileId }}">

                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Variabel Input *</label>
                            <select name="variable_id" x-model="editSource.variable_id" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                                <option value="">Pilih variabel</option>
                                @foreach($inputVariables as $var)
                                    <option value="{{ $var->id }}">{{ $var->name }} ({{ $var->group }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Sumber *</label>
                            <select name="source_type" x-model="editSource.source_type" required class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                                <option value="iot">IoT Sensor</option>
                                <option value="report_metric">Metric Laporan</option>
                                <option value="function">Function</option>
                                <option value="database">Database</option>
                            </select>
                        </div>
                    </div>

                    <div x-show="editSource.source_type === 'iot'" class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-4 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Parameter IoT *</label>
                                <select name="parameter_code" x-model="editSource.parameter_code" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                                    <option value="">Pilih parameter</option>
                                    @foreach($iotParameters as $parameter)
                                        <option value="{{ $parameter->parameterCode }}">{{ $parameter->parameterCode }} - {{ $parameter->parameterName }}{{ $parameter->unit ? ' (' . $parameter->unit . ')' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Batas segar (menit)</label>
                                <input type="number" name="max_age_minutes" min="1" max="10080" x-model="editSource.max_age_minutes" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Offline setelah miss</label>
                                <input type="number" name="offline_after_misses" min="1" max="20" x-model="editSource.offline_after_misses" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            </div>
                        </div>
                    </div>

                    <div x-show="editSource.source_type === 'report_metric'" class="rounded-xl border border-sky-100 bg-sky-50/40 p-4 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Metric Code *</label>
                                <input type="text" name="metric_code" x-model="editSource.metric_code" placeholder="bobot_rata_rata" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Agregasi</label>
                                <select name="aggregation" x-model="editSource.aggregation" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white">
                                    <option value="sum">Sum</option>
                                    <option value="avg">Average</option>
                                    <option value="latest">Latest</option>
                                    <option value="count">Count</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Periode</label>
                                <select name="date_scope" x-model="editSource.date_scope" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white">
                                    <option value="today">Hari ini</option>
                                    <option value="week">Minggu ini</option>
                                    <option value="month">Bulan ini</option>
                                    <option value="all">Semua data</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div x-show="editSource.source_type === 'function'" class="rounded-xl border border-violet-100 bg-violet-50/40 p-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Function *</label>
                        <select name="function_name" x-model="editSource.function_name" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Pilih function</option>
                            @foreach($availableFunctions as $class => $label)
                                <option value="{{ $class }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="editSource.source_type === 'database'" class="rounded-xl border border-amber-100 bg-amber-50/40 p-4 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tabel *</label>
                                <select name="source_name" x-model="editSource.source_name" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white">
                                    <option value="">Pilih tabel</option>
                                    @foreach($databaseSources as $table => $fields)
                                        <option value="{{ $table }}">{{ $table }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Field *</label>
                                <select name="field_name" x-model="editSource.field_name" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white">
                                    <option value="">Pilih field</option>
                                    <template x-for="field in dbFieldsForSource(editSource.source_name)" :key="field">
                                        <option :value="field" x-text="field"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Agregasi</label>
                                <select name="aggregation" x-model="editSource.aggregation" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white">
                                    <option value="sum">Sum</option>
                                    <option value="avg">Average</option>
                                    <option value="latest">Latest</option>
                                    <option value="count">Count</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Periode</label>
                                <select name="date_scope" x-model="editSource.date_scope" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white">
                                    <option value="today">Hari ini</option>
                                    <option value="week">Minggu ini</option>
                                    <option value="month">Bulan ini</option>
                                    <option value="all">Semua data</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <button type="button" @click="modal = null" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors border border-gray-200">Batal</button>
                    <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-[var(--color-primary)] rounded-xl hover:opacity-90 transition-opacity">Simpan Sumber</button>
                </div>
            </form>
        </div>
    </div>
</div>
