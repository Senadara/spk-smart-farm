@extends('layouts.app')

@section('title', 'Histori Notifikasi')
@section('breadcrumb', 'Notifikasi')

@section('content')
    @php
        $periodOptions = [
            'all' => 'Semua periode',
            'today' => 'Hari ini',
            'yesterday' => 'Kemarin',
            'last_7_days' => '7 hari terakhir',
            'this_month' => 'Bulan ini',
            'last_month' => 'Bulan lalu',
            'older' => 'Lebih lama',
        ];
        $statusOptions = [
            'all' => 'Semua status',
            'unread' => 'Belum dibaca',
            'read' => 'Sudah dibaca',
        ];
        $toneClasses = [
            'emerald' => 'border-emerald-100 bg-emerald-50 text-emerald-800',
            'sky' => 'border-sky-100 bg-sky-50 text-sky-800',
            'amber' => 'border-amber-100 bg-amber-50 text-amber-800',
            'red' => 'border-red-100 bg-red-50 text-red-800',
            'gray' => 'border-gray-100 bg-gray-50 text-gray-700',
        ];
        $badgeClasses = [
            'sky' => 'border-sky-200 bg-sky-50 text-sky-700',
            'gray' => 'border-gray-200 bg-gray-50 text-gray-600',
            'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
            'red' => 'border-red-200 bg-red-50 text-red-700',
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-4">
        <section class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold text-gray-950">Histori Notifikasi</h1>
                    <p class="mt-1 text-sm text-gray-500">Pantau notifikasi SPK, siklus ternak, dan log IoT berdasarkan status baca dan waktu masuk.</p>
                </div>

                <form method="GET" action="{{ route('notifications.index') }}" class="grid grid-cols-1 gap-2 sm:grid-cols-3 lg:min-w-[560px]">
                    <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        Status
                        <select name="status" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm normal-case tracking-normal text-gray-700 focus:border-emerald-400 focus:outline-none">
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        Periode
                        <select name="period" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm normal-case tracking-normal text-gray-700 focus:border-emerald-400 focus:outline-none">
                            @foreach($periodOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['period'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        Baris
                        <div class="mt-1 flex gap-2">
                            <select name="per_page" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm normal-case tracking-normal text-gray-700 focus:border-emerald-400 focus:outline-none">
                                @foreach([10, 15, 25, 50] as $limit)
                                    <option value="{{ $limit }}" @selected((int) $filters['per_page'] === $limit)>{{ $limit }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="rounded-lg bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-700">Terapkan</button>
                        </div>
                    </label>
                </form>
            </div>
        </section>

        @forelse($sections as $section)
            @php
                $meta = $section['meta'];
                $sectionTone = $toneClasses[$meta['tone']] ?? $toneClasses['gray'];
            @endphp
            <section class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="flex flex-col gap-2 border-b border-gray-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full {{ str_contains($sectionTone, 'emerald') ? 'bg-emerald-500' : (str_contains($sectionTone, 'sky') ? 'bg-sky-500' : (str_contains($sectionTone, 'amber') ? 'bg-amber-500' : 'bg-gray-400')) }}"></span>
                            <h2 class="text-sm font-semibold text-gray-900">{{ $meta['label'] }}</h2>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ $meta['caption'] }}</p>
                    </div>
                    <span class="inline-flex w-fit rounded-full border px-2.5 py-1 text-xs font-semibold {{ $sectionTone }}">
                        {{ $section['items']->count() }} tampil
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[780px] text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Notifikasi</th>
                                <th class="px-4 py-3 text-left font-semibold">Sumber</th>
                                <th class="px-4 py-3 text-left font-semibold">Tanda</th>
                                <th class="px-4 py-3 text-left font-semibold">Waktu</th>
                                <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($section['items'] as $event)
                                @php
                                    $statusTone = $badgeClasses[$event['status_tone']] ?? $badgeClasses['gray'];
                                    $severityTone = $event['tone'] === 'red' ? $badgeClasses['red'] : $badgeClasses['amber'];
                                @endphp
                                <tr class="{{ $event['is_unread'] ? 'bg-sky-50/45' : 'bg-white' }}">
                                    <td class="px-4 py-3">
                                        <div class="flex gap-3">
                                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $event['is_unread'] ? 'bg-sky-500' : 'bg-gray-300' }}"></span>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-gray-900">{{ $event['title'] }}</p>
                                                <p class="mt-1 line-clamp-2 text-xs leading-5 text-gray-500">{{ $event['message'] }}</p>
                                                @if(!empty($event['unit_name']))
                                                    <p class="mt-1 text-[11px] font-medium text-gray-400">{{ $event['unit_name'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="space-y-1">
                                            <p class="text-xs font-semibold text-gray-700">{{ $event['source'] }}</p>
                                            <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $severityTone }}">
                                                {{ $event['severity_label'] }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusTone }}">
                                            {{ $event['status_label'] }}
                                        </span>
                                        <p class="mt-1 text-[11px] text-gray-400">{{ $event['status_caption'] }}</p>
                                        @if($event['read_at'])
                                            <p class="text-[11px] text-gray-400">{{ $event['read_at'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="text-xs font-semibold text-gray-700">{{ $event['created_at'] }}</p>
                                        <p class="mt-1 text-[11px] text-gray-400">{{ $event['created_at_human'] }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ $event['url'] }}" class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" style="text-decoration:none;">
                                                Buka
                                            </a>
                                            @if(!empty($event['mark_read_url']) && !empty($event['mark_unread_url']))
                                                <form method="POST" action="{{ $event['is_unread'] ? $event['mark_read_url'] : $event['mark_unread_url'] }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:border-emerald-200 hover:text-emerald-700">
                                                        {{ $event['is_unread'] ? 'Tandai dibaca' : 'Tandai belum' }}
                                                    </button>
                                                </form>
                                            @else
                                                <span class="inline-flex items-center justify-center rounded-lg border border-gray-100 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-500">
                                                    Log sistem
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <section class="rounded-xl border border-dashed border-gray-200 bg-white p-10 text-center shadow-sm">
                <p class="text-sm font-semibold text-gray-700">Belum ada notifikasi pada filter ini.</p>
                <p class="mt-1 text-xs text-gray-500">Ubah periode atau status untuk melihat histori lainnya.</p>
            </section>
        @endforelse

        @if($events->hasPages())
            <div class="rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
                {{ $events->links() }}
            </div>
        @endif
    </div>
@endsection
