@extends('layouts.app')

@section('title', $sesi->namaSesi . ' — Sesi Penilaian SPK')

@section('content')
<div x-data="{ deleteModal: { open: false, id: '{{ $sesi->id }}', nama: '{{ addslashes($sesi->namaSesi) }}' } }" class="space-y-6">

    {{-- Toast Notification --}}
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

    {{-- Action Buttons Row --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <a href="{{ route('spk-melon.sesi-penilaian.index') }}"
           class="flex items-center gap-2 px-5 py-2.5 border-2 border-gray-200 text-gray-600 font-semibold rounded-xl text-sm min-h-[44px] hover:border-gray-300 hover:bg-gray-50 transition-all w-fit">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Kembali
        </a>

        <div class="flex items-center gap-2 flex-wrap">
            @if($sesi->status === 'draft' && in_array(session('user')['role'] ?? '', ['inventor', 'admin']))
                @if($sesi->perbandingan()->exists())
                    <a href="{{ route('spk-melon.sesi-penilaian.perbandingan.edit', $sesi->id) }}"
                       class="flex items-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl text-sm min-h-[44px] shadow-sm transition-all">
                        <i data-lucide="git-compare" class="w-4 h-4"></i>
                        Edit Perbandingan
                    </a>
                @else
                    <a href="{{ route('spk-melon.sesi-penilaian.perbandingan.edit', $sesi->id) }}"
                       class="flex items-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl text-sm min-h-[44px] shadow-sm transition-all">
                        <i data-lucide="git-compare" class="w-4 h-4"></i>
                        Mulai Input Perbandingan
                    </a>
                @endif
                <button type="button"
                        @click="deleteModal.open = true"
                        class="flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl text-sm min-h-[44px] shadow-sm transition-all">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    Hapus Sesi
                </button>
            @elseif($sesi->status === 'proses')
                @if(in_array(session('user')['role'] ?? '', ['inventor', 'admin']))
                    <a href="{{ route('spk-melon.sesi-penilaian.perbandingan.edit', $sesi->id) }}"
                       class="flex items-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl text-sm min-h-[44px] shadow-sm transition-all">
                        <i data-lucide="git-compare" class="w-4 h-4"></i>
                        Edit Perbandingan
                    </a>
                    @if($sesi->perbandingan()->exists())
                        <a href="{{ route('spk-melon.sesi-penilaian.validasi-konsistensi.show', $sesi->id) }}"
                           class="flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm min-h-[44px] shadow-sm transition-all">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                            Validasi Konsistensi
                        </a>
                    @endif
                @elseif((session('user')['role'] ?? '') === 'pjawab')
                    <a href="{{ route('spk-melon.sesi-penilaian.perbandingan.edit', $sesi->id) }}"
                       class="flex items-center gap-2 px-5 py-2.5 border-2 border-gray-200 text-gray-600 font-semibold rounded-xl text-sm min-h-[44px] hover:border-gray-300 hover:bg-gray-50 transition-all">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                        Lihat Matriks Perbandingan
                    </a>
                    @if($sesi->perbandingan()->exists())
                        <a href="{{ route('spk-melon.sesi-penilaian.validasi-konsistensi.show', $sesi->id) }}"
                           class="flex items-center gap-2 px-5 py-2.5 border-2 border-blue-200 text-blue-600 font-semibold rounded-xl text-sm min-h-[44px] hover:border-blue-300 hover:bg-blue-50 transition-all">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                            Lihat Validasi Konsistensi
                        </a>
                    @endif
                @endif
            @elseif($sesi->status === 'selesai')
                @if($sesi->perbandingan()->exists())
                    <a href="{{ route('spk-melon.sesi-penilaian.perbandingan.edit', $sesi->id) }}"
                       class="flex items-center gap-2 px-5 py-2.5 border-2 border-gray-200 text-gray-600 font-semibold rounded-xl text-sm min-h-[44px] hover:border-gray-300 hover:bg-gray-50 transition-all">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                        Lihat Matriks Perbandingan
                    </a>
                    <a href="{{ route('spk-melon.sesi-penilaian.validasi-konsistensi.show', $sesi->id) }}"
                       class="flex items-center gap-2 px-5 py-2.5 border-2 border-blue-200 text-blue-600 font-semibold rounded-xl text-sm min-h-[44px] hover:border-blue-300 hover:bg-blue-50 transition-all">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>
                        Lihat Validasi Konsistensi
                    </a>
                @endif
                {{-- TODO: SPK-07 - link ke halaman ranking --}}
                <a href="#"
                   class="flex items-center gap-2 px-5 py-2.5 bg-gray-200 text-gray-500 font-semibold rounded-xl text-sm min-h-[44px] cursor-not-allowed"
                   title="Akan tersedia setelah SPK-07 selesai">
                    <i data-lucide="trophy" class="w-4 h-4"></i>
                    Lihat Hasil Ranking (SPK-07)
                </a>
            @elseif($sesi->status === 'gagal')
                @if($sesi->perbandingan()->exists())
                    <a href="{{ route('spk-melon.sesi-penilaian.perbandingan.edit', $sesi->id) }}"
                       class="flex items-center gap-2 px-5 py-2.5 border-2 border-gray-200 text-gray-600 font-semibold rounded-xl text-sm min-h-[44px] hover:border-gray-300 hover:bg-gray-50 transition-all">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                        Lihat Matriks Perbandingan
                    </a>
                    <a href="{{ route('spk-melon.sesi-penilaian.validasi-konsistensi.show', $sesi->id) }}"
                       class="flex items-center gap-2 px-5 py-2.5 border-2 border-blue-200 text-blue-600 font-semibold rounded-xl text-sm min-h-[44px] hover:border-blue-300 hover:bg-blue-50 transition-all">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>
                        Lihat Validasi Konsistensi
                    </a>
                @endif
            @endif
        </div>
    </div>

    {{-- Page Title + Badges --}}
    @php
        $tipeBadge = $sesi->tipeEvaluasi === 'produktivitas'
            ? ['bg' => 'bg-teal-50 text-teal-700 border-teal-200', 'label' => 'Produktivitas', 'icon' => 'sprout']
            : ['bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'label' => 'Kualitas', 'icon' => 'star'];
        $statusBadge = match($sesi->status) {
            'draft' => ['bg' => 'bg-amber-50 text-amber-700 border-amber-200', 'label' => 'Draft', 'icon' => 'pencil'],
            'proses' => ['bg' => 'bg-blue-50 text-blue-700 border-blue-200', 'label' => 'Proses', 'icon' => 'loader'],
            'selesai' => ['bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'label' => 'Selesai', 'icon' => 'check-circle-2'],
            'gagal' => ['bg' => 'bg-red-50 text-red-700 border-red-200', 'label' => 'Gagal', 'icon' => 'x-circle'],
            default => ['bg' => 'bg-gray-50 text-gray-700 border-gray-200', 'label' => ucfirst($sesi->status), 'icon' => 'circle'],
        };
    @endphp

    <div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $sesi->namaSesi }}</h1>
        <div class="flex items-center gap-2 mt-2">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase border {{ $tipeBadge['bg'] }}">
                <i data-lucide="{{ $tipeBadge['icon'] }}" class="w-3 h-3"></i>
                {{ $tipeBadge['label'] }}
            </span>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase border {{ $statusBadge['bg'] }}">
                <i data-lucide="{{ $statusBadge['icon'] }}" class="w-3 h-3"></i>
                {{ $statusBadge['label'] }}
            </span>
        </div>
    </div>

    {{-- Info Card: Status Gagal --}}
    @if($sesi->status === 'gagal')
    <div class="flex items-start gap-3 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl">
        <i data-lucide="alert-triangle" class="w-5 h-5 text-red-500 shrink-0 mt-0.5"></i>
        <div>
            <p class="font-semibold text-red-900 mb-1">Sesi Evaluasi Gagal</p>
            <p class="text-sm text-red-800">
                Kalkulasi SPK pada sesi ini tidak dapat diselesaikan. Detail kegagalan dapat dilihat di kolom catatan di bawah.
            </p>
        </div>
    </div>
    @endif

    {{-- Detail Sesi Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <i data-lucide="info" class="w-5 h-5 text-gray-400"></i>
            Informasi Sesi
        </h2>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div>
                <dt class="text-gray-500 mb-0.5">Nama Sesi</dt>
                <dd class="text-gray-900 font-medium">{{ $sesi->namaSesi }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 mb-0.5">Tipe Evaluasi</dt>
                <dd class="text-gray-900 font-medium">{{ $tipeBadge['label'] }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 mb-0.5">Periode Mulai</dt>
                <dd class="text-gray-900 font-medium">{{ $sesi->periodeMulai->translatedFormat('d F Y') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 mb-0.5">Periode Selesai</dt>
                <dd class="text-gray-900 font-medium">{{ $sesi->periodeSelesai->translatedFormat('d F Y') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 mb-0.5">Durasi</dt>
                <dd class="text-gray-900 font-medium">
                    {{ $sesi->durasiHari }} hari
                    @unless($sesi->periodeTipikal)
                        <span class="ml-2 inline-flex items-center gap-1 text-xs text-amber-600">
                            <i data-lucide="alert-triangle" class="w-3 h-3"></i>
                            di luar rentang tipikal
                        </span>
                    @endunless
                </dd>
            </div>
            <div>
                <dt class="text-gray-500 mb-0.5">Status</dt>
                <dd class="text-gray-900 font-medium">{{ $statusBadge['label'] }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 mb-0.5">Dinilai Oleh</dt>
                <dd class="text-gray-900 font-medium">{{ $sesi->penilai->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 mb-0.5">Dibuat Pada</dt>
                <dd class="text-gray-900 font-medium">{{ $sesi->createdAt?->translatedFormat('d F Y, H:i') ?? '—' }}</dd>
            </div>
            @if($sesi->rasioKonsistensi !== null)
                <div>
                    <dt class="text-gray-500 mb-0.5"><em>Consistency Ratio</em></dt>
                    <dd class="text-gray-900 font-medium font-mono">{{ number_format($sesi->rasioKonsistensi, 4) }}</dd>
                </div>
            @endif
            @if($sesi->catatanSesi)
                <div class="md:col-span-2">
                    <dt class="text-gray-500 mb-0.5">Catatan</dt>
                    <dd class="text-gray-700">{{ $sesi->catatanSesi }}</dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Preview Kriteria Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                <i data-lucide="list-checks" class="w-5 h-5 text-gray-400"></i>
                Kriteria yang Dimuat
                <span class="text-sm font-normal text-gray-500">({{ $kriteriaList->count() }} kriteria)</span>
            </h2>
        </div>
        <p class="text-sm text-gray-600 mb-4">
            Kriteria di bawah ini dimuat otomatis berdasarkan tipe evaluasi
            <strong>{{ $tipeBadge['label'] }}</strong>.
            Kriteria berkategori <em>lingkungan</em> selalu ikut karena mencakup parameter sensor mikroklimat
            yang relevan untuk semua tipe evaluasi.
        </p>

        @if($kriteriaList->isEmpty())
            <div class="text-center py-8">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="list-x" class="w-6 h-6 text-gray-400"></i>
                </div>
                <p class="text-gray-500 text-sm">Belum ada kriteria yang sesuai dengan tipe evaluasi ini.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left py-3 px-4 font-bold text-gray-600 text-xs uppercase tracking-wider">Kode</th>
                            <th class="text-left py-3 px-4 font-bold text-gray-600 text-xs uppercase tracking-wider">Nama Kriteria</th>
                            <th class="text-center py-3 px-4 font-bold text-gray-600 text-xs uppercase tracking-wider">Tipe</th>
                            <th class="text-center py-3 px-4 font-bold text-gray-600 text-xs uppercase tracking-wider">Kategori</th>
                            <th class="text-left py-3 px-4 font-bold text-gray-600 text-xs uppercase tracking-wider">Sumber Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kriteriaList as $kriteria)
                        <tr class="border-b border-gray-50 last:border-none hover:bg-gray-50/50 transition-colors">
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 text-gray-700 font-bold text-xs">
                                    {{ $kriteria->kode }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-medium text-gray-900">{{ $kriteria->nama }}</td>
                            <td class="py-3 px-4 text-center">
                                @if($kriteria->tipe === 'benefit')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Benefit
                                </span>
                                @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase bg-red-50 text-red-700 border border-red-200">
                                    Cost
                                </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($kriteria->kategori === 'produktivitas')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase bg-blue-50 text-blue-700 border border-blue-200">
                                    Produktivitas
                                </span>
                                @elseif($kriteria->kategori === 'kualitas')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase bg-purple-50 text-purple-700 border border-purple-200">
                                    Kualitas
                                </span>
                                @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide uppercase bg-teal-50 text-teal-700 border border-teal-200">
                                    Lingkungan
                                </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                @if($kriteria->spiSumber)
                                <code class="text-xs bg-gray-100 px-2 py-0.5 rounded font-mono">{{ $kriteria->spiSumber }}</code>
                                @else
                                <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Delete Modal --}}
    @include('spk-melon.sesi-penilaian.partials.delete-modal')

</div>
@endsection
