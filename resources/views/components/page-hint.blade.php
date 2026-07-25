@props([
    'title' => 'Panduan Halaman',
    'tone' => 'emerald',
    'open' => true,
])

@php
    $tones = [
        'emerald' => 'border-emerald-100 bg-emerald-50/70 text-emerald-900 marker:text-emerald-600',
        'amber' => 'border-amber-100 bg-amber-50/70 text-amber-900 marker:text-amber-600',
        'sky' => 'border-sky-100 bg-sky-50/70 text-sky-900 marker:text-sky-600',
        'gray' => 'border-gray-200 bg-gray-50/80 text-gray-800 marker:text-gray-500',
    ];
    $toneClass = $tones[$tone] ?? $tones['emerald'];
@endphp

<details {{ $open ? 'open' : '' }} {{ $attributes->merge(['class' => 'js-dashboard-page-hint rounded-lg border px-4 py-3 text-xs sm:text-sm '.$toneClass]) }}>
    <summary class="cursor-pointer font-semibold">{{ $title }}</summary>
    <div class="mt-2 leading-relaxed text-gray-600">
        {{ $slot }}
    </div>
</details>
