@php
    $priorityColors = [
        'urgent' => 'bg-red-100 text-red-700 border-red-200',
        'high'   => 'bg-amber-100 text-amber-700 border-amber-200',
        'medium' => 'bg-blue-100 text-blue-700 border-blue-200',
        'low'    => 'bg-gray-100 text-gray-600 border-gray-200',
    ];
    $priorityLabels = ['urgent' => 'Urgent', 'high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah'];
    $isOverdue = $task->due_date && $task->status !== 'done' && $task->status !== 'cancelled' && $task->due_date->isPast();
@endphp

<div class="bg-white rounded-xl p-3.5 border border-gray-100 shadow-sm hover:shadow-md transition-all group">
    {{-- Header: Priority + Actions --}}
    <div class="flex items-start justify-between mb-2">
        <span class="text-[8px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded border {{ $priorityColors[$task->priority] ?? $priorityColors['medium'] }}">
            {{ $priorityLabels[$task->priority] ?? 'Sedang' }}
        </span>
        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            @if($task->status === 'todo')
                <form method="POST" action="{{ route('spk.tasks.status', $task->id) }}" class="inline">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="in_progress">
                    <button type="submit" title="Mulai Kerjakan" class="w-6 h-6 rounded-md bg-blue-50 text-blue-500 hover:bg-blue-100 flex items-center justify-center transition">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                    </button>
                </form>
            @endif
            @if($task->status === 'in_progress')
                <button @click="openReport('{{ $task->id }}', '{{ addslashes($task->title) }}')" title="Kirim Laporan" class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-500 hover:bg-emerald-100 flex items-center justify-center transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
            @endif
            <a href="{{ route('spk.tasks.show', $task->id) }}" title="Detail" class="w-6 h-6 rounded-md bg-gray-50 text-gray-400 hover:bg-gray-100 flex items-center justify-center transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>

    {{-- Title --}}
    <a href="{{ route('spk.tasks.show', $task->id) }}" class="block">
        <h4 class="text-xs font-bold text-gray-800 mb-1 line-clamp-2 hover:text-purple-600 transition">{{ $task->title }}</h4>
    </a>

    {{-- Description snippet --}}
    @if($task->description)
        <p class="text-[10px] text-gray-500 line-clamp-2 mb-2">{{ $task->description }}</p>
    @endif

    {{-- Meta row --}}
    <div class="flex items-center justify-between pt-2 border-t border-gray-50">
        <div class="flex items-center gap-2">
            {{-- Assignee --}}
            @if($task->assignee)
                <div class="flex items-center gap-1" title="Ditugaskan ke: {{ $task->assignee->name }}">
                    <div class="w-5 h-5 rounded-full bg-purple-100 flex items-center justify-center text-[8px] font-bold text-purple-600">
                        {{ strtoupper(substr($task->assignee->name, 0, 1)) }}
                    </div>
                    <span class="text-[9px] text-gray-500 max-w-[60px] truncate">{{ $task->assignee->name }}</span>
                </div>
            @else
                <span class="text-[9px] text-gray-400 italic">Belum ditugaskan</span>
            @endif
        </div>

        <div class="flex items-center gap-1.5">
            {{-- Barn --}}
            @if($task->unitBudidaya)
                <span class="text-[8px] font-medium bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded">{{ $task->unitBudidaya->nama }}</span>
            @endif
            {{-- Due date --}}
            @if($task->due_date)
                <span class="text-[8px] font-medium px-1.5 py-0.5 rounded {{ $isOverdue ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500' }}">
                    {{ $isOverdue ? '⚠ ' : '' }}{{ $task->due_date->format('d M') }}
                </span>
            @endif
        </div>
    </div>
</div>
