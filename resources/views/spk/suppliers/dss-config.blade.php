@extends('layouts.app')

@section('title', 'Konfigurasi AHP - Bobot Supplier')
@section('breadcrumb', 'Supplier DSS > Strategi (AHP)')

@section('content')
@php
    $userResolved = isset($userId) && $userId !== null;
    $pairCount = count($pairs);
    $validLatest = ($latestConfig ?? null) && $latestConfig->is_valid;
    $ahpResult = session('ahp_result');

    $initialValues = [];
    foreach ($pairs as $idx => $pair) {
        $key = $pair['parameter_1']->id.'-'.$pair['parameter_2']->id;
        $initialValues[$idx] = (float) old(
            "perbandingans.$idx.nilai_skala",
            isset($saved[$key]) ? $saved[$key]->nilai_skala : 1
        );
    }

    $saatyLeft = [
        ['value' => 9, 'label' => '9', 'caption' => 'Mutlak kiri'],
        ['value' => 7, 'label' => '7', 'caption' => 'Sangat kuat'],
        ['value' => 5, 'label' => '5', 'caption' => 'Kuat'],
        ['value' => 3, 'label' => '3', 'caption' => 'Sedikit'],
    ];

    $saatyRight = [
        ['value' => 0.333, 'label' => '1/3', 'caption' => 'Sedikit'],
        ['value' => 0.2, 'label' => '1/5', 'caption' => 'Kuat'],
        ['value' => 0.143, 'label' => '1/7', 'caption' => 'Sangat kuat'],
        ['value' => 0.111, 'label' => '1/9', 'caption' => 'Mutlak kanan'],
    ];

    $steps = [
        ['title' => 'Pahami Kriteria', 'body' => 'Cek arti benefit/cost agar penilaian tidak terbalik.'],
        ['title' => 'Isi Perbandingan', 'body' => 'Bandingkan dua kriteria pada setiap kartu pasangan.'],
        ['title' => 'Validasi CR', 'body' => 'Sistem menyimpan bobot jika Consistency Ratio <= 0.1.'],
        ['title' => 'Lanjut SAW', 'body' => 'Bobot valid dipakai untuk ranking supplier dan restock.'],
    ];
@endphp

<div x-data="ahpConfig(@js($initialValues))" class="mx-auto max-w-7xl space-y-5 pb-8">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-bold">Form belum bisa disimpan.</p>
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!$userResolved)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
            <p class="font-bold">Pengguna tidak terhubung ke tabel user.</p>
            <p class="mt-2 leading-6">
                Form AHP dinonaktifkan karena akun login tidak dapat dipetakan ke tabel pengguna aplikasi.
                Pastikan ID session atau email akun tersedia di tabel <code class="rounded bg-white px-1 py-0.5">user</code>.
            </p>
        </div>
    @endif

    <section class="rounded-2xl border border-gray-100 bg-white p-5 md:p-6" style="box-shadow: var(--shadow-sm);">
        <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="min-w-0">
                <div class="mb-2 flex flex-wrap items-center gap-2 text-sm">
                    <a href="{{ route('settings.index') }}" class="font-medium text-gray-400 hover:text-gray-600">Pengaturan</a>
                    <span class="text-gray-300">/</span>
                    <span class="font-semibold text-gray-700">Supplier DSS</span>
                    <span class="text-gray-300">/</span>
                    <span class="font-semibold text-emerald-700">Strategi AHP</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 md:text-3xl">Atur Bobot Kriteria Supplier</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500">
                    AHP membantu menentukan prioritas kriteria sebelum sistem SAW meranking supplier. Isi setiap pasangan berdasarkan kebutuhan restock owner.
                </p>

                <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                    @foreach($steps as $idx => $step)
                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-xs font-bold text-emerald-700">{{ $idx + 1 }}</span>
                                <p class="text-sm font-bold text-gray-900">{{ $step['title'] }}</p>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-gray-500">{{ $step['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <aside class="rounded-2xl border {{ $validLatest ? 'border-emerald-100 bg-emerald-50' : 'border-amber-100 bg-amber-50' }} p-4">
                <p class="text-xs font-bold uppercase tracking-wide {{ $validLatest ? 'text-emerald-700' : 'text-amber-700' }}">Status AHP</p>
                @if($latestConfig ?? null)
                    <div class="mt-2 flex items-end justify-between gap-3">
                        <div>
                            <p class="text-2xl font-black {{ $validLatest ? 'text-emerald-900' : 'text-amber-900' }}">CR {{ number_format($latestConfig->cr, 4) }}</p>
                            <p class="mt-1 text-xs {{ $validLatest ? 'text-emerald-700' : 'text-amber-700' }}">Versi {{ $latestConfig->version }} - {{ $validLatest ? 'Valid' : 'Tidak valid' }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $validLatest ? 'bg-emerald-600 text-white' : 'bg-amber-200 text-amber-900' }}">
                            {{ $validLatest ? 'Siap SAW' : 'Perlu cek' }}
                        </span>
                    </div>
                @else
                    <p class="mt-2 text-sm font-semibold text-amber-900">Belum ada bobot valid.</p>
                    <p class="mt-1 text-xs leading-5 text-amber-700">Isi pasangan AHP dan simpan agar ranking supplier bisa berjalan.</p>
                @endif

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="rounded-xl bg-white/70 p-3">
                        <p class="text-xs font-semibold text-gray-500">Kriteria</p>
                        <p class="mt-1 text-xl font-black text-gray-900">{{ $parameters->count() }}</p>
                    </div>
                    <div class="rounded-xl bg-white/70 p-3">
                        <p class="text-xs font-semibold text-gray-500">Pasangan</p>
                        <p class="mt-1 text-xl font-black text-gray-900">{{ $pairCount }}</p>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-2 sm:flex-row xl:flex-col">
                    <a href="{{ route('spk.suppliers.dss.dashboard') }}" class="inline-flex flex-1 items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700">Lanjut ke SAW</a>
                    <a href="{{ route('settings.index') }}" class="inline-flex flex-1 items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 transition hover:bg-gray-50">Kembali</a>
                </div>
            </aside>
        </div>
    </section>

    @if($ahpResult)
        <section class="rounded-2xl border {{ $ahpResult['is_valid'] ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50' }} p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide {{ $ahpResult['is_valid'] ? 'text-emerald-700' : 'text-red-700' }}">Hasil validasi terakhir</p>
                    <h2 class="mt-1 text-lg font-bold {{ $ahpResult['is_valid'] ? 'text-emerald-950' : 'text-red-950' }}">
                        {{ $ahpResult['is_valid'] ? 'Konsisten' : 'Belum konsisten' }}
                    </h2>
                    <p class="mt-1 text-sm leading-6 {{ $ahpResult['is_valid'] ? 'text-emerald-700' : 'text-red-700' }}">
                        CR harus <= 0.1. Jika belum konsisten, tinjau pasangan yang nilainya terlalu ekstrem atau saling bertentangan.
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-white/80 px-4 py-3">
                        <p class="text-[11px] font-bold uppercase text-gray-400">Lambda max</p>
                        <p class="mt-1 font-mono text-sm font-bold text-gray-900">{{ $ahpResult['lambda_max'] ?? '-' }}</p>
                    </div>
                    <div class="rounded-xl bg-white/80 px-4 py-3">
                        <p class="text-[11px] font-bold uppercase text-gray-400">CI</p>
                        <p class="mt-1 font-mono text-sm font-bold text-gray-900">{{ $ahpResult['ci'] ?? '-' }}</p>
                    </div>
                    <div class="rounded-xl bg-white/80 px-4 py-3">
                        <p class="text-[11px] font-bold uppercase text-gray-400">CR</p>
                        <p class="mt-1 font-mono text-sm font-bold text-gray-900">{{ $ahpResult['cr'] ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[380px_minmax(0,1fr)]">
        <aside class="space-y-5 xl:self-start">
            <section class="rounded-2xl border border-gray-100 bg-white p-5" style="box-shadow: var(--shadow-sm);">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">Kriteria DSS</h2>
                        <p class="mt-1 text-xs leading-5 text-gray-500">Benefit semakin besar semakin baik. Cost semakin kecil semakin baik.</p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-600">{{ $parameters->count() }}</span>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach($parameters as $p)
                        <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900">{{ $p->nama_parameter }}</p>
                                    @if($p->deskripsi)
                                        <p class="mt-1 text-xs leading-5 text-gray-500">{{ $p->deskripsi }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-black uppercase {{ $p->tipe === 'benefit' ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-900' }}">
                                    {{ $p->tipe }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-indigo-100 bg-indigo-50 p-5">
                <h2 class="text-sm font-bold text-indigo-950">Cara membaca skala Saaty</h2>
                <div class="mt-4 space-y-2 text-xs text-indigo-900">
                    <div class="flex justify-between gap-3 border-b border-indigo-200/70 pb-2"><span>1</span><strong>Sama penting</strong></div>
                    <div class="flex justify-between gap-3 border-b border-indigo-200/70 pb-2"><span>3</span><strong>Sedikit lebih penting</strong></div>
                    <div class="flex justify-between gap-3 border-b border-indigo-200/70 pb-2"><span>5</span><strong>Kuat lebih penting</strong></div>
                    <div class="flex justify-between gap-3 border-b border-indigo-200/70 pb-2"><span>7</span><strong>Sangat kuat</strong></div>
                    <div class="flex justify-between gap-3 border-b border-indigo-200/70 pb-2"><span>9</span><strong>Mutlak lebih penting</strong></div>
                    <div class="flex justify-between gap-3"><span>1/3 - 1/9</span><strong>Kriteria kanan lebih penting</strong></div>
                </div>
                <p class="mt-4 rounded-xl bg-white/70 px-3 py-2 text-xs leading-5 text-indigo-800">
                    Pilih tombol hijau jika kriteria kiri lebih penting. Pilih tombol amber jika kriteria kanan lebih penting.
                </p>
            </section>

            @if(($bobots ?? collect())->isNotEmpty() && $userResolved)
                <section class="rounded-2xl border border-gray-100 bg-white p-5" style="box-shadow: var(--shadow-sm);">
                    <h2 class="text-sm font-bold text-gray-900">Bobot Tersimpan</h2>
                    <div class="mt-4 space-y-3">
                        @foreach($bobots as $b)
                            @php $percent = max(0, min(100, (float) $b->bobot * 100)); @endphp
                            <div>
                                <div class="mb-1 flex items-center justify-between gap-3">
                                    <span class="truncate text-xs font-semibold text-gray-700">{{ $b->parameter->nama_parameter ?? '-' }}</span>
                                    <span class="font-mono text-xs font-bold text-gray-900">{{ number_format($b->bobot, 4) }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full {{ $b->is_valid ? 'bg-emerald-500' : 'bg-red-400' }}" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>

        <main class="min-w-0">
            @if(($parameters->count() ?? 0) >= 2 && $userResolved)
                <form method="POST" action="{{ route('spk.suppliers.dss.config.store') }}" id="ahp-form" class="space-y-4">
                    @csrf

                    <section class="rounded-2xl border border-gray-100 bg-white p-5" style="box-shadow: var(--shadow-sm);">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h2 class="text-base font-bold text-gray-900">Perbandingan Berpasangan</h2>
                                <p class="mt-1 text-sm leading-6 text-gray-500">
                                    Isi {{ $pairCount }} pasangan. Nilai default 1 berarti kedua kriteria sama penting.
                                </p>
                            </div>
                            <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                <span class="font-bold" x-text="filledCount()"></span>
                                <span>dari {{ $pairCount }} pasangan siap dihitung</span>
                            </div>
                        </div>
                    </section>

                    @foreach($pairs as $idx => $pair)
                        @php
                            $left = $pair['parameter_1'];
                            $right = $pair['parameter_2'];
                            $savedVal = $initialValues[$idx] ?? 1;
                        @endphp

                        <article class="rounded-2xl border border-gray-100 bg-white p-4 md:p-5" style="box-shadow: var(--shadow-sm);">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Pasangan {{ $idx + 1 }} dari {{ $pairCount }}</span>
                                    <h3 class="mt-1 text-base font-bold text-gray-900">
                                        <span class="text-emerald-700">{{ $left->nama_parameter }}</span>
                                        <span class="mx-2 text-sm font-medium text-gray-400">dibandingkan</span>
                                        <span class="text-amber-700">{{ $right->nama_parameter }}</span>
                                    </h3>
                                    <p class="mt-1 text-xs leading-5 text-gray-500">
                                        Tentukan mana yang lebih penting untuk memilih supplier pada kebutuhan restock.
                                    </p>
                                </div>
                                <div class="rounded-xl bg-gray-50 px-3 py-2 text-right">
                                    <p class="text-[11px] font-bold uppercase text-gray-400">Nilai dikirim</p>
                                    <p class="font-mono text-sm font-black text-gray-900" x-text="formatValue(pairValues[{{ $idx }}])"></p>
                                </div>
                            </div>

                            <input type="hidden" name="perbandingans[{{ $idx }}][parameter_1_id]" value="{{ $left->id }}">
                            <input type="hidden" name="perbandingans[{{ $idx }}][parameter_2_id]" value="{{ $right->id }}">
                            <input type="hidden" name="perbandingans[{{ $idx }}][nilai_skala]" :value="formatValue(pairValues[{{ $idx }}])">

                            <div class="mt-5 grid grid-cols-1 gap-3">
                                <div>
                                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-emerald-700">{{ $left->nama_parameter }} lebih penting</p>
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        @foreach($saatyLeft as $option)
                                            <button type="button" @click="setPair({{ $idx }}, {{ $option['value'] }})"
                                                :class="isSelected({{ $idx }}, {{ $option['value'] }}) ? 'border-emerald-600 bg-emerald-600 text-white shadow-md' : 'border-gray-200 bg-white text-gray-700 hover:border-emerald-300 hover:bg-emerald-50'"
                                                class="min-h-14 rounded-xl border px-3 py-2 text-left transition">
                                                <span class="block text-lg font-black">{{ $option['label'] }}</span>
                                                <span class="block text-[11px] font-semibold opacity-80">{{ $option['caption'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <div>
                                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Netral</p>
                                    <button type="button" @click="setPair({{ $idx }}, 1)"
                                        :class="isSelected({{ $idx }}, 1) ? 'border-gray-900 bg-gray-900 text-white shadow-md' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-400 hover:bg-gray-50'"
                                        class="min-h-14 w-full rounded-xl border px-3 py-2 text-left transition sm:max-w-[220px]">
                                        <span class="block text-lg font-black">1</span>
                                        <span class="block text-[11px] font-semibold opacity-80">Sama penting</span>
                                    </button>
                                </div>

                                <div>
                                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-amber-700">{{ $right->nama_parameter }} lebih penting</p>
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        @foreach($saatyRight as $option)
                                            <button type="button" @click="setPair({{ $idx }}, {{ $option['value'] }})"
                                                :class="isSelected({{ $idx }}, {{ $option['value'] }}) ? 'border-amber-500 bg-amber-500 text-white shadow-md' : 'border-gray-200 bg-white text-gray-700 hover:border-amber-300 hover:bg-amber-50'"
                                                class="min-h-14 rounded-xl border px-3 py-2 text-left transition">
                                                <span class="block text-lg font-black">{{ $option['label'] }}</span>
                                                <span class="block text-[11px] font-semibold opacity-80">{{ $option['caption'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach

                    <section class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h2 class="text-base font-bold text-emerald-950">Hitung bobot dan validasi konsistensi</h2>
                                <p class="mt-1 text-sm leading-6 text-emerald-800">
                                    Sistem akan menghitung lambda max, CI, CR, lalu menyimpan bobot hanya jika CR <= 0.1.
                                </p>
                            </div>
                            <button type="submit" form="ahp-form" class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-emerald-600/25 transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 lg:w-auto">
                                Hitung & Simpan Bobot
                            </button>
                        </div>
                    </section>
                </form>
            @elseif(($parameters->count() ?? 0) < 2)
                <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-600">
                    Belum cukup kriteria. Jalankan <code class="rounded bg-white px-1 py-0.5">php artisan db:seed --class=SpkSupplierSeeder</code>.
                </div>
            @endif
        </main>
    </div>
</div>

<script>
function ahpConfig(initialValues) {
    return {
        pairValues: initialValues || {},

        setPair(index, value) {
            this.pairValues[index] = Number(value);
        },

        isSelected(index, value) {
            return Math.abs(Number(this.pairValues[index] ?? 1) - Number(value)) < 0.002;
        },

        formatValue(value) {
            return Number(value ?? 1).toFixed(3);
        },

        filledCount() {
            return Object.keys(this.pairValues).length;
        },
    };
}
</script>
@endsection
