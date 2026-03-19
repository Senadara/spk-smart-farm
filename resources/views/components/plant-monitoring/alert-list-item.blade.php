{{-- resources/views/components/plant-monitoring/alert-list-item.blade.php --}}
@props(['alert', 'isLast' => false])

@php
    // Mapping severity ke warna dan ikon
    $severityConfig = [
        'critical' => [
            'bg'    => 'bg-red-50',
            'icon'  => 'bg-red-100 text-red-600',
            'badge' => 'bg-red-100 text-red-700',
            'label' => 'CRITICAL',
            'svg'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01"/>',
        ],
        'warning' => [
            'bg'    => 'bg-yellow-50',
            'icon'  => 'bg-yellow-100 text-yellow-600',
            'badge' => 'bg-yellow-100 text-yellow-700',
            'label' => 'WARNING',
            'svg'   => '<circle cx="12" cy="12" r="10" stroke-linecap="round" stroke-linejoin="round"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4M12 16h.01"/>',
        ],
        'info' => [
            'bg'    => 'bg-blue-50',
            'icon'  => 'bg-blue-100 text-blue-600',
            'badge' => 'bg-blue-100 text-blue-700',
            'label' => 'INFO',
            'svg'   => '<circle cx="12" cy="12" r="10" stroke-linecap="round" stroke-linejoin="round"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4M12 8h.01"/>',
        ],
    ];

    $config = $severityConfig[$alert->severity] ?? $severityConfig['info'];

    $statusLabel = match($alert->status) {
        'belum_dibaca' => 'Belum Dibaca',
        'dibaca' => 'Dibaca',
        'ditindaklanjuti' => 'Ditindaklanjuti',
        default => $alert->status,
    };

    $statusColor = match($alert->status) {
        'belum_dibaca' => 'bg-red-100 text-red-700',
        'dibaca' => 'bg-yellow-100 text-yellow-700',
        'ditindaklanjuti' => 'bg-green-100 text-green-700',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp

<div class="flex items-start gap-4 p-4 {{ $config['bg'] }} {{ !$isLast ? 'border-b border-gray-100' : '' }}">
    {{-- Severity Icon --}}
    <div class="w-10 h-10 rounded-full {{ $config['icon'] }} flex items-center justify-center flex-shrink-0 mt-0.5">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">{!! $config['svg'] !!}</svg>
    </div>

    {{-- Content --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 mb-1 flex-wrap">
            {{-- Severity Badge --}}
            <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $config['badge'] }}">
                {{ $config['label'] }}
            </span>
            {{-- Nama Aturan --}}
            <span class="text-sm font-semibold text-gray-800">{{ $alert->namaAturan }}</span>
            {{-- Tipe Alert --}}
            <span class="text-xs text-gray-400">
                ({{ $alert->tipeAlert === 'sensor' ? 'Sensor' : 'Evaluasi SPK' }})
            </span>
        </div>

        {{-- Blok Kebun + Nilai Aktual --}}
        <p class="text-sm text-gray-600 mb-1">
            {{ $alert->namaBlok }} — Nilai aktual: <span class="font-semibold">{{ $alert->nilai_aktual }}</span>
        </p>

        {{-- Timestamp + Status --}}
        <div class="flex items-center gap-3 text-xs text-gray-400">
            <span>{{ \Carbon\Carbon::parse($alert->createdAt)->translatedFormat('d M Y, H:i') }}</span>
            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                {{ $statusLabel }}
            </span>
        </div>
    </div>
</div>
