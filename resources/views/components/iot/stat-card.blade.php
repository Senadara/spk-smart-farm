@props([
    'label' => '',
    'value' => '0',
    'color' => 'blue',
    'icon'  => 'cpu',
])

@php
    $colorMap = [
        'blue'    => ['bg' => '#EBF5FF', 'text' => '#1E40AF', 'icon' => '#3B82F6'],
        'emerald' => ['bg' => '#ECFDF5', 'text' => '#065F46', 'icon' => '#10B981'],
        'gray'    => ['bg' => '#F3F4F6', 'text' => '#374151', 'icon' => '#6B7280'],
        'amber'   => ['bg' => '#FFFBEB', 'text' => '#92400E', 'icon' => '#F59E0B'],
        'purple'  => ['bg' => '#F5F3FF', 'text' => '#5B21B6', 'icon' => '#8B5CF6'],
        'rose'    => ['bg' => '#FFF1F2', 'text' => '#9F1239', 'icon' => '#F43F5E'],
    ];
    $c = $colorMap[$color] ?? $colorMap['blue'];

    $icons = [
        'cpu'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m-2 6h2m14-6h2m-2 6h2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>',
        'check'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>',
        'pause'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'wrench' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>',
        'link'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>',
        'chart'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
    ];
    $iconPath = $icons[$icon] ?? $icons['cpu'];
@endphp

<div class="rounded-2xl p-4 flex items-center gap-4 transition-all duration-200 hover:shadow-md"
    style="background: {{ $c['bg'] }};">
    <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
        style="background: {{ $c['icon'] }}15;">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="{{ $c['icon'] }}" stroke-width="2">
            {!! $iconPath !!}
        </svg>
    </div>
    <div>
        <div class="text-2xl font-bold" style="color: {{ $c['text'] }};">{{ $value }}</div>
        <div class="text-xs font-medium mt-0.5" style="color: {{ $c['text'] }}80;">{{ $label }}</div>
    </div>
</div>
