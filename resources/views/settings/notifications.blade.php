@extends('layouts.app')

@section('title', 'Pengaturan Notifikasi')
@section('breadcrumb', 'Pengaturan / Notifikasi')

@section('content')
@php
    $tabs = [
        'harvest' => ['label' => 'Wajib Panen Mobile', 'description' => 'Jadwal dari mobile'],
        'health' => ['label' => 'Indikasi Kesehatan SPK', 'description' => 'Scheduler setelah panen'],
    ];
    $toneClass = [
        'emerald' => 'border-emerald-100 bg-emerald-50 text-emerald-700',
        'sky' => 'border-sky-100 bg-sky-50 text-sky-700',
        'amber' => 'border-amber-100 bg-amber-50 text-amber-700',
        'red' => 'border-red-100 bg-red-50 text-red-700',
        'gray' => 'border-gray-100 bg-gray-50 text-gray-600',
    ];
    $roleLabels = [
        'petugas' => 'Petugas',
        'pjawab' => 'Penanggung Jawab',
        'owner' => 'Owner',
        'all' => 'Semua Role',
    ];
    $healthSummary = $healthSetting['last_summary'] ?? [];
@endphp

<div class="mx-auto max-w-6xl space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ route('settings.index') }}" class="text-xs font-bold text-gray-400 hover:text-gray-600" style="text-decoration:none;">Pengaturan</a>
            <h1 class="mt-1 text-2xl font-black text-gray-950">Pengaturan Notifikasi</h1>
            <p class="mt-1 max-w-3xl text-sm leading-relaxed text-gray-500">
                Pantau jadwal notifikasi mobile dan scheduler SPK dari satu tempat.
            </p>
        </div>
        <div class="grid grid-cols-3 gap-2 sm:min-w-[340px]">
            <div class="rounded-xl border border-sky-100 bg-sky-50 px-3 py-2 text-center">
                <div class="text-xl font-black text-sky-800">{{ $harvestSummary['active'] }}</div>
                <div class="text-[11px] font-semibold text-sky-700">Jadwal aktif</div>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-center">
                <div class="text-xl font-black text-amber-800">{{ $harvestSummary['reminder'] }}</div>
                <div class="text-[11px] font-semibold text-amber-700">Reminder</div>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2 text-center">
                <div class="text-xl font-black text-emerald-800">{{ $harvestSummary['done'] }}</div>
                <div class="text-[11px] font-semibold text-emerald-700">Sudah panen</div>
            </div>
        </div>
    </div>

    <nav class="grid gap-2 md:grid-cols-2">
        @foreach($tabs as $tabKey => $tab)
            <a href="{{ route('settings.notifications.index', ['tab' => $tabKey]) }}"
                class="rounded-xl border px-4 py-3 transition {{ $activeTab === $tabKey ? 'border-emerald-300 bg-emerald-50 text-emerald-900 shadow-sm' : 'border-gray-100 bg-white text-gray-700 hover:border-emerald-200 hover:bg-emerald-50/50' }}"
                style="text-decoration:none;">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-black">{{ $tab['label'] }}</p>
                    <span class="h-2.5 w-2.5 rounded-full {{ $activeTab === $tabKey ? 'bg-emerald-500' : 'bg-gray-200' }}"></span>
                </div>
                <p class="mt-1 text-xs leading-5 {{ $activeTab === $tabKey ? 'text-emerald-700' : 'text-gray-500' }}">{{ $tab['description'] }}</p>
            </a>
        @endforeach
    </nav>

    @if($activeTab === 'harvest')
        <x-page-hint title="Sumber jadwal" tone="sky" :open="false">
            Jadwal wajib panen dibaca dari tabel mobile scheduledUnitNotification. Web hanya menampilkan status agar konfigurasi tidak ganda.
        </x-page-hint>

        @if(! $mobileTables['scheduledUnitNotification'])
            <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Tabel jadwal notifikasi mobile belum tersedia di database ini.
            </div>
        @endif

        @if($otherScheduleCount > 0)
            <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 text-xs text-gray-600">
                Ada {{ $otherScheduleCount }} jadwal mobile lain seperti vitamin. Tab ini hanya menampilkan jadwal panen agar tidak bercampur dengan scheduler kesehatan.
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-gray-100 bg-white p-4">
                <p class="text-xs font-bold uppercase text-gray-400">Total jadwal</p>
                <p class="mt-1 text-2xl font-black text-gray-900">{{ $harvestSummary['total'] }}</p>
            </div>
            <div class="rounded-xl border border-sky-100 bg-sky-50 p-4">
                <p class="text-xs font-bold uppercase text-sky-700">Menunggu jam</p>
                <p class="mt-1 text-2xl font-black text-sky-900">{{ $harvestSummary['waiting'] }}</p>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50 p-4">
                <p class="text-xs font-bold uppercase text-amber-700">Perlu reminder</p>
                <p class="mt-1 text-2xl font-black text-amber-900">{{ $harvestSummary['reminder'] }}</p>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                <p class="text-xs font-bold uppercase text-emerald-700">Sudah panen</p>
                <p class="mt-1 text-2xl font-black text-emerald-900">{{ $harvestSummary['done'] }}</p>
            </div>
        </div>

        <section class="rounded-2xl border border-gray-100 bg-white p-5" style="box-shadow: var(--shadow-sm);">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-900">Jadwal Wajib Panen</h2>
                <p class="mt-1 text-sm text-gray-500">Status dihitung dari jadwal mobile dan laporan panen hari ini.</p>
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
                @forelse($harvestSchedules as $schedule)
                    @php
                        $statusTone = $toneClass[$schedule['status']['tone']] ?? $toneClass['gray'];
                    @endphp
                    <article class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-gray-900">{{ $schedule['unit_name'] }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ $schedule['title'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-bold {{ $statusTone }}">
                                {{ $schedule['status']['label'] }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2 text-xs md:grid-cols-4">
                            <div class="rounded-lg bg-gray-50 px-3 py-2">
                                <p class="font-semibold text-gray-400">Jam</p>
                                <p class="mt-1 font-black text-gray-900">{{ $schedule['time'] }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 px-3 py-2">
                                <p class="font-semibold text-gray-400">Frekuensi</p>
                                <p class="mt-1 font-black text-gray-900">{{ $schedule['frequency'] }}</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 px-3 py-2">
                                <p class="font-semibold text-gray-400">Panen hari ini</p>
                                <p class="mt-1 font-black text-gray-900">{{ $schedule['harvest_count'] }} sesi</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 px-3 py-2">
                                <p class="font-semibold text-gray-400">Trigger</p>
                                <p class="mt-1 truncate font-black text-gray-900">{{ $schedule['last_triggered_label'] }}</p>
                            </div>
                        </div>

                        @if($schedule['status']['key'] === 'reminder')
                            <div class="mt-3 rounded-lg border border-amber-100 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800">
                                Jam panen sudah lewat dan laporan panen hari ini belum tercatat. Reminder wajib panen perlu muncul di mobile.
                            </div>
                        @elseif($schedule['latest_harvest_at'])
                            <p class="mt-3 text-xs text-gray-500">Panen terakhir hari ini: {{ $schedule['latest_harvest_label'] }}</p>
                        @endif
                    </article>
                @empty
                    <div class="col-span-full rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-10 text-center text-sm text-gray-500">
                        Belum ada jadwal wajib panen dari mobile.
                    </div>
                @endforelse
            </div>
        </section>
    @else
        <x-page-hint title="Bedakan dari wajib panen" tone="emerald" :open="false">
            Scheduler indikasi kesehatan berjalan setelah panen tersedia. Wajib panen mengingatkan input laporan, sedangkan scheduler ini membaca HDP individu untuk membuat indikasi pemeriksaan.
        </x-page-hint>

        <section class="rounded-2xl border border-gray-100 bg-white p-5" style="box-shadow: var(--shadow-sm);">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Scheduler Indikasi Kesehatan</h2>
                    <p class="mt-1 text-sm text-gray-500">Konfigurasi SPK untuk reminder pemeriksaan setelah panen valid.</p>
                </div>
                <a href="{{ route('settings.health-scheduler.index') }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700" style="text-decoration:none;">
                    Atur Detail
                </a>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border {{ $healthSetting['is_enabled'] ? 'border-emerald-100 bg-emerald-50' : 'border-gray-100 bg-gray-50' }} p-4">
                    <p class="text-xs font-bold uppercase {{ $healthSetting['is_enabled'] ? 'text-emerald-700' : 'text-gray-500' }}">Status</p>
                    <p class="mt-1 text-xl font-black {{ $healthSetting['is_enabled'] ? 'text-emerald-900' : 'text-gray-800' }}">{{ $healthSetting['is_enabled'] ? 'Aktif' : 'Nonaktif' }}</p>
                </div>
                <div class="rounded-xl border border-sky-100 bg-sky-50 p-4">
                    <p class="text-xs font-bold uppercase text-sky-700">Jadwal cek</p>
                    <p class="mt-1 text-xl font-black text-sky-900">{{ implode(' / ', $healthSetting['schedule_times']) }}</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                    <p class="text-xs font-bold uppercase text-gray-500">Periode</p>
                    <p class="mt-1 text-xl font-black text-gray-900">{{ $healthSetting['days'] }} hari</p>
                </div>
                <div class="rounded-xl border border-amber-100 bg-amber-50 p-4">
                    <p class="text-xs font-bold uppercase text-amber-700">Ambang</p>
                    <p class="mt-1 text-xl font-black text-amber-900">{{ number_format((float) $healthSetting['threshold_percent'], 0) }}%</p>
                </div>
            </div>

            <div class="mt-4 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                Target notifikasi: <span class="font-bold text-gray-900">{{ $roleLabels[$healthSetting['target_role']] ?? $healthSetting['target_role'] }}</span>.
                Run terakhir: <span class="font-bold text-gray-900">{{ $healthSetting['last_run_at'] ? \Illuminate\Support\Carbon::parse($healthSetting['last_run_at'])->format('d M Y, H:i') : '-' }}</span>.
            </div>

            @if(!empty(data_get($healthSummary, 'units')))
                <div class="mt-5 overflow-x-auto rounded-xl border border-gray-100">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-[11px] font-bold uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="px-3 py-2">Kandang</th>
                                <th class="px-3 py-2 text-right">Indikasi</th>
                                <th class="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach(array_slice(data_get($healthSummary, 'units', []), 0, 8) as $unit)
                                <tr>
                                    <td class="px-3 py-2 font-semibold text-gray-900">{{ data_get($unit, 'unitName') }}</td>
                                    <td class="px-3 py-2 text-right text-gray-600">{{ data_get($unit, 'indicationChickenCount', 0) }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500">{{ data_get($unit, 'message') ?: (data_get($unit, 'reason') ?: 'Tidak ada laporan baru') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</div>
@endsection
