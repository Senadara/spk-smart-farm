@props([
    'id'    => 'modal',
    'title' => 'Form',
    'size'  => 'md', // sm, md, lg, xl
])

@php
    $sizeMap = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
    ];
    $sizeClass = $sizeMap[$size] ?? $sizeMap['md'];
@endphp

{{-- Backdrop --}}
<div x-show="modal === '{{ $id }}'" x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    @click.self="modal = null" style="display:none;">
    {{-- Modal Content --}}
    <div x-show="modal === '{{ $id }}'" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="bg-white rounded-2xl shadow-xl w-full {{ $sizeClass }} max-h-[90vh] overflow-hidden flex flex-col">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
            <button @click="modal = null"
                class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5 overflow-y-auto flex-1">
            {{ $slot }}
        </div>

        {{-- Footer --}}
        @if (isset($footer))
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
                {{ $footer }}
            </div>
        @else
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
                <button @click="modal = null"
                    class="px-4 py-2 rounded-xl text-sm font-medium text-gray-600 bg-gray-100 border-none cursor-pointer hover:bg-gray-200 transition-colors">
                    Batal
                </button>
                <button type="submit"
                    class="px-4 py-2 rounded-xl text-sm font-medium text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90 transition-opacity">
                    Simpan
                </button>
            </div>
        @endif
    </div>
</div>
