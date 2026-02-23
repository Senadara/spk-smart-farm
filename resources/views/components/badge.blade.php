@props([
    'color' => 'green',
])

@php
    $colorClasses = [
        'green'  => 'badge-green',
        'amber'  => 'badge-amber',
        'orange' => 'badge-orange',
        'red'    => 'badge-red',
        'teal'   => 'badge-teal',
        'blue'   => 'badge-blue',
    ];
    $colorClass = $colorClasses[$color] ?? 'badge-green';
@endphp

<span {{ $attributes->merge(['class' => "badge $colorClass"]) }}>
    {{ $slot }}
</span>
