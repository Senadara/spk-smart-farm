@php
    $priorityMeta = [
        'urgent' => ['label' => 'Urgent', 'badge' => 'bg-rose-50 text-rose-700 border-rose-200', 'edge' => 'border-rose-100 hover:border-rose-200'],
        'high' => ['label' => 'Tinggi', 'badge' => 'bg-amber-50 text-amber-700 border-amber-200', 'edge' => 'border-amber-100 hover:border-amber-200'],
        'medium' => ['label' => 'Sedang', 'badge' => 'bg-sky-50 text-sky-700 border-sky-200', 'edge' => 'border-sky-100 hover:border-sky-200'],
        'low' => ['label' => 'Rendah', 'badge' => 'bg-slate-50 text-slate-600 border-slate-200', 'edge' => 'border-slate-100 hover:border-slate-200'],
    ];
    $meta = $priorityMeta[$task->priority] ?? $priorityMeta['medium'];
    $isOverdue = $task->due_date && !in_array($task->status, ['done', 'cancelled'], true) && $task->due_date->lt(now()->startOfDay());
    $role = data_get(session('user'), 'role');
    $canWork = $role === 'pjawab' || ($role === 'petugas' && $task->assigned_to === data_get(session('user'), 'id'));
@endphp

<div class="rounded-xl border {{ $meta['edge'] }} bg-white p-3.5 shadow-sm shadow-slate-100/80 transition-all hover:shadow-md">
    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
        <span class="rounded border px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wider {{ $meta['badge'] }}">
            {{ $meta['label'] }}
        </span>

        <div class="flex flex-wrap items-center justify-end gap-1.5">
            @if($canWork && $task->status === 'todo')
                <form method="POST" action="{{ route('spk.tasks.status', $task->id) }}" class="inline">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="in_progress">
                    <button type="submit" class="inline-flex h-7 items-center gap-1 rounded-md border border-sky-100 bg-sky-50 px-2 text-[10px] font-semibold text-sky-700 transition hover:bg-sky-100">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                        Mulai
                    </button>
                </form>
            @endif

            @if($canWork && $task->status === 'in_progress')
                <button @click="openReport(@js($task->id), @js($task->title))" class="inline-flex h-7 items-center gap-1 rounded-md border border-emerald-100 bg-emerald-50 px-2 text-[10px] font-semibold text-emerald-700 transition hover:bg-emerald-100">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Laporan
                </button>
            @endif

            <a href="{{ route('spk.tasks.show', $task->id) }}" class="inline-flex h-7 items-center gap-1 rounded-md border border-slate-200 bg-white px-2 text-[10px] font-semibold text-slate-600 transition hover:bg-slate-50">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                Detail
            </a>
        </div>
    </div>

    <a href="{{ route('spk.tasks.show', $task->id) }}" class="block">
        <h4 class="mb-1 line-clamp-2 text-xs font-bold text-slate-800 transition hover:text-emerald-700">{{ $task->title }}</h4>
    </a>

    @if($task->description)
        <p class="mb-2 line-clamp-2 text-[10px] leading-relaxed text-slate-500">{{ $task->description }}</p>
    @endif

    <div class="flex items-center justify-between gap-2 border-t border-slate-50 pt-2">
        <div class="min-w-0">
            @if($task->assignee)
                <div class="flex items-center gap-1" title="Ditugaskan ke: {{ $task->assignee->name }}">
                    <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-[8px] font-bold text-emerald-700">
                        {{ strtoupper(substr($task->assignee->name, 0, 1)) }}
                    </div>
                    <span class="truncate text-[9px] text-slate-500">{{ $task->assignee->name }}</span>
                </div>
            @else
                <span class="text-[9px] italic text-slate-400">Belum ditugaskan</span>
            @endif
        </div>

        <div class="flex shrink-0 items-center gap-1.5">
            @if($task->unitBudidaya)
                <span class="max-w-[96px] truncate rounded bg-slate-100 px-1.5 py-0.5 text-[8px] font-medium text-slate-500">{{ $task->unitBudidaya->nama }}</span>
            @endif

            @if($task->due_date)
                <span class="rounded px-1.5 py-0.5 text-[8px] font-medium {{ $isOverdue ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-500' }}">
                    {{ $isOverdue ? 'Terlambat ' : '' }}{{ $task->due_date->format('d M') }}
                </span>
            @endif
        </div>
    </div>
</div>
