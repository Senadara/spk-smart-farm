@props([
    'variant' => 'primary',
    'type' => 'submit',
    'block' => false,
    'loading' => false,
    'href' => null,
])

@php
    $variantClasses = [
        'primary' => 'btn-primary',
        'secondary' => 'btn-secondary',
        'danger' => 'btn-danger',
    ];
    $classes = 'btn ' . ($variantClasses[$variant] ?? 'btn-primary');
    if ($block) $classes .= ' btn-block';
    if ($loading) $classes .= ' btn-loading';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
