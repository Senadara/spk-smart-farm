@extends('layouts.app')

@section('title', 'Detail Tugas')
@section('breadcrumb', 'Detail Tugas')

@section('content')
    @php
        $role = data_get(session('user'), 'role');
        $currentUserId = data_get(session('user'), 'id');
        $canManage = $role === 'pjawab';
        $canWork = $canManage || ($role === 'petugas' && $task->assigned_to === $currentUserId);
        $priorityMeta = [
            'urgent' => ['label' => 'Urgent', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
            'high' => ['label' => 'Tinggi', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
            'medium' => ['label' => 'Sedang', 'class' => 'bg-sky-50 text-sky-700 border-sky-200'],
            'low' => ['label' => 'Rendah', 'class' => 'bg-slate-50 text-slate-600 border-slate-200'],
        ];
        $statusMeta = [
            'todo' => ['label' => 'To Do', 'class' => 'bg-slate-50 text-slate-700 border-slate-200'],
            'in_progress' => ['label' => 'Dikerjakan', 'class' => 'bg-sky-50 text-sky-700 border-sky-200'],
            'done' => ['label' => 'Selesai', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
            'cancelled' => ['label' => 'Dibatalkan', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
        ];
        $priority = $priorityMeta[$task->priority] ?? $priorityMeta['medium'];
        $status = $statusMeta[$task->status] ?? $statusMeta['todo'];
        $isOverdue = $task->due_date && !in_array($task->status, ['done', 'cancelled'], true) && $task->due_date->lt(now()->startOfDay());
    @endphp

    <div x-data="taskDetailPage()" class="mx-auto max-w-5xl space-y-6" x-cloak>
        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="flex items-center gap-3">
            <a href="{{ route('spk.tasks.index') }}" class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 transition hover:bg-slate-200">
                <svg class="h-4 w-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="min-w-0">
                <h1 class="truncate text-lg font-bold text-slate-900">{{ $task->title }}</h1>
                <p class="text-[11px] text-slate-400">Dibuat {{ $task->createdAt?->diffForHumans() ?? '-' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-md border px-2 py-1 text-[9px] font-bold uppercase {{ $priority['class'] }}">{{ $priority['label'] }}</span>
                            <span class="rounded-md border px-2 py-1 text-[9px] font-bold uppercase {{ $status['class'] }}">{{ $status['label'] }}</span>
                            @if($isOverdue)
                                <span class="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-[9px] font-bold uppercase text-rose-700">Terlambat</span>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                            @if($canWork && $task->status === 'todo')
                                <form method="POST" action="{{ route('spk.tasks.status', $task->id) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="in_progress">
                                    <button type="submit" class="rounded-lg bg-sky-50 px-3 py-1.5 text-[10px] font-semibold text-sky-700 transition hover:bg-sky-100">Mulai Kerjakan</button>
                                </form>
                            @endif

                            @if($canWork && $task->status === 'in_progress')
                                <button @click="showReportModal = true" class="rounded-lg bg-emerald-50 px-3 py-1.5 text-[10px] font-semibold text-emerald-700 transition hover:bg-emerald-100">Kirim Laporan</button>
                            @endif

                            @if($canManage && !in_array($task->status, ['cancelled', 'done'], true))
                                <form method="POST" action="{{ route('spk.tasks.status', $task->id) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="rounded-lg bg-rose-50 px-3 py-1.5 text-[10px] font-semibold text-rose-700 transition hover:bg-rose-100" onclick="return confirm('Batalkan tugas ini?')">Batalkan</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="text-sm leading-relaxed text-slate-700">
                        {!! nl2br(e($task->description ?? 'Tidak ada deskripsi.')) !!}
                    </div>
                </div>

                @if($task->fuzzyLog)
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-5">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-bold text-slate-900">Ringkasan Sumber SPK</h3>
                            <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-bold text-emerald-700">
                                Skor {{ is_numeric($task->fuzzyLog->output_value) ? round($task->fuzzyLog->output_value, 1) : '-' }}
                            </span>
                        </div>
                        <div class="grid gap-3 text-xs sm:grid-cols-3">
                            <div class="rounded-lg bg-white px-3 py-2">
                                <span class="block text-[10px] font-bold uppercase text-slate-400">Lingkungan</span>
                                <span class="font-semibold text-slate-800">{{ $task->fuzzyLog->status_lingkungan ?? '-' }}</span>
                            </div>
                            <div class="rounded-lg bg-white px-3 py-2">
                                <span class="block text-[10px] font-bold uppercase text-slate-400">Kesehatan</span>
                                <span class="font-semibold text-slate-800">{{ $task->fuzzyLog->status_kesehatan ?? '-' }}</span>
                            </div>
                            <div class="rounded-lg bg-white px-3 py-2">
                                <span class="block text-[10px] font-bold uppercase text-slate-400">Output</span>
                                <span class="font-semibold text-slate-800">{{ $task->fuzzyLog->output_label ?? '-' }}</span>
                            </div>
                        </div>
                        @if($task->fuzzyLog->recommendation)
                            <p class="mt-3 text-xs leading-relaxed text-slate-600">{{ $task->fuzzyLog->recommendation }}</p>
                        @endif
                    </div>
                @endif

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-4 flex items-center gap-2 text-sm font-bold text-slate-800">
                        <svg class="h-4 w-4 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Timeline Laporan Pengerjaan
                    </h3>

                    @if($task->reports->count() > 0)
                        <div class="relative space-y-4 pl-6">
                            <div class="absolute bottom-2 left-2 top-2 w-0.5 bg-slate-200"></div>

                            @foreach($task->reports as $report)
                                <div class="relative">
                                    <div class="absolute -left-6 top-1 h-4 w-4 rounded-full border-2 {{ $report->status_update === 'done' ? 'border-emerald-200 bg-emerald-500' : 'border-sky-200 bg-sky-500' }}"></div>

                                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                                        <div class="mb-1.5 flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-slate-800">{{ $report->reporter->name ?? 'Unknown' }}</span>
                                                <span class="rounded px-1.5 py-0.5 text-[8px] font-bold uppercase {{ $report->status_update === 'done' ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700' }}">
                                                    {{ $report->status_update === 'done' ? 'Selesai' : 'Progress' }}
                                                </span>
                                            </div>
                                            <span class="shrink-0 text-[9px] text-slate-400">{{ $report->createdAt?->diffForHumans() ?? '-' }}</span>
                                        </div>
                                        <p class="text-[11px] leading-relaxed text-slate-600">{{ $report->description }}</p>
                                        @if($report->photo)
                                            <div class="mt-2">
                                                @php
                                                    $photoUrl = \Illuminate\Support\Str::startsWith($report->photo, ['http://', 'https://'])
                                                        ? $report->photo
                                                        : asset('storage/' . $report->photo);
                                                @endphp
                                                <a href="{{ $photoUrl }}" target="_blank" class="flex items-center gap-1 text-[10px] text-sky-600 hover:underline">
                                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                    Lihat Bukti Foto
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-8 text-center">
                            <svg class="mx-auto mb-2 h-10 w-10 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <p class="text-xs text-slate-400">Belum ada laporan pengerjaan</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="space-y-4">
                <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h4 class="border-b border-slate-100 pb-2 text-xs font-bold text-slate-800">Informasi Tugas</h4>

                    <div>
                        <p class="mb-0.5 text-[9px] font-bold uppercase text-slate-400">Ditugaskan Kepada</p>
                        <p class="text-xs font-semibold text-slate-800">{{ $task->assignee->name ?? 'Belum ditugaskan' }}</p>
                    </div>
                    <div>
                        <p class="mb-0.5 text-[9px] font-bold uppercase text-slate-400">Dibuat Oleh</p>
                        <p class="text-xs font-semibold text-slate-800">{{ $task->assigner->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-0.5 text-[9px] font-bold uppercase text-slate-400">Kandang Target</p>
                        <p class="text-xs font-semibold text-slate-800">{{ $task->unitBudidaya->nama ?? 'Umum' }}</p>
                    </div>
                    <div>
                        <p class="mb-0.5 text-[9px] font-bold uppercase text-slate-400">Tenggat Waktu</p>
                        <p class="text-xs font-semibold {{ $isOverdue ? 'text-rose-600' : 'text-slate-800' }}">
                            {{ $task->due_date?->format('d M Y') ?? 'Tidak ada' }}
                            @if($isOverdue)
                                <span class="ml-1 text-[9px] text-rose-500">(Terlambat)</span>
                            @endif
                        </p>
                    </div>
                    @if($task->completed_at)
                        <div>
                            <p class="mb-0.5 text-[9px] font-bold uppercase text-slate-400">Diselesaikan</p>
                            <p class="text-xs font-semibold text-emerald-600">{{ $task->completed_at->format('d M Y, H:i') }}</p>
                        </div>
                    @endif
                    @if($task->fuzzyLog)
                        <div>
                            <p class="mb-0.5 text-[9px] font-bold uppercase text-slate-400">Sumber Analisa SPK</p>
                            <p class="text-xs font-semibold text-emerald-700">#{{ \Illuminate\Support\Str::limit($task->spk_fuzzy_log_id, 12) }}</p>
                        </div>
                    @endif
                </div>

                @if($canManage && !in_array($task->status, ['done', 'cancelled'], true))
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h4 class="mb-3 border-b border-slate-100 pb-2 text-xs font-bold text-slate-800">Edit Tugas</h4>
                        <form method="POST" action="{{ route('spk.tasks.update', $task->id) }}" class="space-y-3">
                            @csrf @method('PUT')
                            <div>
                                <label class="text-[10px] font-semibold text-slate-500">Judul</label>
                                <input type="text" name="title" value="{{ $task->title }}" required class="mt-0.5 w-full rounded-lg border border-slate-200 px-2.5 py-2 text-xs focus:border-emerald-400 focus:outline-none">
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold text-slate-500">Deskripsi</label>
                                <textarea name="description" rows="2" class="mt-0.5 w-full rounded-lg border border-slate-200 px-2.5 py-2 text-xs focus:border-emerald-400 focus:outline-none">{{ $task->description }}</textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[10px] font-semibold text-slate-500">Prioritas</label>
                                    <select name="priority" class="mt-0.5 w-full rounded-lg border border-slate-200 px-2.5 py-2 text-xs focus:border-emerald-400 focus:outline-none">
                                        @foreach(['urgent' => 'Urgent', 'high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah'] as $val => $lbl)
                                            <option value="{{ $val }}" {{ $task->priority === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-slate-500">Tenggat</label>
                                    <input type="date" name="due_date" value="{{ $task->due_date?->format('Y-m-d') }}" class="mt-0.5 w-full rounded-lg border border-slate-200 px-2.5 py-2 text-xs focus:border-emerald-400 focus:outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold text-slate-500">Ditugaskan Kepada</label>
                                <select name="assigned_to" class="mt-0.5 w-full rounded-lg border border-slate-200 px-2.5 py-2 text-xs focus:border-emerald-400 focus:outline-none">
                                    <option value="">Belum ditugaskan</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" {{ $task->assigned_to == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold text-slate-500">Kandang</label>
                                <select name="unit_budidaya_id" class="mt-0.5 w-full rounded-lg border border-slate-200 px-2.5 py-2 text-xs focus:border-emerald-400 focus:outline-none">
                                    <option value="">Umum</option>
                                    @foreach ($barns as $barn)
                                        <option value="{{ $barn->id }}" {{ $task->unit_budidaya_id == $barn->id ? 'selected' : '' }}>{{ $barn->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2 text-[10px] font-semibold text-white transition hover:bg-emerald-700">Simpan Perubahan</button>
                        </form>
                    </div>
                @endif

                @if($canManage)
                    <form method="POST" action="{{ route('spk.tasks.destroy', $task->id) }}" onsubmit="return confirm('Hapus tugas ini secara permanen?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="w-full rounded-xl border border-rose-100 bg-rose-50 py-2.5 text-[10px] font-semibold text-rose-700 transition hover:bg-rose-100">
                            Hapus Tugas Permanen
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div x-show="showReportModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showReportModal = false" style="display: none;">
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl" @click.stop>
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-bold text-slate-900">Kirim Laporan Pengerjaan</h3>
                </div>
                <form method="POST" action="{{ route('spk.tasks.report', $task->id) }}" enctype="multipart/form-data" class="space-y-4 px-6 py-5">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Catatan Pengerjaan <span class="text-rose-500">*</span></label>
                        <textarea name="description" rows="3" required placeholder="Jelaskan apa yang sudah dikerjakan..." class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-sky-400 focus:outline-none"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Upload Bukti Foto (Opsional)</label>
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-sky-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-sky-700 focus:border-sky-400 focus:outline-none">
                        <p class="mt-1 text-[10px] text-slate-400">Format JPG, PNG, atau WebP. Maksimal 4 MB.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Update Status</label>
                        <select name="status_update" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-sky-400 focus:outline-none">
                            <option value="in_progress">Masih Dikerjakan</option>
                            <option value="done">Selesai</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showReportModal = false" class="rounded-lg bg-slate-100 px-4 py-2.5 text-xs text-slate-500 transition hover:bg-slate-200">Batal</button>
                        <button type="submit" class="rounded-lg bg-sky-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-sky-700">Kirim Laporan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('taskDetailPage', () => ({
                showReportModal: false,
            }));
        });
    </script>
@endpush
