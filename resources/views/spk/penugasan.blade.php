@extends('layouts.app')

@section('title', 'Penugasan')
@section('breadcrumb', 'Penugasan')

@section('content')
    <div x-data="penugasanPage()" class="max-w-full space-y-6" x-cloak>

        {{-- ═══ HEADER ═══ --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white px-5 py-4 rounded-xl border border-gray-100 shadow-sm">
            <div>
                <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <svg class="w-6 h-6 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    Penugasan & Laporan Tindakan
                </h1>
                <p class="text-xs text-gray-400 mt-0.5">Kelola tugas tindakan dari hasil analisa SPK untuk petugas lapangan</p>
            </div>
            <div class="flex items-center gap-3">
                @if(session('user') && isset(session('user')['role']) && session('user')['role'] === 'pjawab')
                <button @click="showCreateModal = true" class="flex items-center gap-1.5 text-xs font-semibold text-white bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Buat Tugas
                </button>
                @endif
            </div>
        </div>

        {{-- ═══ STATS ROW ═══ --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            @php
                $statItems = [
                    ['label' => 'Total Tugas', 'value' => $stats['total'], 'color' => 'gray', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2'],
                    ['label' => 'To Do', 'value' => $stats['todo'], 'color' => 'gray', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['label' => 'Dikerjakan', 'value' => $stats['in_progress'], 'color' => 'blue', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                    ['label' => 'Selesai', 'value' => $stats['done'], 'color' => 'emerald', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['label' => 'Terlambat', 'value' => $stats['overdue'], 'color' => 'red', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z'],
                ];
            @endphp
            @foreach ($statItems as $stat)
                <div class="bg-white border border-gray-100 rounded-xl p-4 shadow-sm">
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-7 h-7 rounded-lg bg-{{ $stat['color'] }}-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-{{ $stat['color'] }}-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}"/></svg>
                        </div>
                        <span class="text-[10px] uppercase font-bold tracking-wider text-gray-400">{{ $stat['label'] }}</span>
                    </div>
                    <span class="text-2xl font-black text-gray-900">{{ $stat['value'] }}</span>
                </div>
            @endforeach
        </div>

        {{-- ═══ TABS ═══ --}}
        <div class="flex items-center border-b border-gray-200">
            <a href="?tab=active" class="px-6 py-3 border-b-2 text-sm font-bold transition-colors {{ $tab === 'active' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Papan Tugas Aktif
            </a>
            <a href="?tab=history" class="px-6 py-3 border-b-2 text-sm font-bold transition-colors {{ $tab === 'history' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                Arsip & Histori Selesai
            </a>
        </div>

        @if($tab === 'active')
            {{-- ═══ ACTIVE TASKS (Kanban) ═══ --}}
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-2">
                <h3 class="text-sm font-bold text-gray-800">Board Penugasan</h3>
                <form method="GET" action="{{ route('spk.tasks.index') }}" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="active">
                    @if(session('user') && isset(session('user')['role']) && session('user')['role'] === 'pjawab')
                    <select name="user_id" class="text-xs border border-gray-200 rounded-lg px-2.5 py-2 bg-white text-gray-600 focus:outline-none focus:border-purple-400 cursor-pointer" onchange="this.form.submit()">
                        <option value="all">Semua Petugas</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ $userFilter == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @endif
                    <select name="priority" class="text-xs border border-gray-200 rounded-lg px-2.5 py-2 bg-white text-gray-600 focus:outline-none focus:border-purple-400 cursor-pointer" onchange="this.form.submit()">
                        <option value="all" {{ $priorityFilter === 'all' ? 'selected' : '' }}>Semua Prioritas</option>
                        <option value="urgent" {{ $priorityFilter === 'urgent' ? 'selected' : '' }}>Urgent</option>
                        <option value="high" {{ $priorityFilter === 'high' ? 'selected' : '' }}>Tinggi</option>
                        <option value="medium" {{ $priorityFilter === 'medium' ? 'selected' : '' }}>Sedang</option>
                        <option value="low" {{ $priorityFilter === 'low' ? 'selected' : '' }}>Rendah</option>
                    </select>
                    <div class="relative">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" name="search" value="{{ $search }}" placeholder="Cari tugas..." class="w-48 text-xs border border-gray-200 rounded-lg pl-8 pr-3 py-2 focus:outline-none focus:border-purple-400" onchange="this.form.submit()">
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- Column: TO DO --}}
                <div class="bg-gray-50 rounded-xl p-3 border border-gray-100 min-h-[300px] shadow-inner">
                    <h4 class="text-[10px] font-bold text-gray-500 uppercase tracking-widest px-1 py-2 flex items-center justify-between border-b border-gray-200 mb-3">
                        <span class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-gray-400"></span> To Do
                        </span>
                        <span class="bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded text-[10px] font-bold">{{ $kanban['todo']->count() }}</span>
                    </h4>
                    <div class="space-y-2">
                        @forelse ($kanban['todo'] as $task)
                            @include('spk.partials.task-card', ['task' => $task])
                        @empty
                            <p class="text-[11px] text-gray-400 text-center py-8">Tidak ada tugas</p>
                        @endforelse
                    </div>
                </div>

                {{-- Column: IN PROGRESS --}}
                <div class="bg-blue-50/40 rounded-xl p-3 border border-blue-100 min-h-[300px] shadow-inner">
                    <h4 class="text-[10px] font-bold text-blue-600 uppercase tracking-widest px-1 py-2 flex items-center justify-between border-b border-blue-200 mb-3">
                        <span class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span> Dikerjakan
                        </span>
                        <span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-bold">{{ $kanban['in_progress']->count() }}</span>
                    </h4>
                    <div class="space-y-2">
                        @forelse ($kanban['in_progress'] as $task)
                            @include('spk.partials.task-card', ['task' => $task])
                        @empty
                            <p class="text-[11px] text-blue-400 text-center py-8">Tidak ada tugas</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        @if($tab === 'history')
            {{-- ═══ HISTORY TASKS (Table) ═══ --}}
            <div class="bg-white border border-gray-100 rounded-xl shadow-sm">
                <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <h3 class="text-sm font-bold text-gray-800">Histori & Arsip Tugas</h3>
                    <form method="GET" action="{{ route('spk.tasks.index') }}" class="flex flex-wrap items-center gap-2">
                        <input type="hidden" name="tab" value="history">
                        
                        @if(session('user') && isset(session('user')['role']) && session('user')['role'] === 'pjawab')
                        <select name="user_id" class="text-xs border border-gray-200 rounded-lg px-2.5 py-2 bg-white text-gray-600 focus:outline-none focus:border-purple-400" onchange="this.form.submit()">
                            <option value="all">Semua Petugas</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ $userFilter == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @endif
                        
                        <div class="flex items-center gap-1">
                            <input type="date" name="start_date" value="{{ $startDate }}" class="text-xs border border-gray-200 rounded-lg px-2.5 py-2 bg-white text-gray-600 focus:outline-none focus:border-purple-400" onchange="this.form.submit()">
                            <span class="text-gray-400 text-xs">-</span>
                            <input type="date" name="end_date" value="{{ $endDate }}" class="text-xs border border-gray-200 rounded-lg px-2.5 py-2 bg-white text-gray-600 focus:outline-none focus:border-purple-400" onchange="this.form.submit()">
                        </div>

                        <select name="status" class="text-xs border border-gray-200 rounded-lg px-2.5 py-2 bg-white text-gray-600 focus:outline-none focus:border-purple-400" onchange="this.form.submit()">
                            <option value="all">Semua (Done/Cancel)</option>
                            <option value="done" {{ $statusFilter == 'done' ? 'selected' : '' }}>Selesai</option>
                            <option value="cancelled" {{ $statusFilter == 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
                        </select>
                    </form>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                                <th class="p-3 border-b border-gray-200">Judul Tugas</th>
                                <th class="p-3 border-b border-gray-200">Petugas</th>
                                <th class="p-3 border-b border-gray-200">Waktu Selesai</th>
                                <th class="p-3 border-b border-gray-200">Status</th>
                                <th class="p-3 border-b border-gray-200 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($historyTasks as $htask)
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="p-3">
                                        <p class="text-xs font-semibold text-gray-800 line-clamp-1">{{ $htask->title }}</p>
                                        <p class="text-[10px] text-gray-400">{{ $htask->unitBudidaya->nama ?? 'Umum' }}</p>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-5 h-5 rounded-full bg-purple-100 flex items-center justify-center text-[8px] font-bold text-purple-600">
                                                {{ $htask->assignee ? strtoupper(substr($htask->assignee->name, 0, 1)) : '?' }}
                                            </div>
                                            <span class="text-xs text-gray-600">{{ $htask->assignee->name ?? 'Tidak ada' }}</span>
                                        </div>
                                    </td>
                                    <td class="p-3 text-xs text-gray-500">
                                        {{ $htask->completed_at ? $htask->completed_at->format('d M Y, H:i') : '-' }}
                                    </td>
                                    <td class="p-3">
                                        @if($htask->status == 'done')
                                            <span class="text-[10px] font-bold uppercase px-2 py-1 rounded bg-emerald-100 text-emerald-700">Selesai</span>
                                        @else
                                            <span class="text-[10px] font-bold uppercase px-2 py-1 rounded bg-red-100 text-red-700">Dibatalkan</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-right">
                                        <a href="{{ route('spk.tasks.show', $htask->id) }}" class="text-xs font-semibold text-purple-600 hover:text-purple-800 bg-purple-50 hover:bg-purple-100 px-3 py-1.5 rounded-lg transition">Detail</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-gray-400 text-xs">Belum ada histori tugas yang diselesaikan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($historyTasks->hasPages())
                    <div class="p-4 border-t border-gray-100">
                        {{ $historyTasks->links() }}
                    </div>
                @endif
            </div>
        @endif


        {{-- ═══ CREATE TASK MODAL ═══ --}}
        <div x-show="showCreateModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showCreateModal = false" style="display: none;">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Buat Tugas Baru
                    </h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('spk.tasks.store') }}" class="px-6 py-5 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Judul Tugas <span class="text-red-500">*</span></label>
                        <input type="text" name="title" required placeholder="e.g. Perbaiki ventilasi kandang A2" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-purple-400 focus:ring-1 focus:ring-purple-200">
                    </div>
                    
                    {{-- SPK Reference Dropdown --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Sumber Rekomendasi SPK</label>
                        <select name="spk_fuzzy_log_id" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-purple-400">
                            <option value="">— Tidak Berkaitan dengan SPK (Tugas Umum) —</option>
                            @foreach($recentSpks as $spk)
                                @php
                                    $spkDate = \Carbon\Carbon::parse($spk->createdAt)->format('d M y H:i');
                                    $barnName = $spk->unitBudidaya->nama ?? 'Global';
                                @endphp
                                <option value="{{ $spk->id }}" {{ $prefill['spk_id'] == $spk->id ? 'selected' : '' }}>
                                    [{{ $spkDate }} - {{ $barnName }}] {{ \Str::limit($spk->recommendation, 60) }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-gray-400 mt-1">Pilih ini jika tugas adalah tindak lanjut dari hasil analisa SPK sebelumnya.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Deskripsi & Rekomendasi</label>
                        <textarea name="description" rows="3" placeholder="Detail tindakan yang harus dilakukan..." class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-purple-400 focus:ring-1 focus:ring-purple-200">{{ $prefill['desc'] }}</textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Prioritas <span class="text-red-500">*</span></label>
                            <select name="priority" required class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-purple-400">
                                <option value="medium">Sedang</option>
                                <option value="urgent">Urgent</option>
                                <option value="high">Tinggi</option>
                                <option value="low">Rendah</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Tenggat Waktu</label>
                            <input type="date" name="due_date" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-purple-400">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Ditugaskan Kepada</label>
                            <select name="assigned_to" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-purple-400">
                                <option value="">— Belum ditugaskan —</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Kandang Target</label>
                            <select name="unit_budidaya_id" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-purple-400">
                                <option value="">— Umum —</option>
                                @foreach ($barns as $barn)
                                    <option value="{{ $barn->id }}" {{ $prefill['coop_id'] == $barn->id ? 'selected' : '' }}>{{ $barn->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showCreateModal = false" class="text-xs font-semibold text-gray-500 bg-gray-100 hover:bg-gray-200 px-4 py-2.5 rounded-lg transition">Batal</button>
                        <button type="submit" class="text-xs font-semibold text-white bg-purple-600 hover:bg-purple-700 px-5 py-2.5 rounded-lg transition shadow-sm">Simpan Tugas</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ═══ REPORT MODAL ═══ --}}
        <div x-show="showReportModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showReportModal = false" style="display: none;">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg" @click.stop>
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Laporan Pengerjaan
                    </h3>
                    <button @click="showReportModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form :action="reportActionUrl" method="POST" class="px-6 py-5 space-y-4">
                    @csrf
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-0.5">Tugas</p>
                        <p class="text-sm font-semibold text-gray-800" x-text="reportTaskTitle"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan Pengerjaan <span class="text-red-500">*</span></label>
                        <textarea name="description" rows="3" required placeholder="Jelaskan apa yang sudah dikerjakan..." class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-200"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">URL Bukti Foto (Opsional)</label>
                        <input type="text" name="photo" placeholder="https://..." class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-blue-400">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Update Status <span class="text-red-500">*</span></label>
                        <select name="status_update" required class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-blue-400">
                            <option value="in_progress">Masih Dikerjakan</option>
                            <option value="done">Selesai</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showReportModal = false" class="text-xs font-semibold text-gray-500 bg-gray-100 hover:bg-gray-200 px-4 py-2.5 rounded-lg transition">Batal</button>
                        <button type="submit" class="text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 px-5 py-2.5 rounded-lg transition shadow-sm">Kirim Laporan</button>
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
