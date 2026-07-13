@props([
    'title' => 'Info indikator',
    'body' => '',
    'formula' => null,
    'source' => null,
])

<span
    class="js-dashboard-hint relative inline-flex shrink-0"
    x-data="{
        open: false,
        pinned: false,
        left: 0,
        top: 0,
        closeTimer: null,
        position() {
            const rect = this.$refs.trigger.getBoundingClientRect();
            const width = 288;
            const margin = 12;
            const gap = 8;
            const estimatedHeight = 180;

            this.left = Math.min(Math.max(margin, rect.right - width), window.innerWidth - width - margin);
            this.top = rect.bottom + gap + estimatedHeight < window.innerHeight
                ? rect.bottom + gap
                : Math.max(margin, rect.top - estimatedHeight - gap);
        },
        show() {
            clearTimeout(this.closeTimer);
            this.open = true;
            this.$nextTick(() => this.position());
        },
        togglePinned() {
            clearTimeout(this.closeTimer);
            if (this.open && this.pinned) {
                this.close();
                return;
            }
            this.pinned = true;
            this.show();
        },
        scheduleClose() {
            if (this.pinned) return;
            clearTimeout(this.closeTimer);
            this.closeTimer = setTimeout(() => this.open = false, 120);
        },
        close() {
            this.open = false;
            this.pinned = false;
        }
    }"
    @resize.window="if (open) position()"
    @scroll.window="if (open) position()"
    @keydown.escape.window="close()"
>
    <button
        x-ref="trigger"
        type="button"
        class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-200 bg-white text-[11px] font-black text-slate-500 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-200"
        aria-label="Info {{ $title }}"
        @click.stop="togglePinned()"
        @mouseenter="if (!pinned) show()"
        @mouseleave="scheduleClose()"
        @focus="if (!pinned) show()"
        @blur="scheduleClose()"
    >
        i
    </button>

    <template x-teleport="body">
        <span
            x-show="open"
            x-transition
            style="display: none;"
            class="fixed z-[90] w-72 rounded-lg border border-slate-200 bg-white p-3 text-left text-xs leading-relaxed text-slate-600 shadow-xl"
            :style="'left: ' + left + 'px; top: ' + top + 'px;'"
            @click.outside="close()"
            @mouseenter="show()"
            @mouseleave="scheduleClose()"
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
    </template>
</span>
