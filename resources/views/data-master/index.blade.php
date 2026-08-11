@extends('layouts.app')

@section('title', 'Data Master Ternak')

@section('content')
@php
    $types = $masterOverview['types'];
    $selectedType = $masterOverview['selectedType'];
    $selectedConfig = $masterOverview['selectedConfig'];
    $environmentRows = $masterOverview['environmentParameters'];
    $productivityFunctions = $masterOverview['productivityFunctions'];
    $selectedFunctionIds = $masterOverview['selectedFunctionIds'];
    $selectedOperationalFunctionIds = $masterOverview['selectedOperationalFunctionIds'] ?? [];
    $afkirConfig = $masterOverview['afkirConfig'] ?? ['label' => 'Afkir / akhir siklus', 'target_weeks' => null, 'warning_weeks' => 4, 'is_configured' => false];
    $schemaReady = $masterOverview['schemaReady'];
    $readyCount = $types->filter(fn ($type) => $type['readiness']['configured'] ?? false)->count();
    $pendingCount = max($types->count() - $readyCount, 0);
    $selectedReadiness = $selectedType['readiness'] ?? null;
    $primaryCommodityId = $selectedType['primary_commodity_id'] ?? $selectedConfig?->commodity_id;

    $environmentRowsForJs = $environmentRows->map(fn ($row) => [
        'parameter_code' => $row->parameter_code ?? '',
        'parameter_name' => $row->parameter_name ?? '',
        'unit' => $row->unit ?? '',
        'icon_key' => $row->icon_key ?? 'sensor',
        'min_value' => $row->min_value ?? null,
        'max_value' => $row->max_value ?? null,
        'fallback_value' => $row->fallback_value ?? null,
        'stale_minutes' => $row->stale_minutes ?? 30,
        'required_for_iot' => (bool) ($row->required_for_iot ?? true),
        'required_for_fuzzy' => (bool) ($row->required_for_fuzzy ?? false),
    ])->values();

    $sensorCatalogForJs = $sensorParameters->map(fn ($parameter) => [
        'id' => $parameter->id,
        'parameterCode' => $parameter->parameterCode,
        'parameterName' => $parameter->parameterName,
        'unit' => $parameter->unit,
        'description' => $parameter->description,
    ])->values();

    $functionRowsForJs = $productivityFunctions->map(fn ($function) => [
        'id' => $function->id,
        'name' => $function->name,
        'unit' => $function->output_unit,
        'description' => $function->description,
        'target_min_value' => $function->target_min_value ?? null,
        'target_max_value' => $function->target_max_value ?? null,
    ])->values();
    $environmentIconOptions = [
        'sensor' => 'Sensor umum',
        'gauge' => 'Gauge',
        'air' => 'Udara',
        'water' => 'Cairan',
        'light' => 'Cahaya',
        'alert' => 'Peringatan',
    ];
    $masterTabs = [
        'sensor-parameters' => ['label' => 'Parameter Sensor', 'description' => 'Katalog kode sensor pusat untuk IoT, threshold, dan SPK'],
        'livestock' => ['label' => 'Ternak', 'description' => 'Parameter lingkungan dan referensi produktivitas'],
        'stock-categories' => ['label' => 'Kategori Stok', 'description' => 'Kategori produk dan kebutuhan restock'],
        'product-units' => ['label' => 'Satuan Produk', 'description' => 'Satuan katalog supplier dan inventori'],
    ];
    $stockCategorySource = $stockCategories->contains('source', 'shared')
        ? 'shared'
        : ($dataMasterSources['stock_categories'] ?? ($stockCategories->first()->source ?? 'web'));
    $productUnitSource = $productUnits->contains('source', 'shared')
        ? 'shared'
        : ($dataMasterSources['product_units'] ?? ($productUnits->first()->source ?? 'web'));
    $stockCategoryRowsForJs = $stockCategories->map(fn ($category) => [
        'id' => $category->id,
        'name' => $category->name,
        'description' => $category->description,
        'is_active' => (bool) $category->is_active,
        'source' => $category->source ?? 'web',
        'editable' => (bool) ($category->editable ?? true),
        'update_url' => ($category->id && (bool) ($category->editable ?? true))
            ? route('data-master.stock-categories.update', $category->id)
            : null,
    ])->values();
    $productUnitRowsForJs = $productUnits->map(fn ($unit) => [
        'id' => $unit->id,
        'symbol' => $unit->symbol,
        'name' => $unit->name,
        'description' => $unit->description,
        'is_active' => (bool) $unit->is_active,
        'source' => $unit->source ?? 'web',
        'editable' => (bool) ($unit->editable ?? true),
        'update_url' => ($unit->id && (bool) ($unit->editable ?? true))
            ? route('data-master.product-units.update', $unit->id)
            : null,
    ])->values();
    $dataMasterFormRoutes = [
        'sensorParameterStore' => route('data-master.sensor-parameters.store'),
        'stockCategoryStore' => route('data-master.stock-categories.store'),
        'productUnitStore' => route('data-master.product-units.store'),
    ];
    $usedSensorCount = collect($sensorParameterUsage)->filter(fn ($count) => (int) $count > 0)->count();
@endphp

<div
    x-data="livestockMasterPage(@js($environmentRowsForJs), @js($selectedFunctionIds), @js($selectedOperationalFunctionIds), @js($functionRowsForJs), @js($stockCategoryRowsForJs), @js($productUnitRowsForJs), @js($dataMasterFormRoutes), @js($sensorCatalogForJs))"
    class="space-y-5"
>
    <div class="rounded-2xl border border-gray-100 bg-white p-5" style="box-shadow: var(--shadow-sm);">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="min-w-0">
                <div class="mb-1 text-sm text-gray-500">
                    <span class="font-medium text-gray-700">Data Master</span>
                    <span class="mx-1 text-gray-300">/</span>
                    <span>{{ $masterTabs[$activeTab]['label'] ?? 'Ternak' }}</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Konfigurasi Data Master</h1>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                    Kelola konfigurasi pusat agar dashboard, IoT, Fuzzy SPK, stok, dan supplier memakai istilah yang konsisten.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-gray-700">
                    <span class="text-gray-400">Jenis</span>
                    <span class="font-black text-gray-900">{{ $types->count() }}</span>
                </span>
                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1.5 text-emerald-700">
                    <span>Siap</span>
                    <span class="font-black">{{ $readyCount }}</span>
                </span>
                <span class="inline-flex items-center gap-2 rounded-full border border-amber-100 bg-amber-50 px-3 py-1.5 text-amber-700">
                    <span>Setup</span>
                    <span class="font-black">{{ $pendingCount }}</span>
                </span>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-100 bg-white" style="box-shadow: var(--shadow-sm);">
        <nav class="flex overflow-x-auto px-2 pt-2" aria-label="Navigasi Data Master">
            @foreach($masterTabs as $tabKey => $tab)
                <a href="{{ route('data-master.index', array_filter(['tab' => $tabKey, 'jenis_budidaya_id' => $selectedType['id'] ?? null])) }}"
                    class="relative whitespace-nowrap border-b-2 px-4 py-3 text-sm font-bold transition {{ $activeTab === $tabKey ? 'border-emerald-500 text-emerald-700' : 'border-transparent text-gray-500 hover:border-gray-200 hover:text-gray-900' }}"
                    title="{{ $tab['description'] }}"
                    style="text-decoration:none;">
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </nav>
        <div class="border-t border-gray-100 px-4 py-3">
            <p class="text-sm font-semibold text-gray-900">{{ $masterTabs[$activeTab]['label'] ?? 'Ternak' }}</p>
            <p class="mt-0.5 text-xs leading-5 text-gray-500">{{ $masterTabs[$activeTab]['description'] ?? 'Kelola Data Master.' }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <div class="mb-1 font-semibold">Validasi gagal</div>
            <ul class="list-inside list-disc space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(! $schemaReady)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Tabel Data Master ternak belum tersedia. Tombol simpan tetap aktif, tetapi penyimpanan baru berhasil setelah migration terbaru dijalankan.
        </div>
    @endif

    @if($activeTab === 'sensor-parameters')
        <section class="rounded-2xl border border-gray-100 bg-white p-5" style="box-shadow: var(--shadow-sm);">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-bold text-gray-900">Parameter Sensor</h2>
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">Master pusat</span>
                    </div>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                        Daftar ini menjadi sumber dropdown untuk mapping IoT, konfigurasi threshold sensor per jenis ternak, dan input Fuzzy SPK. Gunakan kode yang singkat dan konsisten, misalnya TEMP, HUMID, AMMON, LIGHT.
                    </p>
                </div>
                <button type="button" @click="openSensorParameterModal()" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">
                    Tambah Parameter Sensor
                </button>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3">
                    <p class="text-xs font-bold uppercase text-emerald-700">Total Kode</p>
                    <p class="mt-1 text-2xl font-black text-emerald-900">{{ $sensorParameters->count() }}</p>
                </div>
                <div class="rounded-xl border border-sky-100 bg-sky-50 px-4 py-3">
                    <p class="text-xs font-bold uppercase text-sky-700">Sudah Dipakai</p>
                    <p class="mt-1 text-2xl font-black text-sky-900">{{ $usedSensorCount }}</p>
                </div>
                <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3">
                    <p class="text-xs font-bold uppercase text-amber-700">Perhatian</p>
                    <p class="mt-1 text-xs leading-5 font-semibold text-amber-800">Parameter yang sudah dipakai tidak bisa dihapus langsung.</p>
                </div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-[11px] font-bold uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Kode Sensor</th>
                            <th class="px-3 py-2">Nama</th>
                            <th class="px-3 py-2">Satuan</th>
                            <th class="px-3 py-2">Deskripsi</th>
                            <th class="px-3 py-2">Dipakai</th>
                            <th class="px-3 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($sensorParameters as $parameter)
                            @php
                                $usageCount = (int) ($sensorParameterUsage[$parameter->id] ?? 0);
                                $parameterPayload = [
                                    'parameterCode' => $parameter->parameterCode,
                                    'parameterName' => $parameter->parameterName,
                                    'unit' => $parameter->unit,
                                    'description' => $parameter->description,
                                ];
                            @endphp
                            <tr class="align-top">
                                <td class="px-3 py-3">
                                    <span class="rounded-lg bg-gray-100 px-2.5 py-1 font-mono text-xs font-black text-gray-900">{{ $parameter->parameterCode }}</span>
                                </td>
                                <td class="px-3 py-3 font-bold text-gray-900">{{ $parameter->parameterName }}</td>
                                <td class="px-3 py-3 text-gray-600">{{ $parameter->unit ?: '-' }}</td>
                                <td class="px-3 py-3 text-gray-600">{{ $parameter->description ?: '-' }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $usageCount > 0 ? 'bg-sky-50 text-sky-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $usageCount }} referensi
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button" @click="openSensorParameterModal('edit', @js(route('data-master.sensor-parameters.update', $parameter->id)), @js($parameterPayload))" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-700 hover:border-emerald-200 hover:text-emerald-700">
                                            Ubah
                                        </button>
                                        @if($usageCount > 0)
                                            <button type="button" disabled class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-xs font-bold text-gray-400" title="Parameter masih dipakai di mapping, threshold, konfigurasi ternak, atau data sensor.">
                                                Hapus
                                            </button>
                                        @else
                                            <form action="{{ route('data-master.sensor-parameters.destroy', $parameter->id) }}" method="POST" onsubmit="return confirm('Hapus parameter sensor {{ $parameter->parameterCode }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-red-100 bg-white px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50">
                                                    Hapus
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-12 text-center">
                                    <p class="text-sm font-bold text-gray-900">Belum ada parameter sensor</p>
                                    <p class="mt-1 text-xs leading-5 text-gray-500">Tambahkan kode sensor pusat terlebih dahulu sebelum mengatur threshold ternak atau mapping payload IoT.</p>
                                    <button type="button" @click="openSensorParameterModal()" class="mt-3 inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700">
                                        Tambah Parameter Sensor
                                    </button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div x-show="sensorParameterModal.open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/45 p-4" @keydown.escape.window="closeSensorParameterModal()">
            <div class="absolute inset-0" @click="closeSensorParameterModal()"></div>
            <form method="POST" :action="sensorParameterModal.action" class="relative w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
                @csrf
                <input type="hidden" name="_method" value="PATCH" :disabled="sensorParameterModal.mode !== 'edit'">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900" x-text="sensorParameterModal.mode === 'edit' ? 'Ubah Parameter Sensor' : 'Tambah Parameter Sensor'"></h3>
                        <p class="mt-1 text-sm text-gray-500">Kode ini akan muncul sebagai pilihan di IoT, threshold jenis ternak, dan SPK.</p>
                    </div>
                    <button type="button" @click="closeSensorParameterModal()" class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50" title="Tutup">
                        <span class="text-lg leading-none">&times;</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-bold text-gray-700">Kode sensor *</span>
                        <input x-ref="sensorParameterCode" name="parameterCode" x-model="sensorParameterModal.parameterCode" @input="sensorParameterModal.parameterCode = normalizeSensorCode(sensorParameterModal.parameterCode)" required class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 font-mono text-sm uppercase focus:border-[var(--color-primary)] focus:outline-none" placeholder="TEMP">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold text-gray-700">Nama parameter *</span>
                        <input name="parameterName" x-model="sensorParameterModal.parameterName" required class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none" placeholder="Suhu">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold text-gray-700">Satuan</span>
                        <input name="unit" x-model="sensorParameterModal.unit" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none" placeholder="C, %, ppm, lux">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold text-gray-700">Deskripsi</span>
                        <textarea name="description" rows="3" x-model="sensorParameterModal.description" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none" placeholder="Keterangan singkat penggunaan sensor"></textarea>
                    </label>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" @click="closeSensorParameterModal()" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50">Batal</button>
                    <button class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700" x-text="sensorParameterModal.mode === 'edit' ? 'Simpan Perubahan' : 'Simpan Parameter'"></button>
                </div>
            </form>
        </div>
    @elseif($activeTab === 'livestock')
    <div class="space-y-5">
        <section class="rounded-2xl border border-gray-100 bg-white p-4" style="box-shadow: var(--shadow-sm);">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-bold text-gray-900">Jenis Ternak</h2>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-600">{{ $types->count() }} data</span>
                    </div>
                    <p class="mt-1 text-xs leading-5 text-gray-500">Pilih jenis ternak yang akan dikonfigurasi. Data pilihan ini dibaca dari mobile.</p>
                </div>

                @if($types->isNotEmpty())
                    <form method="GET" action="{{ route('data-master.index') }}" class="w-full lg:max-w-md">
                        <input type="hidden" name="tab" value="livestock">
                        <label class="sr-only" for="jenis_budidaya_id">Pilih jenis ternak</label>
                        <select
                            id="jenis_budidaya_id"
                            name="jenis_budidaya_id"
                            onchange="this.form.submit()"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-gray-900 focus:border-[var(--color-primary)] focus:outline-none"
                        >
                            @foreach($types as $type)
                                @php
                                    $ready = $type['readiness']['configured'] ?? false;
                                @endphp
                                <option value="{{ $type['id'] }}" @selected(($selectedType['id'] ?? null) === $type['id'])>
                                    {{ $type['nama'] }} - {{ $type['coop_count'] }} kandang / {{ $type['commodity_count'] }} komoditas{{ $ready ? ' / siap' : ' / setup' }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>

            @if($selectedType)
                <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                    <span class="rounded-full px-3 py-1.5 {{ ($selectedReadiness['configured'] ?? false) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                        {{ ($selectedReadiness['configured'] ?? false) ? 'Konfigurasi siap' : 'Perlu setup' }}
                    </span>
                    <span class="rounded-full bg-gray-50 px-3 py-1.5 text-gray-700">{{ $selectedType['coop_count'] }} kandang</span>
                    <span class="rounded-full bg-gray-50 px-3 py-1.5 text-gray-700">{{ $selectedType['commodity_count'] }} komoditas</span>
                    @if(!empty($selectedType['commodities']))
                        <span class="max-w-full truncate rounded-full bg-sky-50 px-3 py-1.5 text-sky-700">{{ implode(', ', $selectedType['commodities']) }}</span>
                    @endif
                </div>
            @else
                <div class="mt-4 rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center">
                    <p class="text-sm font-semibold text-gray-800">Belum ada jenis ternak</p>
                    <p class="mt-1 text-xs leading-5 text-gray-500">Buat jenis ternak dan kandang dari mobile terlebih dahulu.</p>
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-gray-100 bg-white" style="box-shadow: var(--shadow-sm);">
            @if($selectedType)
                <form method="POST" action="{{ route('data-master.livestock.store') }}">
                    @csrf
                    <input type="hidden" name="jenis_budidaya_id" value="{{ $selectedType['id'] }}">
                    <input type="hidden" name="commodity_id" value="{{ $primaryCommodityId }}">

                    <div class="border-b border-gray-100 p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-lg font-bold text-gray-900">{{ $selectedType['nama'] }}</h2>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ ($selectedReadiness['configured'] ?? false) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ ($selectedReadiness['configured'] ?? false) ? 'Terhubung' : 'Belum siap' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">{{ $selectedReadiness['message'] ?? 'Lengkapi konfigurasi Data Master.' }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-2 sm:min-w-[420px] sm:grid-cols-4">
                                <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2 text-center">
                                    <div class="text-lg font-black text-emerald-800" x-text="activeEnvironmentCount"></div>
                                    <div class="text-[10px] font-semibold text-emerald-700">Lingkungan</div>
                                </div>
                                <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-3 py-2 text-center">
                                    <div class="text-lg font-black text-indigo-800" x-text="operationalFunctions.length"></div>
                                    <div class="text-[10px] font-semibold text-indigo-700">Operasional</div>
                                </div>
                                <div class="rounded-xl border border-sky-100 bg-sky-50 px-3 py-2 text-center">
                                    <div class="text-lg font-black text-sky-800" x-text="selectedFunctions.length"></div>
                                    <div class="text-[10px] font-semibold text-sky-700">Fuzzy Produktivitas</div>
                                </div>
                                <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 text-center">
                                    <div class="text-lg font-black text-gray-900">{{ $selectedType['coop_count'] }}</div>
                                    <div class="text-[10px] font-semibold text-gray-500">Kandang</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2">
                                <div class="text-[11px] font-bold uppercase text-emerald-700">1. Lingkungan</div>
                                <p class="mt-1 text-xs text-emerald-800">Pilih data sensor yang dibutuhkan.</p>
                            </div>
                            <div class="rounded-xl border border-sky-100 bg-sky-50 px-3 py-2">
                                <div class="text-[11px] font-bold uppercase text-sky-700">2. Referensi Produktivitas</div>
                                <p class="mt-1 text-xs text-sky-800">Pisahkan data operasional dan input fuzzy.</p>
                            </div>
                            <div class="rounded-xl border border-violet-100 bg-violet-50 px-3 py-2">
                                <div class="text-[11px] font-bold uppercase text-violet-700">3. Siklus Ternak</div>
                                <p class="mt-1 text-xs text-violet-800">Atur target afkir atau akhir panen.</p>
                            </div>
                            <div class="rounded-xl border border-amber-100 bg-amber-50 px-3 py-2">
                                <div class="text-[11px] font-bold uppercase text-amber-700">4. Simpan</div>
                                <p class="mt-1 text-xs text-amber-800">Lanjutkan mapping input di Pengaturan Fuzzy.</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6 p-5">
                        <section class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-gray-900">1. Parameter Lingkungan / IoT</h3>
                                    <p class="mt-1 text-sm text-gray-500">Pilih kode dari katalog Parameter Sensor, lalu atur batas ideal untuk jenis ternak ini.</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('data-master.index', ['tab' => 'sensor-parameters']) }}" class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" style="text-decoration:none;">
                                        Kelola Katalog Sensor
                                    </a>
                                    <button type="button" @click="addEnvironmentRow()" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                                        Tambah Baris Sensor
                                    </button>
                                </div>
                            </div>

                            @if($sensorParameters->isEmpty())
                                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                    Belum ada katalog parameter sensor. Tambahkan kode sensor di tab Parameter Sensor terlebih dahulu agar threshold ternak bisa disimpan konsisten.
                                </div>
                            @endif

                            <div class="mt-4 space-y-3">
                                <template x-for="(row, index) in environmentRows" :key="row.key">
                                    <div class="rounded-xl border bg-white p-4" :class="isEnvironmentRowValid(row) ? 'border-gray-100' : 'border-amber-200'">
                                        <div class="mb-3 flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-xs font-bold uppercase text-gray-400" x-text="`Parameter ${index + 1}`"></div>
                                                <div class="mt-1 text-sm font-bold text-gray-900" x-text="row.parameter_name || 'Parameter baru'"></div>
                                            </div>
                                            <button type="button" @click="removeEnvironmentRow(index)" class="flex h-9 w-9 items-center justify-center rounded-lg border border-red-100 bg-white text-red-500 hover:bg-red-50" title="Hapus parameter">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>

                                        <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
                                            <div class="lg:col-span-2">
                                                <label class="mb-1 block text-xs font-semibold text-gray-600">Kode sensor *</label>
                                                <select :name="`environment_parameters[${index}][parameter_code]`" x-model="row.parameter_code" @change="applySensorCatalog(row)" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold focus:border-[var(--color-primary)] focus:outline-none">
                                                    <option value="">Pilih sensor</option>
                                                    @foreach($sensorParameters as $parameter)
                                                        <option value="{{ $parameter->parameterCode }}">{{ $parameter->parameterCode }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="lg:col-span-3">
                                                <label class="mb-1 block text-xs font-semibold text-gray-600">Nama parameter *</label>
                                                <input type="hidden" :name="`environment_parameters[${index}][parameter_name]`" x-model="row.parameter_name">
                                                <div class="min-h-[42px] rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-800" x-text="row.parameter_name || 'Pilih kode sensor'"></div>
                                            </div>
                                            <div class="lg:col-span-2">
                                                <label class="mb-1 block text-xs font-semibold text-gray-600">Icon</label>
                                                <select :name="`environment_parameters[${index}][icon_key]`" x-model="row.icon_key" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                                    @foreach($environmentIconOptions as $iconKey => $iconLabel)
                                                        <option value="{{ $iconKey }}">{{ $iconLabel }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="lg:col-span-1">
                                                <label class="mb-1 block text-xs font-semibold text-gray-600">Unit</label>
                                                <input type="hidden" :name="`environment_parameters[${index}][unit]`" x-model="row.unit">
                                                <div class="min-h-[42px] rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-700" x-text="row.unit || '-'"></div>
                                            </div>
                                            <div class="lg:col-span-2">
                                                <label class="mb-1 block text-xs font-semibold text-gray-600">Min ideal</label>
                                                <input type="number" step="0.01" :name="`environment_parameters[${index}][min_value]`" x-model="row.min_value" placeholder="20" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                            </div>
                                            <div class="lg:col-span-2">
                                                <label class="mb-1 block text-xs font-semibold text-gray-600">Max ideal</label>
                                                <input type="number" step="0.01" :name="`environment_parameters[${index}][max_value]`" x-model="row.max_value" placeholder="28" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                            </div>
                                        </div>

                                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto_auto] md:items-center">
                                            <label class="flex items-center gap-2 text-xs text-gray-600">
                                                <span>Nilai Cadangan</span>
                                                <input type="number" step="0.01" :name="`environment_parameters[${index}][fallback_value]`" x-model="row.fallback_value" placeholder="0" class="w-24 rounded-lg border border-gray-200 px-2 py-1.5 text-xs">
                                            </label>
                                            <label class="flex items-center gap-2 text-xs text-gray-600">
                                                <span>Batas segar</span>
                                                <input type="number" min="1" max="10080" :name="`environment_parameters[${index}][stale_minutes]`" x-model="row.stale_minutes" class="w-20 rounded-lg border border-gray-200 px-2 py-1.5 text-xs">
                                                <span>menit</span>
                                            </label>
                                            <div class="flex flex-wrap gap-3">
                                                <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                                                    <input type="hidden" :name="`environment_parameters[${index}][required_for_iot]`" value="0">
                                                    <input type="checkbox" :name="`environment_parameters[${index}][required_for_iot]`" value="1" x-model="row.required_for_iot" @change="if (!$event.target.checked) row.required_for_fuzzy = false" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                                    Operasional/Monitoring
                                                </label>
                                                <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                                                    <input type="hidden" :name="`environment_parameters[${index}][required_for_fuzzy]`" value="0">
                                                    <input type="checkbox" :name="`environment_parameters[${index}][required_for_fuzzy]`" value="1" x-model="row.required_for_fuzzy" @change="if ($event.target.checked) row.required_for_iot = true" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                                    Input Fuzzy
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </section>

                        <section class="rounded-xl border border-sky-100 bg-sky-50/40 p-4">
                            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-gray-900">2. Konfigurasi Fuzzy Produktivitas</h3>
                                    <p class="mt-1 max-w-3xl text-sm text-gray-500">Centang Operasional jika indikator dibutuhkan dashboard/laporan jenis ternak ini. Centang Input Fuzzy hanya jika indikator tersebut ikut menjadi variabel input Engine 2.</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="selectAllOperationalFunctions()" class="rounded-lg border border-indigo-200 bg-white px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-50">
                                        Pilih semua operasional
                                    </button>
                                    <button type="button" @click="clearProductivitySelections()" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-600 hover:bg-gray-50">
                                        Kosongkan semua
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                                @foreach($productivityFunctions as $function)
                                    @php
                                        $rawRequiredInputs = $function->required_inputs ?? [];
                                        $requiredInputs = is_array($rawRequiredInputs)
                                            ? $rawRequiredInputs
                                            : (json_decode((string) $rawRequiredInputs, true) ?: []);
                                    @endphp
                                    <div class="block rounded-xl border bg-white p-4 transition"
                                        :class="isOperationalFunctionSelected(@js($function->id)) ? 'border-indigo-200 ring-2 ring-indigo-50' : 'border-gray-100 hover:border-sky-200 hover:bg-sky-50'">
                                        <input type="hidden" name="productivity_functions[{{ $loop->index }}][function_id]" value="{{ $function->id }}">
                                        <input type="hidden" name="productivity_functions[{{ $loop->index }}][aggregation_scope]" value="today">
                                        <input type="hidden" name="productivity_functions[{{ $loop->index }}][is_active]" value="0">
                                        <input type="hidden" name="productivity_functions[{{ $loop->index }}][required_for_fuzzy]" value="0">
                                        <div class="flex flex-col gap-3">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="font-bold text-gray-900">{{ $function->name }}</span>
                                                    @if($function->output_unit)
                                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500">{{ $function->output_unit }}</span>
                                                    @endif
                                                </div>
                                                <p class="mt-1 text-xs leading-5 text-gray-500">{{ $function->description }}</p>
                                            </div>

                                            <div class="grid grid-cols-1 gap-2">
                                                <label for="productivity-operational-{{ $function->id }}"
                                                    class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border px-3 py-2.5 transition"
                                                    :class="isOperationalFunctionSelected(@js($function->id)) ? 'border-indigo-200 bg-indigo-50 text-indigo-800' : 'border-gray-200 bg-gray-50 text-gray-600'">
                                                    <span class="min-w-0">
                                                        <span class="block text-xs font-black uppercase tracking-wide">Data Operasional</span>
                                                        <span class="block text-xs" x-text="isOperationalFunctionSelected(@js($function->id)) ? 'Dipakai dashboard/laporan' : 'Tidak dipakai operasional'"></span>
                                                    </span>
                                                    <input id="productivity-operational-{{ $function->id }}"
                                                        type="checkbox"
                                                        name="productivity_functions[{{ $loop->index }}][is_active]"
                                                        value="1"
                                                        :checked="isOperationalFunctionSelected(@js($function->id))"
                                                        @change="setOperationalFunction(@js($function->id), $event.target.checked)"
                                                        class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                </label>

                                                <label for="productivity-fuzzy-{{ $function->id }}"
                                                    class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border px-3 py-2.5 transition"
                                                    :class="isProductivityFunctionSelected(@js($function->id)) ? 'border-sky-200 bg-sky-50 text-sky-800' : 'border-gray-200 bg-gray-50 text-gray-600'">
                                                    <span class="min-w-0">
                                                        <span class="block text-xs font-black uppercase tracking-wide">Input Fuzzy</span>
                                                        <span class="block text-xs" x-text="isProductivityFunctionSelected(@js($function->id)) ? 'Masuk variabel Engine 2' : 'Tidak masuk variabel fuzzy'"></span>
                                                    </span>
                                                    <input id="productivity-fuzzy-{{ $function->id }}"
                                                        type="checkbox"
                                                        name="productivity_functions[{{ $loop->index }}][required_for_fuzzy]"
                                                        value="1"
                                                        :checked="isProductivityFunctionSelected(@js($function->id))"
                                                        @change="setProductivityFunction(@js($function->id), $event.target.checked)"
                                                        class="h-5 w-5 rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                                                </label>
                                            </div>

                                            <div class="grid grid-cols-2 gap-2 rounded-xl border border-gray-100 bg-gray-50 p-2">
                                                <label class="block">
                                                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-gray-500">Target min</span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        name="productivity_functions[{{ $loop->index }}][target_min_value]"
                                                        value="{{ old("productivity_functions.{$loop->index}.target_min_value", $function->target_min_value ?? '') }}"
                                                        placeholder="-"
                                                        class="w-full rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-xs font-semibold text-gray-800 focus:border-[var(--color-primary)] focus:outline-none"
                                                    >
                                                </label>
                                                <label class="block">
                                                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-gray-500">Target max</span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        name="productivity_functions[{{ $loop->index }}][target_max_value]"
                                                        value="{{ old("productivity_functions.{$loop->index}.target_max_value", $function->target_max_value ?? '') }}"
                                                        placeholder="-"
                                                        class="w-full rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-xs font-semibold text-gray-800 focus:border-[var(--color-primary)] focus:outline-none"
                                                    >
                                                </label>
                                                <p class="col-span-2 text-[10px] leading-4 text-gray-500">
                                                    Opsional. Dipakai sebagai acuan status operasional, bukan sebagai penentu variabel fuzzy.
                                                </p>
                                            </div>

                                            @if(! empty($requiredInputs))
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach($requiredInputs as $input)
                                                        <span class="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-700">{{ $input }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>

                        <section class="rounded-xl border border-violet-100 bg-violet-50/40 p-4">
                            <div class="mb-4">
                                <h3 class="text-base font-bold text-gray-900">3. Konfigurasi Afkir / Akhir Siklus</h3>
                                <p class="mt-1 max-w-3xl text-sm text-gray-500">
                                    Gunakan konfigurasi ini untuk menandai fase produksi, puncak produksi, dan kapan ternak mendekati afkir atau akhir siklus panen.
                                </p>
                            </div>

                            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold text-gray-700">Mulai produksi</span>
                                    <div class="flex rounded-xl border border-gray-200 bg-white focus-within:border-[var(--color-primary)]">
                                        <input
                                            type="number"
                                            name="production_start_weeks"
                                            value="{{ old('production_start_weeks', $afkirConfig['production_start_weeks'] ?? '') }}"
                                            min="0"
                                            max="520"
                                            class="min-w-0 flex-1 rounded-l-xl border-0 px-3 py-2.5 text-sm font-semibold text-gray-900 focus:outline-none focus:ring-0"
                                            placeholder="18"
                                        >
                                        <span class="inline-flex items-center rounded-r-xl border-l border-gray-100 bg-gray-50 px-3 text-xs font-bold text-gray-500">minggu</span>
                                    </div>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold text-gray-700">Awal puncak</span>
                                    <div class="flex rounded-xl border border-gray-200 bg-white focus-within:border-[var(--color-primary)]">
                                        <input
                                            type="number"
                                            name="peak_start_weeks"
                                            value="{{ old('peak_start_weeks', $afkirConfig['peak_start_weeks'] ?? '') }}"
                                            min="0"
                                            max="520"
                                            class="min-w-0 flex-1 rounded-l-xl border-0 px-3 py-2.5 text-sm font-semibold text-gray-900 focus:outline-none focus:ring-0"
                                            placeholder="25"
                                        >
                                        <span class="inline-flex items-center rounded-r-xl border-l border-gray-100 bg-gray-50 px-3 text-xs font-bold text-gray-500">minggu</span>
                                    </div>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold text-gray-700">Akhir puncak</span>
                                    <div class="flex rounded-xl border border-gray-200 bg-white focus-within:border-[var(--color-primary)]">
                                        <input
                                            type="number"
                                            name="peak_end_weeks"
                                            value="{{ old('peak_end_weeks', $afkirConfig['peak_end_weeks'] ?? '') }}"
                                            min="0"
                                            max="520"
                                            class="min-w-0 flex-1 rounded-l-xl border-0 px-3 py-2.5 text-sm font-semibold text-gray-900 focus:outline-none focus:ring-0"
                                            placeholder="45"
                                        >
                                        <span class="inline-flex items-center rounded-r-xl border-l border-gray-100 bg-gray-50 px-3 text-xs font-bold text-gray-500">minggu</span>
                                    </div>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold text-gray-700">Produksi lanjut</span>
                                    <div class="flex rounded-xl border border-gray-200 bg-white focus-within:border-[var(--color-primary)]">
                                        <input
                                            type="number"
                                            name="production_decline_weeks"
                                            value="{{ old('production_decline_weeks', $afkirConfig['production_decline_weeks'] ?? '') }}"
                                            min="0"
                                            max="520"
                                            class="min-w-0 flex-1 rounded-l-xl border-0 px-3 py-2.5 text-sm font-semibold text-gray-900 focus:outline-none focus:ring-0"
                                            placeholder="46"
                                        >
                                        <span class="inline-flex items-center rounded-r-xl border-l border-gray-100 bg-gray-50 px-3 text-xs font-bold text-gray-500">minggu</span>
                                    </div>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
                                <label class="block lg:col-span-4">
                                    <span class="mb-1 block text-xs font-bold text-gray-700">Label di laporan</span>
                                    <input
                                        type="text"
                                        name="afkir_label"
                                        value="{{ old('afkir_label', $afkirConfig['label'] ?? 'Afkir / akhir siklus') }}"
                                        maxlength="80"
                                        class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-gray-900 focus:border-[var(--color-primary)] focus:outline-none"
                                        placeholder="Contoh: Afkir layer / Akhir siklus panen"
                                    >
                                </label>
                                <label class="block lg:col-span-3">
                                    <span class="mb-1 block text-xs font-bold text-gray-700">Target umur/siklus</span>
                                    <div class="flex rounded-xl border border-gray-200 bg-white focus-within:border-[var(--color-primary)]">
                                        <input
                                            type="number"
                                            name="afkir_target_weeks"
                                            value="{{ old('afkir_target_weeks', $afkirConfig['target_weeks'] ?? '') }}"
                                            min="1"
                                            max="520"
                                            class="min-w-0 flex-1 rounded-l-xl border-0 px-3 py-2.5 text-sm font-semibold text-gray-900 focus:outline-none focus:ring-0"
                                            placeholder="80"
                                        >
                                        <span class="inline-flex items-center rounded-r-xl border-l border-gray-100 bg-gray-50 px-3 text-xs font-bold text-gray-500">minggu</span>
                                    </div>
                                </label>
                                <label class="block lg:col-span-3">
                                    <span class="mb-1 block text-xs font-bold text-gray-700">Mulai peringatan</span>
                                    <div class="flex rounded-xl border border-gray-200 bg-white focus-within:border-[var(--color-primary)]">
                                        <input
                                            type="number"
                                            name="afkir_warning_weeks"
                                            value="{{ old('afkir_warning_weeks', $afkirConfig['warning_weeks'] ?? 4) }}"
                                            min="0"
                                            max="52"
                                            class="min-w-0 flex-1 rounded-l-xl border-0 px-3 py-2.5 text-sm font-semibold text-gray-900 focus:outline-none focus:ring-0"
                                            placeholder="4"
                                        >
                                        <span class="inline-flex items-center rounded-r-xl border-l border-gray-100 bg-gray-50 px-3 text-xs font-bold text-gray-500">minggu sebelum target</span>
                                    </div>
                                </label>
                                <div class="rounded-xl border border-violet-100 bg-white px-3 py-2.5 lg:col-span-2">
                                    <span class="block text-xs font-bold text-gray-500">Status</span>
                                    <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ ($afkirConfig['is_configured'] ?? false) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ ($afkirConfig['is_configured'] ?? false) ? 'Sudah disimpan' : 'Default sistem' }}
                                    </span>
                                </div>
                            </div>

                            <p class="mt-3 rounded-xl border border-violet-100 bg-white px-3 py-2 text-xs leading-5 text-violet-700">
                                Contoh: ayam petelur biasanya memakai istilah afkir, sedangkan ayam potong atau lele lebih cocok memakai akhir siklus panen.
                            </p>
                        </section>

                        <section class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <h3 class="text-base font-bold text-gray-900">4. Catatan dan Simpan</h3>
                            <label class="mt-3 block">
                                <span class="mb-1.5 block text-sm font-semibold text-gray-700">Catatan konfigurasi</span>
                                <textarea name="notes" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none" placeholder="Contoh: parameter disesuaikan dengan sensor kandang batch pertama.">{{ old('notes', $selectedConfig->notes ?? '') }}</textarea>
                            </label>
                        </section>
                    </div>

                    <div class="sticky bottom-0 z-10 border-t border-gray-100 bg-white/95 p-4 backdrop-blur">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-5">
                                <div class="rounded-lg bg-gray-50 px-3 py-2">
                                    <span class="font-semibold text-gray-500">Lingkungan</span>
                                    <span class="ml-1 font-black text-gray-900" x-text="activeEnvironmentCount"></span>
                                </div>
                                <div class="rounded-lg bg-gray-50 px-3 py-2">
                                    <span class="font-semibold text-gray-500">Operasional</span>
                                    <span class="ml-1 font-black text-gray-900" x-text="operationalFunctions.length"></span>
                                </div>
                                <div class="rounded-lg bg-gray-50 px-3 py-2">
                                    <span class="font-semibold text-gray-500">Fuzzy Produktivitas</span>
                                    <span class="ml-1 font-black text-gray-900" x-text="selectedFunctions.length"></span>
                                </div>
                                <div class="rounded-lg px-3 py-2" :class="canSubmit ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                                    <span class="font-semibold" x-text="canSubmit ? 'Siap simpan' : 'Cek input'"></span>
                                </div>
                                <div class="rounded-lg px-3 py-2 {{ $schemaReady ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                    <span class="font-semibold">{{ $schemaReady ? 'DB siap' : 'Butuh migrate' }}</span>
                                </div>
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                                Simpan Konfigurasi
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-gray-500" x-show="!canSubmit">
                            Isi minimal satu parameter lingkungan dengan kode dan nama.
                        </p>
                    </div>
                </form>
            @else
                <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-12 text-center">
                    <p class="text-sm font-semibold text-gray-800">Belum ada data yang bisa dikonfigurasi</p>
                    <p class="mt-1 text-xs leading-5 text-gray-500">Jenis ternak akan muncul otomatis setelah dibuat dari mobile.</p>
                </div>
            @endif
        </section>
    </div>
    @elseif($activeTab === 'stock-categories')
        <section class="rounded-2xl border border-gray-100 bg-white p-5" style="box-shadow: var(--shadow-sm);">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-bold text-gray-900">Kategori Stok</h2>
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $stockCategorySource === 'shared' ? 'bg-indigo-50 text-indigo-700' : ($stockCategorySource === 'web' || $stockCategorySource === 'web-legacy' ? 'bg-emerald-50 text-emerald-700' : 'bg-sky-50 text-sky-700') }}">
                            {{ $stockCategorySource === 'shared' ? 'Master bersama mobile + web' : ($stockCategorySource === 'web' || $stockCategorySource === 'web-legacy' ? 'Dikonfigurasi di web' : ($stockCategorySource === 'mobile' ? 'Referensi mobile' : 'Default')) }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">
                        Kategori ini disimpan di master yang sama dengan mobile, lalu dipakai untuk katalog supplier, rekomendasi restock, dan filter inventori.
                    </p>
                </div>
                <button type="button" @click="openStockCategoryModal()" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">
                    Tambah Kategori
                </button>
            </div>

            <div class="mt-5 flex flex-col gap-3 border-t border-gray-100 pt-4 md:flex-row md:items-center md:justify-between">
                <label class="relative block w-full md:max-w-sm">
                    <span class="sr-only">Cari kategori stok</span>
                    <input
                        type="search"
                        x-model.debounce.150ms="stockCategorySearch"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none"
                        placeholder="Cari kategori atau deskripsi..."
                    >
                </label>
                <div class="text-xs font-semibold text-gray-500">
                    <span x-text="filteredStockCategories.length"></span>
                    <span>dari {{ $stockCategories->count() }} kategori</span>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-[11px] font-bold uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Kategori</th>
                            <th class="px-3 py-2">Deskripsi</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Sumber</th>
                            <th class="px-3 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="row in filteredStockCategories" :key="`category-${row.id || row.name}`">
                            <tr class="align-top">
                                <td class="px-3 py-3">
                                    <div class="font-bold text-gray-900" x-text="row.name"></div>
                                </td>
                                <td class="px-3 py-3 text-gray-600">
                                    <span x-text="row.description || '-'"></span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="row.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'" x-text="row.is_active ? 'Aktif' : 'Nonaktif'"></span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="sourceClass(row.source)" x-text="sourceLabel(row.source)"></span>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <template x-if="row.editable && row.update_url">
                                        <button type="button" @click="openStockCategoryModal('edit', row.update_url, row)" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-700 hover:border-emerald-200 hover:text-emerald-700">
                                            Ubah
                                        </button>
                                    </template>
                                    <template x-if="!row.editable || !row.update_url">
                                        <span class="text-xs font-semibold text-gray-400">Terkunci</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredStockCategories.length === 0" x-cloak>
                            <td colspan="5" class="px-3 py-8 text-center text-sm text-gray-500">
                                Tidak ada kategori yang sesuai dengan pencarian.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div x-show="stockCategoryModal.open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/45 p-4" @keydown.escape.window="closeStockCategoryModal()">
            <div class="absolute inset-0" @click="closeStockCategoryModal()"></div>
            <form method="POST" :action="stockCategoryModal.action" class="relative w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
                @csrf
                <input type="hidden" name="_method" value="PATCH" :disabled="stockCategoryModal.mode !== 'edit'">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900" x-text="stockCategoryModal.mode === 'edit' ? 'Ubah Kategori' : 'Tambah Kategori'"></h3>
                        <p class="mt-1 text-sm text-gray-500">Kategori disimpan di master bersama, sehingga pilihan mobile dan web tetap sama.</p>
                    </div>
                    <button type="button" @click="closeStockCategoryModal()" class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50" title="Tutup">
                        <span class="text-lg leading-none">&times;</span>
                    </button>
                </div>

                <div class="space-y-3">
                    <label class="block">
                        <span class="text-xs font-bold text-gray-700">Nama kategori *</span>
                        <input x-ref="stockCategoryName" name="name" x-model="stockCategoryModal.name" required class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none" placeholder="Contoh: Mineral">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold text-gray-700">Catatan</span>
                        <textarea name="description" rows="3" x-model="stockCategoryModal.description" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none" placeholder="Kegunaan kategori ini"></textarea>
                        <span class="mt-1 block text-[11px] text-gray-400">Pada master bersama, yang disimpan ke tabel utama adalah nama dan status aktif.</span>
                    </label>
                    <label x-show="stockCategoryModal.mode === 'edit'" x-cloak class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <input type="hidden" name="is_active" value="0" :disabled="stockCategoryModal.mode !== 'edit'">
                        <input type="checkbox" name="is_active" value="1" x-model="stockCategoryModal.is_active" :disabled="stockCategoryModal.mode !== 'edit'" class="rounded border-gray-300 text-emerald-600">
                        Aktif
                    </label>
                    <p x-show="stockCategoryModal.mode === 'create'" x-cloak class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                        Kategori baru otomatis aktif setelah disimpan.
                    </p>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" @click="closeStockCategoryModal()" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50">Batal</button>
                    <button class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700" x-text="stockCategoryModal.mode === 'edit' ? 'Simpan Perubahan' : 'Simpan Kategori'"></button>
                </div>
            </form>
        </div>
    @elseif($activeTab === 'product-units')
        <section class="rounded-2xl border border-gray-100 bg-white p-5" style="box-shadow: var(--shadow-sm);">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-bold text-gray-900">Satuan Produk</h2>
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $productUnitSource === 'shared' ? 'bg-indigo-50 text-indigo-700' : ($productUnitSource === 'web' || $productUnitSource === 'web-legacy' ? 'bg-emerald-50 text-emerald-700' : 'bg-sky-50 text-sky-700') }}">
                            {{ $productUnitSource === 'shared' ? 'Master bersama mobile + web' : ($productUnitSource === 'web' || $productUnitSource === 'web-legacy' ? 'Dikonfigurasi di web' : ($productUnitSource === 'mobile' ? 'Referensi mobile' : 'Default')) }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">
                        Satuan ini disimpan di master yang sama dengan mobile, lalu dipakai saat supplier membuat produk dan inventori menghubungkan barang ke supplier.
                    </p>
                </div>
                <button type="button" @click="openProductUnitModal()" class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-700">
                    Tambah Satuan
                </button>
            </div>

            <div class="mt-5 flex flex-col gap-3 border-t border-gray-100 pt-4 md:flex-row md:items-center md:justify-between">
                <label class="relative block w-full md:max-w-sm">
                    <span class="sr-only">Cari satuan produk</span>
                    <input
                        type="search"
                        x-model.debounce.150ms="productUnitSearch"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none"
                        placeholder="Cari simbol, nama, atau deskripsi..."
                    >
                </label>
                <div class="text-xs font-semibold text-gray-500">
                    <span x-text="filteredProductUnits.length"></span>
                    <span>dari {{ $productUnits->count() }} satuan</span>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-[11px] font-bold uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Simbol</th>
                            <th class="px-3 py-2">Nama Satuan</th>
                            <th class="px-3 py-2">Deskripsi</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Sumber</th>
                            <th class="px-3 py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="row in filteredProductUnits" :key="`unit-${row.id || row.symbol}`">
                            <tr class="align-top">
                                <td class="px-3 py-3">
                                    <span class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-black text-gray-900" x-text="row.symbol || '-'"></span>
                                </td>
                                <td class="px-3 py-3 font-bold text-gray-900" x-text="row.name"></td>
                                <td class="px-3 py-3 text-gray-600" x-text="row.description || '-'"></td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="row.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'" x-text="row.is_active ? 'Aktif' : 'Nonaktif'"></span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="sourceClass(row.source)" x-text="sourceLabel(row.source)"></span>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <template x-if="row.editable && row.update_url">
                                        <button type="button" @click="openProductUnitModal('edit', row.update_url, row)" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-700 hover:border-sky-200 hover:text-sky-700">
                                            Ubah
                                        </button>
                                    </template>
                                    <template x-if="!row.editable || !row.update_url">
                                        <span class="text-xs font-semibold text-gray-400">Terkunci</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredProductUnits.length === 0" x-cloak>
                            <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-500">
                                Tidak ada satuan yang sesuai dengan pencarian.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div x-show="productUnitModal.open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/45 p-4" @keydown.escape.window="closeProductUnitModal()">
            <div class="absolute inset-0" @click="closeProductUnitModal()"></div>
            <form method="POST" :action="productUnitModal.action" class="relative w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
                @csrf
                <input type="hidden" name="_method" value="PATCH" :disabled="productUnitModal.mode !== 'edit'">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900" x-text="productUnitModal.mode === 'edit' ? 'Ubah Satuan' : 'Tambah Satuan'"></h3>
                        <p class="mt-1 text-sm text-gray-500">Satuan disimpan di master bersama, sehingga pilihan mobile dan web tetap sama.</p>
                    </div>
                    <button type="button" @click="closeProductUnitModal()" class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50" title="Tutup">
                        <span class="text-lg leading-none">&times;</span>
                    </button>
                </div>

                <div class="space-y-3">
                    <label class="block">
                        <span class="text-xs font-bold text-gray-700">Simbol satuan *</span>
                        <input x-ref="productUnitSymbol" name="symbol" x-model="productUnitModal.symbol" required class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none" placeholder="Contoh: Dus">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold text-gray-700">Nama satuan *</span>
                        <input name="name" x-model="productUnitModal.name" required class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none" placeholder="Dus">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold text-gray-700">Catatan</span>
                        <textarea name="description" rows="3" x-model="productUnitModal.description" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none" placeholder="Keterangan satuan"></textarea>
                        <span class="mt-1 block text-[11px] text-gray-400">Pada master bersama, yang disimpan ke tabel utama adalah nama, simbol, dan status aktif.</span>
                    </label>
                    <label x-show="productUnitModal.mode === 'edit'" x-cloak class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <input type="hidden" name="is_active" value="0" :disabled="productUnitModal.mode !== 'edit'">
                        <input type="checkbox" name="is_active" value="1" x-model="productUnitModal.is_active" :disabled="productUnitModal.mode !== 'edit'" class="rounded border-gray-300 text-sky-600">
                        Aktif
                    </label>
                    <p x-show="productUnitModal.mode === 'create'" x-cloak class="rounded-lg bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-700">
                        Satuan baru otomatis aktif setelah disimpan.
                    </p>
                </div>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" @click="closeProductUnitModal()" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50">Batal</button>
                    <button class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-700" x-text="productUnitModal.mode === 'edit' ? 'Simpan Perubahan' : 'Simpan Satuan'"></button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function livestockMasterPage(initialEnvironmentRows, initialSelectedFunctions, initialOperationalFunctions, functionRows, stockCategoryRows, productUnitRows, formRoutes, sensorCatalogRows) {
    const presets = {
        TEMP: { parameter_code: 'TEMP', parameter_name: 'Suhu', unit: 'C', icon_key: 'sensor', min_value: 20, max_value: 28, fallback_value: 26, stale_minutes: 30, required_for_iot: true, required_for_fuzzy: true },
        HUMID: { parameter_code: 'HUMID', parameter_name: 'Kelembapan', unit: '%', icon_key: 'sensor', min_value: 50, max_value: 70, fallback_value: 65, stale_minutes: 30, required_for_iot: true, required_for_fuzzy: true },
        AMMON: { parameter_code: 'AMMON', parameter_name: 'Amonia', unit: 'ppm', icon_key: 'sensor', min_value: 0, max_value: 15, fallback_value: 5, stale_minutes: 30, required_for_iot: true, required_for_fuzzy: true },
    };

    const sensorCatalog = sensorCatalogRows || [];
    const sensorCatalogByCode = Object.fromEntries(sensorCatalog.map((row) => [String(row.parameterCode || '').toUpperCase(), row]));
    const withKeys = (rows) => rows.map((row, index) => ({ key: Date.now() + '-' + index, icon_key: 'sensor', ...row }));
    const blankRow = () => ({
        key: Date.now() + '-' + Math.random().toString(16).slice(2),
        parameter_code: '',
        parameter_name: '',
        unit: '',
        icon_key: 'sensor',
        min_value: null,
        max_value: null,
        fallback_value: null,
        stale_minutes: 30,
        required_for_iot: true,
        required_for_fuzzy: false,
    });
    const routes = formRoutes || {};
    const emptySensorParameterModal = () => ({
        open: false,
        mode: 'create',
        action: routes.sensorParameterStore || '',
        parameterCode: '',
        parameterName: '',
        unit: '',
        description: '',
    });
    const emptyStockCategoryModal = () => ({
        open: false,
        mode: 'create',
        action: routes.stockCategoryStore || '',
        name: '',
        description: '',
        is_active: true,
    });
    const emptyProductUnitModal = () => ({
        open: false,
        mode: 'create',
        action: routes.productUnitStore || '',
        symbol: '',
        name: '',
        description: '',
        is_active: true,
    });

    return {
        environmentRows: withKeys(initialEnvironmentRows.length ? initialEnvironmentRows : [blankRow()]),
        selectedFunctions: initialSelectedFunctions.length ? initialSelectedFunctions : [],
        operationalFunctions: initialOperationalFunctions.length ? initialOperationalFunctions : [],
        functionRows: functionRows || [],
        sensorCatalog,
        stockCategories: stockCategoryRows || [],
        productUnits: productUnitRows || [],
        stockCategorySearch: '',
        productUnitSearch: '',
        sensorParameterModal: emptySensorParameterModal(),
        stockCategoryModal: emptyStockCategoryModal(),
        productUnitModal: emptyProductUnitModal(),

        init() {
            this.environmentRows.forEach((row) => this.applySensorCatalog(row, true));
        },

        get activeEnvironmentCount() {
            return this.environmentRows.filter((row) => this.isEnvironmentRowValid(row)).length;
        },

        get canSubmit() {
            return this.activeEnvironmentCount > 0;
        },

        isProductivityFunctionSelected(id) {
            return this.selectedFunctions.includes(String(id));
        },

        isOperationalFunctionSelected(id) {
            return this.operationalFunctions.includes(String(id));
        },

        setOperationalFunction(id, checked) {
            const normalizedId = String(id);
            const withoutCurrent = this.operationalFunctions.filter((value) => String(value) !== normalizedId);
            this.operationalFunctions = checked ? [...withoutCurrent, normalizedId] : withoutCurrent;

            if (!checked) {
                this.selectedFunctions = this.selectedFunctions.filter((value) => String(value) !== normalizedId);
            }
        },

        setProductivityFunction(id, checked) {
            const normalizedId = String(id);
            const withoutCurrent = this.selectedFunctions.filter((value) => String(value) !== normalizedId);
            this.selectedFunctions = checked ? [...withoutCurrent, normalizedId] : withoutCurrent;

            if (checked && !this.isOperationalFunctionSelected(normalizedId)) {
                this.setOperationalFunction(normalizedId, true);
            }
        },

        selectAllOperationalFunctions() {
            this.operationalFunctions = this.functionRows
                .map((row) => String(row.id || ''))
                .filter((id) => id !== '');
        },

        clearProductivitySelections() {
            this.selectedFunctions = [];
            this.operationalFunctions = [];
        },

        get filteredStockCategories() {
            const term = this.normalizedTerm(this.stockCategorySearch);

            if (!term) {
                return this.stockCategories;
            }

            return this.stockCategories.filter((row) => this.matchesTerm(term, [
                row.name,
                row.description,
                row.source,
                row.is_active ? 'aktif' : 'nonaktif',
            ]));
        },

        get filteredProductUnits() {
            const term = this.normalizedTerm(this.productUnitSearch);

            if (!term) {
                return this.productUnits;
            }

            return this.productUnits.filter((row) => this.matchesTerm(term, [
                row.symbol,
                row.name,
                row.description,
                row.source,
                row.is_active ? 'aktif' : 'nonaktif',
            ]));
        },

        isEnvironmentRowValid(row) {
            return String(row.parameter_code || '').trim() !== '' && String(row.parameter_name || '').trim() !== '';
        },

        normalizedTerm(value) {
            return String(value || '').trim().toLowerCase();
        },

        matchesTerm(term, values) {
            return values.some((value) => String(value || '').toLowerCase().includes(term));
        },

        normalizeSensorCode(value) {
            return String(value || '')
                .trim()
                .toUpperCase()
                .replace(/[^A-Z0-9_]+/g, '_')
                .replace(/_+/g, '_')
                .replace(/^_+|_+$/g, '');
        },

        applySensorCatalog(row, keepExisting = false) {
            const code = this.normalizeSensorCode(row.parameter_code);
            row.parameter_code = code;
            const sensor = sensorCatalogByCode[code];

            if (sensor) {
                row.parameter_name = sensor.parameterName || '';
                row.unit = sensor.unit || '';
                return;
            }

            if (!keepExisting) {
                row.parameter_name = '';
                row.unit = '';
            }
        },

        sourceLabel(source) {
            if (source === 'shared') return 'Master bersama';
            if (source === 'mobile-sync') return 'Sinkron mobile';
            if (source === 'mobile') return 'Mobile';
            if (source === 'default') return 'Default';
            if (source === 'web-legacy') return 'Web lama';

            return 'Web';
        },

        sourceClass(source) {
            if (source === 'shared') return 'bg-indigo-50 text-indigo-700';
            if (source === 'mobile-sync') return 'bg-indigo-50 text-indigo-700';
            if (source === 'web') return 'bg-emerald-50 text-emerald-700';
            if (source === 'web-legacy') return 'bg-amber-50 text-amber-700';
            if (source === 'default') return 'bg-gray-100 text-gray-500';

            return 'bg-sky-50 text-sky-700';
        },

        addPreset(code) {
            const preset = presets[code];
            if (!preset) return;

            const existingIndex = this.environmentRows.findIndex((row) => String(row.parameter_code || '').toUpperCase() === preset.parameter_code);
            if (existingIndex >= 0) {
                this.environmentRows[existingIndex] = { ...this.environmentRows[existingIndex], ...preset };
                return;
            }

            this.environmentRows.push({ key: Date.now() + '-' + code, ...preset });
        },

        addEnvironmentRow() {
            this.environmentRows.push(blankRow());
        },

        removeEnvironmentRow(index) {
            if (this.environmentRows.length <= 1) {
                this.environmentRows = [blankRow()];
                return;
            }

            this.environmentRows.splice(index, 1);
        },

        openSensorParameterModal(mode = 'create', action = routes.sensorParameterStore || '', row = null) {
            this.sensorParameterModal = {
                open: true,
                mode,
                action,
                parameterCode: row ? row.parameterCode || '' : '',
                parameterName: row ? row.parameterName || '' : '',
                unit: row ? row.unit || '' : '',
                description: row ? row.description || '' : '',
            };

            this.$nextTick(() => {
                if (this.$refs.sensorParameterCode) {
                    this.$refs.sensorParameterCode.focus();
                }
            });
        },

        closeSensorParameterModal() {
            this.sensorParameterModal = emptySensorParameterModal();
        },

        openStockCategoryModal(mode = 'create', action = routes.stockCategoryStore || '', row = null) {
            this.stockCategoryModal = {
                open: true,
                mode,
                action,
                name: row ? row.name || '' : '',
                description: row ? row.description || '' : '',
                is_active: row ? Boolean(row.is_active) : true,
            };

            this.$nextTick(() => {
                if (this.$refs.stockCategoryName) {
                    this.$refs.stockCategoryName.focus();
                }
            });
        },

        closeStockCategoryModal() {
            this.stockCategoryModal = emptyStockCategoryModal();
        },

        openProductUnitModal(mode = 'create', action = routes.productUnitStore || '', row = null) {
            this.productUnitModal = {
                open: true,
                mode,
                action,
                symbol: row ? row.symbol || '' : '',
                name: row ? row.name || '' : '',
                description: row ? row.description || '' : '',
                is_active: row ? Boolean(row.is_active) : true,
            };

            this.$nextTick(() => {
                if (this.$refs.productUnitSymbol) {
                    this.$refs.productUnitSymbol.focus();
                }
            });
        },

        closeProductUnitModal() {
            this.productUnitModal = emptyProductUnitModal();
        },
    };
}
</script>
@endpush
