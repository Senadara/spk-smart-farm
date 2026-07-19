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
    $schemaReady = $masterOverview['schemaReady'];
    $readyCount = $types->filter(fn ($type) => $type['readiness']['configured'] ?? false)->count();
    $pendingCount = max($types->count() - $readyCount, 0);
    $selectedReadiness = $selectedType['readiness'] ?? null;
    $primaryCommodityId = $selectedType['primary_commodity_id'] ?? $selectedConfig?->commodity_id;

    $environmentRowsForJs = $environmentRows->map(fn ($row) => [
        'parameter_code' => $row->parameter_code ?? '',
        'parameter_name' => $row->parameter_name ?? '',
        'unit' => $row->unit ?? '',
        'min_value' => $row->min_value ?? null,
        'max_value' => $row->max_value ?? null,
        'fallback_value' => $row->fallback_value ?? null,
        'stale_minutes' => $row->stale_minutes ?? 30,
        'required_for_iot' => (bool) ($row->required_for_iot ?? true),
        'required_for_fuzzy' => (bool) ($row->required_for_fuzzy ?? false),
    ])->values();
@endphp

<div x-data="livestockMasterPage(@js($environmentRowsForJs), @js($selectedFunctionIds))" class="space-y-5">
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white" style="box-shadow: var(--shadow-sm);">
        <div class="h-1 bg-[var(--color-primary)]"></div>
        <div class="flex flex-col gap-4 p-5 xl:flex-row xl:items-center xl:justify-between">
            <div class="min-w-0">
                <div class="mb-1 text-sm text-gray-500">
                    <span class="font-medium text-gray-700">Data Master</span>
                    <span class="mx-1 text-gray-300">/</span>
                    <span>Ternak</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Konfigurasi Data Master Ternak</h1>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                    Jenis ternak dan kandang tetap dibuat dari mobile. Web menentukan parameter lingkungan dan fungsi produktivitas yang wajib tersedia sebelum IoT dan Fuzzy SPK dikonfigurasi.
                </p>
            </div>
            <div class="grid grid-cols-3 gap-2 sm:min-w-[360px]">
                <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 text-center">
                    <div class="text-xl font-black text-gray-900">{{ $types->count() }}</div>
                    <div class="text-[11px] font-semibold text-gray-500">Jenis Ternak</div>
                </div>
                <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2 text-center">
                    <div class="text-xl font-black text-emerald-700">{{ $readyCount }}</div>
                    <div class="text-[11px] font-semibold text-emerald-700">Siap</div>
                </div>
                <div class="rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-center">
                    <div class="text-xl font-black text-amber-700">{{ $pendingCount }}</div>
                    <div class="text-[11px] font-semibold text-amber-700">Perlu Setup</div>
                </div>
            </div>
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
            Tabel Data Master ternak belum tersedia. Jalankan migration terbaru sebelum menyimpan konfigurasi.
        </div>
    @endif

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[360px_minmax(0,1fr)]">
        <aside class="rounded-2xl border border-gray-100 bg-white p-4" style="box-shadow: var(--shadow-sm);">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Jenis Ternak dari Mobile</h2>
                    <p class="text-xs text-gray-500">Pilih jenis yang perlu dikonfigurasi.</p>
                </div>
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-600">{{ $types->count() }}</span>
            </div>

            <div class="space-y-2">
                @forelse($types as $type)
                    @php
                        $ready = $type['readiness']['configured'] ?? false;
                        $isSelected = ($selectedType['id'] ?? null) === $type['id'];
                    @endphp
                    <a href="{{ route('data-master.index', ['jenis_budidaya_id' => $type['id']]) }}"
                        class="block rounded-xl border px-3 py-3 transition-all {{ $isSelected ? 'border-emerald-300 bg-emerald-50 shadow-sm' : 'border-gray-100 bg-white hover:border-emerald-200 hover:bg-emerald-50/50' }}"
                        style="text-decoration:none;">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold {{ $isSelected ? 'text-emerald-900' : 'text-gray-900' }}">{{ $type['nama'] }}</div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $type['coop_count'] }} kandang
                                    <span class="mx-1 text-gray-300">/</span>
                                    {{ $type['commodity_count'] }} komoditas
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $ready ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $ready ? 'Siap' : 'Setup' }}
                            </span>
                        </div>
                        @if(!empty($type['commodities']))
                            <p class="mt-2 truncate text-xs text-gray-400">{{ implode(', ', $type['commodities']) }}</p>
                        @endif
                    </a>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center">
                        <p class="text-sm font-semibold text-gray-800">Belum ada jenis ternak</p>
                        <p class="mt-1 text-xs leading-5 text-gray-500">Buat jenis ternak dan kandang dari aplikasi mobile terlebih dahulu.</p>
                    </div>
                @endforelse
            </div>
        </aside>

        <section class="rounded-2xl border border-gray-100 bg-white p-5" style="box-shadow: var(--shadow-sm);">
            @if($selectedType)
                <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold text-gray-900">{{ $selectedType['nama'] }}</h2>
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ ($selectedReadiness['configured'] ?? false) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ ($selectedReadiness['configured'] ?? false) ? 'Terhubung' : 'Belum siap' }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">{{ $selectedReadiness['message'] ?? 'Lengkapi konfigurasi Data Master.' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('iot.devices') }}" class="inline-flex items-center justify-center rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-700 hover:bg-sky-100" style="text-decoration:none;">IoT Device</a>
                        <a href="{{ route('settings.fuzzy.index') }}" class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100" style="text-decoration:none;">Fuzzy SPK</a>
                    </div>
                </div>

                @if(!empty($selectedReadiness['hints']))
                    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <div class="font-semibold">Yang masih perlu dilengkapi</div>
                        <ul class="mt-1 list-inside list-disc space-y-0.5">
                            @foreach($selectedReadiness['hints'] as $hint)
                                <li>{{ $hint }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('data-master.livestock.store') }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="jenis_budidaya_id" value="{{ $selectedType['id'] }}">
                    <input type="hidden" name="commodity_id" value="{{ $primaryCommodityId }}">

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3">
                            <div class="text-xs font-semibold text-emerald-700">Parameter Lingkungan</div>
                            <div class="mt-1 text-2xl font-black text-emerald-800" x-text="activeEnvironmentCount"></div>
                        </div>
                        <div class="rounded-xl border border-sky-100 bg-sky-50 px-4 py-3">
                            <div class="text-xs font-semibold text-sky-700">Fungsi Produktivitas</div>
                            <div class="mt-1 text-2xl font-black text-sky-800" x-text="selectedFunctions.length"></div>
                        </div>
                        <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                            <div class="text-xs font-semibold text-gray-500">Kandang Mobile</div>
                            <div class="mt-1 text-2xl font-black text-gray-900">{{ $selectedType['coop_count'] }}</div>
                        </div>
                    </div>

                    <div>
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-base font-bold text-gray-900">Parameter Lingkungan / IoT</h3>
                                <p class="text-xs text-gray-500">Bisa ditambah sesuai sensor yang tersedia untuk jenis ternak ini.</p>
                            </div>
                            <button type="button" @click="addEnvironmentRow()" class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                                Tambah Parameter
                            </button>
                        </div>

                        <div class="space-y-3">
                            <template x-for="(row, index) in environmentRows" :key="row.key">
                                <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-3">
                                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-[1fr_1.25fr_0.7fr_0.7fr_0.7fr_0.7fr_auto]">
                                        <div>
                                            <label class="mb-1 block text-[11px] font-semibold uppercase text-gray-500">Kode</label>
                                            <input type="text" :name="`environment_parameters[${index}][parameter_code]`" x-model="row.parameter_code" placeholder="TEMP" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-[11px] font-semibold uppercase text-gray-500">Nama</label>
                                            <input type="text" :name="`environment_parameters[${index}][parameter_name]`" x-model="row.parameter_name" placeholder="Suhu kandang" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-[11px] font-semibold uppercase text-gray-500">Unit</label>
                                            <input type="text" :name="`environment_parameters[${index}][unit]`" x-model="row.unit" placeholder="C" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-[11px] font-semibold uppercase text-gray-500">Min</label>
                                            <input type="number" step="0.01" :name="`environment_parameters[${index}][min_value]`" x-model="row.min_value" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-[11px] font-semibold uppercase text-gray-500">Max</label>
                                            <input type="number" step="0.01" :name="`environment_parameters[${index}][max_value]`" x-model="row.max_value" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-[11px] font-semibold uppercase text-gray-500">Fallback</label>
                                            <input type="number" step="0.01" :name="`environment_parameters[${index}][fallback_value]`" x-model="row.fallback_value" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[var(--color-primary)] focus:outline-none">
                                        </div>
                                        <div class="flex items-end justify-end">
                                            <button type="button" @click="removeEnvironmentRow(index)" class="flex h-9 w-9 items-center justify-center rounded-lg border border-red-100 bg-white text-red-500 hover:bg-red-50" title="Hapus parameter">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-600">
                                        <label class="inline-flex items-center gap-2">
                                            <input type="hidden" :name="`environment_parameters[${index}][required_for_iot]`" value="0">
                                            <input type="checkbox" :name="`environment_parameters[${index}][required_for_iot]`" value="1" x-model="row.required_for_iot" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                            Dipakai IoT
                                        </label>
                                        <label class="inline-flex items-center gap-2">
                                            <input type="hidden" :name="`environment_parameters[${index}][required_for_fuzzy]`" value="0">
                                            <input type="checkbox" :name="`environment_parameters[${index}][required_for_fuzzy]`" value="1" x-model="row.required_for_fuzzy" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                            Disarankan untuk Fuzzy
                                        </label>
                                        <label class="inline-flex items-center gap-2">
                                            <span>Batas segar</span>
                                            <input type="number" min="1" max="10080" :name="`environment_parameters[${index}][stale_minutes]`" x-model="row.stale_minutes" class="w-20 rounded-lg border border-gray-200 px-2 py-1 text-xs">
                                            <span>menit</span>
                                        </label>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div>
                        <div class="mb-3">
                            <h3 class="text-base font-bold text-gray-900">Fungsi Produktivitas Tetap</h3>
                            <p class="text-xs text-gray-500">Formula seperti HDP/FCR bersifat tetap karena mengikuti tabel laporan mobile.</p>
                        </div>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            @foreach($productivityFunctions as $function)
                                <label class="block cursor-pointer rounded-xl border border-gray-100 bg-white p-4 transition hover:border-sky-200 hover:bg-sky-50/50">
                                    <div class="flex items-start gap-3">
                                        <input type="checkbox" name="productivity_function_ids[]" value="{{ $function->id }}" x-model="selectedFunctions" class="mt-1 rounded border-gray-300 text-sky-600 focus:ring-sky-500">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-bold text-gray-900">{{ $function->name }}</span>
                                                @if($function->output_unit)
                                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500">{{ $function->output_unit }}</span>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-xs leading-5 text-gray-500">{{ $function->description }}</p>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Catatan konfigurasi</label>
                        <textarea name="notes" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none" placeholder="Contoh: parameter disesuaikan dengan sensor kandang batch pertama.">{{ old('notes', $selectedConfig->notes ?? '') }}</textarea>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs leading-5 text-gray-500">
                            Setelah tersimpan, parameter lingkungan akan tersedia di mapping IoT dan sumber data Fuzzy.
                        </p>
                        <button type="submit" @if(! $schemaReady) disabled @endif class="inline-flex items-center justify-center rounded-lg px-5 py-2.5 text-sm font-semibold text-white {{ $schemaReady ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-gray-300 cursor-not-allowed' }}">
                            Simpan Konfigurasi
                        </button>
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
</div>
@endsection

@push('scripts')
<script>
function livestockMasterPage(initialEnvironmentRows, initialSelectedFunctions) {
    const withKeys = (rows) => rows.map((row, index) => ({ key: Date.now() + '-' + index, ...row }));

    return {
        environmentRows: withKeys(initialEnvironmentRows.length ? initialEnvironmentRows : []),
        selectedFunctions: initialSelectedFunctions ?? [],

        get activeEnvironmentCount() {
            return this.environmentRows.filter((row) => String(row.parameter_code || '').trim() && String(row.parameter_name || '').trim()).length;
        },

        addEnvironmentRow() {
            this.environmentRows.push({
                key: Date.now() + '-' + Math.random().toString(16).slice(2),
                parameter_code: '',
                parameter_name: '',
                unit: '',
                min_value: null,
                max_value: null,
                fallback_value: null,
                stale_minutes: 30,
                required_for_iot: true,
                required_for_fuzzy: false,
            });
        },

        removeEnvironmentRow(index) {
            if (this.environmentRows.length <= 1) {
                this.environmentRows = [{
                    key: Date.now(),
                    parameter_code: '',
                    parameter_name: '',
                    unit: '',
                    min_value: null,
                    max_value: null,
                    fallback_value: null,
                    stale_minutes: 30,
                    required_for_iot: true,
                    required_for_fuzzy: false,
                }];
                return;
            }

            this.environmentRows.splice(index, 1);
        },
    };
}
</script>
@endpush
