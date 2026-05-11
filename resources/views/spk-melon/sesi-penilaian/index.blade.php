@extends('layouts.app')

@section('title', 'Sesi Penilaian SPK')

@section('content')
<div x-data="sesiPenilaianIndex()" class="space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Sesi Penilaian SPK</h1>
            <p class="mt-1 text-sm text-gray-500">
                Kelola sesi evaluasi SPK untuk produktivitas dan kualitas tanaman melon di RFC.
                Setiap sesi mencakup satu siklus perhitungan <em>Fuzzy</em> AHP-TOPSIS.
            </p>
        </div>
        {{-- Tombol Tambah - hanya untuk role inventor/admin --}}
        @if(in_array(session('user')['role'] ?? '', ['inventor', 'admin']))
        <button @click="$dispatch('open-create-modal')"
            id="btn-tambah-sesi"
            class="flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl min-h-[44px] shadow-sm hover:shadow-md transition-all shrink-0">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Tambah Sesi
        </button>
        @endif
    </div>

    {{-- Toast Notification (inline, matching SPK-01 pattern) --}}
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

    @if(session('error'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-[-8px]"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-end="opacity-0"
        class="flex items-center gap-3 p-4 bg-red-50 border border-red-200 rounded-xl shadow-sm">
        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 shrink-0"></i>
        <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
        <button @click="show = false" class="ml-auto text-red-400 hover:text-red-600 transition-colors">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
    @endif

    {{-- Toolbar: Filter Toggle + Info --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <button type="button" @click="showFilter = !showFilter"
                class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border transition-all min-h-[40px]"
                :class="showFilter ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-white text-gray-600 border-gray-200 hover:border-emerald-300 hover:text-emerald-700'">
                <i data-lucide="filter" class="w-4 h-4"></i>
                Filter
                <template x-if="activeFilterCount > 0">
                    <span class="px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-emerald-600 text-white" x-text="activeFilterCount"></span>
                </template>
            </button>
        </div>
        <div class="text-sm text-gray-500 bg-gray-50 px-3.5 py-2 rounded-lg border border-gray-100 shadow-sm shrink-0">
            <span class="font-bold text-gray-900">{{ $sesiList->total() }}</span>
            <span class="font-medium"> sesi ditemukan</span>
        </div>
    </div>

    {{-- Filter Panel (collapsible) --}}
    <div x-show="showFilter" x-collapse
        class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <form method="GET" action="{{ route('spk-melon.sesi-penilaian.index') }}"
              class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Tipe Evaluasi --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tipe Evaluasi</label>
                <div class="relative">
                    <select name="tipeEvaluasi"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-shadow appearance-none bg-white pr-10">
                        <option value="">-- Semua --</option>
                        <option value="produktivitas" @selected(request('tipeEvaluasi')==='produktivitas')>Produktivitas</option>
                        <option value="kualitas" @selected(request('tipeEvaluasi')==='kualitas')>Kualitas</option>
                    </select>
                    <i data-lucide="chevron-down" class="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                </div>
            </div>
            {{-- Status --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                <div class="relative">
                    <select name="status"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-shadow appearance-none bg-white pr-10">
                        <option value="">-- Semua --</option>
                        <option value="draft" @selected(request('status')==='draft')>Draft</option>
                        <option value="proses" @selected(request('status')==='proses')>Proses</option>
                        <option value="selesai" @selected(request('status')==='selesai')>Selesai</option>
                        <option value="gagal" @selected(request('status')==='gagal')>Gagal</option>
                    </select>
                    <i data-lucide="chevron-down" class="absolute right-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"></i>
                </div>
            </div>
            {{-- Periode Mulai --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Periode Mulai</label>
                <input type="date" name="periodeMulai" value="{{ request('periodeMulai') }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-shadow">
            </div>
            {{-- Periode Selesai --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Periode Selesai</label>
                <input type="date" name="periodeSelesai" value="{{ request('periodeSelesai') }}"
                       class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-shadow">
            </div>
            {{-- Action --}}
            <div class="md:col-span-4 flex gap-2 justify-end">
                <a href="{{ route('spk-melon.sesi-penilaian.index') }}"
                   class="px-5 py-2.5 border-2 border-gray-200 text-gray-600 font-semibold rounded-xl text-sm min-h-[44px] hover:border-gray-300 hover:bg-gray-50 transition-all flex items-center">
                    Reset
                </a>
                <button type="submit"
                        class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-sm min-h-[44px] shadow-sm transition-all flex items-center gap-2">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    {{-- Table Sesi Penilaian --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden transition-all hover:shadow-lg">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left py-4 px-5 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Nama Sesi</th>
                        <th class="text-center py-4 px-5 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Tipe</th>
                        <th class="text-left py-4 px-5 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Periode</th>
                        <th class="text-center py-4 px-5 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="text-left py-4 px-5 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Dinilai Oleh</th>
                        <th class="text-left py-4 px-5 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Dibuat</th>
                        <th class="text-center py-4 px-5 font-bold text-gray-600 text-xs uppercase tracking-wider whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sesiList as $sesi)
                    <tr class="border-b border-gray-50 last:border-none hover:bg-emerald-50/30 transition-colors">
                        {{-- Nama Sesi --}}
                        <td class="py-4 px-5">
                            <div class="font-semibold text-gray-900">{{ $sesi->namaSesi }}</div>
                        </td>
                        {{-- Tipe Evaluasi --}}
                        <td class="py-4 px-5 text-center whitespace-nowrap">
                            @if($sesi->tipeEvaluasi === 'produktivitas')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase bg-teal-50 text-teal-700 border border-teal-200">
                                <i data-lucide="sprout" class="w-3 h-3"></i>
                                Produktivitas
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <i data-lucide="star" class="w-3 h-3"></i>
                                Kualitas
                            </span>
                            @endif
                        </td>
                        {{-- Periode --}}
                        <td class="py-4 px-5 whitespace-nowrap">
                            <div class="text-gray-700">
                                {{ $sesi->periodeMulai->translatedFormat('d M Y') }}
                                <span class="text-gray-400">—</span>
                                {{ $sesi->periodeSelesai->translatedFormat('d M Y') }}
                            </div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ $sesi->durasiHari }} hari</div>
                        </td>
                        {{-- Status --}}
                        <td class="py-4 px-5 text-center whitespace-nowrap">
                            @php
                                $statusConfig = match($sesi->status) {
                                    'draft' => ['bg' => 'bg-amber-50 text-amber-700 border-amber-200', 'label' => 'Draft', 'icon' => 'pencil'],
                                    'proses' => ['bg' => 'bg-blue-50 text-blue-700 border-blue-200', 'label' => 'Proses', 'icon' => 'loader'],
                                    'selesai' => ['bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'label' => 'Selesai', 'icon' => 'check-circle-2'],
                                    'gagal' => ['bg' => 'bg-red-50 text-red-700 border-red-200', 'label' => 'Gagal', 'icon' => 'x-circle'],
                                    default => ['bg' => 'bg-gray-50 text-gray-700 border-gray-200', 'label' => ucfirst($sesi->status), 'icon' => 'circle'],
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase border {{ $statusConfig['bg'] }}">
                                <i data-lucide="{{ $statusConfig['icon'] }}" class="w-3 h-3"></i>
                                {{ $statusConfig['label'] }}
                            </span>
                        </td>
                        {{-- Dinilai Oleh --}}
                        <td class="py-4 px-5 text-gray-600 whitespace-nowrap">
                            {{ $sesi->penilai->name ?? '—' }}
                        </td>
                        {{-- Dibuat --}}
                        <td class="py-4 px-5 text-gray-600 whitespace-nowrap">
                            {{ $sesi->createdAt?->translatedFormat('d M Y') ?? '—' }}
                        </td>
                        {{-- Aksi --}}
                        <td class="py-4 px-5 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-1.5">
                                {{-- Lihat Detail --}}
                                <a href="{{ route('spk-melon.sesi-penilaian.show', $sesi->id) }}"
                                   class="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors"
                                   title="Lihat detail sesi">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                                {{-- Hapus — hanya draft + role inventor/admin --}}
                                @if($sesi->status === 'draft' && in_array(session('user')['role'] ?? '', ['inventor', 'admin']))
                                <button
                                    @click="openDeleteModal('{{ $sesi->id }}', '{{ addslashes($sesi->namaSesi) }}')"
                                    class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                    title="Hapus sesi {{ $sesi->namaSesi }}">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-16 text-center bg-gray-50/50">
                            <div class="max-w-[280px] mx-auto">
                                <div class="w-16 h-16 bg-white shadow-sm rounded-full flex items-center justify-center mx-auto mb-4 border border-gray-100">
                                    <i data-lucide="clipboard-list" class="w-8 h-8 text-gray-300"></i>
                                </div>
                                <p class="text-gray-900 font-semibold">Belum ada sesi evaluasi</p>
                                <p class="text-gray-500 text-sm mt-1 mb-4">
                                    Mulai evaluasi SPK dengan membuat sesi baru sesuai tipe evaluasi yang ingin dijalankan.
                                </p>
                                @if(in_array(session('user')['role'] ?? '', ['inventor', 'admin']))
                                <button @click="$dispatch('open-create-modal')"
                                    class="text-sm font-semibold text-emerald-600 hover:text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-4 py-2 rounded-lg transition-colors border border-emerald-100 shadow-sm">
                                    Tambah Sesi
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($sesiList->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">
            {{ $sesiList->links() }}
        </div>
        @endif
    </div>

    {{-- Modals --}}
    @include('spk-melon.sesi-penilaian.partials.create-modal')
    @include('spk-melon.sesi-penilaian.partials.delete-modal')

</div>
@endsection

@push('scripts')
<script>
function sesiPenilaianIndex() {
    return {
        showFilter: {{ request()->hasAny(['tipeEvaluasi','status','periodeMulai','periodeSelesai']) ? 'true' : 'false' }},
        deleteModal: { open: false, id: null, nama: '' },

        get activeFilterCount() {
            let count = 0;
            @if(request('tipeEvaluasi')) count++; @endif
            @if(request('status')) count++; @endif
            @if(request('periodeMulai')) count++; @endif
            @if(request('periodeSelesai')) count++; @endif
            return count;
        },

        openDeleteModal(id, nama) {
            this.deleteModal = { open: true, id, nama };
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        },

        init() {
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        },
    };
}
</script>
@endpush
