@extends('layouts.app')

@section('title', 'Konfigurasi AHP — Bobot Supplier')
@section('breadcrumb', 'Supplier DSS > Strategi (AHP)')

@section('content')
<div class="max-w-4xl mx-auto pb-6">
    {{-- Flash --}}
    @if(session('success'))
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ session('error') }}</div>
    @endif

    @php $userResolved = isset($userId) && $userId !== null; @endphp

    @if(!$userResolved)
        <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-950 shadow-sm">
            <p class="font-bold flex items-center gap-2 mb-2">
                <span class="text-lg">⚠</span>
                Pengguna tidak terhubung ke tabel `users`
            </p>
            <p class="leading-relaxed text-amber-900/95">
                Session login mengisi <code class="bg-white/80 px-1 rounded border border-amber-200">id</code> dengan <strong>0</strong> atau ID yang tidak ada di basis data —
                penyebab utama error foreign key pada konfigurasi AHP.
            </p>
            <ul class="mt-3 list-disc ms-5 space-y-1 text-amber-900/85">
                <li>Pastikan API login menyimpan primary key Laravel yang sama dengan kolom <code class="bg-white/60 px-0.5 rounded">users.id</code> atau</li>
                <li>Pastikan <strong>email</strong> di session sama dengan salah satu baris di tabel <code class="bg-white/60 px-0.5 rounded">users</code>.</li>
            </ul>
            <p class="mt-3 text-xs text-amber-800/70">Tanpa pemetaan ini form di bawah dinonaktifkan.</p>
        </div>
    @endif

    {{-- Hero + steps --}}
    <div class="rounded-3xl bg-gradient-to-br from-emerald-600 via-teal-600 to-emerald-800 px-6 py-8 sm:px-8 text-white shadow-lg shadow-emerald-900/25 mb-6">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-100/90">Modul DSS · Strategi</p>
                <h1 class="mt-2 text-2xl sm:text-3xl font-extrabold tracking-tight">Bobot Kriteria (AHP)</h1>
                <p class="mt-3 text-sm leading-relaxed text-emerald-50/95 max-w-xl">
                    Isi setiap pasangan dengan skala Saaty. Sistem menolak apabila <strong>Rasio Konsistensi (CR) &gt; 0.1</strong>.
                </p>
                <nav class="mt-6 flex flex-wrap gap-2 text-xs font-semibold" aria-label="Alur DSS">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 ring-1 ring-white/20">① Strategi · AHP</span>
                    <span class="inline-flex opacity-90">→</span>
                    <a href="{{ route('spk.suppliers.dss.dashboard') }}" class="inline-flex items-center rounded-full px-3 py-1.5 hover:bg-white/10 ring-1 ring-white/20 transition">② Operasi · SAW</a>
                </nav>
            </div>
            <div class="flex flex-wrap gap-2 justify-end shrink-0 w-full lg:w-auto">
                    <a href="{{ route('spk.suppliers.dss.dashboard') }}" class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-bold text-emerald-800 shadow-sm hover:bg-emerald-50 transition">Lanjut ke SAW</a>
                    <a href="{{ route('settings.index') }}" class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-bold text-white ring-1 ring-white/40 hover:bg-white/10 transition">← Pengaturan</a>
                </div>
        </div>

        @if($latestConfig ?? null && $userResolved)
            <div class="mt-6 flex flex-wrap items-center gap-3 rounded-2xl bg-black/20 px-4 py-3 backdrop-blur-sm ring-1 ring-white/15">
                <span class="text-xs uppercase font-bold text-emerald-100/85">Konfig valid terakhir</span>
                <span class="font-mono text-sm font-bold">CR {{ number_format($latestConfig->cr, 4) }}</span>
                <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase {{ $latestConfig->is_valid ? 'bg-emerald-400 text-emerald-950' : 'bg-red-400 text-white' }}">
                    {{ $latestConfig->is_valid ? 'Passed' : 'Failed' }}
                </span>
                <span class="text-xs opacity-85">Versi {{ $latestConfig->version }}</span>
            </div>
        @endif
    </div>

    {{-- Criteria chips --}}
    <div class="rounded-3xl bg-white shadow-sm ring-1 ring-gray-900/5 p-5 sm:p-6 mb-6">
        <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-4">Daftar Kriteria</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($parameters as $p)
                <div class="rounded-2xl border border-gray-100 bg-gray-50/80 px-4 py-3 hover:border-emerald-200 hover:bg-emerald-50/30 transition">
                    <p class="font-bold text-gray-900">{{ $p->nama_parameter }}</p>
                    <span class="mt-1 inline-block text-[10px] font-black uppercase px-2 py-0.5 rounded-md {{ $p->tipe === 'benefit' ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-900' }}">{{ $p->tipe }}</span>
                    @if($p->deskripsi)
                        <p class="mt-2 text-xs text-gray-500 leading-snug">{{ $p->deskripsi }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Legenda Saaty --}}
    <div class="rounded-3xl bg-indigo-50 ring-1 ring-indigo-100 p-5 sm:p-6 mb-6">
        <div class="flex items-start gap-4">
            <span class="text-2xl shrink-0" aria-hidden="true">📏</span>
            <div>
                <h2 class="font-bold text-indigo-950">Legenda Skala Saaty</h2>
                <dl class="mt-3 grid sm:grid-cols-2 gap-x-8 gap-y-2 text-xs text-indigo-900">
                    <div class="flex justify-between gap-2 border-b border-indigo-200/70 pb-1"><dt>1</dt><dd class="font-medium text-right">Sama penting</dd></div>
                    <div class="flex justify-between gap-2 border-b border-indigo-200/70 pb-1"><dt>3</dt><dd class="font-medium text-right">Sedikit lebih penting</dd></div>
                    <div class="flex justify-between gap-2 border-b border-indigo-200/70 pb-1"><dt>5</dt><dd class="font-medium text-right">Jelas lebih penting</dd></div>
                    <div class="flex justify-between gap-2 border-b border-indigo-200/70 pb-1"><dt>7</dt><dd class="font-medium text-right">Sangat jelas lebih penting</dd></div>
                    <div class="flex justify-between gap-2 border-b border-indigo-200/70 pb-1"><dt>9</dt><dd class="font-medium text-right">Mutlak lebih penting</dd></div>
                    <div class="flex justify-between gap-2 border-b border-indigo-200/70 pb-1"><dt>1/3 … 1/9</dt><dd class="font-medium text-right">Kebalikan: kriteria kanan lebih penting</dd></div>
                </dl>
                <p class="mt-3 text-[11px] text-indigo-800/85 leading-relaxed">
                    Pada setiap pasangan pilih salah satu tombol hijau (= kriteria di <strong>kiri</strong> lebih penting) atau oranye (= kriteria di <strong>kanan</strong> lebih penting secara relatif).
                </p>
            </div>
        </div>
    </div>

    @if(($parameters->count() ?? 0) >= 2 && $userResolved)
        <form method="POST" action="{{ route('spk.suppliers.dss.config.store') }}" id="ahp-form" class="space-y-6">
            @csrf
            <div class="rounded-3xl bg-white shadow-sm ring-1 ring-gray-900/5 overflow-hidden">
                <div class="border-b border-gray-100 px-5 sm:px-6 py-4 bg-gray-50/80">
                    <h2 class="text-sm font-bold text-gray-900">Perbandingan berpasangan</h2>
                    <p class="text-xs text-gray-500 mt-1">{{ count($pairs) }} pasangan — semua harus diisi untuk matriks konsisten.</p>
                </div>

                @foreach($pairs as $idx => $pair)
                    @php
                        $key = $pair['parameter_1']->id.'-'.$pair['parameter_2']->id;
                        $savedVal = isset($saved[$key]) ? $saved[$key]->nilai_skala : 1;
                    @endphp
                    <div class="border-b border-gray-50 last:border-0 px-5 sm:px-6 py-6 hover:bg-emerald-50/[0.15] transition">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-5">
                            <div class="min-w-0">
                                <span class="text-[10px] font-bold uppercase text-gray-400">Pasangan {{ $idx + 1 }} / {{ count($pairs) }}</span>
                                <p class="mt-1 font-bold text-gray-900">
                                    <span class="text-emerald-700">{{ $pair['parameter_1']->nama_parameter }}</span>
                                    <span class="mx-2 font-normal text-gray-400 text-sm">dibandingkan</span>
                                    <span class="text-purple-700">{{ $pair['parameter_2']->nama_parameter }}</span>
                                </p>
                                <p class="mt-1 text-[11px] text-gray-500">Hijau: kiri lebih penting &nbsp;·&nbsp; Oranye: kanan lebih penting</p>
                            </div>
                            <span class="self-start rounded-lg bg-emerald-100 text-emerald-900 px-3 py-1 text-xs font-mono font-semibold whitespace-nowrap" title="Terakhir yang dipilih akan disimpan">nilai kirim → <span id="preview-{{ $idx }}">{{ number_format($savedVal, 3) }}</span></span>
                        </div>

                        <div class="space-y-3">
                            <p class="text-[10px] font-bold uppercase text-emerald-800/80 tracking-wider">Preferensi mendukung "{{ $pair['parameter_1']->nama_parameter }}"</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach([9, 7, 5, 3, 1] as $scale)
                                    <label class="cursor-pointer touch-manipulation">
                                        <input
                                            type="radio"
                                            name="pair_{{ $idx }}"
                                            class="peer sr-only"
                                            {{ abs($savedVal-$scale)<0.01 ? 'checked' : '' }}
                                            onchange="(function(){var h=document.getElementById('s{{ $idx }}'),p=document.getElementById('preview-{{ $idx }}');if(h)h.value={{ $scale }};if(p)p.textContent='{{ number_format((float)$scale, 3, '.', '') }}';})()"
                                        >
                                        <span class="flex h-11 min-w-[2.75rem] items-center justify-center rounded-xl border border-gray-200 bg-white text-xs font-extrabold peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 peer-checked:shadow-md hover:border-emerald-300 transition">{{ $scale }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <p class="text-[10px] font-bold uppercase text-amber-900/85 tracking-wider mt-5">Preferensi mendukung "{{ $pair['parameter_2']->nama_parameter }}"</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach([['v'=>0.333,'l'=>'1/3'],['v'=>0.2,'l'=>'1/5'],['v'=>0.143,'l'=>'1/7'],['v'=>0.111,'l'=>'1/9']] as $inv)
                                    <label class="cursor-pointer touch-manipulation">
                                        <input
                                            type="radio"
                                            name="pair_{{ $idx }}"
                                            class="peer sr-only"
                                            {{ abs($savedVal-(float)$inv['v'])<0.02 ? 'checked' : '' }}
                                            onchange="(function(){var h=document.getElementById('s{{ $idx }}'),p=document.getElementById('preview-{{ $idx }}');if(h)h.value={{ $inv['v'] }};if(p)p.textContent='{{ number_format((float)$inv['v'], 3, '.', '') }}';})()"
                                        >
                                        <span class="flex h-11 min-w-[2.75rem] items-center justify-center rounded-xl border border-gray-200 bg-white text-xs font-extrabold peer-checked:bg-amber-500 peer-checked:text-white peer-checked:border-amber-500 peer-checked:shadow-md hover:border-amber-300 transition">{{ $inv['l'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <input type="hidden" name="perbandingans[{{ $idx }}][parameter_1_id]" value="{{ $pair['parameter_1']->id }}">
                        <input type="hidden" name="perbandingans[{{ $idx }}][parameter_2_id]" value="{{ $pair['parameter_2']->id }}">
                        <input type="hidden" name="perbandingans[{{ $idx }}][nilai_skala]" id="s{{ $idx }}" value="{{ $savedVal }}">
                    </div>
                @endforeach
            </div>

            {{-- Sticky footer --}}
            <div class="sticky bottom-0 z-20 border-t border-gray-200/80 bg-white/95 backdrop-blur-md px-4 py-4 shadow-[0_-8px_30px_-12px_rgba(0,0,0,.12)]">
                <div class="mx-auto flex max-w-4xl flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 rounded-3xl bg-emerald-50/60 p-4 sm:p-5 ring-1 ring-emerald-100">
                    <div class="text-xs text-gray-600 lg:text-gray-700">
                        <span class="font-bold text-emerald-800">Konfirmasi?</span>
                        Anda akan menghitung λmax, CI, CR, dan menyimpan hanya jika CR ≤ 0.1.
                    </div>
                    <button type="submit" form="ahp-form" class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-8 py-3 text-sm font-extrabold text-white shadow-lg shadow-emerald-600/35 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 disabled:shadow-none transition">
                        Hitung bobot &amp; Validasi konsistensi
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </button>
                </div>
            </div>
        </form>
    @elseif(($parameters->count() ?? 0) < 2)
        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-600">
            Belum cukup kriteria. Jalankan <code class="rounded bg-white px-1 py-0.5">php artisan db:seed --class=SpkSupplierSeeder</code>
        </div>
    @endif

    @if(($bobots ?? collect())->isNotEmpty() && $userResolved)
        <div class="rounded-3xl bg-white shadow-sm ring-1 ring-gray-900/5 p-5 sm:p-6 mt-6">
            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-600 mb-4">Bobot terakhir (tersimpan)</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[320px]">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100">
                            <th class="pb-3 pr-4">Kriteria</th>
                            <th class="pb-3 pr-4">Tipe</th>
                            <th class="pb-3 pr-4">Bobot</th>
                            <th class="pb-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($bobots as $b)
                            <tr>
                                <td class="py-3 font-semibold text-gray-900">{{ $b->parameter->nama_parameter ?? '-' }}</td>
                                <td class="py-3 text-gray-600">{{ $b->parameter->tipe ?? '-' }}</td>
                                <td class="py-3 font-mono text-gray-900">{{ number_format($b->bobot, 4) }}</td>
                                <td class="py-3">
                                    <span class="text-xs font-bold {{ $b->is_valid ? 'text-emerald-600' : 'text-red-600' }}">{{ $b->is_valid ? 'Valid' : 'Tidak valid' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
