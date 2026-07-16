@php
    $groups = $variables->groupBy('group');
    $groupLabels = [
        'lingkungan' => 'Engine 1 - Lingkungan',
        'kesehatan' => 'Engine 2 - Produktivitas',
        'kausalitas' => 'Engine 3 - Kausalitas',
    ];
    $groupHints = [
        'lingkungan' => 'Input sensor seperti suhu, kelembapan, dan amonia. Outputnya status lingkungan.',
        'kesehatan' => 'Input produktivitas seperti HDP, pakan, dan mortalitas. Outputnya indeks kesehatan.',
        'kausalitas' => 'Input label dari engine sebelumnya. Outputnya diagnosis dan arah tindakan.',
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
                <h2 class="text-base font-semibold text-gray-900">Variabel & Membership Function</h2>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                    Mulai dari variabel input/output, lalu lengkapi himpunan fuzzy. Output tiap engine akan dipakai sebagai bahan rule berikutnya.
                </p>
            </div>
            <button type="button" @click="modal = 'addVariable'"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah Variabel
            </button>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-[auto_auto_minmax(220px,1fr)_auto] lg:items-center">
            <label class="flex flex-col gap-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Engine</span>
                <select x-model="varFilterGroup" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm transition-all focus:border-[var(--color-primary)] focus:outline-none">
                    <option value="all">Semua engine</option>
                    <option value="lingkungan">Engine 1 - Lingkungan</option>
                    <option value="kesehatan">Engine 2 - Produktivitas</option>
                    <option value="kausalitas">Engine 3 - Kausalitas</option>
                </select>
            </label>
            <label class="flex flex-col gap-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Tipe</span>
                <select x-model="varFilterType" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm transition-all focus:border-[var(--color-primary)] focus:outline-none">
                    <option value="all">Semua tipe</option>
                    <option value="input">Input</option>
                    <option value="output">Output</option>
                </select>
            </label>
            <label class="flex flex-col gap-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Cari</span>
                <span class="relative">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="varSearch" placeholder="Cari nama atau deskripsi variabel..." class="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm transition-all focus:border-[var(--color-primary)] focus:outline-none">
                </span>
            </label>
            <div class="flex gap-2 lg:self-end">
                <button type="button" @click="expandAll()" class="flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-100 lg:flex-none">Buka semua</button>
                <button type="button" @click="collapseAll()" class="flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-100 lg:flex-none">Tutup semua</button>
            </div>
        </div>
    </div>

    @if($variables->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-200 bg-white p-8 text-center">
            <h3 class="text-sm font-semibold text-gray-900">Belum ada variabel pada profil ini</h3>
            <p class="mt-1 text-sm text-gray-500">Tambahkan variabel input dan output sebelum membuat membership function dan rule.</p>
            <button type="button" @click="modal = 'addVariable'" class="mt-4 rounded-lg bg-[var(--color-primary)] px-4 py-2.5 text-sm font-semibold text-white">Tambah Variabel</button>
        </div>
    @endif

    @foreach($groupLabels as $groupKey => $groupLabel)
        @if(isset($groups[$groupKey]))
            <section x-show="varFilterGroup === 'all' || varFilterGroup === '{{ $groupKey }}'" class="rounded-2xl border border-gray-100 bg-white p-4 md:p-5" style="box-shadow: var(--shadow-sm);">
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
                    <div class="flex gap-2 text-xs font-medium text-gray-500">
                        <span class="rounded-full bg-gray-100 px-2.5 py-1">{{ $groups[$groupKey]->count() }} variabel</span>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1">{{ $groups[$groupKey]->sum(fn ($v) => $v->sets->count()) }} set</span>
                    </div>
                </div>

                <div class="space-y-3">
                    @foreach($groups[$groupKey] as $var)
                        <article
                            x-show="(varFilterType === 'all' || varFilterType === '{{ $var->type }}') && (varSearch === '' || @js(strtolower($var->name . ' ' . ($var->description ?? ''))).includes(varSearch.toLowerCase()))"
                            data-var-id="{{ $var->id }}"
                            class="overflow-hidden rounded-xl border border-gray-100 bg-white">
                            <div class="flex cursor-pointer flex-col gap-3 bg-gray-50/70 px-4 py-3 transition-colors hover:bg-gray-50 sm:flex-row sm:items-center sm:justify-between" @click="toggleVar('{{ $var->id }}')">
                                <div class="flex min-w-0 items-start gap-3">
                                    <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-400 transition-transform duration-200" :class="expandedVars['{{ $var->id }}'] ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-mono text-sm font-bold text-gray-900">{{ $var->name }}</span>
                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold uppercase {{ $var->type === 'input' ? 'bg-sky-50 text-sky-700' : 'bg-orange-50 text-orange-700' }}">{{ $var->type }}</span>
                                            @if($var->unit)
                                                <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-gray-500">{{ $var->unit }}</span>
                                            @endif
                                        </div>
                                        @if($var->description)
                                            <p class="mt-1 text-xs leading-5 text-gray-500">{{ $var->description }}</p>
                                        @else
                                            <p class="mt-1 text-xs leading-5 text-gray-400">Belum ada deskripsi variabel.</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap">
                                    <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-gray-500">{{ $var->sets->count() }} set</span>
                                    <button type="button" @click.stop="openEditVar({{ $var->toJson() }})" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-blue-50 hover:text-blue-600" title="Edit variabel">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <form action="{{ route('settings.fuzzy.variables.destroy', $var->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Menghapus variabel {{ $var->name }} juga menghapus membership dan rule terkait. Lanjutkan?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600" title="Hapus variabel">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div x-show="expandedVars['{{ $var->id }}']" x-cloak x-transition class="space-y-4 border-t border-gray-100 px-4 py-4">
                                @if($var->sets->count() > 0)
                                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                                        <div class="mb-2 flex items-center justify-between gap-3">
                                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Grafik membership</div>
                                            <div class="text-xs text-gray-400">Rentang nilai mengikuti titik terkecil dan terbesar</div>
                                        </div>
                                        <div class="relative h-32 w-full">
                                            @php
                                                $allA = $var->sets->pluck('a')->min();
                                                $allMax = $var->sets->max(fn($s) => $s->d ?? $s->c);
                                                $range = max($allMax - $allA, 1);
                                                $colors = ['#10b981', '#0ea5e9', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4'];
                                            @endphp
                                            <svg viewBox="0 0 400 120" class="h-full w-full" preserveAspectRatio="none">
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
                                @else
                                    <div class="rounded-xl border border-dashed border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                                        Variabel ini belum memiliki membership function. Tambahkan minimal satu set agar bisa digunakan dalam rule.
                                    </div>
                                @endif

                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Himpunan fuzzy</span>
                                        <p class="mt-1 text-xs text-gray-400">Gunakan urutan titik a, b, c, d dari kiri ke kanan.</p>
                                    </div>
                                    <button type="button" @click="editSet = { variable_id: '{{ $var->id }}' }; modal = 'addSet'" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-100">Tambah Set</button>
                                </div>

                                <div class="overflow-x-auto rounded-xl border border-gray-100">
                                    <table class="min-w-[680px] w-full text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-400">Nama</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-400">Shape</th>
                                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase text-gray-400">a</th>
                                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase text-gray-400">b</th>
                                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase text-gray-400">c</th>
                                                <th class="px-3 py-2 text-center text-xs font-semibold uppercase text-gray-400">d</th>
                                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-400">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($var->sets as $set)
                                                <tr class="border-t border-gray-100 transition-colors hover:bg-gray-50/70">
                                                    <td class="px-3 py-2 font-semibold text-gray-800">{{ $set->name }}</td>
                                                    <td class="px-3 py-2"><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $set->shape }}</code></td>
                                                    <td class="px-3 py-2 text-center text-gray-600">{{ $set->a }}</td>
                                                    <td class="px-3 py-2 text-center text-gray-600">{{ $set->b }}</td>
                                                    <td class="px-3 py-2 text-center text-gray-600">{{ $set->c }}</td>
                                                    <td class="px-3 py-2 text-center text-gray-600">{{ $set->d ?? '-' }}</td>
                                                    <td class="px-3 py-2">
                                                        <div class="flex items-center justify-end gap-1">
                                                            <button type="button" @click="openEditSet({{ $set->toJson() }})" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-blue-50 hover:text-blue-600" title="Edit set">
                                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                            </button>
                                                            <form action="{{ route('settings.fuzzy.sets.destroy', $set->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Hapus set {{ $set->name }}? Set yang masih digunakan rule tidak bisa dihapus.');">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600" title="Hapus set">
                                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-500">Belum ada set untuk variabel ini.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
</div>
