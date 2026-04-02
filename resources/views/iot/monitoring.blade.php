@extends('layouts.app')

@section('title', 'Monitoring IoT')

@section('content')
    <div x-data="{ activeTab: '{{ request('tab', 'sensor') }}' }" class="space-y-6">
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
            <form method="GET" action="{{ route('iot.monitoring') }}" class="bg-white rounded-2xl p-5" style="box-shadow: var(--shadow-sm);">
                <input type="hidden" name="tab" value="sensor">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-[var(--color-gray-700)]">Filter Data</h3>
                    <div class="flex gap-2">
                        <a href="{{ route('iot.monitoring') }}" class="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700 border border-gray-200 rounded-lg">Reset</a>
                        <button type="submit" class="px-3 py-1.5 text-xs text-white bg-[var(--color-primary)] hover:bg-emerald-600 rounded-lg shadow-sm">Terapkan</button>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Device</label>
                        <select name="sensor_device_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Semua Device</option>
                            @foreach ($devices as $d)
                                <option value="{{ $d->id }}" {{ request('sensor_device_id') == $d->id ? 'selected' : '' }}>{{ $d->deviceCode }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Parameter</label>
                        <select name="sensor_parameter_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Semua Parameter</option>
                            @foreach ($parameters as $p)
                                <option value="{{ $p->id }}" {{ request('sensor_parameter_id') == $p->id ? 'selected' : '' }}>{{ $p->parameterName }} ({{ $p->unit }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Dari Tanggal</label>
                        <input type="date" name="sensor_date_from" value="{{ request('sensor_date_from') }}" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Sampai Tanggal</label>
                        <input type="date" name="sensor_date_to" value="{{ request('sensor_date_to') }}" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                </div>
            </form>

            {{-- Sensor Data Table --}}
            <div class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
                <h2 class="text-base font-semibold text-[var(--color-gray-900)] mb-4">Riwayat Data Sensor</h2>
                <div class="overflow-x-auto overflow-y-auto max-h-[500px]">
                    <table class="w-full text-sm relative">
                        <thead class="sticky top-0 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.05)] z-10">
                            <tr>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Device</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Parameter</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Nilai</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody id="sensor-data-tbody" class="divide-y divide-gray-50">
                            @foreach ($sensorData as $data)
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3 px-3 whitespace-nowrap">
                                        <div class="font-medium text-[var(--color-gray-900)]">
                                            {{ $data->device->deviceName ?? $data->device->deviceCode ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap text-[var(--color-gray-700)]">{{ $data->parameter->parameterName ?? '-' }}</td>
                                    <td class="py-3 px-3 whitespace-nowrap text-right">
                                        <span class="font-semibold text-[var(--color-gray-900)]">{{ $data->value }}</span>
                                        <span class="text-xs text-[var(--color-gray-500)]">{{ $data->parameter->unit ?? '' }}</span>
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap text-[var(--color-gray-500)] text-xs">{{ $data->sensorTimestamp ? $data->sensorTimestamp->format('d M Y H:i:s') : '-' }}</td>
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
            <form method="GET" action="{{ route('iot.monitoring') }}" class="bg-white rounded-2xl p-5" style="box-shadow: var(--shadow-sm);">
                <input type="hidden" name="tab" value="logs">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-[var(--color-gray-700)]">Filter Log</h3>
                    <div class="flex gap-2">
                        <a href="{{ route('iot.monitoring') }}?tab=logs" class="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-700 border border-gray-200 rounded-lg">Reset</a>
                        <button type="submit" class="px-3 py-1.5 text-xs text-white bg-[var(--color-primary)] hover:bg-emerald-600 rounded-lg shadow-sm">Terapkan</button>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Device</label>
                        <select name="log_device_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Semua Device</option>
                            @foreach ($devices as $d)
                                <option value="{{ $d->id }}" {{ request('log_device_id') == $d->id ? 'selected' : '' }}>{{ $d->deviceCode }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tipe Log</label>
                        <select name="log_type" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Semua Tipe</option>
                            <option value="INFO" {{ request('log_type') == 'INFO' ? 'selected' : '' }}>INFO</option>
                            <option value="WARNING" {{ request('log_type') == 'WARNING' ? 'selected' : '' }}>WARNING</option>
                            <option value="ERROR" {{ request('log_type') == 'ERROR' ? 'selected' : '' }}>ERROR</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal</label>
                        <input type="date" name="log_date" value="{{ request('log_date') }}" class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                </div>
            </form>

            {{-- Logs Table --}}
            <div class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
                <h2 class="text-base font-semibold text-[var(--color-gray-900)] mb-4">Log Aktivitas Device</h2>
                <div class="overflow-x-auto overflow-y-auto max-h-[500px]">
                    <table class="w-full text-sm relative">
                        <thead class="sticky top-0 bg-white shadow-[0_1px_2px_rgba(0,0,0,0.05)] z-10">
                            <tr>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Waktu</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Device</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Tipe</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Pesan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($deviceLogs as $log)
                                @php
                                    $typeColors = [
                                        'INFO'    => ['bg' => '#EBF5FF', 'text' => '#1E40AF'],
                                        'WARNING' => ['bg' => '#FFFBEB', 'text' => '#92400E'],
                                        'ERROR'   => ['bg' => '#FEF2F2', 'text' => '#991B1B'],
                                    ];
                                    $tc = $typeColors[$log->logType] ?? $typeColors['INFO'];
                                @endphp
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3 px-3 text-xs text-[var(--color-gray-500)] whitespace-nowrap">{{ $log->createdAt->format('Y-m-d H:i') }}</td>
                                    <td class="py-3 px-3 whitespace-nowrap">
                                        <div class="font-medium text-[var(--color-gray-900)]">
                                            {{ $log->device->deviceName ?? $log->device->deviceCode ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold tracking-wide"
                                            style="background: {{ $tc['bg'] }}; color: {{ $tc['text'] }};">
                                            {{ $log->logType }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-[var(--color-gray-700)] text-xs">{{ $log->message }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script type="module">
    document.addEventListener('DOMContentLoaded', () => {
        if (window.Echo) {
            window.Echo.channel('iot-sensors')
                .listen('.IotSensorDataReceived', (e) => {
                    const tbody = document.getElementById('sensor-data-tbody');
                    if (tbody && e.sensorData) {
                        const data = e.sensorData;
                        const tr = document.createElement('tr');
                        tr.className = 'hover:bg-gray-50/50 transition-colors bg-green-50/50';
                        tr.innerHTML = `
                            <td class="py-3 px-3 whitespace-nowrap">
                                <div class="font-medium text-[var(--color-gray-900)]">${data.device?.deviceName || data.device?.deviceCode || '-'}</div>
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap text-[var(--color-gray-700)]">${data.parameter?.parameterName || '-'}</td>
                            <td class="py-3 px-3 whitespace-nowrap text-right">
                                <span class="font-semibold text-green-600">${data.value}</span>
                                <span class="text-xs text-[var(--color-gray-500)]">${data.parameter?.unit || ''}</span>
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap text-[var(--color-gray-500)] text-xs">${data.timestamp || '-'}</td>
                        `;
                        tbody.prepend(tr);
                        
                        setTimeout(() => {
                            tr.classList.remove('bg-green-50/50');
                        }, 2000);

                        // Mencegah tabel memanjang tak terbatas (hapus baris paling bawah jika melebihi limit)
                        if (tbody.children.length > 25) {
                            tbody.lastElementChild.remove();
                        }
                    }
                });
        }
    });
</script>
@endpush
