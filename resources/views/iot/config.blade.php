@extends('layouts.app')

@section('title', 'Konfigurasi IoT')

@section('content')
    <div x-data="{ modal: null, activeTab: 'protocols', editProtocol: {}, editConnection: {}, editParameter: {}, editCommodityParam: {} }" class="space-y-6">
        {{-- Page Header --}}
        <div>
            <h1 class="text-2xl font-bold text-[var(--color-gray-900)]">Konfigurasi IoT</h1>
            <p class="text-sm text-[var(--color-gray-500)] mt-1">Kelola protokol, koneksi, parameter sensor, dan threshold komoditas</p>
        </div>

        @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
        @endif
        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
            <div class="flex items-center gap-2 font-semibold mb-1">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                Validasi Gagal
            </div>
            <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
        @endif

        {{-- Tabs --}}
        <div class="bg-white rounded-2xl overflow-hidden" style="box-shadow: var(--shadow-sm);">
            <div class="border-b border-gray-100 px-6 pt-4 flex gap-1 overflow-x-auto">
                @foreach (['protocols' => 'Protokol', 'connections' => 'Koneksi', 'parameters' => 'Parameter Sensor', 'commodity' => 'Komoditas Parameter'] as $key => $label)
                    <button @click="activeTab = '{{ $key }}'"
                        :class="activeTab === '{{ $key }}'
                                    ? 'text-[var(--color-primary)] border-[var(--color-primary)] bg-[var(--color-primary)]05'
                                    : 'text-[var(--color-gray-500)] border-transparent hover:text-[var(--color-gray-700)] hover:bg-gray-50'"
                        class="whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 rounded-t-lg -mb-px transition-all bg-transparent cursor-pointer">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="p-6">
                {{-- ═══ Tab: Protokol ═══ --}}
                <div x-show="activeTab === 'protocols'" x-cloak>
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm text-[var(--color-gray-500)]">Jenis protokol komunikasi IoT yang didukung</p>
                        <button @click="modal = 'addProtocol'"
                            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-medium text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90 transition-opacity">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah
                        </button>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Nama Protokol</th>
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Deskripsi</th>
                                <th
                                    class="text-right py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($protocols as $p)
                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3.5 px-3 font-medium text-[var(--color-gray-900)]">{{ $p->protocolName }}
                                    </td>
                                    <td class="py-3.5 px-3 text-[var(--color-gray-600)] text-xs">{{ $p->description }}</td>
                                    <td class="py-3.5 px-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button @click="editProtocol = {{ $p->toJson() }}; modal = 'editProtocol'"
                                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                                title="Edit">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </button>
                                            <form action="{{ route('iot.protocols.destroy', $p->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Hapus protokol &quot;{{ $p->protocolName }}&quot;?\nProtokol yang masih digunakan koneksi tidak bisa dihapus.');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Hapus">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- ═══ Tab: Koneksi ═══ --}}
                <div x-show="activeTab === 'connections'" x-cloak>
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm text-[var(--color-gray-500)]">Konfigurasi koneksi ke server/broker IoT</p>
                        <button @click="modal = 'addConnection'"
                            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-medium text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90 transition-opacity">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    <th
                                        class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                        Protokol</th>
                                    <th
                                        class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                        Endpoint / Broker</th>
                                    <th
                                        class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                        Auth</th>
                                    <th
                                        class="text-right py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                        Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($connectionConfigs as $cc)
                                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                        <td class="py-3.5 px-3">
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-purple-50 text-purple-700">
                                                {{ $cc->protocol->protocolName ?? '-' }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-3">
                                            <code class="text-xs bg-gray-100 px-2 py-1 rounded text-[var(--color-gray-700)]">
                                                        {{ $cc->mqttBrokerUrl ?? $cc->baseUrl }}{{ $cc->endpointPath ?? '' }}
                                                    </code>
                                            @if ($cc->mqttTopic)
                                                <div class="text-[10px] text-[var(--color-gray-400)] mt-1">Topic:
                                                    {{ $cc->mqttTopic }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-3 text-xs text-[var(--color-gray-600)]">{{ $cc->authType }}</td>
                                        <td class="py-3.5 px-3 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <button @click="editConnection = {{ json_encode($cc) }}; modal = 'editConnection'"
                                                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                                    title="Edit">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                </button>
                                                <form action="{{ route('iot.connections.destroy', $cc->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Hapus koneksi ini?\nKoneksi yang masih dipakai device tidak bisa dihapus.');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Hapus">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ═══ Tab: Parameter Sensor ═══ --}}
                <div x-show="activeTab === 'parameters'" x-cloak>
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm text-[var(--color-gray-500)]">Parameter yang dapat dipetakan ke sensor IoT</p>
                        <button @click="modal = 'addParameter'"
                            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-medium text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90 transition-opacity">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah
                        </button>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Kode</th>
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Nama</th>
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Satuan</th>
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Deskripsi</th>
                                <th
                                    class="text-right py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($parameters as $param)
                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3.5 px-3"><span
                                            class="font-mono text-xs font-medium bg-blue-50 px-2 py-1 rounded-lg text-blue-700">{{ $param->parameterCode }}</span>
                                    </td>
                                    <td class="py-3.5 px-3 font-medium text-[var(--color-gray-900)]">
                                        {{ $param->parameterName }}</td>
                                    <td class="py-3.5 px-3 text-[var(--color-gray-600)]">{{ $param->unit }}</td>
                                    <td class="py-3.5 px-3 text-[var(--color-gray-500)] text-xs">{{ $param->description }}
                                    </td>
                                    <td class="py-3.5 px-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button @click="editParameter = {{ $param->toJson() }}; modal = 'editParameter'"
                                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                                title="Edit">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </button>
                                            <form action="{{ route('iot.parameters.destroy', $param->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Hapus parameter &quot;{{ $param->parameterCode }}&quot;?\nParameter yang masih di-mapping tidak bisa dihapus.');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Hapus">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- ═══ Tab: Komoditas Parameter ═══ --}}
                <div x-show="activeTab === 'commodity'" x-cloak>
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm text-[var(--color-gray-500)]">Threshold ideal parameter sensor per komoditas</p>
                        <button @click="modal = 'addCommodityParam'"
                            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-medium text-white bg-[var(--color-primary)] border-none cursor-pointer hover:opacity-90 transition-opacity">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah
                        </button>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Komoditas</th>
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Parameter</th>
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Min</th>
                                <th
                                    class="text-left py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Max</th>
                                <th
                                    class="text-right py-3 px-3 text-xs font-semibold text-[var(--color-gray-500)] uppercase tracking-wider">
                                    Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($commodityParameters as $cp)
                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3.5 px-3 font-medium text-[var(--color-gray-900)]">{{ $cp->commodity->nama ?? '-' }}
                                    </td>
                                    <td class="py-3.5 px-3 text-[var(--color-gray-700)]">{{ $cp->parameter->parameterName ?? '-' }}</td>
                                    <td class="py-3.5 px-3">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700">{{ $cp->minValue }}</span>
                                    </td>
                                    <td class="py-3.5 px-3">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-50 text-red-700">{{ $cp->maxValue }}</span>
                                    </td>
                                    <td class="py-3.5 px-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button @click="editCommodityParam = {{ $cp->toJson() }}; modal = 'editCommodityParam'"
                                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                                title="Edit">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </button>
                                            <form action="{{ route('iot.commodity-params.destroy', $cp->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Hapus parameter komoditas ini?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border-none cursor-pointer text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Hapus">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ═══ MODAL: Add Protocol ═══ --}}
        <form action="{{ route('iot.protocols.store') }}" method="POST">
            @csrf
            <x-iot.modal-form id="addProtocol" title="Tambah Protokol" size="md">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Protokol *</label>
                        <input type="text" name="protocolName" placeholder="e.g. MQTT" required
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi</label>
                        <textarea rows="3" name="description" placeholder="Deskripsi protokol komunikasi..."
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]20 transition-all resize-none"></textarea>
                    </div>
                </div>
            </x-iot.modal-form>
        </form>

        {{-- ═══ MODAL: Add Connection ═══ --}}
        <form action="{{ route('iot.connections.store') }}" method="POST">
            @csrf
            <x-iot.modal-form id="addConnection" title="Tambah Konfigurasi Koneksi" size="lg">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Protokol *</label>
                        <select name="protocolId" required class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Pilih Protokol</option>
                            @foreach ($protocols as $p)
                                <option value="{{ $p->id }}">{{ $p->protocolName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Base URL</label>
                        <input type="text" name="baseUrl" placeholder="https://platform.example.com" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Endpoint Path</label>
                        <input type="text" name="endpointPath" placeholder="/api/v2/devices" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">MQTT Broker URL</label>
                        <input type="text" name="mqttBrokerUrl" placeholder="mqtts://broker.hivemq.cloud:8883" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">MQTT Topic</label>
                        <input type="text" name="mqttTopic" placeholder="farm/sensor/#" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
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
                <p class="text-xs text-gray-400 mt-2">💡 Harus mengisi minimal Base URL atau MQTT Broker URL.</p>
            </x-iot.modal-form>
        </form>

        {{-- ═══ MODAL: Add Parameter ═══ --}}
        <form action="{{ route('iot.parameters.store') }}" method="POST">
            @csrf
            <x-iot.modal-form id="addParameter" title="Tambah Parameter Sensor" size="md">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Kode Parameter *</label>
                        <input type="text" name="parameterCode" placeholder="e.g. TEMP" required class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Parameter *</label>
                        <input type="text" name="parameterName" placeholder="e.g. Temperature" required class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Satuan</label>
                        <input type="text" name="unit" placeholder="e.g. °C" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi</label>
                        <textarea rows="2" name="description" placeholder="Penjelasan mengenai parameter..." class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all resize-none"></textarea>
                    </div>
                </div>
            </x-iot.modal-form>
        </form>

        {{-- ═══ MODAL: Add Commodity Parameter (FIXED) ═══ --}}
        <form action="{{ route('iot.commodity-params.store') }}" method="POST" onsubmit="return validateMinMax(this)">
            @csrf
            <x-iot.modal-form id="addCommodityParam" title="Tambah Parameter Komoditas" size="md">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Komoditas *</label>
                        <select name="commodityId" required class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Pilih Komoditas</option>
                            @foreach ($commodities as $k)
                                <option value="{{ $k['id'] }}">{{ $k['nama'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Parameter *</label>
                        <select name="parameterId" required class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            <option value="">Pilih Parameter</option>
                            @foreach ($parameters as $p)
                                <option value="{{ $p['id'] }}">{{ $p['parameterCode'] }} — {{ $p['parameterName'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nilai Minimum</label>
                        <input type="number" step="0.1" name="minValue" placeholder="e.g. 20" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nilai Maksimum</label>
                        <input type="number" step="0.1" name="maxValue" placeholder="e.g. 28" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">💡 Nilai minimum harus lebih kecil dari nilai maksimum.</p>
            </x-iot.modal-form>
        </form>

        {{-- ═══ MODAL: Edit Protocol ═══ --}}
        <form :action="`{{ url('/iot/protocols') }}/${editProtocol.id}`" method="POST">
            @csrf @method('PUT')
            <x-iot.modal-form id="editProtocol" title="Edit Protokol" size="md">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Protokol *</label>
                        <input type="text" name="protocolName" x-model="editProtocol.protocolName" required class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi</label>
                        <textarea rows="3" name="description" x-model="editProtocol.description" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all resize-none"></textarea>
                    </div>
                </div>
            </x-iot.modal-form>
        </form>

        {{-- ═══ MODAL: Edit Connection ═══ --}}
        <form :action="`{{ url('/iot/connections') }}/${editConnection.id}`" method="POST">
            @csrf @method('PUT')
            <x-iot.modal-form id="editConnection" title="Edit Konfigurasi Koneksi" size="lg">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Protokol *</label>
                        <select name="protocolId" x-model="editConnection.protocolId" required class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:border-[var(--color-primary)] transition-all">
                            @foreach ($protocols as $p)
                                <option value="{{ $p->id }}">{{ $p->protocolName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Base URL</label>
                        <input type="text" name="baseUrl" x-model="editConnection.baseUrl" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Endpoint Path</label>
                        <input type="text" name="endpointPath" x-model="editConnection.endpointPath" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">MQTT Broker URL</label>
                        <input type="text" name="mqttBrokerUrl" x-model="editConnection.mqttBrokerUrl" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">MQTT Topic</label>
                        <input type="text" name="mqttTopic" x-model="editConnection.mqttTopic" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[var(--color-primary)] transition-all">
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
                </div>
                <p class="text-xs text-gray-400 mt-2">💡 Harus mengisi minimal Base URL atau MQTT Broker URL.</p>
            </x-iot.modal-form>
        </form>

        {{-- ═══ MODAL: Edit Parameter ═══ --}}
        <form :action="`{{ url('/iot/parameters') }}/${editParameter.id}`" method="POST">
            @csrf @method('PUT')
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

        {{-- ═══ MODAL: Edit Commodity Parameter ═══ --}}
        <form :action="`{{ url('/iot/commodity-params') }}/${editCommodityParam.id}`" method="POST" onsubmit="return validateMinMax(this)">
            @csrf @method('PUT')
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
                <p class="text-xs text-gray-400 mt-2">💡 Nilai minimum harus lebih kecil dari nilai maksimum.</p>
            </x-iot.modal-form>
        </form>
    </div>

    <script>
    function validateMinMax(form) {
        const min = parseFloat(form.minValue?.value);
        const max = parseFloat(form.maxValue?.value);
        if (!isNaN(min) && !isNaN(max) && min >= max) {
            alert('Nilai minimum harus lebih kecil dari nilai maksimum.');
            return false;
        }
        return true;
    }
    </script>
@endsection