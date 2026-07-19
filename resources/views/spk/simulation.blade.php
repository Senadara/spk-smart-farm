@extends('layouts.app')

@section('title', 'Simulasi SPK')
@section('breadcrumb', 'Simulasi SPK')

@push('styles')
    <style>
        .spk-simulation-page,
        .spk-simulation-page * {
            min-width: 0;
        }

        .spk-simulation-page {
            overflow-wrap: anywhere;
        }

        .spk-json-preview {
            white-space: pre-wrap;
            word-break: break-word;
        }

        @media (max-width: 639px) {
            .spk-action-bar {
                position: sticky;
                bottom: -1rem;
                margin-inline: -1rem;
                padding: .75rem 1rem calc(.75rem + env(safe-area-inset-bottom));
                background: rgba(248, 250, 252, .96);
                border-top: 1px solid #e2e8f0;
                backdrop-filter: blur(12px);
                z-index: 20;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $groupLabels = [
            'lingkungan' => 'Lingkungan',
            'kesehatan' => 'Produktivitas',
        ];

        $groupDescriptions = [
            'lingkungan' => 'Sensor kandang dan kualitas udara.',
            'kesehatan' => 'Laporan produksi dan konsumsi pakan.',
        ];

        $statusClass = function (?string $label) {
            $label = strtolower((string) $label);

            return match (true) {
                str_contains($label, 'optimal') => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                str_contains($label, 'baik') => 'bg-sky-50 text-sky-800 border-sky-200',
                str_contains($label, 'waspada') => 'bg-amber-50 text-amber-800 border-amber-200',
                str_contains($label, 'buruk'), str_contains($label, 'kritis') => 'bg-red-50 text-red-800 border-red-200',
                default => 'bg-slate-50 text-slate-800 border-slate-200',
            };
        };

        $statusBarClass = function (?string $label) {
            $label = strtolower((string) $label);

            return match (true) {
                str_contains($label, 'optimal') => 'bg-emerald-500',
                str_contains($label, 'baik') => 'bg-sky-500',
                str_contains($label, 'waspada') => 'bg-amber-500',
                str_contains($label, 'buruk'), str_contains($label, 'kritis') => 'bg-red-500',
                default => 'bg-slate-400',
            };
        };

        $formatRule = function (?array $rule) {
            if (!$rule) {
                return 'Tidak ada rule aktif';
            }

            $conditions = collect($rule['conditions'] ?? [])
                ->map(fn ($condition) => ($condition['variable_name'] ?? '-') . ' = ' . ($condition['set_name'] ?? '-'))
                ->implode(' ' . ($rule['operator'] ?? 'AND') . ' ');

            return trim(($rule['name'] ?? 'Rule') . ': IF ' . $conditions . ' THEN ' . ($rule['output_set_name'] ?? '-'));
        };

        $notificationDefaults = $notificationDefaults ?? [
            'title' => 'Uji Notifikasi SPK',
            'body' => 'Ini notifikasi uji dari simulasi SPK Laravel. Nilai input saat ini akan dihitung sebelum dikirim.',
        ];
        $notificationTargetRoles = $notificationTargetRoles ?? [
            'pjawab' => 'Penanggung Jawab',
            'owner' => 'Owner',
            'petugas' => 'Petugas',
            'admin' => 'Admin',
        ];
        $canSendNotificationTest = in_array(strtolower((string) session('user.role')), ['pjawab', 'owner', 'admin'], true);
        $variableCount = $variables->count();
    @endphp

    <div class="spk-simulation-page mx-auto w-full max-w-[1500px] space-y-4 pb-6">
        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(260px,360px)] lg:items-start">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
                            Manual testing
                        </span>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                            Mamdani fuzzy
                        </span>
                    </div>
                    <h1 class="mt-3 text-xl font-black text-slate-950 sm:text-2xl">Simulasi SPK Fuzzy</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Uji input, rule dominan, diagnosis, rekomendasi, dan notifikasi mobile dari konfigurasi fuzzy aktif.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-1">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold text-slate-500">Profil</p>
                        <p class="mt-1 truncate text-sm font-black text-slate-900" title="{{ $profile?->name ?? 'Belum ada' }}">
                            {{ $profile?->name ?? 'Belum ada' }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold text-slate-500">Variabel</p>
                        <p class="mt-1 text-sm font-black text-slate-900">{{ $variableCount }} input</p>
                    </div>
                    <div class="col-span-2 rounded-lg border border-slate-200 bg-slate-50 p-3 sm:col-span-1">
                        <p class="text-xs font-semibold text-slate-500">Data</p>
                        <p class="mt-1 text-sm font-black text-slate-900">Simulasi manual</p>
                    </div>
                </div>
            </div>
        </section>

        @if($notificationResult ?? null)
            @php
                $notificationSuccess = (bool) ($notificationResult['success'] ?? false);
            @endphp
            <section class="rounded-lg border p-4 shadow-sm {{ $notificationSuccess ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900' }}">
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(280px,420px)] lg:items-start">
                    <div>
                        <p class="text-xs font-bold uppercase">
                            {{ $notificationSuccess ? 'Notifikasi uji berhasil' : 'Notifikasi uji gagal' }}
                        </p>
                        <p class="mt-1 text-sm font-semibold leading-6">{{ $notificationResult['message'] ?? '-' }}</p>
                        <p class="mt-1 text-xs opacity-75">
                            Target: {{ $notificationTargetRoles[$notificationResult['target_role'] ?? ''] ?? ($notificationResult['target_role'] ?? '-') }}
                        </p>
                    </div>
                    <details class="rounded-lg border border-white/70 bg-white/75 p-3 text-xs">
                        <summary class="cursor-pointer font-bold">Detail respons gateway</summary>
                        <pre class="spk-json-preview mt-2 max-h-52 overflow-auto rounded-md bg-white p-3 text-[11px] leading-5 text-slate-700">{{ json_encode($notificationResult['response'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                </div>
            </section>
        @endif

        @if($profiles->isEmpty() || !$profile)
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
                Belum ada profil fuzzy yang tersedia. Tambahkan konfigurasi pada menu Pengaturan SPK terlebih dahulu.
            </section>
        @else
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(340px,460px)_minmax(0,1fr)]">
                <form method="POST" action="{{ route('spk.simulation.run') }}" id="spkSimulationForm" class="space-y-4">
                    @csrf

                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <label for="profile_id" class="text-xs font-bold uppercase text-slate-500">Profil Fuzzy</label>
                        <select
                            id="profile_id"
                            name="profile_id"
                            class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                            onchange="window.location='{{ route('spk.simulation.index') }}?profile_id=' + this.value"
                        >
                            @foreach($profiles as $profileOption)
                                <option value="{{ $profileOption->id }}" @selected($profileOption->id === $profile?->id)>
                                    {{ $profileOption->name }}{{ $profileOption->commodity ? ' - ' . $profileOption->commodity->nama : '' }}
                                </option>
                            @endforeach
                        </select>

                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase text-slate-500">Preset Skenario</p>
                                    <p class="text-xs text-slate-500">Isi cepat untuk beberapa kondisi uji.</p>
                                </div>
                            </div>
                            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <button type="button" data-preset="optimal" class="preset-btn rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-left text-xs font-bold text-emerald-800 transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-100">Kondisi optimal</button>
                                <button type="button" data-preset="environment_bad" class="preset-btn rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-left text-xs font-bold text-red-800 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-100">Lingkungan buruk</button>
                                <button type="button" data-preset="productivity_bad" class="preset-btn rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-left text-xs font-bold text-amber-800 transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-100">Produktivitas buruk</button>
                                <button type="button" data-preset="critical" class="preset-btn rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-left text-xs font-bold text-slate-800 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-100">Kondisi kritis</button>
                                <button type="button" data-preset="feed_excess" class="preset-btn rounded-lg border border-sky-200 bg-sky-50 px-3 py-2.5 text-left text-xs font-bold text-sky-800 transition hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-100 sm:col-span-2">Pakan berlebih</button>
                            </div>
                        </div>
                    </section>

                    @foreach($groupedVariables as $group => $items)
                        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                            <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="text-sm font-black text-slate-900">{{ $groupLabels[$group] ?? ucfirst($group) }}</h2>
                                    <p class="text-xs leading-5 text-slate-500">{{ $groupDescriptions[$group] ?? 'Input manual.' }}</p>
                                </div>
                                <span class="w-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                    {{ $items->count() }} variabel
                                </span>
                            </div>

                            @if($items->isEmpty())
                                <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                    Belum ada variabel input pada grup ini.
                                </div>
                            @else
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                    @foreach($items as $variable)
                                        @php
                                            $value = old("input_values.{$variable->id}", $inputValues[$variable->id] ?? '');
                                            $minPoint = $variable->sets->flatMap(fn ($set) => collect([$set->a, $set->b, $set->c, $set->d])->filter(fn ($point) => $point !== null))->min();
                                            $maxPoint = $variable->sets->flatMap(fn ($set) => collect([$set->a, $set->b, $set->c, $set->d])->filter(fn ($point) => $point !== null))->max();
                                        @endphp
                                        <label class="block">
                                            <span class="flex items-center justify-between gap-2 text-xs font-semibold text-slate-600">
                                                <span class="truncate" title="{{ ucfirst($variable->name) }}">{{ ucfirst($variable->name) }}</span>
                                                @if($variable->unit)
                                                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">{{ $variable->unit }}</span>
                                                @endif
                                            </span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                name="input_values[{{ $variable->id }}]"
                                                value="{{ $value }}"
                                                data-variable-name="{{ $variable->name }}"
                                                class="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-900 shadow-sm outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100"
                                                required
                                            >
                                            <span class="mt-1 block text-[11px] leading-4 text-slate-500">
                                                Rentang: {{ $minPoint ?? '-' }} sampai {{ $maxPoint ?? '-' }}
                                            </span>
                                            @error("input_values.{$variable->id}")
                                                <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                                            @enderror
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    @endforeach

                    @if($canSendNotificationTest)
                        <section class="rounded-lg border border-sky-200 bg-white p-4 shadow-sm sm:p-5">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase text-sky-700">Uji Notifikasi Mobile</p>
                                    <h2 class="mt-1 text-sm font-black text-slate-900">Kirim hasil simulasi</h2>
                                </div>
                                <span class="w-fit rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[11px] font-bold text-sky-700">
                                    Tidak menyimpan laporan
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-3">
                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-slate-500">Target Role</span>
                                    <select
                                        name="target_role"
                                        class="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                    >
                                        @foreach($notificationTargetRoles as $roleValue => $roleLabel)
                                            <option value="{{ $roleValue }}" @selected(old('target_role', 'pjawab') === $roleValue)>{{ $roleLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error('target_role')
                                        <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-slate-500">Judul Notifikasi</span>
                                    <input
                                        type="text"
                                        name="notification_title"
                                        value="{{ old('notification_title', $notificationDefaults['title'] ?? 'Uji Notifikasi SPK') }}"
                                        maxlength="120"
                                        class="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 shadow-sm outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                    >
                                    @error('notification_title')
                                        <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-slate-500">Isi Pesan</span>
                                    <textarea
                                        name="notification_body"
                                        rows="3"
                                        maxlength="500"
                                        class="mt-1.5 w-full resize-y rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm leading-6 text-slate-800 shadow-sm outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                    >{{ old('notification_body', $notificationDefaults['body'] ?? '') }}</textarea>
                                    @error('notification_body')
                                        <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                                    @enderror
                                </label>
                            </div>

                            <button
                                type="submit"
                                formaction="{{ route('spk.simulation.notification') }}"
                                formmethod="POST"
                                class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-200"
                            >
                                Kirim Notifikasi Uji
                            </button>
                        </section>
                    @endif

                    <div class="spk-action-bar flex flex-col gap-2 sm:flex-row">
                        <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-lg bg-emerald-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                            Jalankan Simulasi SPK
                        </button>
                        <a href="{{ route('spk.dashboard') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-200">
                            Kembali
                        </a>
                    </div>
                </form>

                <section class="min-w-0">
                    @if($result)
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                                @foreach([
                                    'lingkungan' => 'Lingkungan',
                                    'kesehatan' => 'Produktivitas',
                                    'kausalitas' => 'Kausalitas',
                                ] as $key => $label)
                                    @php
                                        $currentLabel = $result[$key]['label'] ?? '-';
                                        $score = $key === 'kausalitas'
                                            ? ($result[$key]['matched_rule'] ?? 'Rule lookup')
                                            : 'Skor: ' . number_format((float) ($result[$key]['value'] ?? 0), 2);
                                    @endphp
                                    <article class="relative overflow-hidden rounded-lg border p-4 shadow-sm {{ $statusClass($currentLabel) }} {{ $key === 'kausalitas' ? 'sm:col-span-2 2xl:col-span-1' : '' }}">
                                        <div class="absolute inset-y-0 left-0 w-1 {{ $statusBarClass($currentLabel) }}"></div>
                                        <p class="pl-2 text-xs font-bold uppercase opacity-75">{{ $label }}</p>
                                        <p class="mt-2 pl-2 text-xl font-black leading-tight text-balance sm:text-2xl">{{ $currentLabel }}</p>
                                        <p class="mt-2 pl-2 text-xs leading-5 opacity-80">{{ $score }}</p>
                                    </article>
                                @endforeach
                            </div>

                            <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                    <div>
                                        <h2 class="text-sm font-black text-slate-900">Diagnosis dan Rekomendasi</h2>
                                        <p class="text-xs text-slate-500">Ringkasan keputusan dari hasil defuzzifikasi.</p>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                        <p class="text-xs font-bold uppercase text-slate-500">Diagnosis</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-800">{{ $result['kausalitas']['diagnosis'] ?? $result['kausalitas']['label'] ?? '-' }}</p>
                                    </article>
                                    <article class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                                        <p class="text-xs font-bold uppercase text-emerald-700">Rekomendasi</p>
                                        <p class="mt-2 text-sm leading-6 text-emerald-900">{{ $result['kausalitas']['recommendation'] ?? '-' }}</p>
                                    </article>
                                </div>

                                @if($narrative)
                                    <div class="mt-3 border-t border-slate-100 pt-3">
                                        <p class="text-xs font-bold uppercase text-slate-500">Narasi Sistem</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-700">{{ $narrative }}</p>
                                    </div>
                                @endif
                            </section>

                            <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                                <h2 class="text-sm font-black text-slate-900">Rule Dominan</h2>
                                <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                                    <article class="rounded-lg border border-slate-200 p-4">
                                        <p class="text-xs font-bold uppercase text-slate-500">Rule Lingkungan</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-800">{{ $formatRule($result['lingkungan']['dominant_rule'] ?? null) }}</p>
                                        <p class="mt-2 text-xs text-slate-500">Alpha: {{ number_format((float) (($result['lingkungan']['dominant_rule']['alpha'] ?? 0)), 3) }}</p>
                                    </article>
                                    <article class="rounded-lg border border-slate-200 p-4">
                                        <p class="text-xs font-bold uppercase text-slate-500">Rule Produktivitas</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-800">{{ $formatRule($result['kesehatan']['dominant_rule'] ?? null) }}</p>
                                        <p class="mt-2 text-xs text-slate-500">Alpha: {{ number_format((float) (($result['kesehatan']['dominant_rule']['alpha'] ?? 0)), 3) }}</p>
                                    </article>
                                </div>
                            </section>

                            <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                    <div>
                                        <h2 class="text-sm font-black text-slate-900">Derajat Membership Input</h2>
                                        <p class="text-xs text-slate-500">Nilai keanggotaan untuk setiap fuzzy set.</p>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-4 2xl:grid-cols-2">
                                    @foreach(['lingkungan' => 'Lingkungan', 'kesehatan' => 'Produktivitas'] as $group => $label)
                                        <div>
                                            <h3 class="mb-2 text-xs font-bold uppercase text-slate-500">{{ $label }}</h3>
                                            <div class="space-y-3">
                                                @forelse(($result[$group]['fuzzified'] ?? []) as $variableName => $sets)
                                                    <article class="rounded-lg border border-slate-200 p-3">
                                                        <p class="text-sm font-bold text-slate-900">{{ ucfirst($variableName) }}</p>
                                                        <div class="mt-3 space-y-2">
                                                            @foreach($sets as $setName => $degree)
                                                                <div class="grid grid-cols-1 gap-1 sm:grid-cols-[minmax(0,1fr)_minmax(116px,150px)] sm:items-center sm:gap-3">
                                                                    <span class="truncate text-xs font-medium text-slate-600" title="{{ $setName }}">{{ $setName }}</span>
                                                                    <div class="flex items-center gap-2">
                                                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                                                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ max(0, min(100, (float) $degree * 100)) }}%"></div>
                                                                        </div>
                                                                        <span class="w-11 text-right text-xs font-bold text-slate-800">{{ number_format((float) $degree, 3) }}</span>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </article>
                                                @empty
                                                    <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">Tidak ada hasil fuzzifikasi.</p>
                                                @endforelse
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        </div>
                    @else
                        <section class="flex min-h-[320px] items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center shadow-sm sm:p-8">
                            <div class="max-w-md">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 17v-5a2 2 0 012-2h4a2 2 0 012 2v5m-8 0h8m-10 4h12a2 2 0 002-2V7a2 2 0 00-2-2h-4l-2-2h-4L8 5H4a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h2 class="mt-4 text-lg font-black text-slate-900">Belum ada hasil simulasi</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-500">
                                    Pilih preset atau ubah input, lalu jalankan simulasi.
                                </p>
                            </div>
                        </section>
                    @endif
                </section>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const presets = {
                optimal: { suhu: 27, kelembapan: 65, humidity: 65, amonia: 5, hdp: 85, pakan: 115, mortalitas: 0.1 },
                environment_bad: { suhu: 34, kelembapan: 85, humidity: 85, amonia: 30, hdp: 82, pakan: 115, mortalitas: 0.2 },
                productivity_bad: { suhu: 27, kelembapan: 65, humidity: 65, amonia: 5, hdp: 55, pakan: 80, mortalitas: 1.5 },
                critical: { suhu: 35, kelembapan: 88, humidity: 88, amonia: 35, hdp: 50, pakan: 75, mortalitas: 2 },
                feed_excess: { suhu: 28, kelembapan: 68, humidity: 68, amonia: 8, hdp: 60, pakan: 145, mortalitas: 0.5 },
            };

            const aliases = {
                temperature: 'suhu',
                temp: 'suhu',
                humiditas: 'kelembapan',
                humidity: 'kelembapan',
                kelembaban: 'kelembapan',
                ammonia: 'amonia',
                feed: 'pakan',
                konsumsi_pakan: 'pakan',
                mortality: 'mortalitas',
            };

            const normalizeName = (name) => {
                const key = String(name || '').toLowerCase().trim().replace(/\s+/g, '_');
                return aliases[key] || key;
            };

            document.querySelectorAll('.preset-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    const preset = presets[button.dataset.preset] || {};
                    document.querySelectorAll('[data-variable-name]').forEach((input) => {
                        const key = normalizeName(input.dataset.variableName);
                        if (Object.prototype.hasOwnProperty.call(preset, key)) {
                            input.value = preset[key];
                        }
                    });

                    document.querySelectorAll('.preset-btn').forEach((presetButton) => {
                        presetButton.setAttribute('aria-pressed', 'false');
                        presetButton.classList.remove('ring-2', 'ring-offset-1');
                    });
                    button.setAttribute('aria-pressed', 'true');
                    button.classList.add('ring-2', 'ring-offset-1');
                });
            });
        });
    </script>
@endsection
