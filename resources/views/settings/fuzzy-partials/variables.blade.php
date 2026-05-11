{{-- Tab 1: Variabel & Membership Functions --}}
<div class="space-y-4">
    {{-- Header + Filter Bar --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-gray-900">Variabel & Membership Functions</h2>
        <button @click="modal = 'addVariable'"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90 transition-opacity">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Tambah Variabel
        </button>
    </div>

    {{-- Filter & Search --}}
    <div class="flex flex-wrap items-center gap-3 bg-white rounded-xl px-4 py-3" style="box-shadow: var(--shadow-sm);">
        <div class="flex items-center gap-2">
            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Group:</label>
            <select x-model="varFilterGroup" class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                <option value="all">Semua</option>
                <option value="lingkungan">Lingkungan</option>
                <option value="kesehatan">Kesehatan</option>
                <option value="kausalitas">Kausalitas</option>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe:</label>
            <select x-model="varFilterType" class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                <option value="all">Semua</option>
                <option value="input">Input</option>
                <option value="output">Output</option>
            </select>
        </div>
        <div class="flex items-center gap-2 flex-1 min-w-[200px]">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="varSearch" placeholder="Cari variabel..." class="w-full pl-9 pr-3 py-1.5 rounded-lg border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
            </div>
        </div>
        <div class="flex items-center gap-1">
            <button @click="expandAll()" class="px-2.5 py-1.5 text-xs text-gray-500 hover:text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 cursor-pointer transition-colors" title="Buka Semua">Buka Semua</button>
            <button @click="collapseAll()" class="px-2.5 py-1.5 text-xs text-gray-500 hover:text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 cursor-pointer transition-colors" title="Tutup Semua">Tutup Semua</button>
        </div>
    </div>

    @php
        $groups = $variables->groupBy('group');
        $groupLabels = ['lingkungan' => 'Engine 1 — Lingkungan', 'kesehatan' => 'Engine 2 — Kesehatan', 'kausalitas' => 'Engine 3 — Kausalitas'];
        $groupColors = ['lingkungan' => 'emerald', 'kesehatan' => 'blue', 'kausalitas' => 'purple'];
    @endphp

    @foreach($groupLabels as $groupKey => $groupLabel)
        @if(isset($groups[$groupKey]))
        <div x-show="varFilterGroup === 'all' || varFilterGroup === '{{ $groupKey }}'" class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
            <div class="flex items-center gap-3 mb-4">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide uppercase
                    {{ $groupColors[$groupKey] === 'emerald' ? 'bg-emerald-50 text-emerald-700' : '' }}
                    {{ $groupColors[$groupKey] === 'blue' ? 'bg-blue-50 text-blue-700' : '' }}
                    {{ $groupColors[$groupKey] === 'purple' ? 'bg-purple-50 text-purple-700' : '' }}">
                    {{ $groupKey }}
                </span>
                <h3 class="text-sm font-semibold text-gray-800">{{ $groupLabel }}</h3>
                <span class="text-xs text-gray-400 ml-auto">{{ $groups[$groupKey]->count() }} variabel</span>
            </div>

            @foreach($groups[$groupKey] as $var)
            <div x-show="(varFilterType === 'all' || varFilterType === '{{ $var->type }}') && (varSearch === '' || '{{ strtolower($var->name) }}'.includes(varSearch.toLowerCase()))"
                 data-var-id="{{ $var->id }}"
                 class="border border-gray-100 rounded-xl mb-3 last:mb-0 overflow-hidden">
                {{-- Variable Header --}}
                <div class="flex items-center justify-between px-4 py-3 bg-gray-50/50 cursor-pointer" @click="toggleVar('{{ $var->id }}')">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="expandedVars['{{ $var->id }}'] ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        <div>
                            <span class="text-sm font-semibold text-gray-900">{{ $var->name }}</span>
                            <span class="ml-2 text-xs px-2 py-0.5 rounded-full {{ $var->type === 'input' ? 'bg-sky-50 text-sky-600' : 'bg-orange-50 text-orange-600' }}">{{ $var->type }}</span>
                            @if($var->unit)<span class="ml-1 text-xs text-gray-400">({{ $var->unit }})</span>@endif
                            @if($var->description)<span class="ml-2 text-xs text-gray-400 hidden md:inline">— {{ $var->description }}</span>@endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400">{{ $var->sets->count() }} sets</span>
                        <button @click.stop="openEditVar({{ $var->toJson() }})" class="w-7 h-7 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="Edit variabel">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <form action="{{ route('settings.fuzzy.variables.destroy', $var->id) }}" method="POST" class="inline-block" onsubmit="return confirm('PERINGATAN: Menghapus variabel &quot;{{ $var->name }}&quot; akan menghapus {{ $var->sets->count() }} sets dan semua rules terkait.\n\nLanjutkan?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-7 h-7 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Hapus variabel">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Expanded: Sets + Visualization --}}
                <div x-show="expandedVars['{{ $var->id }}']" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-4 py-4 border-t border-gray-100 space-y-4">
                    {{-- Simple MF Visualization --}}
                    @if($var->sets->count() > 0)
                    <div class="bg-gray-50 rounded-xl p-4">
                        <div class="text-xs font-medium text-gray-500 mb-2">Membership Functions</div>
                        <div class="relative h-32 w-full">
                            @php
                                $allA = $var->sets->pluck('a')->min();
                                $allMax = $var->sets->max(fn($s) => $s->d ?? $s->c);
                                $range = max($allMax - $allA, 1);
                                $colors = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4'];
                            @endphp
                            <svg viewBox="0 0 400 120" class="w-full h-full" preserveAspectRatio="none">
                                <line x1="0" y1="110" x2="400" y2="110" stroke="#e5e7eb" stroke-width="1"/>
                                <line x1="0" y1="10" x2="400" y2="10" stroke="#e5e7eb" stroke-width="0.5" stroke-dasharray="4"/>
                                @foreach($var->sets as $i => $set)
                                    @php
                                        $color = $colors[$i % count($colors)];
                                        $toX = fn($v) => (($v - $allA) / $range) * 380 + 10;
                                        $toY = fn($mu) => 110 - ($mu * 100);
                                    @endphp
                                    @if($set->shape === 'triangle')
                                        <polygon points="{{ $toX($set->a) }},{{ $toY(0) }} {{ $toX($set->b) }},{{ $toY(1) }} {{ $toX($set->c) }},{{ $toY(0) }}" fill="{{ $color }}20" stroke="{{ $color }}" stroke-width="1.5"/>
                                        <text x="{{ $toX($set->b) }}" y="{{ $toY(1) - 4 }}" text-anchor="middle" fill="{{ $color }}" font-size="9" font-weight="600">{{ $set->name }}</text>
                                    @else
                                        <polygon points="{{ $toX($set->a) }},{{ $toY(0) }} {{ $toX($set->b) }},{{ $toY(1) }} {{ $toX($set->c) }},{{ $toY(1) }} {{ $toX($set->d) }},{{ $toY(0) }}" fill="{{ $color }}20" stroke="{{ $color }}" stroke-width="1.5"/>
                                        <text x="{{ $toX(($set->b + $set->c) / 2) }}" y="{{ $toY(1) - 4 }}" text-anchor="middle" fill="{{ $color }}" font-size="9" font-weight="600">{{ $set->name }}</text>
                                    @endif
                                @endforeach
                                <text x="5" y="120" fill="#9ca3af" font-size="8">{{ number_format($allA, 1) }}</text>
                                <text x="385" y="120" fill="#9ca3af" font-size="8" text-anchor="end">{{ number_format($allMax, 1) }}</text>
                            </svg>
                        </div>
                    </div>
                    @endif

                    {{-- Sets Table --}}
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Himpunan Fuzzy</span>
                        <button @click="editSet = { variable_id: '{{ $var->id }}' }; modal = 'addSet'" class="text-xs text-[var(--color-primary)] hover:underline cursor-pointer bg-transparent border-none font-medium">+ Tambah Set</button>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left py-2 px-2 text-xs font-semibold text-gray-400 uppercase">Nama</th>
                                <th class="text-left py-2 px-2 text-xs font-semibold text-gray-400 uppercase">Shape</th>
                                <th class="text-center py-2 px-2 text-xs font-semibold text-gray-400 uppercase">a</th>
                                <th class="text-center py-2 px-2 text-xs font-semibold text-gray-400 uppercase">b</th>
                                <th class="text-center py-2 px-2 text-xs font-semibold text-gray-400 uppercase">c</th>
                                <th class="text-center py-2 px-2 text-xs font-semibold text-gray-400 uppercase">d</th>
                                <th class="text-right py-2 px-2 text-xs font-semibold text-gray-400 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($var->sets as $set)
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="py-2 px-2 font-medium text-gray-800">{{ $set->name }}</td>
                                <td class="py-2 px-2"><code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">{{ $set->shape }}</code></td>
                                <td class="py-2 px-2 text-center text-gray-600">{{ $set->a }}</td>
                                <td class="py-2 px-2 text-center text-gray-600">{{ $set->b }}</td>
                                <td class="py-2 px-2 text-center text-gray-600">{{ $set->c }}</td>
                                <td class="py-2 px-2 text-center text-gray-600">{{ $set->d ?? '-' }}</td>
                                <td class="py-2 px-2 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button @click="openEditSet({{ $set->toJson() }})" class="w-7 h-7 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors" title="Edit set">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <form action="{{ route('settings.fuzzy.sets.destroy', $set->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Hapus set &quot;{{ $set->name }}&quot;?\nSet yang masih digunakan oleh rule tidak bisa dihapus.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="w-7 h-7 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Hapus set">
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
            @endforeach
        </div>
        @endif
    @endforeach
</div>
