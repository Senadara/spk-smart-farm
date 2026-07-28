@extends('layouts.app')

@section('title', 'Scheduler Indikasi Kesehatan')
@section('breadcrumb', 'Pengaturan / Notifikasi / Indikasi Kesehatan')

@section('content')
@php
    $summary = $setting['last_summary'] ?? [];
    $roleLabels = [
        'petugas' => 'Petugas',
        'pjawab' => 'Penanggung Jawab',
        'owner' => 'Owner',
        'all' => 'Semua Role',
    ];
@endphp

<div class="mx-auto max-w-6xl space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ route('settings.notifications.index', ['tab' => 'health']) }}" class="text-xs font-bold text-gray-400 hover:text-gray-600" style="text-decoration:none;">Notifikasi</a>
            <h1 class="mt-1 text-2xl font-black text-gray-950">Scheduler Indikasi Kesehatan</h1>
            <p class="mt-1 max-w-3xl text-sm leading-relaxed text-gray-500">
                Cek HDP individu dua kali sehari dan kirim indikasi ke mobile setelah panen tersedia.
            </p>
        </div>

        <form method="POST" action="{{ route('settings.health-scheduler.run') }}">
            @csrf
            <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700 sm:w-auto">
                Jalankan Sekarang
            </button>
        </form>
    </div>

    <x-page-hint title="Cara kerja singkat" tone="sky" :open="false">
        Sistem hanya membuat indikasi jika data panen sesi pagi/sore sudah masuk. Unit tanpa panen dilewati agar data tetap valid.
    </x-page-hint>

    @if(session('success') || session('error'))
        <div class="rounded-xl border px-4 py-3 text-sm font-semibold {{ session('success') ? 'border-emerald-100 bg-emerald-50 text-emerald-700' : 'border-red-100 bg-red-50 text-red-700' }}">
            {{ session('success') ?? session('error') }}
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        <form method="POST" action="{{ route('settings.health-scheduler.update') }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
            @csrf
            @method('PUT')

            <div class="flex items-start justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50 p-4">
                <div>
                    <p class="text-sm font-black text-gray-900">Aktifkan Scheduler</p>
                    <p class="mt-1 text-xs leading-relaxed text-gray-500">Jika aktif, Node akan mengecek jadwal setiap menit dan menjalankan analisis saat jam cocok.</p>
                </div>
                <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" name="is_enabled" value="1" class="peer sr-only" @checked(old('is_enabled', $setting['is_enabled']))>
                    <span class="h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition peer-checked:bg-emerald-600 peer-checked:after:translate-x-5"></span>
                </label>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Jam pengecekan pagi</span>
                    <input type="time" name="morning_time" value="{{ old('morning_time', $setting['morning_time']) }}" required class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    @error('morning_time')
                        <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                    @enderror
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Jam pengecekan sore</span>
                    <input type="time" name="afternoon_time" value="{{ old('afternoon_time', $setting['afternoon_time']) }}" required class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    @error('afternoon_time')
                        <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                    @enderror
                </label>
                <p class="text-xs leading-relaxed text-gray-400 md:col-span-2">Dijalankan pagi dan sore. Jika panen sesi terkait belum masuk, data hari itu tidak diproses.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <label class="block">
                    <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Periode banding</span>
                    <select name="days" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                        @foreach([7, 14, 30] as $day)
                            <option value="{{ $day }}" @selected((int) old('days', $setting['days']) === $day)>{{ $day }} hari</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Ambang penurunan</span>
                    <input type="number" name="threshold_percent" min="1" max="100" step="1" value="{{ old('threshold_percent', number_format((float) $setting['threshold_percent'], 0, '.', '')) }}" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                    @error('threshold_percent')
                        <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                    @enderror
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase tracking-wide text-gray-500">Target notifikasi</span>
                    <select name="target_role" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                        @foreach($roleLabels as $role => $label)
                            <option value="{{ $role }}" @selected(old('target_role', $setting['target_role']) === $role)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:justify-end">
                <a href="{{ route('settings.notifications.index', ['tab' => 'health']) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-200 px-4 py-2 text-sm font-bold text-gray-600 hover:bg-gray-50" style="text-decoration:none;">Kembali</a>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700">
                    Simpan Scheduler
                </button>
            </div>
        </form>

        <aside class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Status</p>
                <p class="mt-2 text-lg font-black {{ $setting['is_enabled'] ? 'text-emerald-700' : 'text-gray-500' }}">
                    {{ $setting['is_enabled'] ? 'Aktif' : 'Nonaktif' }}
                </p>
                <p class="mt-1 text-xs leading-relaxed text-gray-500">
                    Jadwal: {{ implode(', ', $setting['schedule_times']) }}. Target: {{ $roleLabels[$setting['target_role']] ?? $setting['target_role'] }}.
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Run terakhir</p>
                <p class="mt-2 text-sm font-bold text-gray-900">
                    @if($setting['last_run_at'])
                        {{ \Illuminate\Support\Carbon::parse($setting['last_run_at'])->locale('id')->translatedFormat('d M Y, H:i') }}
                    @else
                        Belum pernah jalan
                    @endif
                </p>
                <p class="mt-1 text-xs text-gray-500">Status: {{ $setting['last_status'] ?: '-' }}</p>
            </div>

            <div class="rounded-xl border border-sky-100 bg-sky-50/60 p-5">
                <p class="text-xs font-bold uppercase tracking-wide text-sky-700">Hasil terakhir</p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-lg bg-white/80 p-3">
                        <p class="font-bold text-gray-500">Kandang</p>
                        <p class="mt-1 text-lg font-black text-gray-900">{{ data_get($summary, 'processedUnitCount', 0) }}</p>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3">
                        <p class="font-bold text-gray-500">Laporan</p>
                        <p class="mt-1 text-lg font-black text-gray-900">{{ data_get($summary, 'createdReportCount', 0) }}</p>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3">
                        <p class="font-bold text-gray-500">Ayam</p>
                        <p class="mt-1 text-lg font-black text-gray-900">{{ data_get($summary, 'affectedObjectCount', 0) }}</p>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3">
                        <p class="font-bold text-gray-500">Skip</p>
                        <p class="mt-1 text-lg font-black text-gray-900">{{ data_get($summary, 'skippedCount', 0) }}</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    @if(!empty(data_get($summary, 'units')))
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-black text-gray-900">Detail run terakhir</p>
                    <p class="text-xs text-gray-500">Menampilkan beberapa kandang terakhir yang diproses scheduler.</p>
                </div>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-[11px] font-bold uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Kandang</th>
                            <th class="px-3 py-2 text-right">Indikasi</th>
                            <th class="px-3 py-2 text-right">Laporan</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach(array_slice(data_get($summary, 'units', []), 0, 8) as $unit)
                            @php
                                $reason = data_get($unit, 'reason');
                                $statusText = data_get($unit, 'error') ?: (data_get($unit, 'created')
                                    ? 'Laporan dibuat'
                                    : match ($reason) {
                                        'HARVEST_NOT_READY' => 'Menunggu panen pagi/sore lengkap',
                                        'DUPLICATE_PERIOD' => 'Indikasi periode ini sudah ada',
                                        default => ($reason ?: 'Tidak ada laporan baru'),
                                    });
                            @endphp
                            <tr>
                                <td class="px-3 py-2 font-semibold text-gray-900">{{ data_get($unit, 'unitName') }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ data_get($unit, 'indicationChickenCount', 0) }}</td>
                                <td class="px-3 py-2 text-right text-gray-600">{{ data_get($unit, 'affectedObjectCount', 0) }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $statusText }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
