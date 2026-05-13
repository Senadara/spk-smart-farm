@extends('layouts.app')

@section('title', 'Validasi Konsistensi — ' . $sesi->namaSesi)

@section('content')
<div class="space-y-6">

    {{-- Toast Notifications --}}
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

    {{-- Header + Tombol Kembali --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-gray-400 mb-1">
                <a href="{{ route('spk-melon.sesi-penilaian.index') }}" class="hover:text-gray-600 transition-colors">Sesi Penilaian</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <a href="{{ route('spk-melon.sesi-penilaian.show', $sesi->id) }}" class="hover:text-gray-600 transition-colors truncate max-w-[200px]">{{ $sesi->namaSesi }}</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <span class="text-gray-600 font-medium">Validasi Konsistensi</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Validasi <em>Consistency Ratio</em></h1>
        </div>
        <a href="{{ route('spk-melon.sesi-penilaian.show', $sesi->id) }}"
           class="flex items-center gap-2 px-5 py-2.5 border-2 border-gray-200 text-gray-600 font-semibold rounded-xl text-sm min-h-[44px] hover:border-gray-300 hover:bg-gray-50 transition-all w-fit">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Kembali ke Detail Sesi
        </a>
    </div>

    {{-- Info Card: Short Circuit (n < 3) --}}
    @if($result['isShortCircuit'])
    <div class="flex items-start gap-4 p-5 bg-blue-50 border border-blue-200 rounded-xl">
        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center shrink-0">
            <i data-lucide="info" class="w-5 h-5 text-blue-600"></i>
        </div>
        <div>
            <p class="font-semibold text-blue-900 mb-1">Konsistensi Otomatis</p>
            <p class="text-sm text-blue-800">{{ $result['shortCircuitReason'] }}</p>
        </div>
    </div>
    @endif

    {{-- Result Card --}}
    @php
        if ($result['isShortCircuit']) {
            $borderColor = 'border-blue-300';
            $bgAccent    = 'bg-blue-50';
            $crColor     = 'text-blue-700';
            $badgeBg     = 'bg-blue-100 text-blue-700 border-blue-200';
            $badgeIcon   = 'info';
            $badgeLabel  = 'Konsisten Otomatis';
        } elseif ($result['isConsistent']) {
            $borderColor = 'border-emerald-300';
            $bgAccent    = 'bg-emerald-50';
            $crColor     = 'text-emerald-700';
            $badgeBg     = 'bg-emerald-100 text-emerald-700 border-emerald-200';
            $badgeIcon   = 'check-circle-2';
            $badgeLabel  = 'Matriks Konsisten';
        } else {
            $borderColor = 'border-red-300';
            $bgAccent    = 'bg-red-50';
            $crColor     = 'text-red-700';
            $badgeBg     = 'bg-red-100 text-red-700 border-red-200';
            $badgeIcon   = 'alert-circle';
            $badgeLabel  = 'Matriks Tidak Konsisten';
        }
    @endphp

    <div class="bg-white rounded-xl shadow-sm border-2 {{ $borderColor }} p-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-6">
            <div class="flex-1">
                <p class="text-sm font-medium text-gray-500 mb-1"><em>Consistency Ratio</em> (CR)</p>
                <div class="flex items-end gap-3 mb-2">
                    <span class="text-5xl font-bold font-mono {{ $crColor }}">
                        {{ number_format($result['cr'], 4) }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold border {{ $badgeBg }} mb-1">
                        <i data-lucide="{{ $badgeIcon }}" class="w-3.5 h-3.5"></i>
                        {{ $badgeLabel }}
                    </span>
                </div>
                <p class="text-xs text-gray-400">Threshold: CR &lt; 0.10 &nbsp;|&nbsp; n = {{ $result['n'] }} kriteria</p>
            </div>
            <div class="{{ $bgAccent }} rounded-xl px-6 py-4 text-center min-w-[140px]">
                <p class="text-xs text-gray-500 mb-1"><em>Consistency Index</em> (CI)</p>
                <p class="text-2xl font-bold font-mono text-gray-800">{{ number_format($result['ci'], 4) }}</p>
                <p class="text-xs text-gray-400 mt-2"><em>Random Index</em> (RI)</p>
                <p class="text-lg font-semibold font-mono text-gray-600">{{ number_format($result['ri'], 4) }}</p>
            </div>
        </div>

        @if(! $result['isConsistent'] && ! $result['isShortCircuit'])
        <div class="mt-4 pt-4 border-t border-red-100 flex items-start gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500 shrink-0 mt-0.5"></i>
            <p class="text-sm text-gray-600">
                Nilai CR melebihi ambang batas 0.10. Silakan tinjau kembali penilaian perbandingan berpasangan di SPK-03 dan pastikan penilaian bersifat logis dan transitif.
            </p>
        </div>
        @endif
    </div>

    {{-- Action Buttons --}}
    @if(! $isReadOnly)
    <div class="flex items-center gap-3 flex-wrap">
        @if($result['isConsistent'])
            {{-- CR konsisten: tombol lanjut (disabled, SPK-05 belum tersedia) + revisi --}}
            <button disabled
                    title="Akan tersedia setelah SPK-05 selesai diimplementasi"
                    class="flex items-center gap-2 px-5 py-2.5 bg-gray-200 text-gray-400 font-semibold rounded-xl text-sm min-h-[44px] cursor-not-allowed">
                <i data-lucide="calculator" class="w-4 h-4"></i>
                Lanjut ke Kalkulasi Bobot (SPK-05)
            </button>
            <a href="{{ route('spk-melon.sesi-penilaian.perbandingan.edit', $sesi->id) }}"
               class="flex items-center gap-2 px-5 py-2.5 border-2 border-gray-300 text-gray-600 font-semibold rounded-xl text-sm min-h-[44px] hover:border-gray-400 hover:bg-gray-50 transition-all">
                <i data-lucide="git-compare" class="w-4 h-4"></i>
                Revisi Matriks Perbandingan
            </a>
        @else
            {{-- CR tidak konsisten: tombol revisi dominan + tombol lanjut disabled --}}
            <a href="{{ route('spk-melon.sesi-penilaian.perbandingan.edit', $sesi->id) }}"
               class="flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl text-sm min-h-[44px] shadow-sm transition-all">
                <i data-lucide="git-compare" class="w-4 h-4"></i>
                Revisi Matriks Perbandingan
            </a>
            <button disabled
                    title="Matriks harus konsisten (CR &lt; 0.10) sebelum melanjutkan"
                    class="flex items-center gap-2 px-5 py-2.5 bg-gray-200 text-gray-400 font-semibold rounded-xl text-sm min-h-[44px] cursor-not-allowed">
                <i data-lucide="calculator" class="w-4 h-4"></i>
                Lanjut ke Kalkulasi Bobot (SPK-05)
            </button>
        @endif
    </div>
    @endif

    {{-- Breakdown Perhitungan (Collapsible) --}}
    @if(! $result['isShortCircuit'])
    <details x-data="{ open: false }" :open="open" @toggle="open = $event.target.open"
             class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <summary class="cursor-pointer px-5 py-4 text-sm font-semibold text-gray-900 flex items-center gap-2 list-none">
            <i data-lucide="chevron-right" class="w-4 h-4 transition-transform duration-200" :class="open && 'rotate-90'"></i>
            Detail Perhitungan <em>Step-by-Step</em>
            <span class="ml-auto text-xs font-normal text-gray-400">7 tahap kalkulasi</span>
        </summary>
        <div class="px-5 pb-5 space-y-6 border-t border-gray-100 pt-4">

            @php
                $kriteriaList = $result['kriteriaList'];
                $n = $result['n'];
            @endphp

            {{-- Tahap 1: Matriks Crisp --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                    <span class="w-6 h-6 bg-gray-100 rounded-full text-xs font-bold flex items-center justify-center text-gray-600">1</span>
                    Matriks <em>Crisp</em> (dari kolom <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">tfnM</code>)
                </h3>
                <div class="overflow-x-auto">
                    <table class="text-xs font-mono border-collapse">
                        <thead>
                            <tr>
                                <th class="p-2 bg-gray-50 border border-gray-200 text-gray-500 min-w-[60px]"></th>
                                @foreach($kriteriaList as $k)
                                <th class="p-2 bg-gray-50 border border-gray-200 text-gray-700 font-bold min-w-[70px]">{{ $k->kode }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kriteriaList as $i => $ki)
                            <tr>
                                <td class="p-2 bg-gray-50 border border-gray-200 text-gray-700 font-bold text-center">{{ $ki->kode }}</td>
                                @foreach($result['crispMatrix'][$i] as $val)
                                <td class="p-2 border border-gray-100 text-center text-gray-800">{{ number_format($val, 4) }}</td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tahap 2: Column Sums --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                    <span class="w-6 h-6 bg-gray-100 rounded-full text-xs font-bold flex items-center justify-center text-gray-600">2</span>
                    Jumlah Kolom (<em>Column Sums</em>)
                </h3>
                <div class="overflow-x-auto">
                    <table class="text-xs font-mono border-collapse">
                        <thead>
                            <tr>
                                @foreach($kriteriaList as $k)
                                <th class="p-2 bg-gray-50 border border-gray-200 text-gray-700 font-bold min-w-[70px]">{{ $k->kode }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                @foreach($result['columnSums'] as $val)
                                <td class="p-2 border border-gray-100 text-center text-gray-800">{{ number_format($val, 4) }}</td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tahap 3: Matriks Ternormalisasi --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                    <span class="w-6 h-6 bg-gray-100 rounded-full text-xs font-bold flex items-center justify-center text-gray-600">3</span>
                    Matriks Ternormalisasi (elemen ÷ jumlah kolom)
                </h3>
                <div class="overflow-x-auto">
                    <table class="text-xs font-mono border-collapse">
                        <thead>
                            <tr>
                                <th class="p-2 bg-gray-50 border border-gray-200 text-gray-500 min-w-[60px]"></th>
                                @foreach($kriteriaList as $k)
                                <th class="p-2 bg-gray-50 border border-gray-200 text-gray-700 font-bold min-w-[70px]">{{ $k->kode }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kriteriaList as $i => $ki)
                            <tr>
                                <td class="p-2 bg-gray-50 border border-gray-200 text-gray-700 font-bold text-center">{{ $ki->kode }}</td>
                                @foreach($result['normalizedMatrix'][$i] as $val)
                                <td class="p-2 border border-gray-100 text-center text-gray-800">{{ number_format($val, 4) }}</td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tahap 4: Priority Vector --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                    <span class="w-6 h-6 bg-gray-100 rounded-full text-xs font-bold flex items-center justify-center text-gray-600">4</span>
                    <em>Priority Vector</em> (rata-rata baris matriks ternormalisasi)
                </h3>
                <div class="overflow-x-auto">
                    <table class="text-xs font-mono border-collapse">
                        <thead>
                            <tr>
                                <th class="p-2 bg-gray-50 border border-gray-200 text-gray-500 min-w-[60px]">Kriteria</th>
                                <th class="p-2 bg-gray-50 border border-gray-200 text-gray-700 font-bold min-w-[100px]">Bobot Prioritas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kriteriaList as $i => $ki)
                            <tr>
                                <td class="p-2 bg-gray-50 border border-gray-200 text-gray-700 font-bold text-center">{{ $ki->kode }}</td>
                                <td class="p-2 border border-gray-100 text-center text-gray-800">{{ number_format($result['priorityVector'][$i], 4) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tahap 5 & 6: Weighted Sum Vector & Consistency Vector --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                    <span class="w-6 h-6 bg-gray-100 rounded-full text-xs font-bold flex items-center justify-center text-gray-600">5</span>
                    <em>Weighted Sum Vector</em> & <em>Consistency Vector</em>
                </h3>
                <p class="text-xs text-gray-500 mb-2">WSV = Matriks Crisp × <em>Priority Vector</em> &nbsp;|&nbsp; CV = WSV ÷ <em>Priority Vector</em></p>
                <div class="overflow-x-auto">
                    <table class="text-xs font-mono border-collapse">
                        <thead>
                            <tr>
                                <th class="p-2 bg-gray-50 border border-gray-200 text-gray-500">Kriteria</th>
                                <th class="p-2 bg-gray-50 border border-gray-200 text-gray-700 font-bold min-w-[130px]"><em>Weighted Sum</em></th>
                                <th class="p-2 bg-gray-50 border border-gray-200 text-gray-700 font-bold min-w-[130px]"><em>Consistency Vector</em></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kriteriaList as $i => $ki)
                            <tr>
                                <td class="p-2 bg-gray-50 border border-gray-200 text-gray-700 font-bold text-center">{{ $ki->kode }}</td>
                                <td class="p-2 border border-gray-100 text-center text-gray-800">{{ number_format($result['weightedSumVector'][$i], 4) }}</td>
                                <td class="p-2 border border-gray-100 text-center text-gray-800">{{ number_format($result['consistencyVector'][$i], 4) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tahap 7: λmax, CI, RI, CR --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                    <span class="w-6 h-6 bg-gray-100 rounded-full text-xs font-bold flex items-center justify-center text-gray-600">7</span>
                    Kalkulasi <em>Consistency Index</em> (CI), <em>Random Index</em> (RI), dan CR Final
                </h3>
                <div class="bg-gray-50 rounded-lg p-4 space-y-2 font-mono text-sm">
                    <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                        <span class="text-gray-600">λ<sub>max</sub> = rata-rata <em>Consistency Vector</em></span>
                        <span class="font-bold text-gray-900">{{ number_format($result['lambdaMax'], 4) }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                        <span class="text-gray-600">CI = (λ<sub>max</sub> − n) / (n − 1) = ({{ number_format($result['lambdaMax'], 4) }} − {{ $n }}) / ({{ $n }} − 1)</span>
                        <span class="font-bold text-gray-900">{{ number_format($result['ci'], 4) }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                        <span class="text-gray-600">RI (n = {{ $n }}, tabel Saaty 1980)</span>
                        <span class="font-bold text-gray-900">{{ number_format($result['ri'], 4) }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                        <span class="text-gray-600 font-semibold">CR = CI / RI = {{ number_format($result['ci'], 4) }} / {{ number_format($result['ri'], 4) }}</span>
                        <span class="font-bold text-lg {{ $crColor }}">{{ number_format($result['cr'], 4) }}</span>
                    </div>
                </div>
            </div>

        </div>
    </details>
    @endif

    {{-- Catatan Interpretasi --}}
    <div class="bg-gray-50 rounded-xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
            <i data-lucide="book-open" class="w-4 h-4 text-gray-400"></i>
            Catatan Interpretasi
        </h3>
        <ul class="text-sm text-gray-600 space-y-1.5 list-disc list-inside">
            <li>Nilai <em>Consistency Ratio</em> (CR) mengukur tingkat konsistensi logis penilaian perbandingan berpasangan.</li>
            <li>Jika <strong>CR &lt; 0.10</strong>, matriks dianggap konsisten dan proses dapat dilanjutkan ke kalkulasi bobot Fuzzy AHP (SPK-05).</li>
            <li>Jika <strong>CR ≥ 0.10</strong>, penilaian perlu ditinjau ulang. Periksa apakah terdapat inkonsistensi transitif, misalnya A lebih penting dari B, B lebih penting dari C, tetapi C dinilai lebih penting dari A.</li>
            <li>Kalkulasi CR menggunakan nilai <em>crisp</em> (kolom <code class="text-xs bg-gray-200 px-1 rounded">tfnM</code>) dari <em>Triangular Fuzzy Number</em>, bukan nilai Saaty mentah.</li>
            <li>Nilai CR dihitung ulang setiap kali halaman ini diakses berdasarkan data matriks terbaru.</li>
        </ul>
    </div>

</div>
@endsection
