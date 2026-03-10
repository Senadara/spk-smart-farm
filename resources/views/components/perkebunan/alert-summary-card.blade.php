{{--
Alert Summary Card — Ringkasan alert aktif berdasarkan severity + daftar alert terbaru.

Props:
- $summary      : array — { total, critical, warning, info }
- $recentAlerts : array — Array of { id, message, severity, block, time }
--}}

@props([
    'summary' => [],
    'recentAlerts' => [],
])

@php
    $total = $summary['total'] ?? 0;
    $critical = $summary['critical'] ?? 0;
    $warning = $summary['warning'] ?? 0;
    $info = $summary['info'] ?? 0;

    // Badge total color: critical > warning > info
    $totalBadgeColor = $critical > 0 ? 'red' : ($warning > 0 ? 'amber' : 'blue');

    $dotColors = [
        'critical' => 'bg-red-500',
        'warning' => 'bg-amber-500',
        'info' => 'bg-blue-500',
    ];
@endphp

<x-card>
    <div>
        @if ($total === 0)
            {{-- Empty State --}}
            <div class="text-center py-4">
                <svg class="w-10 h-10 mx-auto text-emerald-400 mb-2" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm text-[var(--color-gray-400)]">Tidak ada alert aktif</p>
            </div>
        @else
            {{-- Header --}}
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-[var(--color-gray-900)]">Alert Aktif</h3>
                <x-badge :color="$totalBadgeColor">{{ $total }} alert</x-badge>
            </div>

            {{-- Summary Row --}}
            <div class="flex flex-wrap items-center gap-3 sm:gap-4 mb-4">
                {{-- Critical --}}
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                    <span class="text-sm font-semibold text-red-600">{{ $critical }}</span>
                    <span class="text-xs text-[var(--color-gray-400)]">Kritis</span>
                </div>
                {{-- Warning --}}
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    <span class="text-sm font-semibold text-amber-600">{{ $warning }}</span>
                    <span class="text-xs text-[var(--color-gray-400)]">Peringatan</span>
                </div>
                {{-- Info --}}
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    <span class="text-sm font-semibold text-blue-600">{{ $info }}</span>
                    <span class="text-xs text-[var(--color-gray-400)]">Info</span>
                </div>
            </div>

            <hr class="border-[var(--color-gray-100)] my-3">

            {{-- Recent Alerts List --}}
            <div class="space-y-3">
                @foreach (array_slice($recentAlerts, 0, 5) as $alert)
                    @php
                        $alertDot = $dotColors[$alert['severity'] ?? 'info'] ?? $dotColors['info'];
                    @endphp
                    <div
                        class="flex items-start gap-2.5 group cursor-pointer hover:bg-[var(--color-gray-50)] rounded-lg p-2 -mx-2 transition-colors">
                        <span class="w-2 h-2 rounded-full shrink-0 mt-1.5 {{ $alertDot }}"></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-[var(--color-gray-700)] truncate">{{ $alert['message'] }}</p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span
                                    class="text-xs font-medium text-[var(--color-gray-500)]">{{ $alert['block'] }}</span>
                                <span class="text-xs text-[var(--color-gray-400)]">{{ $alert['time'] }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Footer --}}
            {{-- TODO: [ALERT-04] Replace href with actual alert page route --}}
            <div class="mt-4 pt-3 border-t border-[var(--color-gray-100)]">
                <a href="#"
                    class="inline-flex items-center gap-1 text-xs font-medium text-[var(--color-primary)] hover:underline">
                    Lihat Semua Alert
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
        @endif
    </div>
</x-card>
