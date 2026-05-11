{{-- Modal: Buat Sesi Evaluasi Baru (SPK-02) --}}
<div x-data="createSesiModal()"
     x-on:open-create-modal.window="openModal()"
     x-show="open"
     x-cloak
     class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-end="opacity-0"
     @keydown.escape.window="open = false">

    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         @click.stop>

        {{-- Modal Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-base font-bold text-gray-900">Buat Sesi Evaluasi Baru</h2>
            <button @click="open = false" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- Modal Body --}}
        <form method="POST" action="{{ route('spk-melon.sesi-penilaian.store') }}" @submit="onSubmit($event)" class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
            @csrf

            {{-- Nama Sesi --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Nama Sesi <span class="text-red-500">*</span>
                </label>
                <input type="text" name="namaSesi" x-model="form.namaSesi"
                    required maxlength="150"
                    placeholder="cth: Evaluasi Produktivitas Periode Mei-Juli 2026"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-shadow">
                @error('namaSesi') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Tipe Evaluasi --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Tipe Evaluasi <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <select name="tipeEvaluasi" x-model="form.tipeEvaluasi" required
                            @change="loadKriteria()"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-shadow appearance-none bg-white pr-10">
                        <option value="">Pilih tipe evaluasi...</option>
                        <option value="produktivitas">Produktivitas (siklus tanam)</option>
                        <option value="kualitas">Kualitas (pasca-panen)</option>
                    </select>
                    <i data-lucide="chevron-down" class="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                </div>
                @error('tipeEvaluasi') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Periode --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Tanggal Mulai <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="periodeMulai" x-model="form.periodeMulai" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-shadow">
                    @error('periodeMulai') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Tanggal Selesai <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="periodeSelesai" x-model="form.periodeSelesai" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-shadow">
                    @error('periodeSelesai') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Helper Text Durasi --}}
            <div x-show="form.tipeEvaluasi" class="flex items-start gap-2 text-xs text-gray-500 bg-gray-50 rounded-lg p-3 border border-gray-100">
                <i data-lucide="info" class="w-3.5 h-3.5 text-gray-400 shrink-0 mt-0.5"></i>
                <div>
                    <span x-show="form.tipeEvaluasi === 'produktivitas'">
                        Periode tipikal evaluasi <em>produktivitas</em>: 60-90 hari (siklus tanam melon penuh).
                    </span>
                    <span x-show="form.tipeEvaluasi === 'kualitas'">
                        Periode tipikal evaluasi <em>kualitas</em>: 1-7 hari (pengukuran pasca-panen).
                    </span>
                    <span x-show="durasiHari > 0" class="font-medium text-gray-700 ml-1">
                        Durasi terpilih: <strong x-text="durasiHari"></strong> hari.
                    </span>
                </div>
            </div>

            {{-- Catatan --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Catatan
                    <span class="text-xs text-gray-400 font-normal ml-1">(opsional)</span>
                </label>
                <textarea name="catatanSesi" x-model="form.catatanSesi"
                    rows="2" maxlength="1000"
                    placeholder="Tambahkan catatan terkait sesi ini (opsional)"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-shadow resize-none"></textarea>
            </div>

            {{-- Live Preview Kriteria --}}
            <div x-show="form.tipeEvaluasi" x-transition
                 class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                    <i data-lucide="list-checks" class="w-4 h-4 text-gray-400"></i>
                    Pratinjau Kriteria yang Akan Dimuat
                    <span x-show="loadingKriteria" class="text-xs text-gray-400 italic">(memuat...)</span>
                </h3>
                <p class="text-xs text-gray-500 mb-3">
                    Kriteria berikut akan otomatis dimuat ke sesi berdasarkan tipe evaluasi yang dipilih.
                </p>

                {{-- Loading state --}}
                <template x-if="loadingKriteria">
                    <div class="space-y-2 animate-pulse">
                        <div class="h-3 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                        <div class="h-3 bg-gray-200 rounded w-2/3"></div>
                    </div>
                </template>

                {{-- Empty state --}}
                <template x-if="!loadingKriteria && kriteriaList.length === 0 && form.tipeEvaluasi">
                    <div class="flex items-start gap-2 text-sm text-red-600 bg-red-50 rounded-lg p-3 border border-red-100">
                        <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0 mt-0.5"></i>
                        <span>
                            Belum ada kriteria untuk tipe evaluasi ini. Silakan tambahkan minimal 2 kriteria
                            di menu <a href="{{ route('spk-melon.kriteria.index') }}" class="underline font-semibold">Kriteria SPK</a> terlebih dahulu.
                        </span>
                    </div>
                </template>

                {{-- Kriteria list --}}
                <template x-if="!loadingKriteria && kriteriaList.length > 0">
                    <div>
                        <ul class="text-sm space-y-1.5">
                            <template x-for="k in kriteriaList" :key="k.id">
                                <li class="flex items-center gap-2 flex-wrap py-1">
                                    <code class="text-xs bg-white border border-gray-200 px-2 py-0.5 rounded font-mono font-bold" x-text="k.kode"></code>
                                    <span x-text="k.nama" class="text-gray-700"></span>
                                    <span class="text-xs px-2 py-0.5 rounded-full text-white font-bold"
                                          :class="k.tipe === 'benefit' ? 'bg-emerald-500' : 'bg-red-400'"
                                          x-text="k.tipe === 'benefit' ? 'Benefit' : 'Cost'"></span>
                                    <span class="text-xs text-gray-400" x-text="'(' + k.kategori + ')'"></span>
                                    <span x-show="k.spiSumber" class="text-xs text-gray-400 ml-auto">
                                        <code class="bg-white border border-gray-200 px-1 py-0.5 rounded" x-text="k.spiSumber"></code>
                                    </span>
                                </li>
                            </template>
                        </ul>
                        <div class="mt-3 pt-3 border-t border-gray-200 text-xs">
                            <span x-show="kriteriaList.length >= 2" class="text-emerald-600 font-medium flex items-center gap-1">
                                <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                                <span x-text="kriteriaList.length + ' kriteria siap dimuat'"></span>
                            </span>
                            <span x-show="kriteriaList.length < 2" class="text-red-600 font-medium flex items-center gap-1">
                                <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                                Minimum 2 kriteria diperlukan
                            </span>
                        </div>
                    </div>
                </template>
            </div>
        </form>

        {{-- Modal Footer --}}
        <div class="flex gap-3 justify-end px-6 py-4 border-t border-gray-100">
            <button type="button" @click="open = false"
                class="px-5 py-2.5 border-2 border-gray-200 text-gray-600 font-semibold rounded-xl text-sm min-h-[44px] hover:border-gray-300 hover:bg-gray-50 transition-all">
                Batal
            </button>
            <button type="button"
                @click="$el.closest('[x-data]').querySelector('form').requestSubmit()"
                :disabled="!canSubmit || submitting"
                class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-sm min-h-[44px] shadow-sm transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                x-text="submitting ? 'Menyimpan...' : 'Simpan Sesi'">
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function createSesiModal() {
    return {
        open: false,
        submitting: false,
        loadingKriteria: false,
        kriteriaList: [],
        form: {
            namaSesi: '',
            tipeEvaluasi: '',
            periodeMulai: '',
            periodeSelesai: '',
            catatanSesi: '',
        },

        openModal() {
            this.open = true;
            this.submitting = false;
            this.kriteriaList = [];
            this.form = {
                namaSesi: '',
                tipeEvaluasi: '',
                periodeMulai: '',
                periodeSelesai: '',
                catatanSesi: '',
            };
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        },

        get durasiHari() {
            if (!this.form.periodeMulai || !this.form.periodeSelesai) return 0;
            const a = new Date(this.form.periodeMulai);
            const b = new Date(this.form.periodeSelesai);
            const diff = Math.floor((b - a) / (1000 * 60 * 60 * 24)) + 1;
            return diff > 0 ? diff : 0;
        },

        get canSubmit() {
            return this.form.namaSesi
                && this.form.tipeEvaluasi
                && this.form.periodeMulai
                && this.form.periodeSelesai
                && this.kriteriaList.length >= 2
                && !this.submitting;
        },

        async loadKriteria() {
            if (!this.form.tipeEvaluasi) {
                this.kriteriaList = [];
                return;
            }
            this.loadingKriteria = true;
            try {
                const url = `/spk-melon/kriteria/tipe-evaluasi/${this.form.tipeEvaluasi}`;
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                this.kriteriaList = json.data || [];
            } catch (e) {
                console.error('Gagal memuat kriteria:', e);
                this.kriteriaList = [];
            } finally {
                this.loadingKriteria = false;
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                });
            }
        },

        onSubmit(event) {
            // Soft warning periode di luar rentang tipikal
            const d = this.durasiHari;
            const t = this.form.tipeEvaluasi;
            let outOfRange = false;
            let pesan = '';

            if (t === 'produktivitas' && (d < 60 || d > 90)) {
                outOfRange = true;
                pesan = `Durasi periode (${d} hari) di luar rentang tipikal evaluasi produktivitas (60-90 hari). Lanjutkan?`;
            } else if (t === 'kualitas' && (d < 1 || d > 7)) {
                outOfRange = true;
                pesan = `Durasi periode (${d} hari) di luar rentang tipikal evaluasi kualitas (1-7 hari). Lanjutkan?`;
            }

            if (outOfRange && !confirm(pesan)) {
                event.preventDefault();
                return false;
            }

            this.submitting = true;
            // Biarkan form submit normal
        },
    };
}
</script>
@endpush
