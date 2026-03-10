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
                <svg class="w-4 h-4 text-[var(--color-gray-400)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                    <line x1="16" y1="2" x2="16" y2="6" />
                    <line x1="8" y1="2" x2="8" y2="6" />
                    <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
                <span>{{ now()->translatedFormat('l, d F Y') }}</span>
            </div>
        </div>

        {{-- ═══════════ SECTION B: Stat Cards ═══════════ --}}
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
        @endphp

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            {{-- Blok Kebun Aktif --}}
            <x-perkebunan.stat-card label="Blok Kebun Aktif" :value="$kebunStats['aktif']" :subtitle="'dari ' . $kebunStats['total_blok'] . ' total blok'"
                icon='<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>'
                iconBg="bg-[var(--color-primary-lighter)]" />

            {{-- Total Tanaman --}}
            <x-perkebunan.stat-card label="Total Tanaman" :value="number_format($kebunStats['total_tanaman'])" subtitle="tanaman melon aktif"
                icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>'
                iconBg="bg-amber-50" iconColor="text-amber-500" />

            {{-- Evaluasi Terakhir --}}
            <x-perkebunan.stat-card label="Evaluasi Terakhir" :value="$evaluasiStatusLabels[$evStatus] ?? 'Draft'" :valueColor="$evaluasiStatusColors[$evStatus] ?? 'text-blue-600'" :subtitle="Str::limit($evaluasiTerbaru['nama_sesi'] ?? '-', 50)"
                icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/>'
                iconBg="bg-blue-50" iconColor="text-blue-500" />

            {{-- Alert Aktif --}}
            <x-perkebunan.stat-card label="Alert Aktif" :value="$alertSummary['total']" :subtitle="$alertSummary['critical'] . ' kritis, ' . $alertSummary['warning'] . ' peringatan'"
                icon='<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>'
                iconBg="bg-red-50" iconColor="text-red-500" />
        </div>

        {{-- ═══════════ SECTION C: Evaluasi SPK + Cuaca ═══════════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
            {{-- Evaluasi SPK Terbaru (kiri, 3/5) --}}
            <div class="lg:col-span-3">
                <x-card>
                    <div>
                        {{-- Header --}}
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-[var(--color-gray-900)]">Evaluasi SPK Terbaru</h3>
                            @php
                                $tipeBadgeColor =
                                    ($evaluasiTerbaru['tipe'] ?? '') === 'produktivitas' ? 'green' : 'blue';
                            @endphp
                            <x-badge :color="$tipeBadgeColor">{{ ucfirst($evaluasiTerbaru['tipe'] ?? '-') }}</x-badge>
                        </div>

                        {{-- Detail list --}}
                        <dl class="space-y-3">
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
                                            ['selesai' => 'green', 'berlangsung' => 'amber', 'draft' => 'blue'][
                                                $evStatus
                                            ] ?? 'blue';
                                    @endphp
                                    <x-badge :color="$statusBadgeColor">{{ $evaluasiStatusLabels[$evStatus] ?? 'Draft' }}</x-badge>
                                </dd>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-0.5 sm:gap-2">
                                <dt class="text-sm text-[var(--color-gray-500)]">Tanggal</dt>
                                <dd class="text-sm font-medium text-[var(--color-gray-900)]">
                                    {{ \Carbon\Carbon::parse($evaluasiTerbaru['tanggal'] ?? now())->translatedFormat('d F Y') }}
                                </dd>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-0.5 sm:gap-2">
                                <dt class="text-sm text-[var(--color-gray-500)]">Jumlah Alternatif</dt>
                                <dd class="text-sm font-medium text-[var(--color-gray-900)]">
                                    {{ $evaluasiTerbaru['jumlah_alternatif'] ?? '-' }} blok kebun</dd>
                            </div>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-0.5 sm:gap-2">
                                <dt class="text-sm text-[var(--color-gray-500)]">Dinilai Oleh</dt>
                                <dd class="text-sm font-medium text-[var(--color-gray-900)]">
                                    {{ $evaluasiTerbaru['dinilai_oleh'] ?? '-' }}</dd>
                            </div>
                        </dl>

                        {{-- Footer --}}
                        {{-- TODO: [SPK-09] Replace href with actual evaluation detail route --}}
                        <div class="mt-4 pt-3 border-t border-[var(--color-gray-100)]">
                            <a href="#"
                                class="inline-flex items-center gap-1 text-xs font-medium text-[var(--color-primary)] hover:underline">
                                Lihat Detail Evaluasi
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </x-card>
            </div>

            {{-- Cuaca (kanan, 2/5) --}}
            <div class="lg:col-span-2">
                <x-perkebunan.weather-card :weather="$weather" />
            </div>
        </div>

        {{-- ═══════════ SECTION D: Ranking Terbaru ═══════════ --}}
        <x-card>
            <div>
                {{-- Header --}}
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-[var(--color-gray-900)]">Ranking Blok Kebun Terbaru</h3>
                    @php
                        $rankTipeBadge = ($rankingTerbaru['sesi_tipe'] ?? '') === 'produktivitas' ? 'green' : 'blue';
                    @endphp
                    <x-badge :color="$rankTipeBadge">{{ ucfirst($rankingTerbaru['sesi_tipe'] ?? '-') }}</x-badge>
                </div>

                {{-- Table --}}
                @if (!empty($rankingTerbaru['items']))
                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th class="w-16 text-center">#</th>
                                    <th>Blok Kebun</th>
                                    <th>Skor Preferensi</th>
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
                                                'icon' =>
                                                    '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>',
                                                'label' => 'Disetujui',
                                            ],
                                            'ditunda' => [
                                                'color' => 'amber',
                                                'icon' =>
                                                    '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5"/>',
                                                'label' => 'Ditunda',
                                            ],
                                            'ditolak' => [
                                                'color' => 'red',
                                                'icon' =>
                                                    '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>',
                                                'label' => 'Ditolak',
                                            ],
                                            'belum_divalidasi' => [
                                                'color' => 'blue',
                                                'icon' =>
                                                    '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>',
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
                                                    title="Skor Preferensi TOPSIS">{{ number_format($item['skor'], 4) }}</span>
                                                <div
                                                    class="flex-1 h-1.5 bg-[var(--color-gray-100)] rounded-full overflow-hidden">
                                                    <div class="h-full bg-[var(--color-primary)] rounded-full transition-all"
                                                        style="width: {{ $skorPersen }}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <x-badge :color="$kConfig['color']">
                                                <span class="inline-flex items-center gap-1">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor" stroke-width="2">{!! $kConfig['icon'] !!}</svg>
                                                    {{ $kConfig['label'] }}
                                                </span>
                                            </x-badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Footer --}}
                    {{-- TODO: [SPK-09] Replace href with actual calculation detail route --}}
                    <div class="mt-4 pt-3 border-t border-[var(--color-gray-100)]">
                        <a href="#"
                            class="inline-flex items-center gap-1 text-xs font-medium text-[var(--color-primary)] hover:underline">
                            Lihat Detail Perhitungan
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                @else
                    {{-- Empty State --}}
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 mx-auto text-[var(--color-gray-300)] mb-3" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                        <p class="text-sm text-[var(--color-gray-400)]">Belum ada sesi evaluasi yang selesai</p>
                        <p class="text-xs text-[var(--color-gray-300)] mt-1">Ranking akan muncul setelah evaluasi SPK
                            pertama selesai</p>
                    </div>
                @endif
            </div>
        </x-card>

        {{-- ═══════════ SECTION E: Data Sensor ═══════════ --}}
        {{-- TODO: [IOT-01] BLOCKED — data sensor masih dummy, replace setelah diskusi akses Antares dengan Pak Dwi --}}
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-[var(--color-gray-900)]">Data Sensor Terkini</h2>
                <span class="text-xs text-[var(--color-gray-400)]">
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" />
                        </svg>
                        IoT Antares
                    </span>
                </span>
            </div>

            @if (!empty($sensorData))
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach ($sensorData as $block)
                        <x-perkebunan.sensor-card :block="$block" />
                    @endforeach
                </div>
            @else
                {{-- Empty State --}}
                <x-card>
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 mx-auto text-[var(--color-gray-300)] mb-3" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z" />
                        </svg>
                        <p class="text-sm text-[var(--color-gray-400)]">Belum ada data sensor tersedia</p>
                        <p class="text-xs text-[var(--color-gray-300)] mt-1">Data sensor akan muncul setelah integrasi IoT
                            Antares aktif</p>
                    </div>
                </x-card>
            @endif
        </div>

        {{-- ═══════════ SECTION F: Alert Aktif ═══════════ --}}
        <x-perkebunan.alert-summary-card :summary="$alertSummary" :recentAlerts="$recentAlerts" />

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush
