@props([
    'clickable' => false,
])

<div {{ $attributes->merge([
    'class' => 'card' . ($clickable ? ' card-clickable' : '')
]) }}>
    {{ $slot }}
</div>
