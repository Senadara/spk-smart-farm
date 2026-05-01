{{--
Modal Detail Laporan (DASH-03 Redesin) — Menampilkan detail laporan berdasarkan jenis.
Reusable: satu komponen untuk semua layout jenis laporan.

Konteks Alpine.js: Mengandalkan parent x-data yang memiliki:
- showModal: boolean
- selectedItem: object { tanggal, blok, jenis, tipe, detail: {...} }

Semua 6 layout tersedia:
- Laporan Harian, Tanaman Sakit, Hama Tanaman, Hasil Panen, Pemberian Nutrisi, Tanaman Mati
--}}

{{-- Backdrop Overlay --}}
<div x-show="showModal"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[60]"
     @click="showModal = false"
     x-cloak>
</div>

{{-- Modal Container --}}
<div x-show="showModal"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-4 scale-95"
     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
     x-transition:leave-end="opacity-0 translate-y-4 scale-95"
     class="fixed inset-0 z-[70] flex items-center justify-center p-4"
     @click.self="showModal = false"
     x-cloak>

    <div class="bg-white rounded-2xl shadow-2xl border border-[var(--color-gray-100)] w-full max-w-2xl max-h-[85vh] overflow-y-auto">

        {{-- Modal Header --}}
        <div class="sticky top-0 bg-white/95 backdrop-blur-sm border-b border-[var(--color-gray-100)] px-6 py-4 rounded-t-2xl z-10">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                         :class="{
                            'bg-green-100 text-green-700': selectedItem?.jenis === 'Laporan Harian',
                            'bg-amber-100 text-amber-700': selectedItem?.jenis === 'Tanaman Sakit',
                            'bg-red-100 text-red-700': selectedItem?.jenis === 'Tanaman Mati',
                            'bg-orange-100 text-orange-700': selectedItem?.jenis === 'Hama Tanaman',
                            'bg-blue-100 text-blue-700': selectedItem?.jenis === 'Hasil Panen',
                            'bg-teal-100 text-teal-700': selectedItem?.jenis === 'Pemberian Nutrisi',
                         }">
                        <template x-if="selectedItem?.jenis === 'Laporan Harian'"><i data-lucide="leaf" class="w-4.5 h-4.5"></i></template>
                        <template x-if="selectedItem?.jenis === 'Tanaman Sakit'"><i data-lucide="heart-crack" class="w-4.5 h-4.5"></i></template>
                        <template x-if="selectedItem?.jenis === 'Tanaman Mati'"><i data-lucide="skull" class="w-4.5 h-4.5"></i></template>
                        <template x-if="selectedItem?.jenis === 'Hama Tanaman'"><i data-lucide="bug" class="w-4.5 h-4.5"></i></template>
                        <template x-if="selectedItem?.jenis === 'Hasil Panen'"><i data-lucide="package-check" class="w-4.5 h-4.5"></i></template>
                        <template x-if="selectedItem?.jenis === 'Pemberian Nutrisi'"><i data-lucide="flask-conical" class="w-4.5 h-4.5"></i></template>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-[var(--color-gray-900)]"
                            x-text="'Detail ' + (selectedItem?.jenis || 'Laporan')"></h3>
                        <p class="text-xs text-[var(--color-gray-500)]"
                           x-text="selectedItem?.blok + ' — ' + (selectedItem?.tanggal ? new Date(selectedItem.tanggal).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : '')"></p>
                    </div>
                </div>
                <button @click="showModal = false"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-[var(--color-gray-400)] hover:bg-[var(--color-gray-100)] hover:text-[var(--color-gray-600)] transition-colors">
                    <i data-lucide="x" class="w-4.5 h-4.5"></i>
                </button>
            </div>
        </div>

        {{-- Modal Body --}}
        <div class="px-6 py-5 space-y-6">

            {{-- ═══════════ LAYOUT: Laporan Harian ═══════════ --}}
            <template x-if="selectedItem?.jenis === 'Laporan Harian'">
                <div class="space-y-5">
                    {{-- Informasi Laporan --}}
                    <div>
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Informasi Laporan</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Kode Tanaman</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.kode_tanaman || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Nama Tanaman</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.nama_tanaman || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Lokasi</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.blok || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Pelapor</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.pelapor || '-'"></p>
                            </div>
                        </div>
                    </div>

                    {{-- Status Perawatan --}}
                    <div>
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Status Perawatan</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <template x-for="item in [
                                { label: 'Penyiraman', key: 'penyiraman' },
                                { label: 'Pruning', key: 'pruning' },
                                { label: 'Pemberian Nutrisi', key: 'nutrisi' },
                                { label: 'Repotting', key: 'repotting' }
                            ]">
                                <div class="flex items-center justify-between bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                    <span class="text-sm text-[var(--color-gray-700)]" x-text="item.label"></span>
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full"
                                          :class="selectedItem?.detail?.[item.key]
                                              ? 'bg-green-100 text-green-700'
                                              : 'bg-red-100 text-red-700'">
                                        <template x-if="selectedItem?.detail?.[item.key]">
                                            <span class="flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Ya</span>
                                        </template>
                                        <template x-if="!selectedItem?.detail?.[item.key]">
                                            <span class="flex items-center gap-1"><i data-lucide="x" class="w-3 h-3"></i> Tidak</span>
                                        </template>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Kondisi Tanaman --}}
                    <div>
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Kondisi Tanaman</h4>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Tinggi Tanaman</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]">
                                    <span x-text="selectedItem?.detail?.tinggi ? selectedItem.detail.tinggi + ' cm' : '-'"></span>
                                </p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Kondisi Daun</p>
                                <p class="text-sm font-bold"
                                   :class="{
                                       'text-green-700': selectedItem?.detail?.kondisi_daun === 'Sehat',
                                       'text-amber-600': selectedItem?.detail?.kondisi_daun === 'Kuning' || selectedItem?.detail?.kondisi_daun === 'Bercak',
                                       'text-red-600': selectedItem?.detail?.kondisi_daun === 'Layu',
                                       'text-[var(--color-gray-900)]': !['Sehat','Kuning','Bercak','Layu'].includes(selectedItem?.detail?.kondisi_daun)
                                   }"
                                   x-text="selectedItem?.detail?.kondisi_daun || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Status Pertumbuhan</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.status_pertumbuhan || '-'"></p>
                            </div>
                        </div>
                    </div>

                    {{-- Catatan --}}
                    <div x-show="selectedItem?.detail?.catatan">
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Catatan</h4>
                        <div class="bg-amber-50 border border-amber-100 rounded-xl px-4 py-3">
                            <p class="text-sm text-amber-900 italic leading-relaxed" x-text="selectedItem?.detail?.catatan"></p>
                        </div>
                    </div>
                </div>
            </template>

            {{-- LAYOUT: Tanaman Sakit --}}
            <template x-if="selectedItem?.jenis === 'Tanaman Sakit'">
                <div class="space-y-5">
                    <div>
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Informasi Tanaman</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Kode Tanaman</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.kode_tanaman || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Nama Tanaman</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.nama_tanaman || '-'"></p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Detail Penyakit</h4>
                        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                            <div class="flex items-center gap-2 mb-2">
                                <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600"></i>
                                <p class="text-sm font-bold text-amber-800" x-text="selectedItem?.detail?.nama_penyakit || '-'"></p>
                            </div>
                            <p class="text-sm text-amber-700" x-text="'Pelapor: ' + (selectedItem?.detail?.pelapor || '-')"></p>
                        </div>
                    </div>
                    <div x-show="selectedItem?.detail?.catatan">
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Catatan / Jurnal Gejala</h4>
                        <div class="bg-amber-50 border border-amber-100 rounded-xl px-4 py-3">
                            <p class="text-sm text-amber-900 italic leading-relaxed" x-text="selectedItem?.detail?.catatan"></p>
                        </div>
                    </div>
                </div>
            </template>

            {{-- LAYOUT: Hama Tanaman --}}
            <template x-if="selectedItem?.jenis === 'Hama Tanaman'">
                <div class="space-y-5">
                    <div>
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Detail Hama</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-orange-50 border border-orange-100 rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-orange-500 mb-0.5">Nama Hama</p>
                                <p class="text-sm font-bold text-orange-900" x-text="selectedItem?.detail?.nama_hama || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Jumlah Terlihat</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.jumlah ?? '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Status Hama</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.status_hama || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Pelapor</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.pelapor || '-'"></p>
                            </div>
                        </div>
                    </div>
                    <div x-show="selectedItem?.detail?.catatan">
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Deskripsi Hama</h4>
                        <div class="bg-orange-50 border border-orange-100 rounded-xl px-4 py-3">
                            <p class="text-sm text-orange-900 italic leading-relaxed" x-text="selectedItem?.detail?.catatan"></p>
                        </div>
                    </div>
                </div>
            </template>

            {{-- LAYOUT: Hasil Panen --}}
            <template x-if="selectedItem?.jenis === 'Hasil Panen'">
                <div class="space-y-5">
                    <div>
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Data Panen</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 col-span-2">
                                <p class="text-[11px] font-medium text-blue-500 mb-0.5">Nama Komoditas</p>
                                <p class="text-sm font-bold text-blue-900" x-text="selectedItem?.detail?.nama_komoditas || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Estimasi Panen</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.estimasi_panen ?? '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Realisasi Panen</p>
                                <p class="text-sm font-bold text-green-700" x-text="selectedItem?.detail?.realisasi_panen ?? '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Gagal Panen</p>
                                <p class="text-sm font-bold text-red-600" x-text="selectedItem?.detail?.gagal_panen ?? '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Umur Tanaman</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]">
                                    <span x-text="selectedItem?.detail?.umur_tanaman ? selectedItem.detail.umur_tanaman + ' hari' : '-'"></span>
                                </p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Satuan</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.satuan || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Pelapor</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.pelapor || '-'"></p>
                            </div>
                        </div>
                    </div>
                    <div x-show="selectedItem?.detail?.catatan">
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Catatan</h4>
                        <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3">
                            <p class="text-sm text-blue-900 italic leading-relaxed" x-text="selectedItem?.detail?.catatan"></p>
                        </div>
                    </div>
                </div>
            </template>

            {{-- LAYOUT: Pemberian Nutrisi --}}
            <template x-if="selectedItem?.jenis === 'Pemberian Nutrisi'">
                <div class="space-y-5">
                    <div>
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Informasi Inventaris</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-teal-50 border border-teal-100 rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-teal-500 mb-0.5">Kategori Inventaris</p>
                                <p class="text-sm font-bold text-teal-900" x-text="selectedItem?.detail?.kategori_inventaris || '-'"></p>
                            </div>
                            <div class="bg-teal-50 border border-teal-100 rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-teal-500 mb-0.5">Nama Inventaris</p>
                                <p class="text-sm font-bold text-teal-900" x-text="selectedItem?.detail?.nama_inventaris || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Jumlah Digunakan</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.jumlah_digunakan ?? '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Satuan</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.satuan || '-'"></p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Informasi Penggunaan</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Kode Tanaman</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.kode_tanaman || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Lokasi</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.blok || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Pelapor</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.pelapor || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Keperluan</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.keperluan || '-'"></p>
                            </div>
                        </div>
                    </div>
                    <div x-show="selectedItem?.detail?.catatan">
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Catatan</h4>
                        <div class="bg-teal-50 border border-teal-100 rounded-xl px-4 py-3">
                            <p class="text-sm text-teal-900 italic leading-relaxed" x-text="selectedItem?.detail?.catatan"></p>
                        </div>
                    </div>
                </div>
            </template>

            {{-- LAYOUT: Tanaman Mati --}}
            <template x-if="selectedItem?.jenis === 'Tanaman Mati'">
                <div class="space-y-5">
                    <div>
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Informasi Tanaman</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Kode Tanaman</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.kode_tanaman || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Nama Tanaman</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.nama_tanaman || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Lokasi</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.blok || '-'"></p>
                            </div>
                            <div class="bg-[var(--color-gray-50)] rounded-xl px-4 py-3">
                                <p class="text-[11px] font-medium text-[var(--color-gray-500)] mb-0.5">Pelapor</p>
                                <p class="text-sm font-bold text-[var(--color-gray-900)]" x-text="selectedItem?.detail?.pelapor || '-'"></p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Penyebab Kematian</h4>
                        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3">
                            <div class="flex items-center gap-2 mb-1.5">
                                <i data-lucide="skull" class="w-4 h-4 text-red-600"></i>
                                <p class="text-sm font-bold text-red-800" x-text="selectedItem?.detail?.penyebab_kematian || '-'"></p>
                            </div>
                        </div>
                    </div>
                    <div x-show="selectedItem?.detail?.catatan">
                        <h4 class="text-xs font-semibold text-[var(--color-gray-400)] uppercase tracking-wider mb-3">Catatan</h4>
                        <div class="bg-red-50 border border-red-100 rounded-xl px-4 py-3">
                            <p class="text-sm text-red-900 italic leading-relaxed" x-text="selectedItem?.detail?.catatan"></p>
                        </div>
                    </div>
                </div>
            </template>

            {{-- FALLBACK: Jenis tidak dikenal --}}
            <template x-if="selectedItem && !['Laporan Harian', 'Tanaman Sakit', 'Hama Tanaman', 'Hasil Panen', 'Pemberian Nutrisi', 'Tanaman Mati'].includes(selectedItem?.jenis)">
                <div class="text-center py-8">
                    <div class="w-14 h-14 rounded-full bg-[var(--color-gray-100)] flex items-center justify-center mx-auto mb-3 text-[var(--color-gray-400)]">
                        <i data-lucide="file-question" class="w-6 h-6"></i>
                    </div>
                    <p class="text-sm font-semibold text-[var(--color-gray-700)]">Jenis laporan tidak dikenal</p>
                    <p class="text-xs text-[var(--color-gray-500)] mt-1" x-text="'Jenis: ' + selectedItem?.jenis"></p>
                </div>
            </template>
        </div>

        {{-- Modal Footer --}}
        <div class="sticky bottom-0 bg-white/95 backdrop-blur-sm border-t border-[var(--color-gray-100)] px-6 py-3 rounded-b-2xl">
            <div class="flex items-center justify-end">
                <button @click="showModal = false"
                        class="px-4 py-2 text-sm font-semibold text-[var(--color-gray-600)] bg-[var(--color-gray-100)] hover:bg-[var(--color-gray-200)] rounded-xl transition-colors">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>
