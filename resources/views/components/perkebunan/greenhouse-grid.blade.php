@props([
    'data' => ['blocks' => []],
])

@php
    $blocks  = $data['blocks'] ?? [];
@endphp

<div class="h-full flex flex-col" x-data="{
        activeBlock: 0,
        blocks: {{ json_encode($blocks) }},
        getColorClass(status, type) {
            const config = {
                'normal':   { ring: 'ring-emerald-400', bgBorder: 'border-emerald-300 bg-emerald-50 text-emerald-700', text: 'text-emerald-600', dot: 'bg-emerald-500' },
                'warning':  { ring: 'ring-amber-400', bgBorder: 'border-amber-300 bg-amber-50 text-amber-700', text: 'text-amber-600', dot: 'bg-amber-500' },
                'critical': { ring: 'ring-red-400', bgBorder: 'border-red-300 bg-red-50 text-red-700', text: 'text-red-600', dot: 'bg-red-500' }
            };
            return config[status] ? config[status][type] : config['normal'][type];
        }
    }">
    
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-[var(--color-gray-900)]">Kondisi Greenhouse</h3>
        {{-- TODO: [DASH-03] Replace href with actual monitoring page route --}}
        <a href="{{ route('plant-monitoring.index') ?? '#' }}"
           class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 hover:text-emerald-700 transition-colors">
            Lihat Detail
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        </a>
    </div>

    {{-- Grid Cards (Clickable) --}}
    @if(!empty($blocks))
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-5 flex-1 content-start">
            <template x-for="(block, index) in blocks" :key="index">
                <button
                    @click="activeBlock = index"
                    :class="[
                        getColorClass(block.status, 'bgBorder'),
                        activeBlock === index ? 'ring-2 shadow-md scale-105 ' + getColorClass(block.status, 'ring') : 'hover:shadow-sm hover:scale-[1.02] opacity-75 hover:opacity-100'
                    ]"
                    class="relative rounded-xl border px-3 py-3 text-center cursor-pointer transition-all duration-200"
                >
                    <p class="text-xs font-medium truncate" x-text="block.name"></p>
                    <p class="text-2xl font-bold mt-0.5" x-text="block.suhu + '°'"></p>
                </button>
            </template>
        </div>
    @endif

    {{-- Dynamic Summary Row (2x2 Grid) --}}
    <div class="grid grid-cols-2 gap-3 mt-auto border-t border-[var(--color-gray-100)] pt-4" x-show="blocks.length > 0" x-cloak>
        
        {{-- pH --}}
        <div class="flex items-center gap-2.5 p-2 rounded-lg bg-[var(--color-gray-50)] border border-[var(--color-gray-100)] group relative cursor-help">
            <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center shadow-sm shrink-0">
                <i data-lucide="file-text" class="w-4 h-4 text-gray-500"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold mb-0.5">pH Tanah</p>
                <p class="text-sm font-bold flex items-center gap-1.5" :class="getColorClass(blocks[activeBlock].summary.ph_status, 'text')">
                    <span x-text="blocks[activeBlock].summary.ph"></span>
                    <span class="w-1.5 h-1.5 rounded-full" :class="getColorClass(blocks[activeBlock].summary.ph_status, 'dot')"></span>
                </p>
            </div>
             {{-- Tooltip --}}
             <div class="absolute z-50 bottom-full mb-2 left-0 w-48 p-3 bg-white rounded-xl border border-[var(--color-gray-100)] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all pointer-events-none" style="box-shadow: var(--shadow-lg);">
                 <p class="font-semibold text-xs text-[var(--color-gray-900)] mb-1">pH Tanah</p>
                 <p class="text-[11px] text-[var(--color-gray-600)] leading-relaxed font-normal">Tingkat keasaman tanah untuk pertumbuhan akar melon. Ideal: 6.0 - 7.0</p>
             </div>
        </div>

        {{-- Suhu --}}
        <div class="flex items-center gap-2.5 p-2 rounded-lg bg-[var(--color-gray-50)] border border-[var(--color-gray-100)] group relative cursor-help">
            <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center shadow-sm shrink-0">
                <i data-lucide="thermometer" class="w-4 h-4 text-gray-500"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold mb-0.5">Suhu</p>
                <p class="text-sm font-bold flex items-center gap-1.5" :class="getColorClass(blocks[activeBlock].summary.suhu_status, 'text')">
                    <span x-text="blocks[activeBlock].summary.suhu + ' °C'"></span>
                    <span class="w-1.5 h-1.5 rounded-full" :class="getColorClass(blocks[activeBlock].summary.suhu_status, 'dot')"></span>
                </p>
            </div>
             {{-- Tooltip --}}
             <div class="absolute z-50 bottom-full mb-2 right-0 sm:left-0 sm:right-auto w-48 p-3 bg-white rounded-xl border border-[var(--color-gray-100)] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all pointer-events-none" style="box-shadow: var(--shadow-lg);">
                 <p class="font-semibold text-xs text-[var(--color-gray-900)] mb-1">Suhu Udara</p>
                 <p class="text-[11px] text-[var(--color-gray-600)] leading-relaxed font-normal">Suhu udara di greenhouse yang dipilih. Ideal: 25 - 30 °C</p>
             </div>
        </div>

        {{-- EC --}}
        <div class="flex items-center gap-2.5 p-2 rounded-lg bg-[var(--color-gray-50)] border border-[var(--color-gray-100)] group relative cursor-help">
            <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center shadow-sm shrink-0">
                <i data-lucide="zap" class="w-4 h-4 text-gray-500"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold mb-0.5">Kadar EC</p>
                <p class="text-sm font-bold flex items-center gap-1.5" :class="getColorClass(blocks[activeBlock].summary.ec_status, 'text')">
                    <span x-text="blocks[activeBlock].summary.ec + ' mS'"></span>
                    <span class="w-1.5 h-1.5 rounded-full" :class="getColorClass(blocks[activeBlock].summary.ec_status, 'dot')"></span>
                </p>
            </div>
             {{-- Tooltip --}}
             <div class="absolute z-50 bottom-full mb-2 left-0 w-48 p-3 bg-white rounded-xl border border-[var(--color-gray-100)] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all pointer-events-none" style="box-shadow: var(--shadow-lg);">
                 <p class="font-semibold text-xs text-[var(--color-gray-900)] mb-1">Electrical Conductivity (EC)</p>
                 <p class="text-[11px] text-[var(--color-gray-600)] leading-relaxed font-normal">Konduktivitas listrik larutan nutrisi. Ideal: 2.0 - 3.5 mS/cm</p>
             </div>
        </div>

        {{-- Kelembapan --}}
        <div class="flex items-center gap-2.5 p-2 rounded-lg bg-[var(--color-gray-50)] border border-[var(--color-gray-100)] group relative cursor-help">
            <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center shadow-sm shrink-0">
                <i data-lucide="droplets" class="w-4 h-4 text-gray-500"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold mb-0.5">Kelembapan</p>
                <p class="text-sm font-bold flex items-center gap-1.5" :class="getColorClass(blocks[activeBlock].summary.kelembapan_status, 'text')">
                    <span x-text="blocks[activeBlock].summary.kelembapan + '%'"></span>
                    <span class="w-1.5 h-1.5 rounded-full" :class="getColorClass(blocks[activeBlock].summary.kelembapan_status, 'dot')"></span>
                </p>
            </div>
             {{-- Tooltip --}}
             <div class="absolute z-50 bottom-full mb-2 right-0 w-48 p-3 bg-white rounded-xl border border-[var(--color-gray-100)] opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all pointer-events-none" style="box-shadow: var(--shadow-lg);">
                 <p class="font-semibold text-xs text-[var(--color-gray-900)] mb-1">Kelembapan Udara</p>
                 <p class="text-[11px] text-[var(--color-gray-600)] leading-relaxed font-normal">Kelembapan udara di greenhouse yang dipilih. Ideal: 60 - 85 %</p>
             </div>
        </div>

    </div>
</div>

