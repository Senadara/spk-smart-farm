@props([
    'name',
    'label' => null,
    'type' => 'text',
    'placeholder' => '',
    'required' => false,
    'value' => '',
    'icon' => null,
    'autocomplete' => null,
    'autofocus' => false,
])

<div class="mb-4">
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-semibold text-[var(--color-gray-700)] mb-1.5">
            {{ $label }}
            @if ($required)
                <span class="text-[var(--color-danger)]">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        @if ($icon)
            <div class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--color-gray-300)] pointer-events-none">
                {!! $icon !!}
            </div>
        @endif

        <input
            type="{{ $type }}"
            id="{{ $name }}"
            name="{{ $name }}"
            {{ $attributes->merge([
                'class' => 'form-input' . ($icon ? ' pl-11' : '') . ($errors->has($name) ? ' is-invalid' : ''),
            ]) }}
            placeholder="{{ $placeholder }}"
            value="{{ old($name, $value) }}"
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($autofocus) autofocus @endif
            @if ($required) required @endif
        >

        {{ $slot }}
    </div>

    @error($name)
        <div class="flex items-center gap-1.5 mt-1.5 text-[var(--color-danger)] text-xs font-medium">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ $message }}
        </div>
    @enderror
</div>
