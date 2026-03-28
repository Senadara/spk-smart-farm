@props(['barns', 'indicators', 'spkResults', 'hideBarnFilter' => false, 'evaluationTime' => 'Auto Evaluated'])

<div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
            </div>
            <div>
                <h3 class="text-base font-semibold text-gray-800">Fuzzy Productivity Decision Engine</h3>
                <p class="text-xs text-gray-400">Mamdani Inference System for Farm Performance Decision Support</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if(!$hideBarnFilter)
            <select x-model="fuzzyFilter" class="text-xs border border-gray-200 rounded-lg px-3 py-1.5 bg-white text-gray-600 focus:outline-none focus:ring-1 focus:ring-emerald-300 focus:border-emerald-400">
                <option value="all">All Barn</option>
                @foreach($barns as $barn)
                    <option value="{{ $barn['id'] }}">{{ $barn['name'] }}</option>
                @endforeach
            </select>
            @endif
            <span class="px-2.5 py-1 text-xs font-medium bg-emerald-50 text-emerald-700 rounded-full flex flex-shrink-0 items-center gap-1">
                @if($evaluationTime === 'Auto Evaluated')
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                @else
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                @endif
                {{ $evaluationTime }}
            </span>
        </div>
    </div>

    {{-- 3 columns side-by-side --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:divide-x lg:divide-gray-100">

        {{-- COL 1: Environment Logic (driven by fuzzyFilter dropdown) --}}
        <div class="flex flex-col h-full">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                        <span class="text-sm font-semibold text-gray-700">Environment Logic</span>
                    </div>
                    <span class="px-1.5 py-0.5 text-[10px] font-semibold bg-emerald-100 text-emerald-700 rounded">IoT Real-time</span>
                </div>

                {{-- Sensor bars: driven by fuzzyFilter (dropdown), NOT activeBarn --}}
                <div class="space-y-3 mb-6">
                    <template x-for="(sensor, idx) in fuzzySensors?.lingkungan || []" :key="idx">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-gray-600" x-text="sensor.label"></span>
                                <span
                                    class="text-xs font-medium px-2 py-0.5 rounded"
                                    :class="{
                                        'text-emerald-600 bg-emerald-50': sensor.status === 'normal',
                                        'text-amber-600 bg-amber-50': sensor.status === 'warning',
                                        'text-red-600 bg-red-50': sensor.status === 'danger',
                                    }"
                                    x-text="sensor.statusLabel"
                                />
                            </div>
                            <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="{
                                        'bg-emerald-500': sensor.status === 'normal',
                                        'bg-amber-500': sensor.status === 'warning',
                                        'bg-red-500': sensor.status === 'danger',
                                    }"
                                    :style="'width: ' + sensor.percent + '%'"
                                ></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Lingkungan Verdict --}}
            <div class="mt-auto border-t border-gray-100 pt-4">
                @php
                    $res = $spkResults['lingkungan'] ?? null;
                    $verdictStyles = match($res['statusColor'] ?? 'gray') {
                        'red' => ['bg' => 'bg-red-50/50 border-red-100', 'text' => 'text-red-700', 'badge' => 'border-red-200'],
                        'amber' => ['bg' => 'bg-amber-50/50 border-amber-100', 'text' => 'text-amber-700', 'badge' => 'border-amber-200'],
                        'emerald' => ['bg' => 'bg-emerald-50/50 border-emerald-100', 'text' => 'text-emerald-700', 'badge' => 'border-emerald-200'],
                        default => ['bg' => 'bg-gray-50 border-gray-100', 'text' => 'text-gray-700', 'badge' => 'border-gray-200'],
                    };
                @endphp
                @if($res)
                <div class="border rounded-lg p-3 {{ $verdictStyles['bg'] }}">
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-white border {{ $verdictStyles['text'] }} {{ $verdictStyles['badge'] }}">{{ $res['status'] }}</span>
                        <span class="text-xs font-semibold text-gray-900">{{ $res['title'] }}</span>
                    </div>
                    <p class="text-[11px] text-gray-600 leading-relaxed">{{ $res['description'] }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- COL 2: Productivity Logic (Spider Chart) --}}
        <div class="lg:pl-6 flex flex-col h-full">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        <span class="text-sm font-semibold text-gray-700">Productivity Logic</span>
                    </div>
                    <span class="px-1.5 py-0.5 text-[10px] font-semibold bg-blue-100 text-blue-700 rounded">Data Driven</span>
                </div>

                <div class="flex items-start gap-4 mb-6">
                    {{-- Spider Chart --}}
                    <div class="w-36 h-36 shrink-0">
                        <canvas x-ref="spiderCanvas"></canvas>
                    </div>
                    {{-- Indicators --}}
                    <div class="flex-1 space-y-2.5 pt-2">
                        @foreach($indicators as $item)
                            @php
                                $dotColors = [
                                    'emerald' => 'bg-emerald-500',
                                    'amber'   => 'bg-amber-500',
                                    'red'     => 'bg-red-500',
                                ];
                                $dotColor = $dotColors[$item['color']] ?? 'bg-gray-400';
                            @endphp
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-600">{{ $item['label'] }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-1.5 rounded-full {{ $dotColor }}"></span>
                                    <span class="text-xs font-semibold text-gray-800 w-8">{{ $item['value'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Productivity Verdict --}}
            <div class="mt-auto border-t border-gray-100 pt-4">
                @php
                    $res = $spkResults['produktivitas'] ?? null;
                    $verdictStyles = match($res['statusColor'] ?? 'gray') {
                        'red' => ['bg' => 'bg-red-50/50 border-red-100', 'text' => 'text-red-700', 'badge' => 'border-red-200'],
                        'amber' => ['bg' => 'bg-amber-50/50 border-amber-100', 'text' => 'text-amber-700', 'badge' => 'border-amber-200'],
                        'emerald' => ['bg' => 'bg-emerald-50/50 border-emerald-100', 'text' => 'text-emerald-700', 'badge' => 'border-emerald-200'],
                        default => ['bg' => 'bg-gray-50 border-gray-100', 'text' => 'text-gray-700', 'badge' => 'border-gray-200'],
                    };
                @endphp
                @if($res)
                <div class="border rounded-lg p-3 {{ $verdictStyles['bg'] }}">
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-white border {{ $verdictStyles['text'] }} {{ $verdictStyles['badge'] }}">{{ $res['status'] }}</span>
                        <span class="text-xs font-semibold text-gray-900">{{ $res['title'] }}</span>
                    </div>
                    <p class="text-[11px] text-gray-600 leading-relaxed">{{ $res['description'] }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- COL 3: AI Console --}}
        <div class="lg:pl-6 flex flex-col h-full">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        <span class="text-sm font-semibold text-gray-700">AI Console</span>
                    </div>
                    <span class="text-xs text-gray-400">Manual Trigger</span>
                </div>

                <div class="flex flex-col items-center text-center pt-2 mb-6">
                    <div class="w-14 h-14 rounded-full bg-purple-50 flex items-center justify-center mb-3">
                        <svg class="w-7 h-7 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    </div>
                    <h4 class="text-sm font-semibold text-gray-800 mb-1">Full Farm Diagnostic</h4>
                    <p class="text-xs text-gray-400 max-w-[200px] mb-4">Run a comprehensive analysis combining environmental and productivity data points.</p>
                    <button class="px-5 py-2.5 bg-gradient-to-r from-red-500 to-rose-500 text-white text-sm font-medium rounded-full hover:shadow-lg transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                        Run Full Evaluation
                    </button>
                </div>
            </div>

            {{-- Gabungan Verdict --}}
            <div class="mt-auto border-t border-gray-100 pt-4">
                @php
                    $res = $spkResults['gabungan'] ?? null;
                    $verdictStyles = match($res['statusColor'] ?? 'gray') {
                        'red' => ['bg' => 'bg-red-50 border-red-200', 'text' => 'text-red-700', 'badge' => 'border-red-200', 'btn' => 'bg-red-600 hover:bg-red-700'],
                        'amber' => ['bg' => 'bg-amber-50 border-amber-200', 'text' => 'text-amber-700', 'badge' => 'border-amber-200', 'btn' => 'bg-amber-600 hover:bg-amber-700'],
                        'emerald' => ['bg' => 'bg-emerald-50 border-emerald-200', 'text' => 'text-emerald-700', 'badge' => 'border-emerald-200', 'btn' => 'bg-emerald-600 hover:bg-emerald-700'],
                        default => ['bg' => 'bg-gray-50 border-gray-100', 'text' => 'text-gray-700', 'badge' => 'border-gray-200', 'btn' => 'bg-gray-600 hover:bg-gray-700'],
                    };
                @endphp
                @if($res)
                <div class="border-2 rounded-lg p-3 {{ $verdictStyles['bg'] }} shadow-sm">
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="px-2 py-0.5 text-[10px] font-black tracking-wider rounded-full bg-white border {{ $verdictStyles['text'] }} {{ $verdictStyles['badge'] }}">{{ $res['status'] }}</span>
                        <span class="text-xs font-bold text-gray-900">{{ $res['title'] }}</span>
                    </div>
                    <p class="text-[11px] text-gray-700 leading-relaxed font-medium">{{ $res['description'] }}</p>
                    <a href="{{ $res['link'] }}" class="inline-block mt-2.5 px-3 py-1.5 text-[10px] font-bold text-white {{ $verdictStyles['btn'] }} rounded transition w-full text-center">Lihat Laporan Lengkap →</a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
