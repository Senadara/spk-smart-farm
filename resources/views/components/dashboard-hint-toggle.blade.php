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

<span
    x-data="{
        showHints: true,
        infoOpen: false,
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
    class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2 py-1 shadow-sm"
>
    <span class="relative inline-flex">
        <button
            type="button"
            class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-black text-slate-500 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-200"
            aria-label="Fungsi tombol hint indikator"
            @mouseenter="infoOpen = true"
            @mouseleave="infoOpen = false"
            @focus="infoOpen = true"
            @blur="infoOpen = false"
        >
            ?
        </button>
        <span
            x-show="infoOpen"
            x-cloak
            x-transition
            class="absolute right-0 top-8 z-[80] w-60 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs leading-relaxed text-slate-600 shadow-xl"
        >
            Mengatur tampil atau sembunyi ikon penjelasan pada indikator dashboard.
        </span>
    </span>

    <button
        type="button"
        class="relative h-5 w-9 rounded-full transition focus:outline-none focus:ring-2 focus:ring-emerald-200"
        :class="showHints ? 'bg-emerald-500' : 'bg-slate-300'"
        :aria-pressed="showHints.toString()"
        :aria-label="showHints ? 'Sembunyikan hint indikator' : 'Tampilkan hint indikator'"
        :title="showHints ? 'Hint indikator aktif' : 'Hint indikator mati'"
        @click="toggle()"
    >
        <span
            class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition"
            :class="showHints ? 'left-4' : 'left-0.5'"
        ></span>
    </button>
</span>
