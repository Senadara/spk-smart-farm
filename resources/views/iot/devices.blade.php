@extends('layouts.app')

@section('title', 'Setup IoT')

@section('content')
    @php
        $connectionProtocolMap = $connectionConfigs->mapWithKeys(function ($config) {
            return [$config->id => strtoupper($config->protocol->protocolName ?? '')];
        });
    @endphp
    <div x-data="{
        modal: {{ session('errors') ? (old('deviceCode') && old('_method') == 'PUT' ? '\'editDevice\'' : '\'addDevice\'') : 'null' }},
        activeTab: 'devices',
        editData: {},
        editConnection: {},
        editParameter: {},
        editCommodityParam: {},
        connectionMode: 'MQTT',
        protocolIds: @js($protocolOptions->pluck('id', 'code')),
        selectedConnectionId: '',
        connectionTypes: @js($connectionProtocolMap),
        normalizeProtocol(code) {
            const value = (code || '').toString().toUpperCase();
            if (value.includes('MQTT')) return 'MQTT';
            if (value.includes('API') || value.includes('REST') || value.includes('ANTARES')) return 'API';
            return '';
        },
        selectedProtocol() {
            return this.normalizeProtocol(this.connectionTypes[this.selectedConnectionId] || '');
        },
        editProtocol() {
            return this.normalizeProtocol(this.connectionTypes[this.editData.connectionConfigId] || '');
        },
        openAddDevice(connectionId = '') {
            this.selectedConnectionId = connectionId;
            this.modal = 'addDevice';
            this.activeTab = 'devices';
        },
        openAddConnection(mode = 'MQTT') {
            this.connectionMode = this.normalizeProtocol(mode) || 'MQTT';
            this.modal = 'addConnection';
        },
        openEditConnection(data, mode = 'MQTT') {
            const headers = data?.headers || null;
            this.editConnection = {
                ...data,
                headersText: headers ? JSON.stringify(headers, null, 2) : '',
            };
            this.connectionMode = this.normalizeProtocol(mode || data?.protocol?.protocolName || 'MQTT') || 'MQTT';
            this.modal = 'editConnection';
        }
    }" class="space-y-6">
        {{-- Page Header --}}
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[var(--color-gray-900)]">Setup IoT Kandang</h1>
                <p class="text-sm text-[var(--color-gray-500)] mt-1">Satu alur untuk membuat koneksi, memasang device per kandang, lalu mapping payload sensor.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <button @click="openAddConnection('MQTT')"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-white
                           bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90 transition-opacity">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Mulai Setup
                </button>
            </div>
        </div>

        <div class="rounded-2xl border border-blue-100 bg-blue-50/60 p-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-blue-900">Urutan setup yang benar</h2>
                    <p class="mt-1 text-xs leading-5 text-blue-700">Ikuti langkah dari kiri ke kanan. Koneksi adalah jalur komunikasi, device adalah alat fisik pada kandang, mapping adalah penerjemah key payload menjadi parameter sistem.</p>
                </div>
                <a href="{{ route('iot.monitoring') }}" class="inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold bg-white text-blue-700 border border-blue-100 hover:bg-blue-50" style="text-decoration:none;">Lihat Monitoring</a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
            <div class="rounded-2xl border {{ $connectionConfigs->isEmpty() ? 'border-blue-200' : 'border-emerald-100' }} bg-white p-4" style="box-shadow: var(--shadow-sm);">
                <div class="flex items-start justify-between gap-3">
                    <span class="inline-flex px-2 py-1 rounded-full {{ $connectionConfigs->isEmpty() ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700' }} text-[10px] font-bold uppercase">1. Koneksi</span>
                    <span class="text-xs font-semibold {{ $connectionConfigs->isEmpty() ? 'text-blue-700' : 'text-emerald-700' }}">{{ $connectionConfigs->count() }} koneksi</span>
                </div>
                <h2 class="mt-3 text-sm font-bold text-gray-900">Hubungkan Laravel ke sumber data</h2>
                <p class="mt-1 text-xs leading-5 text-gray-500">Pilih MQTT untuk broker topic, atau API/Antares untuk data yang ditarik berkala.</p>
            </div>
            <div class="rounded-2xl border {{ $connectionConfigs->isEmpty() ? 'border-gray-100' : ($devices->isEmpty() ? 'border-violet-200' : 'border-emerald-100') }} bg-white p-4" style="box-shadow: var(--shadow-sm);">
                <div class="flex items-start justify-between gap-3">
                    <span class="inline-flex px-2 py-1 rounded-full {{ $connectionConfigs->isEmpty() ? 'bg-gray-50 text-gray-500' : ($devices->isEmpty() ? 'bg-violet-50 text-violet-700' : 'bg-emerald-50 text-emerald-700') }} text-[10px] font-bold uppercase">2. Device</span>
                    <span class="text-xs font-semibold {{ $devices->isEmpty() ? 'text-violet-700' : 'text-emerald-700' }}">{{ $devices->count() }} device</span>
                </div>
                <h2 class="mt-3 text-sm font-bold text-gray-900">Pasangkan device ke kandang</h2>
                <p class="mt-1 text-xs leading-5 text-gray-500">Satu device wajib punya kode unik, kandang tujuan, dan koneksi yang dipakai.</p>
                @if($connectionConfigs->isEmpty())
                    <p class="mt-2 text-[11px] leading-4 text-amber-600">Selesaikan langkah 1 dulu.</p>
                @endif
            </div>
            <div class="rounded-2xl border {{ $devices->isEmpty() ? 'border-gray-100' : ($mappings->isEmpty() ? 'border-emerald-200' : 'border-emerald-100') }} bg-white p-4" style="box-shadow: var(--shadow-sm);">
                <div class="flex items-start justify-between gap-3">
                    <span class="inline-flex px-2 py-1 rounded-full {{ $devices->isEmpty() ? 'bg-gray-50 text-gray-500' : 'bg-emerald-50 text-emerald-700' }} text-[10px] font-bold uppercase">3. Mapping</span>
                    <span class="text-xs font-semibold {{ $mappings->isEmpty() ? 'text-emerald-700' : 'text-emerald-700' }}">{{ $mappings->count() }} mapping</span>
                </div>
                <h2 class="mt-3 text-sm font-bold text-gray-900">Terjemahkan payload sensor</h2>
                <p class="mt-1 text-xs leading-5 text-gray-500">Contoh: key payload temperature dipetakan ke parameter Suhu.</p>
                @if($devices->isEmpty())
                    <p class="mt-2 text-[11px] leading-4 text-amber-600">Selesaikan langkah 2 dulu.</p>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-base font-semibold text-[var(--color-gray-900)]">Langkah 1 - Koneksi Tersedia</h2>
                    <p class="mt-1 text-sm text-[var(--color-gray-500)]">Koneksi ini dipilih saat mendaftarkan device. Satu koneksi bisa dipakai banyak device di kandang berbeda.</p>
                </div>
                <button type="button" @click="openAddConnection('MQTT')" class="inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90">Tambah Koneksi</button>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                @forelse ($connectionConfigs as $connection)
                    @php
                        $protocolName = strtoupper($connection->protocol->protocolName ?? '');
                        $target = str_contains($protocolName, 'MQTT')
                            ? ($connection->mqttBrokerUrl ?: 'Broker MQTT belum diisi')
                            : trim(($connection->baseUrl ?: 'Base URL API belum diisi') . ($connection->endpointPath ?? ''));
                    @endphp
                    <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <span class="inline-flex px-2 py-1 rounded-full {{ $protocolName === 'MQTT' ? 'bg-violet-50 text-violet-700' : 'bg-blue-50 text-blue-700' }} text-[10px] font-bold uppercase">{{ $connection->protocol->protocolName ?? '-' }}</span>
                                <p class="mt-2 font-mono text-xs text-gray-800 break-all">{{ $target }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                @if ($protocolName === 'MQTT')
                                    <form action="{{ route('iot.connections.test', $connection->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-100 bg-white text-emerald-700 transition hover:bg-emerald-50" title="Test MQTT">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12.55a11 11 0 0114.08 0M1.42 9a16 16 0 0121.16 0M8.53 16.11a6 6 0 016.95 0M12 20h.01" />
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                                <button type="button" @click="openEditConnection(@js($connection), '{{ $protocolName }}')" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-blue-100 bg-white text-blue-600 transition hover:bg-blue-50" title="Edit koneksi">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <form action="{{ route('iot.connections.destroy', $connection->id) }}" method="POST" onsubmit="return confirm('Hapus koneksi {{ $connection->protocol->protocolName ?? 'IoT' }} ini?\nKoneksi yang masih dipakai device tidak bisa dihapus.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-red-100 bg-white text-red-600 transition hover:bg-red-50" title="Hapus koneksi">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-[11px] text-gray-500">
                            @if ($protocolName === 'MQTT')
                                <span class="px-2 py-1 rounded-lg bg-white">Port {{ $connection->mqttPort ?: (($connection->mqttUseTls ?? false) ? 8883 : 1883) }}</span>
                                <span class="px-2 py-1 rounded-lg bg-white">{{ $connection->mqttUseTls ? 'TLS aktif' : 'TCP' }}</span>
                                <span class="px-2 py-1 rounded-lg bg-white">QoS {{ $connection->mqttQos ?? 0 }}</span>
                                @if($connection->mqttTopic)
                                    <span class="px-2 py-1 rounded-lg bg-white">Topic default tersedia</span>
                                @endif
                            @else
                                <span class="px-2 py-1 rounded-lg bg-white">{{ $connection->authType ?: 'Tanpa auth' }}</span>
                                <span class="px-2 py-1 rounded-lg bg-white">Polling dari device</span>
                            @endif
                            <span class="px-2 py-1 rounded-lg bg-white">{{ $connection->devices_count ?? 0 }} device memakai koneksi ini</span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" @click="openAddDevice('{{ $connection->id }}')" class="inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold text-[var(--color-primary)] bg-white border border-[var(--color-primary)]20 hover:bg-[var(--color-primary)]05">Pakai untuk Device</button>
                            @if(($connection->devices_count ?? 0) > 0)
                                <span class="inline-flex items-center rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-700">Hapus terkunci sampai device dipindahkan</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="lg:col-span-2 rounded-xl border border-dashed border-blue-200 bg-blue-50/50 p-6 text-center">
                        <p class="text-sm font-semibold text-blue-900">Belum ada koneksi IoT</p>
                        <p class="mt-1 text-xs leading-5 text-blue-700">Mulai dari sini. Pilih MQTT atau API/Antares, lalu lanjutkan ke pendaftaran device.</p>
                        <button type="button" @click="openAddConnection('MQTT')" class="mt-3 inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90">Tambah Koneksi Pertama</button>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Device Table --}}
        <div class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-base font-semibold text-[var(--color-gray-900)]">Langkah 2 - Device per Kandang</h2>
                    <p class="mt-1 text-sm text-[var(--color-gray-500)]">Daftarkan setiap alat sensor dan pilih kandang tempat alat tersebut dipasang.</p>
                </div>
                <button type="button" @click="openAddDevice()" @if($connectionConfigs->isEmpty()) disabled @endif class="inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold border-none {{ $connectionConfigs->isEmpty() ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'text-white bg-[var(--color-primary)] cursor-pointer hover:opacity-90' }}">Tambah Device</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Kode Device</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Nama</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Kandang</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Koneksi</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Jalur Data</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Status</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Polling</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Terpasang</th>
                            <th class="text-right py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($devices as $device)
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="py-3.5 px-3">
                                    <div class="font-medium text-[var(--color-gray-900)]">
                                        {{ $device->deviceCode }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-3 text-[var(--color-gray-700)]">{{ $device->deviceName ?? '-' }}</td>
                                <td class="py-3.5 px-3 text-[var(--color-gray-700)]">{{ $device->unitBudidaya->nama ?? '-' }}</td>
                                <td class="py-3.5 px-3 text-[var(--color-gray-700)] text-xs">{{ $device->connectionConfig->protocol->protocolName ?? '-' }}</td>
                                <td class="py-3.5 px-3 text-xs">
                                    @php
                                        $deviceProtocol = strtoupper($device->connectionConfig?->protocol?->protocolName ?? '');
                                        $resolvedTopic = $device->mqttTopic ?: ($device->connectionConfig?->mqttTopic ? str_replace('{deviceCode}', $device->deviceCode, $device->connectionConfig->mqttTopic) : null);
                                    @endphp
                                    <div class="space-y-1">
                                        @if (str_contains($deviceProtocol, 'MQTT'))
                                            <div class="font-mono text-[11px] text-gray-700">{{ $resolvedTopic ?: 'Topic mengikuti koneksi' }}</div>
                                        @else
                                            <div class="font-mono text-[11px] text-gray-700">Polling API tiap {{ $device->pollingInterval ?: 300 }} detik</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3.5 px-3">
                                    @php
                                    $statusColors = [
                                        'active'      => ['bg' => '#ECFDF5', 'text' => '#065F46'],
                                        'inactive'    => ['bg' => '#F3F4F6', 'text' => '#374151'],
                                        'maintenance' => ['bg' => '#FFFBEB', 'text' => '#92400E'],
                                    ];
                                    $sc = $statusColors[$device->status] ?? $statusColors['inactive'];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide uppercase"
                                        style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }};">
                                        {{ ucfirst($device->status) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-3 text-[var(--color-gray-700)]">{{ str_contains($deviceProtocol, 'API') && $device->pollingInterval ? $device->pollingInterval . 's' : '-' }}</td>
                            <td class="py-3.5 px-3 text-[var(--color-gray-500)] text-xs">{{ optional($device->installedAt)->format('d M Y') ?? '-' }}</td>
                                <td class="py-3.5 px-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            @click="editData = {{ json_encode($device) }}; modal = 'editDevice'"
                                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                            title="Edit">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <form action="{{ route('iot.devices.destroy', $device->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus device ini?');">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                                title="Hapus">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-10 px-3 text-center">
                                    <div class="mx-auto max-w-md">
                                        <p class="text-sm font-semibold text-gray-900">Belum ada device terdaftar</p>
                                        <p class="mt-1 text-xs leading-5 text-gray-500">Tambahkan koneksi terlebih dahulu, lalu daftarkan device sesuai kandang tempat sensor dipasang.</p>
                                        <button type="button" @click="{{ $connectionConfigs->isEmpty() ? "openAddConnection('MQTT')" : 'openAddDevice()' }}" class="mt-3 inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90">
                                            {{ $connectionConfigs->isEmpty() ? 'Tambah Koneksi' : 'Tambah Device' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Parameter Mapping Table --}}
        <div class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-base font-semibold text-[var(--color-gray-900)]">Langkah 3 - Mapping Payload</h2>
                    <p class="mt-1 text-sm text-[var(--color-gray-500)]">Pemetaan antara key payload IoT dan parameter sensor di sistem.</p>
                </div>
                <button type="button" @click="modal = 'addMapping'" @if($devices->isEmpty()) disabled @endif class="inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold border-none {{ $devices->isEmpty() ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'text-white bg-[var(--color-primary)] cursor-pointer hover:opacity-90' }}">Tambah Mapping</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Device</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Parameter</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Payload Key</th>
                            <th class="text-right py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mappings as $mapping)
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="py-3.5 px-3">
                                    <div class="font-medium text-[var(--color-gray-900)]">
                                        {{ $mapping->device->deviceName ?? $mapping->device->deviceCode ?? '-' }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-3 text-[var(--color-gray-700)]">{{ $mapping->parameter->parameterName ?? '-' }}</td>
                                <td class="py-3.5 px-3">
                                    <code class="text-xs bg-gray-100 px-2 py-1 rounded text-[var(--color-gray-800)]">{{ $mapping->payloadKey }}</code>
                                </td>
                                <td class="py-3.5 px-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button @click="editData = {{ json_encode($mapping) }}; modal = 'editMapping'"
                                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                            title="Edit Payload Key">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        </button>
                                        <form action="{{ route('iot.mappings.destroy', $mapping->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus mapping parameter ini?');">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                                title="Hapus">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-10 px-3 text-center">
                                    <div class="mx-auto max-w-md">
                                        <p class="text-sm font-semibold text-gray-900">Belum ada mapping payload</p>
                                        <p class="mt-1 text-xs leading-5 text-gray-500">Mapping dipakai agar sistem tahu key payload mana yang menjadi suhu, kelembapan, amonia, dan parameter sensor lain.</p>
                                        <button type="button" @click="{{ $devices->isEmpty() ? 'openAddDevice()' : "modal = 'addMapping'" }}" @if($devices->isEmpty()) disabled @endif class="mt-3 inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold border-none {{ $devices->isEmpty() ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-[var(--color-primary)] text-white cursor-pointer hover:opacity-90' }}">
                                            {{ $devices->isEmpty() ? 'Tambahkan Device Dulu' : 'Tambah Mapping' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div id="advanced-iot-config" class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-[var(--color-gray-900)]">Parameter Sensor</h2>
                        <p class="mt-1 text-sm text-[var(--color-gray-500)]">Daftar parameter yang bisa dipakai pada mapping payload device.</p>
                    </div>
                    <button type="button" @click="modal = 'addParameter'" class="inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90">Tambah Parameter</button>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Kode</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Nama</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Satuan</th>
                                <th class="text-right py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($parameters as $param)
                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3.5 px-3">
                                        <span class="font-mono text-xs font-medium bg-blue-50 px-2 py-1 rounded-lg text-blue-700">{{ $param->parameterCode }}</span>
                                    </td>
                                    <td class="py-3.5 px-3">
                                        <p class="font-medium text-[var(--color-gray-900)]">{{ $param->parameterName }}</p>
                                        @if($param->description)
                                            <p class="mt-0.5 line-clamp-1 text-xs text-[var(--color-gray-500)]">{{ $param->description }}</p>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-3 text-[var(--color-gray-600)]">{{ $param->unit ?: '-' }}</td>
                                    <td class="py-3.5 px-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" @click="editParameter = {{ $param->toJson() }}; modal = 'editParameter'"
                                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                                title="Edit Parameter">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </button>
                                            <form action="{{ route('iot.parameters.destroy', $param->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Hapus parameter {{ $param->parameterCode }}? Parameter yang masih dipakai mapping atau threshold tidak bisa dihapus.');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Hapus Parameter">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 px-3 text-center">
                                        <p class="text-sm font-semibold text-gray-900">Belum ada parameter sensor</p>
                                        <p class="mt-1 text-xs text-gray-500">Tambahkan parameter seperti TEMP, HUMID, AMMON, atau LIGHT sebelum mapping payload.</p>
                                        <button type="button" @click="modal = 'addParameter'" class="mt-3 inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90">Tambah Parameter</button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-[var(--color-gray-900)]">Threshold Komoditas</h2>
                        <p class="mt-1 text-sm text-[var(--color-gray-500)]">Batas ideal parameter sensor untuk komoditas tertentu.</p>
                    </div>
                    <button type="button" @click="modal = 'addCommodityParam'" class="inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90">Tambah Threshold</button>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Komoditas</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Parameter</th>
                                <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Rentang</th>
                                <th class="text-right py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($commodityParameters as $cp)
                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3.5 px-3 font-medium text-[var(--color-gray-900)]">{{ $cp->commodity->nama ?? '-' }}</td>
                                    <td class="py-3.5 px-3 text-[var(--color-gray-700)]">{{ $cp->parameter->parameterName ?? '-' }}</td>
                                    <td class="py-3.5 px-3">
                                        <span class="inline-flex items-center rounded-lg bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700">
                                            {{ $cp->minValue ?? '-' }} - {{ $cp->maxValue ?? '-' }} {{ $cp->parameter->unit ?? '' }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" @click="editCommodityParam = {{ $cp->toJson() }}; modal = 'editCommodityParam'"
                                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                                title="Edit Threshold">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </button>
                                            <form action="{{ route('iot.commodity-params.destroy', $cp->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Hapus threshold komoditas ini?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Hapus Threshold">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 px-3 text-center">
                                        <p class="text-sm font-semibold text-gray-900">Belum ada threshold komoditas</p>
                                        <p class="mt-1 text-xs text-gray-500">Tambahkan batas ideal setelah parameter sensor tersedia.</p>
                                        <button type="button" @click="modal = 'addCommodityParam'" @if($parameters->isEmpty()) disabled @endif class="mt-3 inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold border-none {{ $parameters->isEmpty() ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'text-white bg-[var(--color-primary)] cursor-pointer hover:opacity-90' }}">Tambah Threshold</button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Modal: Add Connection --}}
        <form action="{{ route('iot.connections.store') }}" method="POST">
            @csrf
            <x-iot.modal-form id="addConnection" title="Langkah 1: Tambah Koneksi IoT" size="lg">
                <div class="space-y-4">
                    <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-3">
                        <p class="text-xs leading-5 text-blue-700">Koneksi adalah jalur komunikasi yang akan dipakai beberapa device. Pilih API/Antares jika Laravel mengambil data berkala, atau MQTT jika Laravel subscribe ke broker.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Jalur Koneksi *</label>
                        <input type="hidden" name="protocolId" :value="protocolIds[connectionMode]">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach ($protocolOptions as $option)
                                <button type="button" @click="connectionMode = '{{ $option['code'] }}'"
                                    :class="connectionMode === '{{ $option['code'] }}' ? 'border-[var(--color-primary)] bg-[var(--color-primary)]10 text-[var(--color-primary)]' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                                    class="text-left rounded-xl border px-3 py-3 transition-colors">
                                    <span class="block text-sm font-bold">{{ $option['label'] }}</span>
                                    <span class="block mt-1 text-[11px] leading-4">{{ $option['description'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div x-show="connectionMode === 'API'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2 rounded-xl border border-blue-100 bg-blue-50/60 p-3">
                            <p class="text-xs leading-5 text-blue-700">Isi alamat layanan API/Antares. Setelah koneksi dibuat, device akan memakai interval polling untuk mengambil data.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Base URL *</label>
                            <input type="text" name="baseUrl" placeholder="https://platform.example.com" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Endpoint Path</label>
                            <input type="text" name="endpointPath" placeholder="/api/v2/devices" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipe Autentikasi</label>
                            <select name="authType" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                                <option value="none">None</option>
                                <option value="api_key">API Key</option>
                                <option value="bearer">Bearer Token</option>
                                <option value="basic">Basic Auth</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Auth Key / Token</label>
                            <input type="password" name="authKey" placeholder="Token atau API key" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Custom Headers (JSON)</label>
                            <textarea rows="3" name="headers" placeholder='{"Content-Type": "application/json"}' class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm font-mono focus:outline-none focus:border-[var(--color-primary)] transition-all resize-none"></textarea>
                        </div>
                    </div>

                    <div x-show="connectionMode === 'MQTT'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2 rounded-xl border border-violet-100 bg-violet-50/60 p-3">
                            <p class="text-xs leading-5 text-violet-700">Isi broker MQTT. Default topic boleh memakai {deviceCode} agar setiap device otomatis punya topic berbeda.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">MQTT Broker URL *</label>
                            <input type="text" name="mqttBrokerUrl" placeholder="broker.hivemq.cloud" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Port MQTT</label>
                            <input type="number" name="mqttPort" placeholder="8883" min="1" max="65535" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Default MQTT Topic</label>
                            <input type="text" name="mqttTopic" placeholder="smartfarm/devices/{deviceCode}/sensors" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">MQTT Client ID</label>
                            <input type="text" name="mqttClientId" placeholder="laravel-smartfarm-owner-01" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">MQTT Username</label>
                            <input type="text" name="mqttUsername" placeholder="username broker" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">MQTT Password</label>
                            <input type="password" name="mqttPassword" placeholder="password broker" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">QoS</label>
                            <select name="mqttQos" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                                <option value="0">0 - At most once</option>
                                <option value="1">1 - At least once</option>
                                <option value="2">2 - Exactly once</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Keep Alive (detik)</label>
                            <input type="number" name="mqttKeepAlive" value="60" min="5" max="65535" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                        </div>
                        <div class="flex items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50/50 px-3 py-2">
                            <input type="checkbox" name="mqttUseTls" value="1" id="mqttUseTlsSetup" class="rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            <label for="mqttUseTlsSetup" class="text-sm font-medium text-gray-700">Gunakan TLS</label>
                        </div>
                    </div>
                </div>
            </x-iot.modal-form>
        </form>

        {{-- Modal: Edit Connection --}}
        <template x-if="editConnection.id">
            <form :action="`{{ url('/iot/connections') }}/${editConnection.id}`" method="POST">
                @csrf
                @method('PUT')
                <x-iot.modal-form id="editConnection" title="Edit Koneksi IoT" size="lg">
                    <div class="space-y-4">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                            <p class="text-xs leading-5 text-slate-600">Perubahan koneksi akan langsung dipakai oleh device yang terhubung. Jika koneksi MQTT sedang didengar listener, jalankan ulang listener agar konfigurasi baru terbaca.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jalur Koneksi *</label>
                            <input type="hidden" name="protocolId" :value="protocolIds[connectionMode]">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach ($protocolOptions as $option)
                                    <button type="button" @click="connectionMode = '{{ $option['code'] }}'"
                                        :class="connectionMode === '{{ $option['code'] }}' ? 'border-[var(--color-primary)] bg-[var(--color-primary)]10 text-[var(--color-primary)]' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                                        class="text-left rounded-xl border px-3 py-3 transition-colors">
                                        <span class="block text-sm font-bold">{{ $option['label'] }}</span>
                                        <span class="block mt-1 text-[11px] leading-4">{{ $option['description'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div x-show="connectionMode === 'API'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2 rounded-xl border border-blue-100 bg-blue-50/60 p-3">
                                <p class="text-xs leading-5 text-blue-700">Edit koneksi API/Antares. Auth key dikosongkan jika tidak ingin mengganti token yang sudah tersimpan.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Base URL *</label>
                                <input type="text" name="baseUrl" x-model="editConnection.baseUrl" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Endpoint Path</label>
                                <input type="text" name="endpointPath" x-model="editConnection.endpointPath" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipe Autentikasi</label>
                                <select name="authType" x-model="editConnection.authType" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                                    <option value="none">None</option>
                                    <option value="api_key">API Key</option>
                                    <option value="bearer">Bearer Token</option>
                                    <option value="basic">Basic Auth</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Auth Key / Token</label>
                                <input type="password" name="authKey" placeholder="Kosongkan jika tidak berubah" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Custom Headers (JSON)</label>
                                <textarea rows="3" name="headers" x-model="editConnection.headersText" placeholder='{"Content-Type": "application/json"}' class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm font-mono focus:outline-none focus:border-[var(--color-primary)] transition-all resize-none"></textarea>
                            </div>
                        </div>

                        <div x-show="connectionMode === 'MQTT'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2 rounded-xl border border-violet-100 bg-violet-50/60 p-3">
                                <p class="text-xs leading-5 text-violet-700">Edit broker MQTT. Password dikosongkan jika tidak ingin mengganti password yang sudah tersimpan.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">MQTT Broker URL *</label>
                                <input type="text" name="mqttBrokerUrl" x-model="editConnection.mqttBrokerUrl" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Port MQTT</label>
                                <input type="number" name="mqttPort" x-model="editConnection.mqttPort" min="1" max="65535" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Default MQTT Topic</label>
                                <input type="text" name="mqttTopic" x-model="editConnection.mqttTopic" placeholder="smartfarm/devices/{deviceCode}/sensors" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">MQTT Client ID</label>
                                <input type="text" name="mqttClientId" x-model="editConnection.mqttClientId" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">MQTT Username</label>
                                <input type="text" name="mqttUsername" x-model="editConnection.mqttUsername" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">MQTT Password</label>
                                <input type="password" name="mqttPassword" placeholder="Kosongkan jika tidak berubah" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">QoS</label>
                                <select name="mqttQos" x-model="editConnection.mqttQos" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                                    <option value="0">0 - At most once</option>
                                    <option value="1">1 - At least once</option>
                                    <option value="2">2 - Exactly once</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Keep Alive (detik)</label>
                                <input type="number" name="mqttKeepAlive" x-model="editConnection.mqttKeepAlive" min="5" max="65535" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            </div>
                            <div class="flex items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50/50 px-3 py-2">
                                <input type="checkbox" name="mqttUseTls" value="1" id="mqttUseTlsEditSetup" x-model="editConnection.mqttUseTls" class="rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                                <label for="mqttUseTlsEditSetup" class="text-sm font-medium text-gray-700">Gunakan TLS</label>
                            </div>
                        </div>
                    </div>
                </x-iot.modal-form>
            </form>
        </template>

        {{-- ═══ MODAL: Add Device ═══ --}}
        <form action="{{ route('iot.devices.store') }}" method="POST">
            @csrf
            <x-iot.modal-form id="addDevice" title="Langkah 2: Tambah Device ke Kandang" size="lg">
                <div class="mb-4 rounded-xl border border-violet-100 bg-violet-50/60 p-3">
                    <p class="text-xs leading-5 text-violet-700">Device adalah alat fisik yang dipasang di kandang. Pastikan kode device sama dengan identifier API atau topic MQTT agar data bisa dikenali.</p>
                </div>
                @if($connectionConfigs->isEmpty())
                    <div class="mb-4 rounded-xl border border-amber-100 bg-amber-50/70 p-3">
                        <p class="text-xs leading-5 text-amber-700">Belum ada koneksi. Buat koneksi dulu agar device tahu harus mengambil data dari API/Antares atau MQTT.</p>
                    </div>
                @endif
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Kode Device *</label>
                        <input type="text" name="deviceCode" placeholder="e.g. DHT22-KA-01" required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Device</label>
                        <input type="text" name="deviceName" placeholder="e.g. Sensor Suhu Kandang A"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Kandang *</label>
                        <select name="unitBudidayaId" required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all bg-white">
                            <option value="">Pilih Kandang</option>
                            @foreach ($unitBudidaya as $ub)
                                <option value="{{ $ub->id }}">{{ $ub->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Konfigurasi Koneksi *</label>
                        <select name="connectionConfigId" x-model="selectedConnectionId" required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all bg-white">
                            <option value="">Pilih Koneksi</option>
                            @foreach ($connectionConfigs as $cc)
                                @php
                                    $protocolName = strtoupper($cc->protocol->protocolName ?? '');
                                    $connectionTarget = str_contains($protocolName, 'MQTT')
                                        ? ($cc->mqttBrokerUrl ?: 'Broker MQTT')
                                        : trim(($cc->baseUrl ?: 'Base URL API') . ($cc->endpointPath ?? ''));
                                @endphp
                                <option value="{{ $cc->id }}">{{ $cc->protocol->protocolName }} - {{ $connectionTarget }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="!selectedProtocol()" x-cloak class="sm:col-span-2 rounded-xl border border-gray-100 bg-gray-50 p-3">
                        <p class="text-xs leading-5 text-gray-500">Pilih konfigurasi koneksi dulu. Field lanjutan akan mengikuti jalur device yang dipakai.</p>
                    </div>
                    <div x-show="selectedProtocol() === 'MQTT'" x-cloak class="sm:col-span-2 rounded-xl border border-violet-100 bg-violet-50/60 p-3">
                        <p class="text-xs leading-5 text-violet-700">Device MQTT akan dibaca dari topic. Topic bisa mengikuti default koneksi atau ditentukan khusus per device.</p>
                    </div>
                    <div x-show="selectedProtocol() === 'API'" x-cloak class="sm:col-span-2 rounded-xl border border-blue-100 bg-blue-50/60 p-3">
                        <p class="text-xs leading-5 text-blue-700">Device API/Antares akan dibaca berkala oleh Laravel lewat command polling.</p>
                    </div>
                    <div x-show="selectedProtocol() === 'MQTT'" x-cloak class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">MQTT Topic Device</label>
                        <input type="text" name="mqttTopic" placeholder="smartfarm/devices/DHT22-KA-01/sensors"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                        <p class="text-xs text-[var(--color-gray-400)] mt-1">Kosongkan jika memakai default topic koneksi. Isi topic unik jika satu broker melayani banyak device.</p>
                    </div>
                    <div x-show="selectedProtocol() === 'API'" x-cloak>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Polling Interval (detik)</label>
                        <input type="number" name="pollingInterval" value="300" min="10"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                        <select name="status"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all bg-white">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Tanggal Pemasangan</label>
                        <input type="datetime-local" name="installedAt"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                    </div>
                </div>
            </x-iot.modal-form>
        </form>

        {{-- ═══ MODAL: Edit Device ═══ --}}
        <template x-if="editData.id">
            <form :action="`{{ url('/iot/devices') }}/${editData.id}`" method="POST">
                @csrf
                @method('PUT')
                <x-iot.modal-form id="editDevice" title="Edit Device" size="lg">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Kode Device *</label>
                            <input type="text" name="deviceCode" x-model="editData.deviceCode" required
                                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Device</label>
                            <input type="text" name="deviceName" x-model="editData.deviceName"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Kandang *</label>
                            <select name="unitBudidayaId" x-model="editData.unitBudidayaId" required
                                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all bg-white">
                                <option value="">Pilih Kandang</option>
                                @foreach ($unitBudidaya as $ub)
                                    <option value="{{ $ub->id }}">{{ $ub->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Konfigurasi Koneksi *</label>
                            <select name="connectionConfigId" x-model="editData.connectionConfigId" required
                                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all bg-white">
                                <option value="">Pilih Koneksi</option>
                                @foreach ($connectionConfigs as $cc)
                                    @php
                                        $protocolName = strtoupper($cc->protocol->protocolName ?? '');
                                        $connectionTarget = str_contains($protocolName, 'MQTT')
                                            ? ($cc->mqttBrokerUrl ?: 'Broker MQTT')
                                            : trim(($cc->baseUrl ?: 'Base URL API') . ($cc->endpointPath ?? ''));
                                    @endphp
                                    <option value="{{ $cc->id }}">{{ $cc->protocol->protocolName }} - {{ $connectionTarget }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="editProtocol() === 'MQTT'" x-cloak class="sm:col-span-2 rounded-xl border border-violet-100 bg-violet-50/60 p-3">
                            <p class="text-xs leading-5 text-violet-700">Device MQTT akan dibaca dari topic. Kosongkan topic khusus jika ingin memakai default koneksi.</p>
                        </div>
                        <div x-show="editProtocol() === 'API'" x-cloak class="sm:col-span-2 rounded-xl border border-blue-100 bg-blue-50/60 p-3">
                            <p class="text-xs leading-5 text-blue-700">Interval polling dipakai saat Laravel menarik data dari API/Antares.</p>
                        </div>
                        <div x-show="editProtocol() === 'MQTT'" x-cloak class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">MQTT Topic Device</label>
                            <input type="text" name="mqttTopic" x-model="editData.mqttTopic"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                        </div>
                        <div x-show="editProtocol() === 'API'" x-cloak>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Polling Interval (detik)</label>
                            <input type="number" name="pollingInterval" x-model="editData.pollingInterval" min="10"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                            <select name="status" x-model="editData.status"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all bg-white">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                </x-iot.modal-form>
            </form>
        </template>

        {{-- ═══ MODAL: Add Mapping ═══ --}}
        <form action="{{ route('iot.mappings.store') }}" method="POST">
            @csrf
            <x-iot.modal-form id="addMapping" title="Langkah 3: Tambah Mapping Payload" size="md">
                <div class="space-y-4">
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3">
                        <p class="text-xs leading-5 text-emerald-700">Mapping menjawab pertanyaan: key JSON dari device ini masuk ke parameter apa di sistem? Contoh payload `{&quot;temperature&quot;: 28}` berarti payload key `temperature` dipetakan ke parameter Suhu.</p>
                    </div>
                    @if($parameters->isEmpty())
                        <div class="rounded-xl border border-amber-100 bg-amber-50/70 p-3">
                            <p class="text-xs leading-5 text-amber-700">Belum ada parameter sensor. Tambahkan parameter di section Parameter Sensor pada halaman ini terlebih dahulu.</p>
                            <button type="button" @click="modal = 'addParameter'" class="mt-2 inline-flex items-center justify-center px-3 py-2 rounded-xl text-xs font-semibold bg-white text-amber-700 border border-amber-100 hover:bg-amber-50">Tambah Parameter Sensor</button>
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Device *</label>
                        <select name="deviceId" required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all bg-white">
                            <option value="">Pilih Device</option>
                            @foreach ($devices as $d)
                                <option value="{{ $d->id }}">{{ $d->deviceCode }} — {{ $d->deviceName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Parameter Sensor *</label>
                        <select name="parameterId" required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all bg-white">
                            <option value="">Pilih Parameter</option>
                            @foreach ($parameters as $p)
                                <option value="{{ $p->id }}">{{ $p->parameterCode }} — {{ $p->parameterName }} ({{ $p->unit }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Payload Key *</label>
                        <input type="text" name="payloadKey" placeholder="e.g. temperature" required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                        <p class="text-xs text-[var(--color-gray-400)] mt-1">Key yang digunakan pada JSON payload dari device IoT (Contoh Postman: phospor/la => phospor)</p>
                    </div>
                </div>
            </x-iot.modal-form>
        </form>

        {{-- ═══ MODAL: Edit Mapping ═══ --}}
        <template x-if="editData.id">
            <form :action="`{{ url('/iot/mappings') }}/${editData.id}`" method="POST">
                @csrf
                @method('PUT')
                <x-iot.modal-form id="editMapping" title="Edit Mapping Parameter" size="md">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Payload Key *</label>
                            <input type="text" name="payloadKey" x-model="editData.payloadKey" required
                                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                            <p class="text-xs text-[var(--color-gray-400)] mt-1">Key yang digunakan pada JSON payload dari device IoT</p>
                        </div>
                    </div>
                </x-iot.modal-form>
            </form>
        </template>

        <form action="{{ route('iot.parameters.store') }}" method="POST">
            @csrf
            <x-iot.modal-form id="addParameter" title="Tambah Parameter Sensor" size="md">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Kode Parameter *</label>
                        <input type="text" name="parameterCode" placeholder="TEMP" required class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Parameter *</label>
                        <input type="text" name="parameterName" placeholder="Suhu" required class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Satuan</label>
                        <input type="text" name="unit" placeholder="C, %, ppm, lux" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi</label>
                        <textarea rows="2" name="description" placeholder="Penjelasan singkat parameter sensor..." class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all resize-none"></textarea>
                    </div>
                </div>
            </x-iot.modal-form>
        </form>

        <form action="{{ route('iot.commodity-params.store') }}" method="POST" onsubmit="return validateMinMax(this)">
            @csrf
            <x-iot.modal-form id="addCommodityParam" title="Tambah Threshold Komoditas" size="md">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Komoditas *</label>
                        <select name="commodityId" required class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Pilih Komoditas</option>
                            @foreach ($commodities as $k)
                                <option value="{{ $k->id }}">{{ $k->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Parameter *</label>
                        <select name="parameterId" required class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Pilih Parameter</option>
                            @foreach ($parameters as $p)
                                <option value="{{ $p->id }}">{{ $p->parameterCode }} - {{ $p->parameterName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nilai Minimum</label>
                        <input type="number" step="0.1" name="minValue" placeholder="20" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nilai Maksimum</label>
                        <input type="number" step="0.1" name="maxValue" placeholder="28" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">Nilai minimum harus lebih kecil dari nilai maksimum.</p>
            </x-iot.modal-form>
        </form>

        <form :action="`{{ url('/iot/parameters') }}/${editParameter.id}`" method="POST">
            @csrf
            @method('PUT')
            <x-iot.modal-form id="editParameter" title="Edit Parameter Sensor" size="md">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Kode Parameter *</label>
                        <input type="text" name="parameterCode" x-model="editParameter.parameterCode" required class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Parameter *</label>
                        <input type="text" name="parameterName" x-model="editParameter.parameterName" required class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Satuan</label>
                        <input type="text" name="unit" x-model="editParameter.unit" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi</label>
                        <textarea rows="2" name="description" x-model="editParameter.description" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all resize-none"></textarea>
                    </div>
                </div>
            </x-iot.modal-form>
        </form>

        <form :action="`{{ url('/iot/commodity-params') }}/${editCommodityParam.id}`" method="POST" onsubmit="return validateMinMax(this)">
            @csrf
            @method('PUT')
            <x-iot.modal-form id="editCommodityParam" title="Edit Threshold Komoditas" size="md">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nilai Minimum</label>
                        <input type="number" step="0.1" name="minValue" x-model="editCommodityParam.minValue" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nilai Maksimum</label>
                        <input type="number" step="0.1" name="maxValue" x-model="editCommodityParam.maxValue" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">Nilai minimum harus lebih kecil dari nilai maksimum.</p>
            </x-iot.modal-form>
        </form>
    </div>

    <script>
        function validateMinMax(form) {
            const min = parseFloat(form.minValue?.value);
            const max = parseFloat(form.maxValue?.value);

            if (!Number.isNaN(min) && !Number.isNaN(max) && min >= max) {
                alert('Nilai minimum harus lebih kecil dari nilai maksimum.');
                return false;
            }

            return true;
        }
    </script>
@endsection
