@props(['ticket'])

@php
    $pColor = ['High' => 'text-red-600 bg-red-100', 'Medium' => 'text-amber-600 bg-amber-100', 'Low' => 'text-blue-600 bg-blue-100'][$ticket['priority']] ?? 'text-gray-600 bg-gray-100';
    $iconMap = [
        'Fuzzy Engine' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
        'Causality Analysis' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>',
        'AHP-SAW Restock' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>',
    ];
    $iconPath = $iconMap[$ticket['source']] ?? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>';
@endphp

<div class="bg-white p-3 rounded-lg border border-gray-100 shadow-sm hover:shadow-md hover:border-emerald-200 transition-all cursor-move group">
    <div class="flex items-start justify-between mb-2">
        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded {{ $pColor }}">{{ $ticket['priority'] }} Priority</span>
        <span class="text-[9px] font-mono text-gray-400">#{{ $ticket['id'] }}</span>
    </div>
    
    <h5 class="text-xs font-bold text-gray-900 leading-tight mb-2 group-hover:text-emerald-700 transition-colors">{{ $ticket['title'] }}</h5>
    
    <div class="flex flex-col gap-2 mt-3 pt-2 border-t border-gray-50">
        <div class="flex items-center gap-1.5 text-[10px] text-gray-500">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">{!! $iconPath !!}</svg>
            <span class="truncate">{{ $ticket['source'] }}</span>
        </div>
        
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5">
                <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[9px] font-bold">
                    {{ substr($ticket['assignee'], 0, 1) }}
                </div>
                <span class="text-[10px] font-medium text-gray-600">{{ $ticket['assignee'] }}</span>
            </div>
            
            <button class="text-gray-400 hover:text-emerald-600 transition group/btn p-1">
                <svg class="w-4 h-4 opacity-50 group-hover/btn:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
            </button>
        </div>
    </div>
</div>
