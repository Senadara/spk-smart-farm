{{--
Recommendation Card — Kartu rekomendasi untuk blok kebun bermasalah.

Menampilkan badge "Perhatian" (amber) atau "Kritis" (red),
narasi yang di-compose dari skor + faktor dominan, dan CTA ke SPK-09.

Props:
- $items : array[] — [{ blok, peringkat, skor, badge, narasi }]
--}}

@props([
    'items' => [],
])

@php
    $badgeConfig = [
        'perhatian' => [
            'icon'    => 'alert-triangle',
            'bgBadge' => 'bg-amber-100 text-amber-800',
            'border'  => 'border-amber-200',
            'bg'      => 'bg-amber-50/50',
            'label'   => 'Perhatian',
        ],
        'kritis' => [
            'icon'    => 'alert-circle',
            'bgBadge' => 'bg-red-100 text-red-800',
            'border'  => 'border-red-200',
            'bg'      => 'bg-red-50/50',
            'label'   => 'Kritis',
        ],
    ];
@endphp

@if(!empty($items))
    <div class="mt-5 pt-5 border-t border-[var(--color-gray-100)]">
        <h4 class="text-sm font-semibold text-[var(--color-gray-900)] mb-3">Ringkasan Rekomendasi</h4>
        <div class="grid grid-cols-1 md:grid-cols-{{ min(count($items), 3) }} gap-3">
            @foreach(array_slice($items, 0, 3) as $item)
                @php
                    $cfg = $badgeConfig[$item['badge'] ?? 'perhatian'] ?? $badgeConfig['perhatian'];
                @endphp
                <div class="rounded-xl border p-4 {{ $cfg['border'] }} {{ $cfg['bg'] }}">
                    {{-- Badge + Blok --}}
                    <div class="flex items-center gap-2 mb-2">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold rounded-full {{ $cfg['bgBadge'] }}">
                            <i data-lucide="{{ $cfg['icon'] }}" class="w-3.5 h-3.5"></i>
                            {{ $cfg['label'] }}
                        </span>
                    </div>

                    {{-- Title --}}
                    <p class="text-sm font-semibold text-[var(--color-gray-900)] mb-1">
                        {{ $item['blok'] }} (#{{ $item['peringkat'] }})
                    </p>

                    {{-- Narasi --}}
                    <p class="text-xs text-[var(--color-gray-600)] leading-relaxed mb-3">
                        {{ $item['narasi'] }}
                    </p>

                    {{-- CTA --}}
                    {{-- TODO: [SPK-09] Replace href with actual evaluation detail route --}}
                    <a href="#"
                       class="inline-flex items-center gap-1 text-xs font-medium text-[var(--color-primary)] hover:underline">
                        Lihat Detail
                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            @endforeach
        </div>

        @if(count($items) > 3)
            <div class="mt-3 text-center">
                {{-- TODO: [SPK-09] Replace href with actual evaluation list route --}}
                <a href="#" class="text-xs font-medium text-[var(--color-primary)] hover:underline">
                    Lihat Semua Rekomendasi ({{ count($items) }})
                </a>
            </div>
        @endif
    </div>
@endif
