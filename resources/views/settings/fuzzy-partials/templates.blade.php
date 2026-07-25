@php
    $assignments = $templateAssignments ?? collect();
    $activeAssignmentCount = $assignments->whereNotNull('active_profile')->count();
@endphp

<section class="space-y-4">
    <div class="rounded-2xl border border-gray-100 bg-white p-4" style="box-shadow: var(--shadow-sm);">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Template Fuzzy per Jenis Ternak</h2>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">
                    Setiap jenis ternak dapat memiliki template Fuzzy aktif sendiri. Template aktif inilah yang dipakai saat sistem menjalankan SPK untuk kandang dengan jenis ternak terkait.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">
                    {{ $activeAssignmentCount }} dari {{ $assignments->count() }} jenis aktif
                </span>
                <button type="button" @click="modal = 'addProfile'"
                    class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-gray-800">
                    Buat Template
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4">
        @forelse($assignments as $assignment)
            @php
                $activeTemplate = $assignment->active_profile;
                $availableProfiles = $assignment->profiles
                    ->reject(fn ($profile) => $profile->status === 'archived')
                    ->values();
            @endphp
            <article class="min-w-0 rounded-2xl border border-gray-100 bg-white p-4" style="box-shadow: var(--shadow-sm);">
                <div class="grid min-w-0 grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(340px,420px)] xl:items-start">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="truncate text-base font-bold text-gray-900">{{ $assignment->jenis_budidaya_nama }}</h3>
                            @if($activeTemplate)
                                <span class="rounded-full bg-emerald-600 px-2.5 py-1 text-[11px] font-bold uppercase text-white">Aktif</span>
                            @else
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold uppercase text-amber-700">Belum aktif</span>
                            @endif
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                <div class="text-[11px] font-semibold uppercase text-gray-400">Template</div>
                                <div class="text-sm font-bold text-gray-800">{{ $assignment->template_count }}</div>
                            </div>
                            <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                <div class="text-[11px] font-semibold uppercase text-gray-400">Draft</div>
                                <div class="text-sm font-bold text-gray-800">{{ $assignment->draft_count }}</div>
                            </div>
                            <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                <div class="text-[11px] font-semibold uppercase text-gray-400">Review</div>
                                <div class="text-sm font-bold text-gray-800">{{ $assignment->review_count }}</div>
                            </div>
                            <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                <div class="text-[11px] font-semibold uppercase text-gray-400">Arsip</div>
                                <div class="text-sm font-bold text-gray-800">{{ $assignment->archived_count }}</div>
                            </div>
                        </div>

                        @if($activeTemplate)
                            <div class="mt-3 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2">
                                <div class="text-[11px] font-bold uppercase text-emerald-700">Template aktif saat ini</div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-emerald-900">
                                    <span class="font-bold">{{ $activeTemplate->name }}</span>
                                    <span class="text-emerald-700">{{ $activeTemplate->version ?: 'tanpa versi' }}</span>
                                    <span class="rounded-full bg-white/80 px-2 py-0.5 text-[11px] font-bold uppercase text-emerald-700">{{ $activeTemplate->status }}</span>
                                </div>
                            </div>
                        @else
                            <div class="mt-3 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-sm leading-6 text-amber-800">
                                Belum ada template aktif. SPK untuk jenis ternak ini akan memakai fallback dan bisa membuat hasil tidak konsisten.
                            </div>
                        @endif
                    </div>

                    <div class="w-full min-w-0 rounded-xl border border-gray-100 bg-gray-50/70 p-3">
                        <form action="{{ route('settings.fuzzy.templates.activate') }}" method="POST" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="jenis_budidaya_id" value="{{ $assignment->jenis_budidaya_id }}">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Pilih template untuk diaktifkan</label>
                                <select name="profile_id"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm transition-all focus:border-[var(--color-primary)] focus:outline-none"
                                    @disabled($availableProfiles->isEmpty())>
                                    @forelse($availableProfiles as $profile)
                                        <option value="{{ $profile->id }}" {{ $activeTemplate?->id === $profile->id ? 'selected' : '' }}>
                                            {{ $profile->name }} {{ $profile->version ? '(' . $profile->version . ')' : '' }} - {{ ucfirst($profile->status) }}
                                        </option>
                                    @empty
                                        <option value="">Belum ada template</option>
                                    @endforelse
                                </select>
                            </div>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                <button type="submit"
                                    class="inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                                    @disabled($availableProfiles->isEmpty())>
                                    Aktifkan Template
                                </button>
                                @if($activeTemplate)
                                    <a href="{{ route('settings.fuzzy.index', ['profile_id' => $activeTemplate->id, 'jenis_budidaya_id' => $assignment->jenis_budidaya_id, 'tab' => 'variables']) }}"
                                        class="inline-flex w-full items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50"
                                        style="text-decoration:none;">
                                        Buka Konfigurasi
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800">
                Belum ada jenis ternak bertipe hewan di Data Master. Tambahkan jenis ternak terlebih dahulu agar template fuzzy bisa dipetakan.
            </div>
        @endforelse
    </div>
</section>
