 @extends('layouts.app')

 @section('title', 'Perkebunan')
 @section('breadcrumb', 'Perkebunan')

 @section('content')
     <div class="max-w-full space-y-5">

         {{-- ═══════════ SECTION A: Header Bar ═══════════ --}}
         <div class="flex flex-wrap items-center justify-between gap-3">
             <div>
                 <h1 class="text-xl font-bold text-[var(--color-gray-900)]">Monitoring Perkebunan Melon</h1>
                 <p class="text-xs text-[var(--color-gray-400)] mt-0.5">
                     <span class="inline-flex items-center gap-1">
                         <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                         Live monitoring
                     </span>
                     &bull; {{ $kebunStats['aktif'] }} blok kebun aktif
                 </p>
             </div>
             <div
                 class="flex items-center gap-2 px-3 py-2 bg-white border border-[var(--color-gray-200)] rounded-lg text-sm text-[var(--color-gray-600)]">
                 <i data-lucide="calendar" class="w-4 h-4 text-[var(--color-gray-400)]"></i>
                 <span>{{ now()->translatedFormat('l, d F Y') }}</span>
             </div>
         </div>

         {{-- ═══════════ SECTION B: Overview KPI Cards ═══════════ --}}
         @php
             $evaluasiStatusColors = [
                 'selesai' => 'text-emerald-600',
                 'berlangsung' => 'text-amber-600',
                 'draft' => 'text-blue-600',
             ];
             $evaluasiStatusLabels = [
                 'selesai' => 'Selesai',
                 'berlangsung' => 'Berlangsung',
                 'draft' => 'Draft',
             ];
             $evStatus = $evaluasiTerbaru['status'] ?? 'draft';

             // Trend badge for avg preferensi
             $trendConfig = [
                 'up' => ['direction' => 'up', 'status' => 'positive', 'icon' => '↑'],
                 'down' => ['direction' => 'down', 'status' => 'negative', 'icon' => '↓'],
                 'stable' => ['direction' => 'stable', 'status' => 'neutral', 'icon' => '→'],
             ];
             $avgTrend = $trendConfig[$averagePreferensi['trend'] ?? 'stable'] ?? $trendConfig['stable'];

             // Format trend value
             $trendDiff = abs(($averagePreferensi['current'] ?? 0) - ($averagePreferensi['previous'] ?? 0));
             $trendLabel = '';
         @endphp

         <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5 lg:gap-6 mt-4">
             {{-- KPI 1: Blok Kebun Aktif --}}
             <x-perkebunan.stat-card label="Blok Kebun Aktif" :value="$kebunStats['aktif']" :subtitle="'dari ' . $kebunStats['total_blok'] . ' total blok'" icon="layout-grid"
                 iconBg="bg-emerald-50" iconColor="text-emerald-600" />

             {{-- KPI 2: Evaluasi SPK Terbaru --}}
             <x-perkebunan.stat-card label="Evaluasi SPK Terbaru" :value="$evaluasiStatusLabels[$evStatus] ?? 'Draft'" :valueColor="$evaluasiStatusColors[$evStatus] ?? 'text-blue-600'" :subtitle="ucfirst($evaluasiTerbaru['tipe'] ?? '-')"
                 icon="clipboard-check" iconBg="bg-blue-50" iconColor="text-blue-600" />

             {{-- KPI 3: Rata-rata Skor Preferensi --}}
             <div x-data="{ showTooltip: false }" @mouseenter="showTooltip = true" @mouseleave="showTooltip = false"
                 class="relative h-full">
                 <x-perkebunan.stat-card label="Rata-rata Skor Preferensi" :value="number_format($averagePreferensi['current'] ?? 0, 4)" subtitle=""
                     icon="trending-up" iconBg="bg-emerald-50" iconColor="text-emerald-600" :trend="['direction' => $avgTrend['direction'], 'status' => $avgTrend['status']]" />

                 {{-- Tooltip --}}
                 <div x-show="showTooltip" x-cloak x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     class="absolute z-50 top-full mt-2 left-0 right-[-40px] md:right-auto md:w-64 p-3 bg-white border border-[var(--color-gray-100)] rounded-xl pointer-events-none"
                     style="box-shadow: var(--shadow-lg);">
                     <p class="text-[11px] text-[var(--color-gray-600)] leading-relaxed font-medium">
                         Rata-rata skor preferensi TOPSIS dari seluruh blok pada sesi evaluasi terakhir.
                         Rentang 0-1, semakin mendekati 1 semakin baik performa keseluruhan.
                     </p>
                 </div>
             </div>

             {{-- KPI 4: Alert Aktif --}}
             <x-perkebunan.stat-card label="Alert Aktif" :value="$alertSummary['total']" :subtitle="$alertSummary['critical'] . ' kritis, ' . $alertSummary['warning'] . ' peringatan'" icon="bell"
                 iconBg="bg-red-50" iconColor="text-red-600" />
         </div>

         {{-- ═══════════ SECTION C: Distribusi Bobot + Kondisi Greenhouse ═══════════ --}}
         <div class="grid grid-cols-1 lg:grid-cols-5 gap-5 lg:gap-6 mt-6">
             {{-- C-Kiri: Distribusi Bobot Kriteria (3/5) --}}
             <div class="lg:col-span-3">
                 <div
                     class="h-full flex flex-col bg-white rounded-xl p-6 shadow-sm border border-gray-100 transition-all hover:shadow-lg">
                     <div class="flex items-center justify-between mb-5">
                         <h3 class="text-base font-semibold text-gray-900 tracking-tight">Distribusi Bobot Kriteria</h3>
                         <span
                             class="inline-flex items-center px-2.5 py-1 rounded-md bg-gray-50 text-xs font-medium text-gray-500 border border-gray-100">
                             {{ Str::limit($distribusiBobot['sesi_nama'] ?? '', 40) }}
                         </span>
                     </div>

                     {{-- Chart Container --}}
                     <div class="relative flex-1" style="min-height: 250px;">
                         <canvas id="bobotChart"></canvas>
                     </div>

                     {{-- CTA --}}
                     {{-- TODO: [SPK-09] Replace href with actual calculation detail route --}}
                     <div class="mt-5 pt-4 border-t border-gray-100 flex justify-end">
                         <a href="#"
                             class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600 hover:text-emerald-700 transition-colors">
                             Lihat Detail Perhitungan
                             <i data-lucide="chevron-right" class="w-4 h-4"></i>
                         </a>
                     </div>
                 </div>
             </div>

             {{-- C-Kanan: Kondisi Greenhouse (2/5) --}}
             <div class="lg:col-span-2">
                 <div
                     class="h-full flex flex-col bg-white rounded-xl p-6 shadow-sm border border-gray-100 transition-all hover:shadow-lg">
                     <x-perkebunan.greenhouse-grid :data="$kondisiGreenhouse" />
                 </div>
             </div>
         </div>

         {{-- ═══════════ SECTION D: Evaluasi SPK Terbaru ═══════════ --}}
         <div
             class="bg-white rounded-xl p-6 shadow-sm border border-emerald-100 mt-6 relative overflow-hidden transition-all hover:shadow-lg">
             {{-- Decorative pattern overlay --}}
             <div class="absolute right-0 top-0 w-64 h-full pointer-events-none opacity-[0.03]"
                 style="background-image: radial-gradient(circle at right center, var(--color-emerald-600) 0%, transparent 70%);">
             </div>

             <div class="relative z-10">
                 {{-- Header --}}
                 <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                     <h3 class="text-base font-semibold text-gray-900 tracking-tight flex items-center gap-2">
                         <i data-lucide="calculator" class="w-5 h-5 text-emerald-600"></i>
                         Evaluasi SPK Terbaru
                     </h3>
                     @php
                         $tipeBadgeColor = ($evaluasiTerbaru['tipe'] ?? '') === 'produktivitas' ? 'green' : 'blue';
                     @endphp
                     <x-badge :color="$tipeBadgeColor" class="px-2.5 py-1">
                         {{ ucfirst($evaluasiTerbaru['tipe'] ?? '-') }}
                     </x-badge>
                 </div>

                 {{-- Detail list --}}
                 <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3">
                     <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-0.5 sm:gap-2">
                         <dt class="text-sm text-[var(--color-gray-500)]">Nama Sesi</dt>
                         <dd class="text-sm font-medium text-[var(--color-gray-900)]">
                             {{ $evaluasiTerbaru['nama_sesi'] ?? '-' }}</dd>
                     </div>
                     <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-0.5 sm:gap-2">
                         <dt class="text-sm text-[var(--color-gray-500)]">Status</dt>
                         <dd>
                             @php
                                 $statusBadgeColor =
                                     ['selesai' => 'green', 'berlangsung' => 'amber', 'draft' => 'blue'][$evStatus] ??
                                     'blue';
                             @endphp
                             <x-badge :color="$statusBadgeColor">{{ $evaluasiStatusLabels[$evStatus] ?? 'Draft' }}</x-badge>
                         </dd>
                     </div>
                     <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-0.5 sm:gap-2">
                         <dt class="text-sm text-[var(--color-gray-500)]">Periode</dt>
                         <dd class="text-sm font-medium text-[var(--color-gray-900)]">
                             {{ \Carbon\Carbon::parse($evaluasiTerbaru['periode_start'] ?? now())->translatedFormat('d M Y') }}
                             —
                             {{ \Carbon\Carbon::parse($evaluasiTerbaru['periode_end'] ?? now())->translatedFormat('d M Y') }}
                         </dd>
                     </div>
                     <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-0.5 sm:gap-2"
                         x-data="{ showCr: false, align: 'start' }">
                         <dt class="text-sm text-[var(--color-gray-500)] flex items-center gap-1">
                             CR
                             <span @mouseenter="showCr = true" @mouseleave="showCr = false" class="relative cursor-help">
                                 <i data-lucide="help-circle" class="w-3.5 h-3.5 text-[var(--color-gray-400)]"></i>
                                 {{-- CR Tooltip --}}
                                 <div x-show="showCr" x-cloak x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 translate-y-1"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                     class="absolute z-50 bottom-full mb-2 left-0 w-64 p-3 bg-white rounded-xl border border-[var(--color-gray-100)] pointer-events-none"
                                     style="box-shadow: var(--shadow-lg);">
                                     <p class="text-xs text-[var(--color-gray-600)] leading-relaxed">
                                         Consistency Ratio (CR) mengukur konsistensi penilaian perbandingan berpasangan
                                         antar-kriteria.
                                         Nilai CR &lt; 0.10 menunjukkan bahwa penilaian konsisten dan hasilnya dapat
                                         dipercaya.
                                     </p>
                                 </div>
                             </span>
                         </dt>
                         <dd class="text-sm font-medium text-[var(--color-gray-900)]">
                             {{ number_format($evaluasiTerbaru['cr'] ?? 0, 4) }}
                             @if ($evaluasiTerbaru['cr_konsisten'] ?? false)
                                 <x-badge color="green">Konsisten ✓</x-badge>
                             @else
                                 <x-badge color="red">Tidak Konsisten ✗</x-badge>
                             @endif
                         </dd>
                     </div>
                     <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-0.5 sm:gap-2">
                         <dt class="text-sm text-[var(--color-gray-500)]">Dinilai Oleh</dt>
                         <dd class="text-sm font-medium text-[var(--color-gray-900)]">
                             {{ $evaluasiTerbaru['dinilai_oleh'] ?? '-' }}</dd>
                     </div>
                     <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-0.5 sm:gap-2">
                         <dt class="text-sm text-[var(--color-gray-500)]">Alternatif</dt>
                         <dd class="text-sm font-medium text-[var(--color-gray-900)]">
                             {{ $evaluasiTerbaru['jumlah_alternatif'] ?? '-' }} blok kebun</dd>
                     </div>
                 </dl>

                 {{-- Footer --}}
                 {{-- TODO: [SPK-09] Replace href with actual evaluation detail route --}}
                 <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end">
                     <a href="#"
                         class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-600 hover:text-emerald-700 transition-colors">
                         Lihat Detail Evaluasi
                         <i data-lucide="chevron-right" class="w-4 h-4"></i>
                     </a>
                 </div>
             </div>
         </div>

         {{-- ═══════════ SECTION E: Ranking + Ringkasan Rekomendasi ═══════════ --}}
         <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 mt-6 transition-all hover:shadow-lg">
             <div class="flex flex-col gap-6">
                 {{-- Ranking Table Component --}}
                 <div>
                     {{-- Header --}}
                     <div class="flex items-center justify-between mb-5">
                         <h3 class="text-base font-semibold text-gray-900 tracking-tight flex items-center gap-2">
                             <i data-lucide="award" class="w-5 h-5 text-emerald-600"></i>
                             Ranking Blok Kebun Terbaru
                         </h3>
                         @php
                             $rankTipeBadge =
                                 ($rankingTerbaru['sesi_tipe'] ?? '') === 'produktivitas' ? 'green' : 'blue';
                         @endphp
                         <x-badge :color="$rankTipeBadge"
                             class="px-2.5 py-1">{{ ucfirst($rankingTerbaru['sesi_tipe'] ?? '-') }}</x-badge>
                     </div>

                     {{-- Table --}}
                     @if (!empty($rankingTerbaru['items']))
                         <div class="overflow-x-auto">
                             <table class="table w-full">
                                 <thead>
                                     <tr>
                                         <th class="w-16 text-center">#</th>
                                         <th>Blok Kebun</th>
                                         <th class="group">
                                             <div class="flex items-center gap-1.5 w-max" x-data="{ showSkorInfo: false, pos: { bottom: '0px', left: '0px' } }"
                                                 @mouseenter="const iconRect = $refs.helpIcon.getBoundingClientRect(); pos.bottom = Math.round(window.innerHeight - iconRect.top + 8) + 'px'; pos.left = Math.round(iconRect.left + (iconRect.width / 2) - 21) + 'px'; showSkorInfo = true;"
                                                 @mouseleave="showSkorInfo = false">
                                                 Skor Preferensi
                                                 <span x-ref="helpIcon" class="inline-flex">
                                                     <i data-lucide="help-circle"
                                                         class="w-3.5 h-3.5 text-[var(--color-gray-400)] cursor-help"></i>
                                                 </span>
                                                 <template x-teleport="body">
                                                     <div x-show="showSkorInfo" x-cloak
                                                         x-transition:enter="transition ease-out duration-150"
                                                         x-transition:enter-start="opacity-0 translate-y-1"
                                                         x-transition:enter-end="opacity-100 translate-y-0"
                                                         class="fixed z-[100] w-64 p-3.5 bg-white rounded-xl border border-[var(--color-gray-100)] pointer-events-none"
                                                         style="box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);"
                                                         :style="`bottom: ${pos.bottom}; left: ${pos.left};`">
                                                         
                                                         <div class="absolute top-full" style="left: 17px;">
                                                             <div class="w-2.5 h-2.5 bg-white border-b border-r border-[var(--color-gray-100)] rotate-45 -translate-y-1.5"></div>
                                                         </div>

                                                         <div class="space-y-1.5 text-left text-sans leading-none">
                                                             <div class="flex items-center justify-between gap-2">
                                                                 <span class="normal-case text-xs font-semibold text-[var(--color-gray-900)]">Preferensi TOPSIS</span>
                                                             </div>
                                                             <p
                                                                 class="normal-case tracking-normal text-[12px] text-[var(--color-gray-600)] leading-relaxed font-normal whitespace-normal text-left">
                                                                 Skor (0-1) yang menunjukkan kedekatan relatif blok terhadap solusi ideal. Semakin mendekati 1, semakin baik performanya.
                                                             </p>
                                                         </div>
                                                     </div>
                                                 </template>
                                             </div>
                                         </th>
                                         <th>Faktor Dominan</th>
                                         <th class="text-center">Status Keputusan</th>
                                     </tr>
                                 </thead>
                                 <tbody>
                                     @foreach ($rankingTerbaru['items'] as $item)
                                         @php
                                             $isFirst = $item['peringkat'] === 1;
                                             $skorPersen = round(($item['skor'] / 1.0) * 100);

                                             $keputusanConfig = [
                                                 'disetujui' => [
                                                     'color' => 'green',
                                                     'icon' => 'check',
                                                     'label' => 'Disetujui',
                                                 ],
                                                 'ditunda' => [
                                                     'color' => 'amber',
                                                     'icon' => 'pause',
                                                     'label' => 'Ditunda',
                                                 ],
                                                 'ditolak' => [
                                                     'color' => 'red',
                                                     'icon' => 'x',
                                                     'label' => 'Ditolak',
                                                 ],
                                                 'belum_divalidasi' => [
                                                     'color' => 'blue',
                                                     'icon' => 'clock',
                                                     'label' => 'Belum Divalidasi',
                                                 ],
                                             ];
                                             $kConfig =
                                                 $keputusanConfig[$item['status_keputusan']] ??
                                                 $keputusanConfig['belum_divalidasi'];
                                         @endphp
                                         <tr
                                             class="{{ $isFirst ? 'bg-[var(--color-primary-lighter)]/30 border-l-4 border-l-[var(--color-primary)]' : '' }}">
                                             <td class="text-center font-bold text-[var(--color-gray-900)]">
                                                 {{ $item['peringkat'] }}</td>
                                             <td class="font-medium text-[var(--color-gray-900)]">{{ $item['blok'] }}</td>
                                             <td>
                                                 <div class="flex items-center gap-3">
                                                     <span class="text-sm font-semibold text-[var(--color-gray-900)] w-16"
                                                         title="Skor Preferensi TOPSIS">{{ number_format($item['skor'], 4) }}
                                                     </span>
                                                     <div
                                                         class="flex-1 h-1.5 bg-[var(--color-gray-100)] rounded-full overflow-hidden">
                                                         <div class="h-full bg-[var(--color-primary)] rounded-full transition-all"
                                                             style="width: {{ $skorPersen }}%"></div>
                                                     </div>
                                                 </div>
                                             </td>
                                             <td>
                                                 <div class="flex flex-wrap gap-1">
                                                     @foreach ($item['faktor_dominan'] ?? [] as $faktor)
                                                         <span
                                                             class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-[var(--color-gray-100)] text-[var(--color-gray-600)]">
                                                             {{ $faktor }}
                                                         </span>
                                                     @endforeach
                                                 </div>
                                             </td>
                                             <td class="text-center">
                                                 <x-badge :color="$kConfig['color']" class="gap-1.5 px-2.5 py-1">
                                                     <i data-lucide="{{ $kConfig['icon'] }}" class="w-3.5 h-3.5"></i>
                                                     {{ $kConfig['label'] }}
                                                 </x-badge>
                                             </td>
                                         </tr>
                                     @endforeach
                                 </tbody>
                             </table>
                         </div>
                     @else
                         {{-- Empty State --}}
                         <div class="text-center py-10 bg-gray-50 rounded-xl border border-dashed border-gray-200 mt-2">
                             <div
                                 class="mx-auto w-12 h-12 bg-white shadow-sm rounded-full flex items-center justify-center mb-3">
                                 <i data-lucide="bar-chart-3" class="w-6 h-6 text-gray-400"></i>
                             </div>
                             <p class="text-sm font-medium text-gray-600">Belum ada sesi evaluasi yang selesai</p>
                             <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">Ranking akan muncul setelah evaluasi
                                 SPK pertama selesai dan divalidasi oleh manajer.</p>
                         </div>
                     @endif

                     {{-- Ringkasan Rekomendasi --}}
                     <div class="pt-6 mt-2 border-t border-gray-100">
                         <div class="mb-4">
                             <h4 class="text-md font-semibold text-gray-800 flex items-center gap-1.5">
                                 <i data-lucide="lightbulb" class="w-4 h-4 text-amber-500"></i>
                                 Insights Knowledge
                             </h4>
                             <p class="text-xs text-gray-500 mt-1">Rekomendasi tindakan berbasis hasil evaluasi terbaru
                                 untuk blok underperforming.</p>
                         </div>
                         <x-perkebunan.recommendation-card :items="$ringkasanRekomendasi" />
                     </div>
                 </div>
             </div>

             {{-- ═══════════ SECTION F: Alert Aktif ═══════════ --}}
             <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 mt-6 transition-all hover:shadow-lg">
                 <div>
                     {{-- Header --}}
                     <div class="flex items-center justify-between mb-5">
                         <h3 class="text-base font-semibold text-gray-900 tracking-tight flex items-center gap-2">
                             <i data-lucide="bell" class="w-5 h-5 text-red-500"></i>
                             Alert Aktif
                         </h3>
                         <span
                             class="inline-flex items-center justify-center min-w-[24px] px-2 py-0.5 text-xs font-bold rounded-full bg-red-100 text-red-700">
                             {{ $alertSummary['total'] ?? 0 }}
                         </span>
                     </div>

                     {{-- Alert severity summary --}}
                     <div
                         class="flex flex-wrap items-center gap-4 mb-5 px-4 py-3 bg-gray-50 rounded-lg border border-gray-100">
                         <span class="inline-flex items-center gap-2 text-xs font-medium">
                             <span class="w-2.5 h-2.5 rounded-full bg-red-500 shadow-sm border border-red-200"></span>
                             <span class="text-gray-700">{{ $alertSummary['critical'] ?? 0 }} Kritis</span>
                         </span>
                         <span class="inline-flex items-center gap-2 text-xs font-medium">
                             <span class="w-2.5 h-2.5 rounded-full bg-amber-500 shadow-sm border border-amber-200"></span>
                             <span class="text-gray-700">{{ $alertSummary['warning'] ?? 0 }} Peringatan</span>
                         </span>
                         <span class="inline-flex items-center gap-2 text-xs font-medium">
                             <span class="w-2.5 h-2.5 rounded-full bg-blue-500 shadow-sm border border-blue-200"></span>
                             <span class="text-gray-700">{{ $alertSummary['info'] ?? 0 }} Info</span>
                         </span>
                     </div>

                     {{-- Alert List --}}
                     @if (!empty($recentAlerts))
                         <div class="space-y-3">
                             @foreach ($recentAlerts as $alert)
                                 @php
                                     $severityConfig = [
                                         'critical' => [
                                             'bg' => 'bg-red-50',
                                             'border' => 'border-red-200',
                                             'dot' => 'bg-red-500',
                                             'text' => 'text-red-700',
                                         ],
                                         'warning' => [
                                             'bg' => 'bg-amber-50',
                                             'border' => 'border-amber-200',
                                             'dot' => 'bg-amber-500',
                                             'text' => 'text-amber-700',
                                         ],
                                         'info' => [
                                             'bg' => 'bg-blue-50',
                                             'border' => 'border-blue-200',
                                             'dot' => 'bg-blue-500',
                                             'text' => 'text-blue-700',
                                         ],
                                     ];
                                     $sc = $severityConfig[$alert['severity'] ?? 'info'] ?? $severityConfig['info'];
                                 @endphp
                                 <div
                                     class="flex items-start gap-4 p-4 rounded-xl border {{ $sc['bg'] }} {{ $sc['border'] }} transition-all hover:bg-white/80 hover:shadow-sm">
                                     <span
                                         class="w-3 h-3 rounded-full mt-1.5 shrink-0 shadow-sm {{ $sc['dot'] }}"></span>
                                     <div class="flex-1 min-w-0">
                                         <p class="text-sm font-medium text-gray-900 leading-snug">{{ $alert['message'] }}
                                         </p>
                                         <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-2">
                                             <span
                                                 class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 bg-white px-2 py-0.5 border border-gray-200 rounded-md shadow-sm">
                                                 <i data-lucide="map-pin" class="w-3 h-3 text-gray-400"></i>
                                                 {{ $alert['block'] }}
                                             </span>
                                             <span class="inline-flex items-center gap-1.5 text-xs text-gray-500">
                                                 <i data-lucide="clock" class="w-3 h-3 text-gray-400"></i>
                                                 {{ $alert['time'] }}
                                             </span>
                                             @if (!empty($alert['ranking_context']))
                                                 <span
                                                     class="text-[11px] font-bold tracking-wide uppercase px-2 py-0.5 rounded-full bg-white bg-opacity-50 border {{ $sc['border'] }} {{ $sc['text'] }}">
                                                     {{ $alert['ranking_context'] }}
                                                 </span>
                                             @endif
                                         </div>
                                     </div>
                                 </div>
                             @endforeach
                         </div>
                     @else
                         <div class="text-center py-8 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                             <div
                                 class="mx-auto w-12 h-12 bg-white shadow-sm rounded-full flex items-center justify-center mb-3">
                                 <i data-lucide="check-circle" class="w-6 h-6 text-emerald-500"></i>
                             </div>
                             <p class="text-sm font-medium text-gray-700">Tidak ada alert aktif</p>
                             <p class="text-xs text-gray-400 mt-1">Kondisi kebun beserta semua sensor berjalan dengan
                                 normal.</p>
                         </div>
                     @endif
                 </div>
             </div>

         </div>
     @endsection

     @push('scripts')
         <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
         <script>
             document.addEventListener('DOMContentLoaded', function() {
                 // ─── Distribusi Bobot Kriteria — Horizontal Bar Chart ─────────
                 const bobotData = @json($distribusiBobot['items'] ?? []);

                 if (bobotData.length > 0) {
                     const ctx = document.getElementById('bobotChart');
                     if (ctx) {
                         // Generate gradient green colors (darkest for highest weight → lightest for lowest)
                         const greens = [
                             'rgba(46, 125, 50, 0.85)', // darkest
                             'rgba(56, 142, 60, 0.75)',
                             'rgba(76, 175, 80, 0.65)',
                             'rgba(129, 199, 132, 0.60)',
                             'rgba(200, 230, 201, 0.55)', // lightest
                         ];

                         new Chart(ctx, {
                             type: 'bar',
                             data: {
                                 labels: bobotData.map(d => d.kode + ' ' + d.nama),
                                 datasets: [{
                                     data: bobotData.map(d => Math.round(d.bobotAkhir * 100)),
                                     backgroundColor: bobotData.map((_, i) => greens[i] || greens[greens
                                         .length - 1]),
                                     borderColor: bobotData.map((_, i) => greens[i] || greens[greens
                                         .length - 1]),
                                     borderWidth: 1,
                                     borderRadius: 4,
                                     barThickness: 24,
                                 }],
                             },
                             options: {
                                 indexAxis: 'y',
                                 responsive: true,
                                 maintainAspectRatio: false,
                                 plugins: {
                                     legend: {
                                         display: false
                                     },
                                     tooltip: {
                                         backgroundColor: '#ffffff',
                                         titleColor: '#1f2937',
                                         bodyColor: '#4b5563',
                                         borderColor: '#e5e7eb',
                                         borderWidth: 1,
                                         cornerRadius: 8,
                                         padding: 12,
                                         titleFont: {
                                             family: 'Inter',
                                             size: 12,
                                             weight: '600'
                                         },
                                         bodyFont: {
                                             family: 'Inter',
                                             size: 12
                                         },
                                         displayColors: false,
                                         callbacks: {
                                             title: function(tooltipItems) {
                                                 const idx = tooltipItems[0].dataIndex;
                                                 return bobotData[idx].kode + ' ' + bobotData[idx].nama;
                                             },
                                             label: function(context) {
                                                 const idx = context.dataIndex;
                                                 const d = bobotData[idx];
                                                 return [
                                                     'Bobot: ' + d.bobotAkhir.toFixed(4),
                                                     'Tipe: ' + (d.tipe === 'benefit' ? 'Benefit' :
                                                         'Cost'),
                                                     'Sumber: ' + d.spiSumber,
                                                     '',
                                                     'Bobot akhir setelah proses',
                                                     'defuzzifikasi Fuzzy AHP.',
                                                 ];
                                             },
                                         },
                                     },
                                 },
                                 scales: {
                                     x: {
                                         max: 40,
                                         ticks: {
                                             callback: v => v + '%',
                                             font: {
                                                 family: 'Inter',
                                                 size: 12
                                             },
                                             color: '#9ca3af',
                                         },
                                         grid: {
                                             color: 'rgba(0,0,0,0.04)'
                                         },
                                     },
                                     y: {
                                         ticks: {
                                             font: {
                                                 family: 'Inter',
                                                 size: 12
                                             },
                                             color: '#374151',
                                         },
                                         grid: {
                                             display: false
                                         },
                                     },
                                 },
                             },
                         });
                     }
                 }

                 // Re-init Lucide icons for dynamically loaded content
                 if (typeof lucide !== 'undefined') {
                     lucide.createIcons();
                 }
             });
         </script>
     @endpush
