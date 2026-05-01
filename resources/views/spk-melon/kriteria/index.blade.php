@extends('layouts.app')

@section('title', 'Kriteria SPK')

@section('content')
<div x-data="kriteriaPage()" x-init="init()" class="space-y-6">

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- Page Header                                                    --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Kriteria SPK</h1>
            <p class="mt-1 text-sm text-gray-500">
                Kelola master kriteria evaluasi SPK untuk produktivitas dan kualitas tanaman melon di RFC.
            </p>
        </div>
        {{-- Tombol Tambah --}}
        <button @click="openCreateModal()"
            id="btn-tambah-kriteria"
            class="flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl min-h-[44px] shadow-sm hover:shadow-md transition-all shrink-0">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Tambah Kriteria
        </button>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- Toast Notification                                             --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-[-8px]"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-end="opacity-0"
        class="flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-200 rounded-xl shadow-sm">
        <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-500 shrink-0"></i>
        <p class="text-sm font-medium text-emerald-800">{{ session('success') }}</p>
        <button @click="show = false" class="ml-auto text-emerald-400 hover:text-emerald-600 transition-colors">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- Toolbar: Filter Kategori                                       --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            {{-- Filter: Semua --}}
            <a href="{{ route('spk-melon.kriteria.index') }}"
                class="px-4 py-2 rounded-xl text-sm font-semibold border transition-all min-h-[40px] flex items-center
                    {{ !$filterKategori ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:border-emerald-300 hover:text-emerald-700' }}">
                Semua
                <span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-bold rounded-full
                    {{ !$filterKategori ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500' }}">
                    {{ $daftarKriteria->count() }}
                </span>
            </a>
            {{-- Filter: Produktivitas --}}
            <a href="{{ route('spk-melon.kriteria.index', ['kategori' => 'produktivitas']) }}"
                class="px-4 py-2 rounded-xl text-sm font-semibold border transition-all min-h-[40px] flex items-center
                    {{ $filterKategori === 'produktivitas' ? 'bg-blue-600 text-white border-blue-600 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:border-blue-300 hover:text-blue-700' }}">
                Produktivitas
            </a>
            {{-- Filter: Kualitas --}}
            <a href="{{ route('spk-melon.kriteria.index', ['kategori' => 'kualitas']) }}"
                class="px-4 py-2 rounded-xl text-sm font-semibold border transition-all min-h-[40px] flex items-center
                    {{ $filterKategori === 'kualitas' ? 'bg-purple-600 text-white border-purple-600 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300 hover:text-purple-700' }}">
                Kualitas
            </a>
            {{-- Filter: Lingkungan --}}
            <a href="{{ route('spk-melon.kriteria.index', ['kategori' => 'lingkungan']) }}"
                class="px-4 py-2 rounded-xl text-sm font-semibold border transition-all min-h-[40px] flex items-center
                    {{ $filterKategori === 'lingkungan' ? 'bg-teal-600 text-white border-teal-600 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:border-teal-300 hover:text-teal-700' }}">
                Lingkungan
            </a>
        </div>
        <div class="text-sm text-gray-500 bg-gray-50 px-3.5 py-2 rounded-lg border border-gray-100 shadow-sm shrink-0">
            <span class="font-bold text-gray-900">{{ $daftarKriteria->count() }}</span>
            <span class="font-medium"> kriteria ditemukan</span>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- Tabel Kriteria                                                 --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden transition-all hover:shadow-lg">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left py-4 px-5 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Kode</th>
                        <th class="text-left py-4 px-5 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Nama Kriteria</th>
                        <th class="text-center py-4 px-5 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Tipe</th>
                        <th class="text-center py-4 px-5 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Kategori</th>
                        <th class="text-left py-4 px-5 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Sumber Data</th>
                        <th class="text-left py-4 px-5 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Keterangan</th>
                        <th class="text-center py-4 px-5 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($daftarKriteria as $k)
                    <tr class="border-b border-gray-50 last:border-none hover:bg-emerald-50/30 transition-colors">
                        {{-- Kode --}}
                        <td class="py-4 px-5 whitespace-nowrap">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-700 font-bold text-sm">
                                {{ $k->kode }}
                            </span>
                        </td>
                        {{-- Nama --}}
                        <td class="py-4 px-5">
                            <div class="font-semibold text-gray-900">{{ $k->nama }}</div>
                            @if($k->spiHitung)
                            <div class="text-[11px] text-gray-400 mt-0.5 font-mono">{{ $k->spiHitung }}</div>
                            @endif
                        </td>
                        {{-- Tipe --}}
                        <td class="py-4 px-5 text-center whitespace-nowrap">
                            @if($k->tipe === 'benefit')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Benefit
                            </span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase bg-red-50 text-red-700 border border-red-200">
                                Cost
                            </span>
                            @endif
                        </td>
                        {{-- Kategori --}}
                        <td class="py-4 px-5 text-center whitespace-nowrap">
                            @if($k->kategori === 'produktivitas')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase bg-blue-50 text-blue-700 border border-blue-200">
                                Produktivitas
                            </span>
                            @elseif($k->kategori === 'kualitas')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase bg-purple-50 text-purple-700 border border-purple-200">
                                Kualitas
                            </span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase bg-teal-50 text-teal-700 border border-teal-200">
                                Lingkungan
                            </span>
                            @endif
                        </td>
                        {{-- Sumber Data --}}
                        <td class="py-4 px-5 text-gray-600 whitespace-nowrap">
                            {{ $k->spiSumber ?? '—' }}
                        </td>
                        {{-- Keterangan --}}
                        <td class="py-4 px-5 max-w-xs">
                            @if($k->keterangan)
                            <p class="text-gray-500 text-xs leading-relaxed line-clamp-2" title="{{ $k->keterangan }}">
                                {{ $k->keterangan }}
                            </p>
                            @else
                            <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                        {{-- Aksi --}}
                        <td class="py-4 px-5 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-1.5">
                                {{-- Edit --}}
                                <button
                                    @click="openEditModal({{ json_encode([
                                        'id'         => $k->id,
                                        'kode'       => $k->kode,
                                        'nama'       => $k->nama,
                                        'tipe'       => $k->tipe,
                                        'kategori'   => $k->kategori,
                                        'spiSumber'  => $k->spiSumber ?? '',
                                        'spiHitung'  => $k->spiHitung ?? '',
                                        'keterangan' => $k->keterangan ?? '',
                                        'isLocked'   => $k->isLocked(),
                                    ]) }})"
                                    class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                    title="Edit kriteria {{ $k->kode }}">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                                {{-- Hapus --}}
                                <button
                                    @click="openDeleteConfirm({{ json_encode(['id' => $k->id, 'kode' => $k->kode, 'nama' => $k->nama]) }})"
                                    class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                    title="Hapus kriteria {{ $k->kode }}">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-16 text-center bg-gray-50/50">
                            <div class="max-w-[280px] mx-auto">
                                <div class="w-16 h-16 bg-white shadow-sm rounded-full flex items-center justify-center mx-auto mb-4 border border-gray-100">
                                    <i data-lucide="list-checks" class="w-8 h-8 text-gray-300"></i>
                                </div>
                                <p class="text-gray-900 font-semibold">Belum ada kriteria</p>
                                <p class="text-gray-500 text-sm mt-1 mb-4">
                                    Klik <strong>Tambah Kriteria</strong> untuk menambahkan kriteria evaluasi SPK pertama.
                                </p>
                                <button @click="openCreateModal()"
                                    class="text-sm font-semibold text-emerald-600 hover:text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-4 py-2 rounded-lg transition-colors border border-emerald-100 shadow-sm">
                                    Tambah Kriteria
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- Modal: Tambah / Edit Kriteria                                  --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div x-show="modalOpen" x-cloak
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-end="opacity-0"
        @keydown.escape.window="closeModal()">

        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            @click.stop>

            {{-- Modal Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-bold text-gray-900" x-text="mode === 'create' ? 'Tambah Kriteria SPK' : 'Edit Kriteria SPK'"></h2>
                <button @click="closeModal()" class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- Modal Body --}}
            <form :action="formAction" method="POST" @submit="submitting = true" class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
                @csrf
                <template x-if="mode === 'edit'">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                {{-- Notice: Locked --}}
                <template x-if="locked">
                    <div class="flex items-start gap-2.5 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                        <i data-lucide="lock" class="w-4 h-4 text-amber-500 mt-0.5 shrink-0"></i>
                        <p class="text-xs text-amber-800 leading-relaxed">
                            Kriteria ini sudah digunakan di sesi evaluasi yang selesai.
                            Kolom <strong>Tipe</strong> dan <strong>Kategori</strong> tidak dapat diubah untuk menjaga traceability historis.
                        </p>
                    </div>
                </template>

                {{-- Kode Kriteria (read-only) --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kode Kriteria</label>
                    <input type="text" x-model="form.kode" readonly
                        class="w-full px-4 py-2.5 bg-gray-100 text-gray-500 rounded-xl border border-gray-200 text-sm font-mono cursor-not-allowed">
                    <p class="text-xs text-gray-400 mt-1">Kode di-generate otomatis oleh sistem.</p>
                </div>

                {{-- Nama Kriteria --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Nama Kriteria <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="nama" x-model="form.nama"
                        required maxlength="100" placeholder="contoh: Realisasi Panen"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-shadow">
                </div>

                {{-- Tipe --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Tipe <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer" :class="locked ? 'opacity-50 cursor-not-allowed' : ''">
                            <input type="radio" name="tipe" value="benefit" x-model="form.tipe" :disabled="locked"
                                class="accent-emerald-600 w-4 h-4">
                            <span class="text-sm font-medium text-gray-700">Benefit</span>
                            <span class="text-xs text-gray-400">(semakin besar semakin baik)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer" :class="locked ? 'opacity-50 cursor-not-allowed' : ''">
                            <input type="radio" name="tipe" value="cost" x-model="form.tipe" :disabled="locked"
                                class="accent-red-500 w-4 h-4">
                            <span class="text-sm font-medium text-gray-700">Cost</span>
                            <span class="text-xs text-gray-400">(semakin kecil semakin baik)</span>
                        </label>
                    </div>
                </div>

                {{-- Kategori --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Kategori <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <select name="kategori" x-model="form.kategori"
                            @change="loadSpiSumber()"
                            :disabled="locked" required
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-shadow appearance-none bg-white pr-10"
                            :class="locked ? 'opacity-50 cursor-not-allowed' : ''">
                            <option value="">Pilih kategori...</option>
                            <option value="produktivitas">Produktivitas</option>
                            <option value="kualitas">Kualitas</option>
                            <option value="lingkungan">Lingkungan</option>
                        </select>
                        <i data-lucide="chevron-down" class="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                    </div>
                </div>

                {{-- Sumber Data / spiSumber (dropdown dinamis) --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Sumber Data</label>
                    <div class="relative">
                        <select name="spiSumber" x-model="form.spiSumber"
                            :disabled="!form.kategori || loadingSpiSumber"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-shadow appearance-none bg-white pr-10"
                            :class="(!form.kategori || loadingSpiSumber) ? 'opacity-50 cursor-not-allowed' : ''">
                            <option value="" x-text="loadingSpiSumber ? 'Memuat...' : (form.kategori ? 'Pilih sumber data...' : 'Pilih kategori dahulu...')"></option>
                            <template x-for="sd in spiSumberOptions" :key="sd">
                                <option :value="sd" x-text="sd"></option>
                            </template>
                        </select>
                        <i data-lucide="chevron-down" class="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                    </div>
                </div>

                {{-- Formula / spiHitung (opsional) --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Formula Pengambilan Data
                        <span class="text-xs text-gray-400 font-normal ml-1">(opsional)</span>
                    </label>
                    <input type="text" name="spiHitung" x-model="form.spiHitung"
                        maxlength="50" placeholder="Contoh: harianKebun.tinggiTanaman"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm font-mono focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-shadow">
                </div>

                {{-- Keterangan --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Keterangan
                        <span class="text-xs text-gray-400 font-normal ml-1">(opsional)</span>
                    </label>
                    <textarea name="keterangan" x-model="form.keterangan"
                        rows="3" placeholder="Deskripsi singkat tentang kriteria ini..."
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-shadow resize-none"></textarea>
                </div>
            </form>

            {{-- Modal Footer --}}
            <div class="flex gap-3 justify-end px-6 py-4 border-t border-gray-100">
                <button type="button" @click="closeModal()"
                    class="px-5 py-2.5 border-2 border-gray-200 text-gray-600 font-semibold rounded-xl text-sm min-h-[44px] hover:border-gray-300 hover:bg-gray-50 transition-all">
                    Batal
                </button>
                <button type="button"
                    @click="$el.closest('[x-data]').querySelector('form').requestSubmit()"
                    :disabled="submitting"
                    class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-sm min-h-[44px] shadow-sm transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                    x-text="submitting ? 'Menyimpan...' : 'Simpan'">
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- Dialog Konfirmasi Hapus                                        --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div x-show="deleteOpen" x-cloak
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-end="opacity-0"
        @keydown.escape.window="deleteOpen = false">

        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            @click.stop>

            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center shrink-0">
                    <i data-lucide="trash-2" class="w-5 h-5 text-red-500"></i>
                </div>
                <h3 class="text-base font-bold text-gray-900">Hapus Kriteria?</h3>
            </div>

            <p class="text-sm text-gray-600 mb-1">
                Anda akan menghapus kriteria:
            </p>
            <p class="text-sm font-semibold text-gray-900 mb-4">
                <span x-text="deleteTarget.kode" class="font-mono bg-gray-100 px-1.5 py-0.5 rounded mr-1"></span>
                <span x-text="deleteTarget.nama"></span>
            </p>
            <p class="text-xs text-gray-500 mb-5">
                Data akan dihapus secara <em>soft delete</em> dan tidak akan muncul di daftar.
                Data tetap tersimpan di database untuk menjaga integritas historis SPK.
            </p>

            <div class="flex gap-3 justify-end">
                <button @click="deleteOpen = false"
                    class="px-4 py-2 border-2 border-gray-200 text-gray-600 font-semibold rounded-xl text-sm min-h-[40px] hover:border-gray-300 transition-all">
                    Batal
                </button>
                <form :action="deleteAction" method="POST" @submit="deletingSubmit = true">
                    @csrf
                    @method('DELETE')
                    <button type="submit" :disabled="deletingSubmit"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl text-sm min-h-[40px] transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                        x-text="deletingSubmit ? 'Menghapus...' : 'Ya, Hapus'">
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function kriteriaPage() {
    return {
        // Modal state
        modalOpen: false,
        mode: 'create',       // 'create' | 'edit'
        locked: false,
        submitting: false,
        formAction: '',

        // Form data
        form: {
            kode: '',
            nama: '',
            tipe: 'benefit',
            kategori: '',
            spiSumber: '',
            spiHitung: '',
            keterangan: '',
        },

        // Dropdown spiSumber
        spiSumberOptions: [],
        loadingSpiSumber: false,

        // Delete confirm state
        deleteOpen: false,
        deletingSubmit: false,
        deleteTarget: { id: '', kode: '', nama: '' },
        deleteAction: '',

        init() {
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        },

        openCreateModal() {
            this.mode = 'create';
            this.locked = false;
            this.submitting = false;
            this.form = {
                kode: '{{ $nextKode }}',
                nama: '',
                tipe: 'benefit',
                kategori: '',
                spiSumber: '',
                spiHitung: '',
                keterangan: '',
            };
            this.spiSumberOptions = [];
            this.formAction = '{{ route("spk-melon.kriteria.store") }}';
            this.modalOpen = true;
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        },

        openEditModal(kriteria) {
            this.mode = 'edit';
            this.locked = kriteria.isLocked;
            this.submitting = false;
            this.form = {
                kode:        kriteria.kode,
                nama:        kriteria.nama,
                tipe:        kriteria.tipe,
                kategori:    kriteria.kategori,
                spiSumber:   kriteria.spiSumber,
                spiHitung:   kriteria.spiHitung,
                keterangan:  kriteria.keterangan,
            };
            this.formAction = `/spk-melon/kriteria/${kriteria.id}`;
            this.loadSpiSumber(); // load opsi berdasarkan kategori existing
            this.modalOpen = true;
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        },

        closeModal() {
            this.modalOpen = false;
        },

        /**
         * Fetch opsi spiSumber dari AJAX endpoint berdasarkan kategori yang dipilih.
         */
        async loadSpiSumber() {
            if (!this.form.kategori) {
                this.spiSumberOptions = [];
                this.form.spiSumber = '';
                return;
            }

            // Jika kategori berubah saat edit, reset spiSumber
            if (this.mode === 'create') {
                this.form.spiSumber = '';
            }

            this.loadingSpiSumber = true;
            try {
                const url = `{{ route('spk-melon.kriteria.spi-sumber.by-kategori') }}?kategori=${this.form.kategori}`;
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                this.spiSumberOptions = json.data ?? [];
            } catch (e) {
                this.spiSumberOptions = [];
            } finally {
                this.loadingSpiSumber = false;
            }
        },

        openDeleteConfirm(target) {
            this.deleteTarget = target;
            this.deleteAction = `/spk-melon/kriteria/${target.id}`;
            this.deletingSubmit = false;
            this.deleteOpen = true;
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        },
    };
}
</script>
@endpush
