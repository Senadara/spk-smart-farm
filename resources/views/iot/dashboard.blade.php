@extends('layouts.app')

@section('title', 'IoT Dashboard')

@section('content')
    <div class="space-y-6">
        {{-- Page Header --}}
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[var(--color-gray-900)]">IoT Dashboard</h1>
                <p class="text-sm text-[var(--color-gray-500)] mt-1">Overview status perangkat dan aktivitas sensor</p>
            </div>
            <a href="{{ route('iot.devices') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-white
                           bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90 transition-opacity"
                style="text-decoration:none;">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Register Device
            </a>
        </div>

        {{-- Stat Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            @foreach ($stats as $stat)
                <x-iot.stat-card :label="$stat['label']" :value="$stat['value']" :color="$stat['color']"
                    :icon="$stat['icon']" />
            @endforeach
        </div>

        {{-- Main Grid: Devices + Logs --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            {{-- Devices per Kandang --}}
            <div class="xl:col-span-2 bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
                <h2 class="text-base font-semibold text-[var(--color-gray-900)] mb-4">Devices per Kandang</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Device</th>
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Kandang</th>
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Koneksi</th>
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Status</th>
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Interval</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($devices as $device)
                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3.5 px-3">
                                        <div class="font-medium text-[var(--color-gray-900)]">{{ $device['deviceCode'] }}</div>
                                        <div class="text-xs text-[var(--color-gray-500)]">{{ $device['deviceName'] }}</div>
                                    </td>
                                    <td class="py-3.5 px-3 text-[var(--color-gray-700)]">{{ $device['unitBudidaya'] }}</td>
                                    <td class="py-3.5 px-3 text-[var(--color-gray-700)]">{{ $device['connectionConfig'] }}</td>
                                    <td class="py-3.5 px-3">
                                        @php
                                            $statusColors = [
                                                'active' => ['bg' => '#ECFDF5', 'text' => '#065F46'],
                                                'inactive' => ['bg' => '#F3F4F6', 'text' => '#374151'],
                                                'maintenance' => ['bg' => '#FFFBEB', 'text' => '#92400E'],
                                            ];
                                            $sc = $statusColors[$device['status']] ?? $statusColors['inactive'];
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                                            style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }};">
                                            {{ ucfirst($device['status']) }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-3 text-[var(--color-gray-700)]">{{ $device['pollingInterval'] }}s</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Recent Logs --}}
            <div class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-[var(--color-gray-900)]">Log Terbaru</h2>
                    <a href="{{ route('iot.monitoring') }}"
                        class="text-xs font-medium text-[var(--color-primary)] hover:underline"
                        style="text-decoration:none;">Lihat Semua →</a>
                </div>
                <div class="space-y-3">
                    @foreach ($recentLogs as $log)
                        @php
                            $typeColors = [
                                'INFO' => ['bg' => '#EBF5FF', 'text' => '#1E40AF', 'dot' => '#3B82F6'],
                                'WARNING' => ['bg' => '#FFFBEB', 'text' => '#92400E', 'dot' => '#F59E0B'],
                                'ERROR' => ['bg' => '#FEF2F2', 'text' => '#991B1B', 'dot' => '#EF4444'],
                            ];
                            $tc = $typeColors[$log['logType']] ?? $typeColors['INFO'];
                        @endphp
                        <div class="flex items-start gap-3 p-3 rounded-xl" style="background: {{ $tc['bg'] }}40;">
                            <div class="w-2 h-2 rounded-full mt-1.5 shrink-0" style="background: {{ $tc['dot'] }};"></div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="text-xs font-semibold" style="color: {{ $tc['text'] }};">
                                        {{ $log['logType'] }}
                                    </span>
                                    <span class="text-xs text-[var(--color-gray-400)]">{{ $log['deviceName'] }}</span>
                                </div>
                                <p class="text-xs text-[var(--color-gray-600)] m-0 leading-relaxed">{{ $log['message'] }}</p>
                                <span class="text-[10px] text-[var(--color-gray-400)] mt-1 block">{{ $log['createdAt'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection