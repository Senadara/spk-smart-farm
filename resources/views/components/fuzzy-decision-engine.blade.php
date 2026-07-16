@props([
    'barns',
    'indicators',
    'spkResults',
    'hideBarnFilter' => false,
    'showEvaluateButton' => true,
    'showReportButton' => true,
    'evaluationTime' => 'Auto Evaluated',
])

@php
    $scoreTone = function ($score, string $fallback = 'gray'): string {
        if (is_numeric($score)) {
            $score = (float) $score;

            return match (true) {
                $score >= 85 => 'emerald',
                $score >= 70 => 'blue',
                $score >= 55 => 'amber',
                default => 'red',
            };
        }

        return in_array($fallback, ['emerald', 'blue', 'amber', 'red', 'gray'], true) ? $fallback : 'gray';
    };
@endphp

<div class="bg-white border border-gray-100 rounded-xl p-5 xl:p-6 shadow-sm">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-50 to-teal-50 flex items-center justify-center border border-emerald-100">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900">Fuzzy Decision Engine</h3>
                <p class="text-sm text-gray-500 mt-0.5">Sistem inferensi Mamdani untuk analisa lingkungan, produktivitas, dan tindakan SPK.</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if(!$hideBarnFilter)
            <select x-model="fuzzyFilter" @change="onFuzzyBarnChange()"
                class="text-sm border border-gray-200 rounded-xl px-4 py-2.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 font-medium"
                title="Pilih kandang untuk melihat hasil SPK spesifik">
                <option value="all">Semua Kandang</option>
                @foreach($barns as $barn)
                    @if(($barn['id'] ?? '') !== 'no-data')
                    <option value="{{ $barn['id'] }}">{{ $barn['name'] }}</option>
                    @endif
                @endforeach
            </select>
            @endif
            <span class="px-3 py-1.5 text-sm font-semibold bg-emerald-50 text-emerald-700 rounded-full flex flex-shrink-0 items-center gap-2 border border-emerald-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span x-text="evaluationTimeLabel">{{ $evaluationTime }}</span>
            </span>
            @if(data_get(session('user'), 'role') === 'pjawab' && \Illuminate\Support\Facades\Route::has('settings.fuzzy.index'))
                <a href="{{ route('settings.fuzzy.index') }}" title="Pengaturan Fuzzy Logic" class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors border border-transparent hover:border-emerald-100 flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </a>
            @endif
        </div>
    </div>

    <p x-show="evalMessage" class="mb-5 px-4 py-3 text-sm rounded-xl font-medium" :class="evalSuccess ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-red-50 text-red-700 border border-red-100'" x-text="evalMessage"></p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:divide-x lg:divide-gray-100">

        {{-- COL 1: Environment Logic --}}
        <div class="flex flex-col h-full lg:pr-6">
            <div>
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9V3m0 0a2 2 0 10-4 0v9.764a4 4 0 106.764 1.528A3.99 3.99 0 0012 13V3z"/></svg>
                        </div>
                        <span class="text-base font-semibold text-gray-800">Environment Logic</span>
                    </div>
                    <span class="px-2 py-1 text-xs font-bold bg-emerald-100 text-emerald-700 rounded-lg"
                        x-text="(fuzzySensors?.lingkungan?.length || 0) + ' Parameter'">0 Parameter</span>
                </div>

                <div class="space-y-4 mb-6">
                    <template x-for="(sensor, idx) in fuzzySensors?.lingkungan || []" :key="idx">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700" x-text="sensor.label"></span>
                                <span class="text-xs font-semibold px-2.5 py-1 rounded-md"
                                    :class="{
                                        'text-emerald-700 bg-emerald-50 border border-emerald-200': sensor.status === 'normal',
                                        'text-amber-700 bg-amber-50 border border-amber-200': sensor.status === 'warning',
                                        'text-red-700 bg-red-50 border border-red-200': sensor.status === 'danger',
                                    }"
                                    x-text="sensor.statusLabel"></span>
                            </div>
                            <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500"
                                    :class="{
                                        'bg-emerald-500': sensor.status === 'normal',
                                        'bg-amber-500': sensor.status === 'warning',
                                        'bg-red-500': sensor.status === 'danger',
                                    }"
                                    :style="'width: ' + sensor.percent + '%'"></div>
                            </div>
                        </div>
                    </template>
                    <p x-show="!(fuzzySensors?.lingkungan?.length)" class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-xs font-medium text-gray-500">
                        Belum ada parameter lingkungan aktif.
                    </p>
                </div>
            </div>

            <div class="mt-auto border-t border-gray-100 pt-5">
                @php
                    $res = $spkResults['lingkungan'] ?? null;
                    $tone = $scoreTone($res['score'] ?? null, $res['scoreColor'] ?? $res['statusColor'] ?? 'gray');
                @endphp
                @if($res)
                <div class="border rounded-xl p-4 shadow-sm transition-colors"
                    :class="{
                        'bg-emerald-50/80 border-emerald-200': (activeSpkResults?.lingkungan?.scoreColor ?? '{{ $tone }}') === 'emerald',
                        'bg-blue-50/80 border-blue-200': (activeSpkResults?.lingkungan?.scoreColor ?? '{{ $tone }}') === 'blue',
                        'bg-amber-50/80 border-amber-200': (activeSpkResults?.lingkungan?.scoreColor ?? '{{ $tone }}') === 'amber',
                        'bg-red-50/80 border-red-200': (activeSpkResults?.lingkungan?.scoreColor ?? '{{ $tone }}') === 'red',
                        'bg-gray-50 border-gray-200': !['emerald', 'blue', 'amber', 'red'].includes(activeSpkResults?.lingkungan?.scoreColor ?? '{{ $tone }}'),
                    }">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-white border"
                            :class="{
                                'text-emerald-700 border-emerald-200': (activeSpkResults?.lingkungan?.scoreColor ?? '{{ $tone }}') === 'emerald',
                                'text-blue-700 border-blue-200': (activeSpkResults?.lingkungan?.scoreColor ?? '{{ $tone }}') === 'blue',
                                'text-amber-700 border-amber-200': (activeSpkResults?.lingkungan?.scoreColor ?? '{{ $tone }}') === 'amber',
                                'text-red-700 border-red-200': (activeSpkResults?.lingkungan?.scoreColor ?? '{{ $tone }}') === 'red',
                                'text-gray-700 border-gray-200': !['emerald', 'blue', 'amber', 'red'].includes(activeSpkResults?.lingkungan?.scoreColor ?? '{{ $tone }}'),
                            }"
                            x-text="activeSpkResults?.lingkungan?.status ?? @js($res['status'])">{{ $res['status'] }}</span>
                        <span class="text-sm font-semibold text-gray-900" x-text="activeSpkResults?.lingkungan?.title ?? @js($res['title'])">{{ $res['title'] }}</span>
                    </div>
                    <p class="text-xs text-gray-600 leading-relaxed" x-text="activeSpkResults?.lingkungan?.description ?? @js($res['description'])">{{ $res['description'] }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- COL 2: Productivity Logic (4 parameter SPK, tanpa FCR) --}}
        <div class="lg:px-6 flex flex-col h-full">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <span class="text-base font-semibold text-gray-800">Productivity Logic</span>
                    </div>
                    <span class="px-2 py-1 text-xs font-bold bg-blue-100 text-blue-700 rounded-lg"
                        x-text="(activeIndicators?.length || 0) + ' Parameter SPK'">0 Parameter SPK</span>
                </div>

                <p class="text-xs text-gray-500 mb-4 leading-relaxed">
                    Parameter produktivitas dan kesehatan dari profil SPK aktif.
                </p>

                <div x-show="(activeSpider?.labels?.length || 0) > 0" class="rounded-xl border border-blue-100 bg-gradient-to-b from-blue-50/60 to-white p-4 mb-5 shadow-sm">
                    <div class="w-full max-w-[300px] aspect-square mx-auto">
                        <canvas x-ref="spiderCanvas"></canvas>
                    </div>
                </div>

                <div x-show="activeIndicators?.length" class="grid grid-cols-2 gap-3 mb-6">
                    <template x-for="(item, idx) in activeIndicators" :key="idx">
                        <div class="rounded-xl border border-gray-100 bg-gray-50/80 px-3.5 py-3">
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <span class="text-[11px] font-bold uppercase tracking-wide text-gray-500 leading-tight" x-text="item.label"></span>
                                <span class="text-xs font-bold text-emerald-600" x-text="(item.score ?? 0) + '%'"></span>
                            </div>
                            <p class="text-base font-bold text-gray-900 leading-tight" x-text="item.value"></p>
                            <p class="text-xs text-gray-500 mt-1" x-text="item.detail || ''"></p>
                        </div>
                    </template>
                </div>
                <p x-show="!(activeIndicators?.length)" class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-xs font-medium text-gray-500">
                    Belum ada parameter produktivitas aktif.
                </p>
            </div>

            <div class="mt-auto border-t border-gray-100 pt-5">
                @php
                    $res = $spkResults['produktivitas'] ?? null;
                    $tone = $scoreTone($res['score'] ?? null, $res['scoreColor'] ?? $res['statusColor'] ?? 'gray');
                @endphp
                @if($res)
                <div class="border rounded-xl p-4 shadow-sm transition-colors"
                    :class="{
                        'bg-emerald-50/80 border-emerald-200': (activeSpkResults?.produktivitas?.scoreColor ?? '{{ $tone }}') === 'emerald',
                        'bg-blue-50/80 border-blue-200': (activeSpkResults?.produktivitas?.scoreColor ?? '{{ $tone }}') === 'blue',
                        'bg-amber-50/80 border-amber-200': (activeSpkResults?.produktivitas?.scoreColor ?? '{{ $tone }}') === 'amber',
                        'bg-red-50/80 border-red-200': (activeSpkResults?.produktivitas?.scoreColor ?? '{{ $tone }}') === 'red',
                        'bg-gray-50 border-gray-200': !['emerald', 'blue', 'amber', 'red'].includes(activeSpkResults?.produktivitas?.scoreColor ?? '{{ $tone }}'),
                    }">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-white border"
                            :class="{
                                'text-emerald-700 border-emerald-200': (activeSpkResults?.produktivitas?.scoreColor ?? '{{ $tone }}') === 'emerald',
                                'text-blue-700 border-blue-200': (activeSpkResults?.produktivitas?.scoreColor ?? '{{ $tone }}') === 'blue',
                                'text-amber-700 border-amber-200': (activeSpkResults?.produktivitas?.scoreColor ?? '{{ $tone }}') === 'amber',
                                'text-red-700 border-red-200': (activeSpkResults?.produktivitas?.scoreColor ?? '{{ $tone }}') === 'red',
                                'text-gray-700 border-gray-200': !['emerald', 'blue', 'amber', 'red'].includes(activeSpkResults?.produktivitas?.scoreColor ?? '{{ $tone }}'),
                            }"
                            x-text="activeSpkResults?.produktivitas?.status ?? @js($res['status'])">{{ $res['status'] }}</span>
                        <span class="text-sm font-semibold text-gray-900" x-text="activeSpkResults?.produktivitas?.title ?? @js($res['title'])">{{ $res['title'] }}</span>
                    </div>
                    <p class="text-xs text-gray-600 leading-relaxed" x-text="activeSpkResults?.produktivitas?.description ?? @js($res['description'])">{{ $res['description'] }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- COL 3: AI Console --}}
        <div class="lg:px-6 flex flex-col h-full">
            <div>
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        </div>
                        <span class="text-base font-semibold text-gray-800">AI Console</span>
                    </div>
                    <span class="text-xs font-medium text-gray-400 bg-gray-50 px-2 py-1 rounded-md">Manual Trigger</span>
                </div>

                <div class="flex flex-col items-center text-center pt-3 mb-6">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-purple-50 to-violet-50 flex items-center justify-center mb-4 border border-purple-100 shadow-sm">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    </div>
                    <h4 class="text-base font-bold text-gray-900 mb-2">Full Farm Diagnostic</h4>
                    <p class="text-sm text-gray-500 max-w-[220px] mb-5 leading-relaxed">Jalankan evaluasi SPK untuk semua kandang komoditas aktif.</p>
                    @if($showEvaluateButton)
                    <button type="button" @click="runFullEvaluation()" :disabled="evaluating"
                        class="px-6 py-3 bg-gradient-to-r from-red-500 to-rose-500 text-white text-sm font-semibold rounded-xl hover:shadow-lg hover:shadow-red-500/25 transition-all flex items-center gap-2.5 disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg x-show="!evaluating" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                        <svg x-show="evaluating" class="w-4.5 h-4.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="evaluating ? 'Menjalankan...' : 'Run Full Evaluation'"></span>
                    </button>
                    @endif
                </div>
            </div>

            <div class="mt-auto border-t border-gray-100 pt-5">
                @php
                    $res = $spkResults['gabungan'] ?? null;
                    $tone = $scoreTone($res['score'] ?? null, $res['scoreColor'] ?? $res['statusColor'] ?? 'gray');
                @endphp
                @if($res)
                <div class="border-2 rounded-xl p-4 shadow-sm transition-colors"
                    :class="{
                        'bg-emerald-50/80 border-emerald-200': (activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}') === 'emerald',
                        'bg-blue-50/80 border-blue-200': (activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}') === 'blue',
                        'bg-amber-50/80 border-amber-200': (activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}') === 'amber',
                        'bg-red-50/80 border-red-200': (activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}') === 'red',
                        'bg-gray-50 border-gray-200': !['emerald', 'blue', 'amber', 'red'].includes(activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}'),
                    }">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="px-2.5 py-1 text-xs font-black tracking-wider rounded-full bg-white border"
                            :class="{
                                'text-emerald-700 border-emerald-200': (activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}') === 'emerald',
                                'text-blue-700 border-blue-200': (activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}') === 'blue',
                                'text-amber-700 border-amber-200': (activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}') === 'amber',
                                'text-red-700 border-red-200': (activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}') === 'red',
                                'text-gray-700 border-gray-200': !['emerald', 'blue', 'amber', 'red'].includes(activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}'),
                            }"
                            x-text="activeSpkResults?.gabungan?.status ?? @js($res['status'])">{{ $res['status'] }}</span>
                        <span class="text-sm font-bold text-gray-900" x-text="activeSpkResults?.gabungan?.title ?? @js($res['title'])">{{ $res['title'] }}</span>
                    </div>
                    <p class="text-xs text-gray-700 leading-relaxed font-medium" x-text="activeSpkResults?.gabungan?.description ?? @js($res['description'])">{{ $res['description'] }}</p>
                    @if($showReportButton)
                    <a href="{{ $res['link'] }}" class="inline-block mt-3 px-4 py-2 text-xs font-bold text-white rounded-lg transition w-full text-center hover:shadow-md"
                        :class="{
                            'bg-emerald-600 hover:bg-emerald-700': (activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}') === 'emerald',
                            'bg-blue-600 hover:bg-blue-700': (activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}') === 'blue',
                            'bg-amber-600 hover:bg-amber-700': (activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}') === 'amber',
                            'bg-red-600 hover:bg-red-700': (activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}') === 'red',
                            'bg-gray-600 hover:bg-gray-700': !['emerald', 'blue', 'amber', 'red'].includes(activeSpkResults?.gabungan?.scoreColor ?? '{{ $tone }}'),
                        }"
                        style="text-decoration: none;">Lihat laporan lengkap</a>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
