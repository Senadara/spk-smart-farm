@extends('layouts.app')

@section('title', 'Monitoring IoT')

@section('content')
    <div x-data="{ activeTab: 'sensor' }" class="space-y-6">
        {{-- Page Header --}}
        <div>
            <h1 class="text-2xl font-bold text-[var(--color-gray-900)]">Monitoring IoT</h1>
            <p class="text-sm text-[var(--color-gray-500)] mt-1">Riwayat data sensor dan log aktivitas perangkat</p>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-3">
            <button @click="activeTab = 'sensor'"
                :class="activeTab === 'sensor'
                    ? 'bg-[var(--color-primary)] text-white shadow-lg'
                    : 'bg-white text-[var(--color-gray-600)] hover:bg-gray-50'"
                class="px-5 py-2.5 rounded-xl text-sm font-medium border-none cursor-pointer transition-all"
                style="box-shadow: var(--shadow-sm);">
                📊 Data Sensor
            </button>
            <button @click="activeTab = 'logs'"
                :class="activeTab === 'logs'
                    ? 'bg-[var(--color-primary)] text-white shadow-lg'
                    : 'bg-white text-[var(--color-gray-600)] hover:bg-gray-50'"
                class="px-5 py-2.5 rounded-xl text-sm font-medium border-none cursor-pointer transition-all"
                style="box-shadow: var(--shadow-sm);">
                📋 Device Logs
            </button>
        </div>

        {{-- ═══ Sensor Data ═══ --}}
        <div x-show="activeTab === 'sensor'" x-cloak class="space-y-4">
            {{-- Filter --}}
            <div class="bg-white rounded-2xl p-5" style="box-shadow: var(--shadow-sm);">
                <h3 class="text-sm font-semibold text-[var(--color-gray-700)] mb-3">Filter Data</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Device</label>
                        <select class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Semua Device</option>
                            @foreach ($devices as $d)
                                <option value="{{ $d['id'] }}">{{ $d['deviceCode'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Parameter</label>
                        <select class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Semua Parameter</option>
                            @foreach ($parameters as $p)
                                <option value="{{ $p['id'] }}">{{ $p['parameterName'] }} ({{ $p['unit'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Dari Tanggal</label>
                        <input type="date" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Sampai Tanggal</label>
                        <input type="date" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                </div>
            </div>

            {{-- Sensor Data Table --}}
            <div class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
                <h2 class="text-base font-semibold text-[var(--color-gray-900)] mb-4">Riwayat Data Sensor</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Device</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Parameter</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Nilai</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sensorData as $data)
                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3 px-3">
                                        <span class="font-mono text-xs font-medium bg-gray-100 px-2 py-1 rounded-lg text-[var(--color-gray-800)]">
                                            {{ $data['deviceName'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-[var(--color-gray-700)]">{{ $data['parameterName'] }}</td>
                                    <td class="py-3 px-3">
                                        <span class="font-semibold text-[var(--color-gray-900)]">{{ $data['value'] }}</span>
                                        <span class="text-xs text-[var(--color-gray-500)]">{{ $data['unit'] }}</span>
                                    </td>
                                    <td class="py-3 px-3 text-[var(--color-gray-500)] text-xs">{{ $data['sensorTimestamp'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ═══ Device Logs ═══ --}}
        <div x-show="activeTab === 'logs'" x-cloak class="space-y-4">
            {{-- Filter --}}
            <div class="bg-white rounded-2xl p-5" style="box-shadow: var(--shadow-sm);">
                <h3 class="text-sm font-semibold text-[var(--color-gray-700)] mb-3">Filter Log</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Device</label>
                        <select class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Semua Device</option>
                            @foreach ($devices as $d)
                                <option value="{{ $d['id'] }}">{{ $d['deviceCode'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tipe Log</label>
                        <select class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Semua Tipe</option>
                            <option value="INFO">INFO</option>
                            <option value="WARNING">WARNING</option>
                            <option value="ERROR">ERROR</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal</label>
                        <input type="date" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                </div>
            </div>

            {{-- Logs Table --}}
            <div class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
                <h2 class="text-base font-semibold text-[var(--color-gray-900)] mb-4">Log Aktivitas Device</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Waktu</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Device</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Tipe</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Pesan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deviceLogs as $log)
                                @php
                                    $typeColors = [
                                        'INFO'    => ['bg' => '#EBF5FF', 'text' => '#1E40AF'],
                                        'WARNING' => ['bg' => '#FFFBEB', 'text' => '#92400E'],
                                        'ERROR'   => ['bg' => '#FEF2F2', 'text' => '#991B1B'],
                                    ];
                                    $tc = $typeColors[$log['logType']] ?? $typeColors['INFO'];
                                @endphp
                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3 px-3 text-xs text-[var(--color-gray-500)] whitespace-nowrap">{{ $log['createdAt'] }}</td>
                                    <td class="py-3 px-3">
                                        <span class="font-mono text-xs font-medium bg-gray-100 px-2 py-1 rounded-lg text-[var(--color-gray-800)]">
                                            {{ $log['deviceName'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
                                            style="background: {{ $tc['bg'] }}; color: {{ $tc['text'] }};">
                                            {{ $log['logType'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-[var(--color-gray-700)] text-xs">{{ $log['message'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
