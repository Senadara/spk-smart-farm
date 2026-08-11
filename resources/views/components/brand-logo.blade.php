@props([
    'showText' => true,
    'logoClass' => 'h-10 w-10',
    'textClass' => 'text-lg font-bold text-[var(--color-gray-900)]',
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <div class="{{ $logoClass }} shrink-0">
        <img
            src="{{ asset('assets/brand/spk-smart-farm-logo.png') }}"
            alt="SPK Smart Farm"
            class="h-full w-full object-contain object-center"
        >
    </div>

    @if($showText)
        <div class="{{ $textClass }}">
            SPK <span class="text-[var(--color-primary)]">Smart Farm</span>
        </div>
    @endif
</div>
