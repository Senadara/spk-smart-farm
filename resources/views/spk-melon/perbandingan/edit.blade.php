@extends('layouts.app')

@section('title', 'Input Perbandingan Berpasangan — ' . $sesi->namaSesi)

@section('content')
<div class="space-y-6 p-6">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-gray-600">
        <a href="{{ route('spk-melon.sesi-penilaian.index') }}" class="hover:text-green-600">Sesi Penilaian</a>
        <span>/</span>
        <a href="{{ route('spk-melon.sesi-penilaian.show', $sesi->id) }}" class="hover:text-green-600">{{ $sesi->namaSesi }}</a>
        <span>/</span>
        <span class="font-medium text-gray-900">Perbandingan Berpasangan</span>
    </nav>

    {{-- Header --}}
    @php
        $statusBadge = match($sesi->status) {
            'draft' => ['color' => 'amber', 'label' => 'Draft'],
            'proses' => ['color' => 'blue', 'label' => 'Proses'],
            'selesai' => ['color' => 'green', 'label' => 'Selesai'],
            'gagal' => ['color' => 'red', 'label' => 'Gagal'],
            default => ['color' => 'green', 'label' => ucfirst($sesi->status)],
        };
    @endphp

    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Input Perbandingan Berpasangan</h1>
            <p class="mt-1 text-sm text-gray-600">
                Sesi: <span class="font-medium text-gray-900">{{ $sesi->namaSesi }}</span>
                &middot; Tipe: <span class="font-medium">{{ ucfirst($sesi->tipeEvaluasi) }}</span>
                &middot; Status:
                <x-badge :color="$statusBadge['color']">{{ $statusBadge['label'] }}</x-badge>
            </p>
        </div>
        <a href="{{ route('spk-melon.sesi-penilaian.show', $sesi->id) }}"
           class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Kembali ke Detail Sesi
        </a>
    </div>

    {{-- Pesan kesalahan / read-only --}}
    @if ($errorMessage)
        <div class="flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-500 shrink-0 mt-0.5"></i>
            <span>{{ $errorMessage }}</span>
        </div>
    @endif

    @if ($isReadOnly && ! $errorMessage)
        <div class="flex items-start gap-3 p-4 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800">
            <i data-lucide="lock" class="w-5 h-5 text-blue-500 shrink-0 mt-0.5"></i>
            <span>
                Matriks ini berada dalam mode <strong>hanya-baca</strong>.
                @if ((session('user')['role'] ?? '') === 'pjawab')
                    Akun Penanggung Jawab tidak dapat mengubah matriks.
                @elseif (in_array($sesi->status, ['selesai', 'gagal']))
                    Sesi sudah {{ $sesi->status }}. Matriks tidak dapat diubah.
                @endif
            </span>
        </div>
    @endif

    {{-- Flash messages --}}
    @if (session('success'))
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

    @if (session('error'))
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

    {{-- Panduan penggunaan --}}
    <details class="rounded-xl border border-gray-200 bg-white shadow-sm p-5" {{ ! $isReadOnly && empty($existingMatrix) ? 'open' : '' }}>
        <summary class="cursor-pointer text-sm font-semibold text-gray-900 flex items-center gap-2">
            <i data-lucide="book-open" class="w-4 h-4 text-gray-400"></i>
            Panduan Pengisian Matriks
        </summary>
        <div class="mt-3 space-y-2 text-sm text-gray-700">
            <p>1. Isi hanya sel pada <strong>segitiga atas matriks</strong> (di atas garis diagonal).</p>
            <p>2. Pilih nilai Skala Saaty (1-9) yang menggambarkan tingkat kepentingan kriteria <em>baris</em> dibandingkan kriteria <em>kolom</em>:</p>
            <ul class="ml-6 list-disc space-y-1">
                <li><strong>1</strong> = Kedua kriteria sama penting</li>
                <li><strong>3</strong> = Kriteria baris sedikit lebih penting dari kriteria kolom</li>
                <li><strong>5</strong> = Kriteria baris lebih penting</li>
                <li><strong>7</strong> = Kriteria baris sangat lebih penting</li>
                <li><strong>9</strong> = Kriteria baris mutlak lebih penting</li>
                <li><strong>1/3, 1/5, 1/7, 1/9</strong> = Kebalikan (kriteria kolom lebih penting)</li>
                <li>Nilai genap (2, 4, 6, 8) adalah nilai antara untuk kompromi.</li>
            </ul>
            <p>3. Sel diagonal otomatis bernilai 1 dan terkunci.</p>
            <p>4. Segitiga bawah matriks akan otomatis terisi sebagai kebalikan dari segitiga atas.</p>
            <p>5. Klik <em>"Simpan & Lanjut Validasi CR"</em> setelah seluruh sel segitiga atas terisi.</p>
        </div>
    </details>

    {{-- Matriks Perbandingan --}}
    @if ($kriteriaList->count() >= 2)
    <form method="POST" action="{{ route('spk-melon.sesi-penilaian.perbandingan.update', $sesi->id) }}"
          x-data="perbandinganMatrix({{ Js::from($existingMatrix) }}, {{ Js::from($kriteriaList->pluck('id')) }})">
        @csrf
        @method('PUT')

        {{-- Error validation summary --}}
        @if ($errors->any())
            <div class="flex items-start gap-3 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-800">
                <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 shrink-0 mt-0.5"></i>
                <div>
                    <p class="mb-2 font-medium">Terdapat kesalahan pada input:</p>
                    <ul class="ml-4 list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border-b border-gray-200 px-4 py-3 text-left font-semibold text-gray-700">Kriteria</th>
                        @foreach ($kriteriaList as $kCol)
                            <th class="border-b border-gray-200 px-3 py-3 text-center font-semibold text-gray-700">
                                <div class="text-xs uppercase tracking-wide">{{ $kCol->kode }}</div>
                                <div class="text-[10px] font-normal text-gray-500 truncate max-w-[140px]" title="{{ $kCol->nama }}">
                                    {{ Str::limit($kCol->nama, 18) }}
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($kriteriaList as $i => $kRow)
                        <tr class="border-b border-gray-100 last:border-none">
                            <td class="bg-gray-50 px-4 py-3 font-medium text-gray-900">
                                <div class="text-xs uppercase tracking-wide">{{ $kRow->kode }}</div>
                                <div class="text-[10px] font-normal text-gray-500 truncate max-w-[160px]" title="{{ $kRow->nama }}">
                                    {{ Str::limit($kRow->nama, 22) }}
                                </div>
                            </td>
                            @foreach ($kriteriaList as $j => $kCol)
                                <td class="px-2 py-2 text-center
                                    @if ($i === $j) bg-gray-100
                                    @elseif ($i > $j) bg-gray-50
                                    @endif">

                                    @if ($i === $j)
                                        {{-- Diagonal: locked at 1 --}}
                                        <span class="font-mono text-gray-400 font-semibold">1</span>

                                    @elseif ($i < $j)
                                        {{-- Upper triangle: editable --}}
                                        <select
                                            name="perbandingan[{{ $kRow->id }}::{{ $kCol->id }}]"
                                            x-model="matrix['{{ $kRow->id }}::{{ $kCol->id }}']"
                                            @change="onChange('{{ $kRow->id }}', '{{ $kCol->id }}')"
                                            @if ($isReadOnly) disabled @endif
                                            class="w-full rounded-md border-gray-300 px-2 py-1.5 text-sm focus:border-green-500 focus:ring-green-500
                                                   @if ($isReadOnly) bg-gray-100 text-gray-500 cursor-not-allowed @endif">
                                            <option value="">Pilih...</option>
                                            @foreach ($saatyOptions as $opt)
                                                <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </select>

                                    @else
                                        {{-- Lower triangle: auto-reciprocal --}}
                                        <span class="font-mono text-xs text-gray-500"
                                              x-text="formatReciprocal('{{ $kCol->id }}::{{ $kRow->id }}')">
                                            -
                                        </span>
                                    @endif

                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Action buttons --}}
        @unless ($isReadOnly)
            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('spk-melon.sesi-penilaian.show', $sesi->id) }}"
                   class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i data-lucide="x" class="w-4 h-4"></i>
                    Batal
                </a>
                <button type="submit"
                        class="flex items-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Simpan & Lanjut Validasi CR
                </button>
            </div>
        @endunless

    </form>
    @endif

</div>

{{-- Alpine.js component --}}
<script>
function perbandinganMatrix(existingMatrix, kriteriaIds) {
    return {
        matrix: existingMatrix || {},

        onChange(kRowId, kColId) {
            // Trigger update untuk lower triangle (reciprocal display)
            // Alpine.js otomatis re-render via x-text karena matrix berubah
        },

        formatReciprocal(upperKey) {
            const val = parseFloat(this.matrix[upperKey] || 0);
            if (! val) return '-';
            const reciprocal = 1 / val;

            // Format fraksi yang readable
            if (reciprocal >= 1) {
                return Number.isInteger(reciprocal) ? reciprocal.toString() : reciprocal.toFixed(4);
            } else {
                const inverse = Math.round(1 / reciprocal);
                return '1/' + inverse;
            }
        },
    };
}
</script>
@endsection
