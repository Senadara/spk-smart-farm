@extends('layouts.app')

@section('title', 'IoT Monitoring')

@section('content')
    <div class="space-y-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">IoT</p>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Monitoring Sensor</h1>
                <p class="mt-1 text-sm text-gray-500">Pantau kondisi sensor dan log perangkat. Mapping dan konfigurasi perangkat dikelola dari Setup IoT.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('iot.monitoring') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700" style="text-decoration:none;">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h4l3 7 4-16 3 9h4"/>
                    </svg>
                    Buka Monitoring
                </a>
                <a href="{{ route('iot.devices') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317a1.724 1.724 0 013.35 0 1.724 1.724 0 002.573 1.066 1.724 1.724 0 012.37 2.37 1.724 1.724 0 001.065 2.572 1.724 1.724 0 010 3.35 1.724 1.724 0 00-1.066 2.573 1.724 1.724 0 01-2.37 2.37 1.724 1.724 0 00-2.572 1.065 1.724 1.724 0 01-3.35 0 1.724 1.724 0 00-2.573-1.066 1.724 1.724 0 01-2.37-2.37 1.724 1.724 0 00-1.065-2.572 1.724 1.724 0 010-3.35 1.724 1.724 0 001.066-2.573 1.724 1.724 0 012.37-2.37 1.724 1.724 0 002.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Setup IoT
                </a>
            </div>
        </div>

        <x-page-hint title="Alur IoT" tone="sky" :open="false">
            Gunakan halaman ini untuk memantau data sensor dan log perangkat. Untuk registrasi device, mapping payload, protokol, parameter, dan threshold, buka Setup IoT.
        </x-page-hint>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($stats as $stat)
                <x-iot.stat-card :label="$stat['label']" :value="$stat['value']" :color="$stat['color']" :icon="$stat['icon']" />
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm xl:col-span-2">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Device per Kandang</h2>
                        <p class="text-sm text-gray-500">Status perangkat yang terhubung ke unit budidaya.</p>
                    </div>
                    <a href="{{ route('iot.devices') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">Kelola</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Device</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Kandang</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Koneksi</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Interval</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($devices as $device)
                                @php
                                    $statusColors = [
                                        'active' => 'bg-emerald-50 text-emerald-700',
                                        'inactive' => 'bg-gray-100 text-gray-700',
                                        'maintenance' => 'bg-amber-50 text-amber-700',
                                    ];
                                @endphp
                                <tr class="border-b border-gray-50 hover:bg-gray-50/70">
                                    <td class="px-3 py-3.5">
                                        <div class="font-semibold text-gray-900">{{ $device->deviceCode }}</div>
                                        <div class="text-xs text-gray-500">{{ $device->deviceName ?? '-' }}</div>
                                    </td>
                                    <td class="px-3 py-3.5 text-gray-700">{{ $device->unitBudidaya->nama ?? '-' }}</td>
                                    <td class="px-3 py-3.5 text-gray-700">{{ $device->connectionConfig->protocol->protocolName ?? '-' }}</td>
                                    <td class="px-3 py-3.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $statusColors[$device->status] ?? $statusColors['inactive'] }}">
                                            {{ ucfirst($device->status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3.5 text-gray-700">{{ $device->pollingInterval ? $device->pollingInterval . 's' : '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-10 text-center text-sm text-gray-500">Belum ada device terdaftar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Log Terbaru</h2>
                        <p class="text-sm text-gray-500">Dibatasi agar halaman tidak memanjang.</p>
                    </div>
                    <a href="{{ route('iot.monitoring', ['tab' => 'logs']) }}" class="text-xs font-semibold text-emerald-700 hover:underline" style="text-decoration:none;">Semua</a>
                </div>

                <div class="max-h-[360px] space-y-3 overflow-y-auto pr-1">
                    @forelse ($recentLogs as $log)
                        @php
                            $typeColors = [
                                'INFO' => ['bg' => 'bg-sky-50', 'text' => 'text-sky-700', 'dot' => 'bg-sky-500'],
                                'WARNING' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'dot' => 'bg-amber-500'],
                                'ERROR' => ['bg' => 'bg-red-50', 'text' => 'text-red-700', 'dot' => 'bg-red-500'],
                            ];
                            $tc = $typeColors[$log->logType] ?? $typeColors['INFO'];
                        @endphp
                        <div class="rounded-lg {{ $tc['bg'] }} px-3 py-3">
                            <div class="flex items-start gap-3">
                                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $tc['dot'] }}"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $log->device->deviceName ?? $log->device->deviceCode ?? 'Unknown' }}</p>
                                        <span class="shrink-0 text-[11px] text-gray-500">{{ $log->createdAt?->diffForHumans() ?? '-' }}</span>
                                    </div>
                                    <p class="mt-1 line-clamp-2 text-xs text-gray-600">{{ $log->message }}</p>
                                    <span class="mt-1 block text-[10px] font-bold {{ $tc['text'] }}">{{ $log->logType }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
                            Belum ada log perangkat.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
