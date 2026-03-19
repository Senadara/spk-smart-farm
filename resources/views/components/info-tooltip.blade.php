{{--
Info Tooltip — Tooltip penjelasan teks untuk istilah teknis/KPI.
Digunakan untuk tooltip yang berisi penjelasan deskriptif (bukan data sensor).

Pattern: Alpine.js hover, muncul di bawah elemen trigger.

Props:
- $text : string — Teks penjelasan yang ditampilkan
--}}

@props([
    'text' => '',
])

<div x-show="show" x-cloak
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0 translate-y-1"
     x-transition:enter-end="opacity-100 translate-y-0"
     :class="{
         'left-0': align === 'start',
         'right-0': align === 'end',
         'left-1/2 -translate-x-1/2': align === 'center'
     }"
     class="absolute z-50 top-full mt-2 w-64 p-3 bg-white rounded-xl border border-[var(--color-gray-100)] pointer-events-none"
     style="box-shadow: var(--shadow-lg);">
    <p class="text-xs text-[var(--color-gray-600)] leading-relaxed">
        {{ $text }}
    </p>
</div>
