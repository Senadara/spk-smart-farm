@props([
    'label' => 'Hint indikator',
])

@once
    <style>
        .dashboard-hints-hidden .js-dashboard-hint,
        .dashboard-hints-hidden .js-dashboard-page-hint {
            display: none !important;
        }
    </style>
@endonce

<div
    x-data="{
        showHints: true,
        init() {
            this.showHints = localStorage.getItem('smartfarm.dashboardHints') !== 'hidden';
            this.apply();
        },
        toggle() {
            this.showHints = !this.showHints;
            localStorage.setItem('smartfarm.dashboardHints', this.showHints ? 'shown' : 'hidden');
            this.apply();
        },
        apply() {
            document.documentElement.classList.toggle('dashboard-hints-hidden', !this.showHints);
        }
    }"
    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm"
>
    <span>{{ $label }}</span>
    <button
        type="button"
        class="relative h-5 w-9 rounded-full transition"
        :class="showHints ? 'bg-emerald-500' : 'bg-slate-300'"
        :aria-pressed="showHints.toString()"
        @click="toggle()"
    >
        <span
            class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition"
            :class="showHints ? 'left-4' : 'left-0.5'"
        ></span>
    </button>
    <span class="min-w-[46px] text-[11px] text-slate-400" x-text="showHints ? 'Aktif' : 'Mati'"></span>
</div>
