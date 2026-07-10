@extends('layouts.app')

@section('title', 'Penugasan')
@section('breadcrumb', 'Penugasan')

@section('content')
    @php
        $role = data_get(session('user'), 'role');
        $isPjawab = $role === 'pjawab';
        $isPetugas = $role === 'petugas';
        $priorityOptions = ['urgent' => 'Urgent', 'high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah'];
        $statItems = [
            ['label' => 'Total Tugas', 'value' => $stats['total'], 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2', 'class' => 'bg-slate-50 text-slate-600 border-slate-100'],
            ['label' => 'To Do', 'value' => $stats['todo'], 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'class' => 'bg-zinc-50 text-zinc-600 border-zinc-100'],
            ['label' => 'Dikerjakan', 'value' => $stats['in_progress'], 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'class' => 'bg-sky-50 text-sky-600 border-sky-100'],
            ['label' => 'Selesai', 'value' => $stats['done'], 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'class' => 'bg-emerald-50 text-emerald-600 border-emerald-100'],
            ['label' => 'Terlambat', 'value' => $stats['overdue'], 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z', 'class' => 'bg-rose-50 text-rose-600 border-rose-100'],
        ];
    @endphp

    <div x-data="penugasanPage()" class="max-w-full space-y-6" x-cloak>
        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="h-1 bg-gradient-to-r from-emerald-500 via-sky-500 to-indigo-500"></div>
            <div class="flex flex-col gap-4 px-5 py-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="flex items-center gap-2 text-xl font-bold text-slate-900">
                            <svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            Penugasan & Laporan Tindakan
                        </h1>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            {{ $isPetugas ? 'Petugas' : 'Penanggung Jawab' }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Kelola tindak lanjut hasil analisa SPK sampai laporan pengerjaan selesai.</p>
                </div>

                @if($isPjawab)
                    <button @click="showCreateModal = true" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Buat Tugas
                    </button>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            @foreach ($statItems as $stat)
                <div class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm">
                    <div class="mb-2 flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg border {{ $stat['class'] }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}"/></svg>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $stat['label'] }}</span>
                    </div>
                    <span class="text-2xl font-black text-slate-900">{{ $stat['value'] }}</span>
                </div>
            @endforeach
        </div>

        @if($isPjawab)
            <div class="overflow-hidden rounded-xl border border-emerald-100 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-emerald-100 bg-emerald-50/70 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Planning Penugasan Petugas</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Rekomendasi ini dibuat dari log SPK terbaru yang belum memiliki tugas aktif.</p>
                    </div>
                    <a href="{{ route('spk.dashboard') }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3h2v18h-2zM4 13h2v8H4zM18 8h2v13h-2z"/></svg>
                        Analisa SPK
                    </a>
                </div>

                <div class="grid gap-3 p-4 lg:grid-cols-2 2xl:grid-cols-3">
                    @forelse($taskPlans as $plan)
                        <div class="flex min-h-[190px] flex-col justify-between rounded-xl border border-slate-200 bg-white p-4 transition hover:border-emerald-200 hover:shadow-sm">
                            <div class="space-y-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $plan['barn'] }}</p>
                                        <h3 class="mt-1 text-sm font-bold leading-snug text-slate-900">{{ $plan['title'] }}</h3>
                                    </div>
                                    <span class="shrink-0 rounded-full border px-2 py-1 text-[10px] font-bold {{ $plan['priorityClass'] }}">
                                        {{ $plan['priorityLabel'] }}
                                    </span>
                                </div>

                                <p class="text-xs leading-relaxed text-slate-600">{{ $plan['recommendation'] }}</p>

                                <div class="grid grid-cols-2 gap-2 text-[11px]">
                                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                                        <span class="block font-bold uppercase tracking-wide text-slate-400">Alasan</span>
                                        <span class="mt-0.5 block text-slate-700">{{ $plan['reason'] }}</span>
                                    </div>
                                    <div class="rounded-lg bg-sky-50 px-3 py-2">
                                        <span class="block font-bold uppercase tracking-wide text-sky-500">Petugas</span>
                                        <span class="mt-0.5 block text-slate-700">{{ $plan['assignee'] }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
                                <span class="text-[11px] font-semibold text-slate-500">Tenggat {{ \Carbon\Carbon::parse($plan['due_date'])->format('d M Y') }}</span>
                                <a href="{{ $plan['url'] }}" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-700">
                                    Buat Tugas
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full rounded-xl border border-dashed border-slate-200 bg-slate-50 px-5 py-8 text-center">
                            <p class="text-sm font-semibold text-slate-700">
                                {{ $users->isEmpty() ? 'Belum ada petugas aktif untuk menerima tugas.' : 'Belum ada planning tugas baru dari log SPK terbaru.' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $users->isEmpty() ? 'Tambahkan akun petugas terlebih dahulu agar rekomendasi bisa langsung ditugaskan.' : 'Semua log SPK terbaru sudah stabil atau sudah memiliki tugas aktif.' }}
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
        @elseif($isPetugas)
            <div class="rounded-xl border border-sky-100 bg-sky-50/70 px-5 py-4">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Agenda Petugas</h2>
                        <p class="text-xs text-slate-500">Daftar di bawah otomatis hanya menampilkan tugas yang ditugaskan kepada akun Anda.</p>
                    </div>
                    <div class="flex gap-2 text-[11px] font-semibold">
                        <span class="rounded-full bg-white px-3 py-1.5 text-sky-700">{{ $stats['todo'] }} To Do</span>
                        <span class="rounded-full bg-white px-3 py-1.5 text-emerald-700">{{ $stats['in_progress'] }} Dikerjakan</span>
                    </div>
                </div>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200">
            <a href="{{ route('spk.tasks.index', ['tab' => 'active']) }}" class="border-b-2 px-5 py-3 text-sm font-bold transition-colors {{ $tab === 'active' ? 'border-emerald-500 text-emerald-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                Papan Tugas Aktif
            </a>
            <a href="{{ route('spk.tasks.index', ['tab' => 'history']) }}" class="border-b-2 px-5 py-3 text-sm font-bold transition-colors {{ $tab === 'history' ? 'border-emerald-500 text-emerald-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                Arsip & Histori
            </a>
        </div>

        @if($tab === 'active')
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <h3 class="text-sm font-bold text-slate-800">Board Penugasan</h3>
                <form method="GET" action="{{ route('spk.tasks.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="active">
                    @if($isPjawab)
                        <select name="user_id" class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-600 focus:border-emerald-400 focus:outline-none" onchange="this.form.submit()">
                            <option value="all">Semua Petugas</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ $userFilter == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <select name="priority" class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-600 focus:border-emerald-400 focus:outline-none" onchange="this.form.submit()">
                        <option value="all" {{ $priorityFilter === 'all' ? 'selected' : '' }}>Semua Prioritas</option>
                        @foreach($priorityOptions as $value => $label)
                            <option value="{{ $value }}" {{ $priorityFilter === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="relative">
                        <svg class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" name="search" value="{{ $search }}" placeholder="Cari tugas..." class="w-full rounded-lg border border-slate-200 py-2 pl-8 pr-3 text-xs focus:border-emerald-400 focus:outline-none sm:w-52" onchange="this.form.submit()">
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <div class="min-h-[320px] rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <h4 class="mb-3 flex items-center justify-between border-b border-slate-200 px-1 py-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">
                        <span class="flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-slate-400"></span> To Do
                        </span>
                        <span class="rounded bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-700">{{ $kanban['todo']->count() }}</span>
                    </h4>
                    <div class="space-y-2">
                        @forelse ($kanban['todo'] as $task)
                            @include('spk.partials.task-card', ['task' => $task])
                        @empty
                            <p class="py-8 text-center text-[11px] text-slate-400">Tidak ada tugas</p>
                        @endforelse
                    </div>
                </div>

                <div class="min-h-[320px] rounded-xl border border-sky-100 bg-sky-50/50 p-3">
                    <h4 class="mb-3 flex items-center justify-between border-b border-sky-100 px-1 py-2 text-[10px] font-bold uppercase tracking-widest text-sky-600">
                        <span class="flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-sky-500"></span> Dikerjakan
                        </span>
                        <span class="rounded bg-white px-1.5 py-0.5 text-[10px] font-bold text-sky-700">{{ $kanban['in_progress']->count() }}</span>
                    </h4>
                    <div class="space-y-2">
                        @forelse ($kanban['in_progress'] as $task)
                            @include('spk.partials.task-card', ['task' => $task])
                        @empty
                            <p class="py-8 text-center text-[11px] text-sky-400">Tidak ada tugas</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        @if($tab === 'history')
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                    <h3 class="text-sm font-bold text-slate-800">Histori & Arsip Tugas</h3>
                    <form method="GET" action="{{ route('spk.tasks.index') }}" class="flex flex-wrap items-center gap-2">
                        <input type="hidden" name="tab" value="history">

                        @if($isPjawab)
                            <select name="user_id" class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-600 focus:border-emerald-400 focus:outline-none" onchange="this.form.submit()">
                                <option value="all">Semua Petugas</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ $userFilter == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        @endif

                        <div class="flex items-center gap-1">
                            <input type="date" name="start_date" value="{{ $startDate }}" class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-600 focus:border-emerald-400 focus:outline-none" onchange="this.form.submit()">
                            <span class="text-xs text-slate-400">-</span>
                            <input type="date" name="end_date" value="{{ $endDate }}" class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-600 focus:border-emerald-400 focus:outline-none" onchange="this.form.submit()">
                        </div>

                        <select name="status" class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-600 focus:border-emerald-400 focus:outline-none" onchange="this.form.submit()">
                            <option value="all">Semua Status</option>
                            <option value="done" {{ $statusFilter == 'done' ? 'selected' : '' }}>Selesai</option>
                            <option value="cancelled" {{ $statusFilter == 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
                        </select>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                <th class="border-b border-slate-200 p-3">Judul Tugas</th>
                                <th class="border-b border-slate-200 p-3">Petugas</th>
                                <th class="border-b border-slate-200 p-3">Waktu Selesai</th>
                                <th class="border-b border-slate-200 p-3">Status</th>
                                <th class="border-b border-slate-200 p-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($historyTasks as $htask)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="p-3">
                                        <p class="line-clamp-1 text-xs font-semibold text-slate-800">{{ $htask->title }}</p>
                                        <p class="text-[10px] text-slate-400">{{ $htask->unitBudidaya->nama ?? 'Umum' }}</p>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex items-center gap-1.5">
                                            <div class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-[8px] font-bold text-emerald-700">
                                                {{ $htask->assignee ? strtoupper(substr($htask->assignee->name, 0, 1)) : '?' }}
                                            </div>
                                            <span class="text-xs text-slate-600">{{ $htask->assignee->name ?? 'Tidak ada' }}</span>
                                        </div>
                                    </td>
                                    <td class="p-3 text-xs text-slate-500">
                                        {{ $htask->completed_at ? $htask->completed_at->format('d M Y, H:i') : '-' }}
                                    </td>
                                    <td class="p-3">
                                        @if($htask->status == 'done')
                                            <span class="rounded bg-emerald-100 px-2 py-1 text-[10px] font-bold uppercase text-emerald-700">Selesai</span>
                                        @else
                                            <span class="rounded bg-rose-100 px-2 py-1 text-[10px] font-bold uppercase text-rose-700">Dibatalkan</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-right">
                                        <a href="{{ route('spk.tasks.show', $htask->id) }}" class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">Detail</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-xs text-slate-400">Belum ada histori tugas yang diselesaikan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($historyTasks->hasPages())
                    <div class="border-t border-slate-100 p-4">
                        {{ $historyTasks->links() }}
                    </div>
                @endif
            </div>
        @endif

        <div x-show="showCreateModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showCreateModal = false" style="display: none;">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl" @click.stop>
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Buat Tugas Baru
                    </h3>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('spk.tasks.store') }}" class="space-y-4 px-6 py-5">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Judul Tugas <span class="text-rose-500">*</span></label>
                        <input type="text" name="title" required value="{{ old('title', $prefill['title']) }}" placeholder="Contoh: Perbaiki ventilasi kandang A2" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-200">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Sumber Rekomendasi SPK</label>
                        <select name="spk_fuzzy_log_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                            <option value="">Tidak berkaitan dengan SPK</option>
                            @foreach($recentSpks as $spk)
                                @php
                                    $spkDate = \Carbon\Carbon::parse($spk->createdAt)->format('d M y H:i');
                                    $barnName = $spk->unitBudidaya->nama ?? 'Global';
                                @endphp
                                <option value="{{ $spk->id }}" {{ old('spk_fuzzy_log_id', $prefill['spk_id']) == $spk->id ? 'selected' : '' }}>
                                    [{{ $spkDate }} - {{ $barnName }}] {{ \Str::limit($spk->recommendation ?: $spk->narrative, 60) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Deskripsi & Rekomendasi</label>
                        <textarea name="description" rows="4" placeholder="Detail tindakan yang harus dilakukan..." class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-200">{{ old('description', $prefill['desc']) }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Prioritas <span class="text-rose-500">*</span></label>
                            <select name="priority" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                                @foreach($priorityOptions as $value => $label)
                                    <option value="{{ $value }}" {{ old('priority', $prefill['priority']) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Tenggat Waktu</label>
                            <input type="date" name="due_date" value="{{ old('due_date', $prefill['due_date']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Ditugaskan Kepada</label>
                            <select name="assigned_to" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                                <option value="">Belum ditugaskan</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" {{ old('assigned_to', $prefill['assigned_to']) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Kandang Target</label>
                            <select name="unit_budidaya_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none">
                                <option value="">Umum</option>
                                @foreach ($barns as $barn)
                                    <option value="{{ $barn->id }}" {{ old('unit_budidaya_id', $prefill['coop_id']) == $barn->id ? 'selected' : '' }}>{{ $barn->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showCreateModal = false" class="rounded-lg bg-slate-100 px-4 py-2.5 text-xs font-semibold text-slate-500 transition hover:bg-slate-200">Batal</button>
                        <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700">Simpan Tugas</button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showReportModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showReportModal = false" style="display: none;">
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl" @click.stop>
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                        <svg class="h-5 w-5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Laporan Pengerjaan
                    </h3>
                    <button @click="showReportModal = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form :action="reportActionUrl" method="POST" enctype="multipart/form-data" class="space-y-4 px-6 py-5">
                    @csrf
                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <p class="mb-0.5 text-[10px] font-bold uppercase text-slate-400">Tugas</p>
                        <p class="text-sm font-semibold text-slate-800" x-text="reportTaskTitle"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Catatan Pengerjaan <span class="text-rose-500">*</span></label>
                        <textarea name="description" rows="3" required placeholder="Jelaskan apa yang sudah dikerjakan..." class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-sky-400 focus:outline-none focus:ring-1 focus:ring-sky-200"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Upload Bukti Foto (Opsional)</label>
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-sky-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-sky-700 focus:border-sky-400 focus:outline-none">
                        <p class="mt-1 text-[10px] text-slate-400">Format JPG, PNG, atau WebP. Maksimal 4 MB.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Update Status <span class="text-rose-500">*</span></label>
                        <select name="status_update" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-sky-400 focus:outline-none">
                            <option value="in_progress">Masih Dikerjakan</option>
                            <option value="done">Selesai</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showReportModal = false" class="rounded-lg bg-slate-100 px-4 py-2.5 text-xs font-semibold text-slate-500 transition hover:bg-slate-200">Batal</button>
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
            Alpine.data('penugasanPage', () => ({
                showCreateModal: {{ $prefill['showModal'] ? 'true' : 'false' }},
                showReportModal: false,
                reportTaskId: null,
                reportTaskTitle: '',
                get reportActionUrl() {
                    return this.reportTaskId ? '{{ url("penugasan") }}/' + this.reportTaskId + '/report' : '#';
                },
                openReport(taskId, taskTitle) {
                    this.reportTaskId = taskId;
                    this.reportTaskTitle = taskTitle;
                    this.showReportModal = true;
                },
            }));
        });
    </script>
@endpush
