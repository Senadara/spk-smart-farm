@props([
    'title' => 'Panduan Halaman',
    'tone' => 'emerald',
    'open' => true,
])

@php
    $tones = [
        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-900 marker:text-emerald-600',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-900 marker:text-amber-600',
        'sky' => 'border-sky-200 bg-sky-50 text-sky-900 marker:text-sky-600',
        'gray' => 'border-gray-200 bg-gray-50 text-gray-800 marker:text-gray-500',
    ];
    $toneClass = $tones[$tone] ?? $tones['emerald'];
@endphp

<details {{ $open ? 'open' : '' }} {{ $attributes->merge(['class' => 'js-dashboard-page-hint rounded-lg border px-4 py-3 text-sm '.$toneClass]) }}>
    <summary class="cursor-pointer font-bold">{{ $title }}</summary>
    <div class="mt-2 leading-relaxed text-gray-700">
        {{ $slot }}
    </div>
</details>
