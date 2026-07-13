@props([
    'title' => 'Info indikator',
    'body' => '',
    'formula' => null,
    'source' => null,
])

<span class="js-dashboard-hint relative inline-flex shrink-0" x-data="{ open: false }">
    <button
        type="button"
        class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-200 bg-white text-[11px] font-black text-slate-500 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-200"
        aria-label="Info {{ $title }}"
        @click.stop="open = !open"
        @mouseenter="open = true"
        @mouseleave="open = false"
        @focus="open = true"
        @blur="open = false"
    >
        i
    </button>

    <span
        x-show="open"
        x-transition
        style="display: none;"
        class="absolute right-0 top-7 z-50 w-72 rounded-lg border border-slate-200 bg-white p-3 text-left text-xs leading-relaxed text-slate-600 shadow-xl"
        @mouseenter="open = true"
        @mouseleave="open = false"
    >
        <span class="block font-bold text-slate-900">{{ $title }}</span>
        @if($body)
            <span class="mt-1 block">{{ $body }}</span>
        @endif
        @if($formula)
            <span class="mt-2 block rounded-md bg-emerald-50 px-2 py-1 font-mono text-[11px] text-emerald-800">
                {{ $formula }}
            </span>
        @endif
        @if($source)
            <span class="mt-2 block text-[11px] font-semibold text-slate-400">
                Sumber: {{ $source }}
            </span>
        @endif
    </span>
</span>
