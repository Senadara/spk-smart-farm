@extends('layouts.app')

@section('title', 'Device Management')

@section('content')
    <div x-data="{ modal: null, activeTab: 'devices' }" class="space-y-6">
        {{-- Page Header --}}
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[var(--color-gray-900)]">Device Management</h1>
                <p class="text-sm text-[var(--color-gray-500)] mt-1">Registrasi, pairing, dan mapping parameter sensor</p>
            </div>
            <div class="flex gap-3">
                <button @click="modal = 'addDevice'; activeTab = 'devices'"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-white
                           bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90 transition-opacity">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Device
                </button>
                <button @click="modal = 'addMapping'"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium text-[var(--color-primary)]
                           bg-[var(--color-primary)]10 border border-[var(--color-primary)]30 cursor-pointer hover:bg-[var(--color-primary)]20 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                    Tambah Mapping
                </button>
            </div>
        </div>

        {{-- Device Table --}}
        <div class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
            <h2 class="text-base font-semibold text-[var(--color-gray-900)] mb-4">Daftar Device Terdaftar</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Kode Device</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Nama</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Kandang</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Koneksi</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Status</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Polling</th>
                            <th class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Terpasang</th>
                            <th class="text-right py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($devices as $device)
                            @php
                                $statusColors = [
                                    'active'      => ['bg' => '#ECFDF5', 'text' => '#065F46'],
                                    'inactive'    => ['bg' => '#F3F4F6', 'text' => '#374151'],
                                    'maintenance' => ['bg' => '#FFFBEB', 'text' => '#92400E'],
                                ];
                                $sc = $statusColors[$device['status']] ?? $statusColors['inactive'];
                            @endphp
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="py-3.5 px-3">
                                    <span class="font-mono text-xs font-medium bg-gray-100 px-2 py-1 rounded-lg text-[var(--color-gray-800)]">
                                        {{ $device['deviceCode'] }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-3 text-[var(--color-gray-700)]">{{ $device['deviceName'] }}</td>
                                <td class="py-3.5 px-3 text-[var(--color-gray-700)]">{{ $device['unitBudidaya'] }}</td>
                                <td class="py-3.5 px-3 text-[var(--color-gray-700)] text-xs">{{ $device['connectionConfig'] }}</td>
                                <td class="py-3.5 px-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                                        style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }};">
                                        {{ ucfirst($device['status']) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-3 text-[var(--color-gray-700)]">{{ $device['pollingInterval'] }}s</td>
                                <td class="py-3.5 px-3 text-[var(--color-gray-500)] text-xs">{{ $device['installedAt'] }}</td>
                                <td class="py-3.5 px-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                            title="Edit">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button
                                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                            title="Hapus">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Parameter Mapping Table --}}
        <div class="bg-white rounded-2xl p-6" style="box-shadow: var(--shadow-sm);">
            <h2 class="text-base font-semibold text-[var(--color-gray-900)] mb-4">Parameter Mapping</h2>
            <p class="text-sm text-[var(--color-gray-500)] mb-4">Pemetaan antara payload IoT dan parameter sensor di sistem</p>
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
                        @foreach ($mappings as $mapping)
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                <td class="py-3.5 px-3">
                                    <span class="font-mono text-xs font-medium bg-blue-50 px-2 py-1 rounded-lg text-blue-700">
                                        {{ $mapping['deviceName'] }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-3 text-[var(--color-gray-700)]">{{ $mapping['parameterName'] }}</td>
                                <td class="py-3.5 px-3">
                                    <code class="text-xs bg-gray-100 px-2 py-1 rounded text-[var(--color-gray-800)]">{{ $mapping['payloadKey'] }}</code>
                                </td>
                                <td class="py-3.5 px-3 text-right">
                                    <button
                                        class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                        title="Hapus">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══ MODAL: Add Device ═══ --}}
        <x-iot.modal-form id="addDevice" title="Registrasi Device Baru" size="lg">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Kode Device *</label>
                    <input type="text" placeholder="e.g. DHT22-KA-01"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Device</label>
                    <input type="text" placeholder="e.g. Sensor Suhu Kandang A"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Kandang *</label>
                    <select
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all bg-white">
                        <option value="">Pilih Kandang</option>
                        @foreach ($unitBudidaya as $ub)
                            <option value="{{ $ub['id'] }}">{{ $ub['nama'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Konfigurasi Koneksi *</label>
                    <select
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all bg-white">
                        <option value="">Pilih Koneksi</option>
                        @foreach ($connectionConfigs as $cc)
                            <option value="{{ $cc['id'] }}">{{ $cc['protocolName'] }} — {{ $cc['mqttBrokerUrl'] ?? $cc['baseUrl'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Polling Interval (detik)</label>
                    <input type="number" value="300" min="10"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                    <select
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all bg-white">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Tanggal Pemasangan</label>
                    <input type="datetime-local"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                </div>
            </div>
        </x-iot.modal-form>

        {{-- ═══ MODAL: Add Mapping ═══ --}}
        <x-iot.modal-form id="addMapping" title="Tambah Mapping Parameter" size="md">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Device *</label>
                    <select
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all bg-white">
                        <option value="">Pilih Device</option>
                        @foreach ($devices as $d)
                            <option value="{{ $d['id'] }}">{{ $d['deviceCode'] }} — {{ $d['deviceName'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Parameter Sensor *</label>
                    <select
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all bg-white">
                        <option value="">Pilih Parameter</option>
                        @foreach ($parameters as $p)
                            <option value="{{ $p['id'] }}">{{ $p['parameterCode'] }} — {{ $p['parameterName'] }} ({{ $p['unit'] }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Payload Key *</label>
                    <input type="text" placeholder="e.g. temperature"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                    <p class="text-xs text-[var(--color-gray-400)] mt-1">Key yang digunakan pada JSON payload dari device IoT</p>
                </div>
            </div>
        </x-iot.modal-form>
    </div>
@endsection
