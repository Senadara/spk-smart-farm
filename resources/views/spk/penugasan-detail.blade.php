@extends('layouts.app')

@section('title', 'Detail Tugas')
@section('breadcrumb', 'Detail Tugas')

@section('content')
    <div x-data="taskDetailPage()" class="max-w-5xl mx-auto space-y-6" x-cloak>

        {{-- Back + Title --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('spk.tasks.index') }}" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition">
                <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-lg font-bold text-gray-900">{{ $task->title }}</h1>
                <p class="text-[11px] text-gray-400">Dibuat {{ $task->createdAt?->diffForHumans() ?? '-' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- LEFT: Detail + Report Timeline (2/3) --}}
            <div class="lg:col-span-2 space-y-4">

                {{-- Task Info Card --}}
                <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-2">
                            @php
                                $pc = ['urgent' => 'red', 'high' => 'amber', 'medium' => 'blue', 'low' => 'gray'];
                                $pl = ['urgent' => 'Urgent', 'high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah'];
                                $sc = ['todo' => 'gray', 'in_progress' => 'blue', 'done' => 'emerald', 'cancelled' => 'red'];
                                $sl = ['todo' => 'To Do', 'in_progress' => 'Dikerjakan', 'done' => 'Selesai', 'cancelled' => 'Dibatalkan'];
                            @endphp
                            <span class="text-[9px] font-bold uppercase px-2 py-1 rounded-md bg-{{ $pc[$task->priority] ?? 'gray' }}-100 text-{{ $pc[$task->priority] ?? 'gray' }}-700">{{ $pl[$task->priority] ?? '-' }}</span>
                            <span class="text-[9px] font-bold uppercase px-2 py-1 rounded-md bg-{{ $sc[$task->status] ?? 'gray' }}-100 text-{{ $sc[$task->status] ?? 'gray' }}-700">{{ $sl[$task->status] ?? '-' }}</span>
                        </div>
                        {{-- Quick Status Actions --}}
                        <div class="flex items-center gap-1.5">
                            @if($task->status === 'todo')
                                <form method="POST" action="{{ route('spk.tasks.status', $task->id) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="in_progress">
                                    <button type="submit" class="text-[10px] font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition">▶ Mulai Kerjakan</button>
                                </form>
                            @endif
                            @if($task->status === 'in_progress')
                                <button @click="showReportModal = true" class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-lg transition">📝 Kirim Laporan</button>
                            @endif
                            @if($task->status !== 'cancelled' && $task->status !== 'done')
                                <form method="POST" action="{{ route('spk.tasks.status', $task->id) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="text-[10px] font-semibold text-red-600 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition" onclick="return confirm('Batalkan tugas ini?')">✕ Batalkan</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    {{-- Description --}}
                    <div class="prose prose-sm max-w-none text-gray-700 text-sm leading-relaxed">
                        {!! nl2br(e($task->description ?? 'Tidak ada deskripsi.')) !!}
                    </div>
                </div>

                {{-- Report Timeline --}}
                <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                    <h3 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Timeline Laporan Pengerjaan
                    </h3>

                    @if($task->reports->count() > 0)
                        <div class="relative pl-6 space-y-4">
                            {{-- Vertical line --}}
                            <div class="absolute left-2 top-2 bottom-2 w-0.5 bg-gray-200"></div>

                            @foreach($task->reports as $report)
                                <div class="relative">
                                    {{-- Dot --}}
                                    <div class="absolute -left-6 top-1 w-4 h-4 rounded-full border-2 {{ $report->status_update === 'done' ? 'bg-emerald-500 border-emerald-200' : 'bg-blue-500 border-blue-200' }}"></div>

                                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                                        <div class="flex items-center justify-between mb-1.5">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-gray-800">{{ $report->reporter->name ?? 'Unknown' }}</span>
                                                <span class="text-[8px] font-bold uppercase px-1.5 py-0.5 rounded {{ $report->status_update === 'done' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' }}">
                                                    {{ $report->status_update === 'done' ? 'Selesai' : 'Progress' }}
                                                </span>
                                            </div>
                                            <span class="text-[9px] text-gray-400">{{ $report->createdAt?->diffForHumans() ?? '-' }}</span>
                                        </div>
                                        <p class="text-[11px] text-gray-600 leading-relaxed">{{ $report->description }}</p>
                                        @if($report->photo)
                                            <div class="mt-2">
                                                <a href="{{ $report->photo }}" target="_blank" class="text-[10px] text-blue-500 hover:underline flex items-center gap-1">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                    Lihat Bukti Foto
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="w-10 h-10 text-gray-200 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <p class="text-xs text-gray-400">Belum ada laporan pengerjaan</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- RIGHT: Meta Info (1/3) --}}
            <div class="space-y-4">
                {{-- Info Card --}}
                <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm space-y-4">
                    <h4 class="text-xs font-bold text-gray-800 pb-2 border-b border-gray-100">Informasi Tugas</h4>

                    <div>
                        <p class="text-[9px] uppercase font-bold text-gray-400 mb-0.5">Ditugaskan Kepada</p>
                        <p class="text-xs font-semibold text-gray-800">{{ $task->assignee->name ?? '— Belum ditugaskan —' }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase font-bold text-gray-400 mb-0.5">Dibuat Oleh</p>
                        <p class="text-xs font-semibold text-gray-800">{{ $task->assigner->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase font-bold text-gray-400 mb-0.5">Kandang Target</p>
                        <p class="text-xs font-semibold text-gray-800">{{ $task->unitBudidaya->nama ?? 'Umum' }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase font-bold text-gray-400 mb-0.5">Tenggat Waktu</p>
                        <p class="text-xs font-semibold {{ $task->due_date && $task->due_date->isPast() && !in_array($task->status, ['done','cancelled']) ? 'text-red-600' : 'text-gray-800' }}">
                            {{ $task->due_date?->format('d M Y') ?? '— Tidak ada —' }}
                            @if($task->due_date && $task->due_date->isPast() && !in_array($task->status, ['done','cancelled']))
                                <span class="text-[9px] text-red-500 ml-1">(Terlambat)</span>
                            @endif
                        </p>
                    </div>
                    @if($task->completed_at)
                        <div>
                            <p class="text-[9px] uppercase font-bold text-gray-400 mb-0.5">Diselesaikan</p>
                            <p class="text-xs font-semibold text-emerald-600">{{ $task->completed_at->format('d M Y, H:i') }}</p>
                        </div>
                    @endif
                    @if($task->fuzzyLog)
                        <div>
                            <p class="text-[9px] uppercase font-bold text-gray-400 mb-0.5">Sumber Analisa SPK</p>
                            <p class="text-xs font-semibold text-purple-600">{{ Str::limit($task->spk_fuzzy_log_id, 12) }}</p>
                        </div>
                    @endif
                </div>

                {{-- Edit Card --}}
                @if(!in_array($task->status, ['done', 'cancelled']))
                    <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm">
                        <h4 class="text-xs font-bold text-gray-800 pb-2 border-b border-gray-100 mb-3">Edit Tugas</h4>
                        <form method="POST" action="{{ route('spk.tasks.update', $task->id) }}" class="space-y-3">
                            @csrf @method('PUT')
                            <div>
                                <label class="text-[10px] font-semibold text-gray-500">Judul</label>
                                <input type="text" name="title" value="{{ $task->title }}" required class="w-full text-xs border border-gray-200 rounded-lg px-2.5 py-2 mt-0.5 focus:outline-none focus:border-purple-400">
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold text-gray-500">Deskripsi</label>
                                <textarea name="description" rows="2" class="w-full text-xs border border-gray-200 rounded-lg px-2.5 py-2 mt-0.5 focus:outline-none focus:border-purple-400">{{ $task->description }}</textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[10px] font-semibold text-gray-500">Prioritas</label>
                                    <select name="priority" class="w-full text-xs border border-gray-200 rounded-lg px-2.5 py-2 mt-0.5 focus:outline-none focus:border-purple-400">
                                        @foreach(['urgent' => 'Urgent', 'high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah'] as $val => $lbl)
                                            <option value="{{ $val }}" {{ $task->priority === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-gray-500">Tenggat</label>
                                    <input type="date" name="due_date" value="{{ $task->due_date?->format('Y-m-d') }}" class="w-full text-xs border border-gray-200 rounded-lg px-2.5 py-2 mt-0.5 focus:outline-none focus:border-purple-400">
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold text-gray-500">Ditugaskan Kepada</label>
                                <select name="assigned_to" class="w-full text-xs border border-gray-200 rounded-lg px-2.5 py-2 mt-0.5 focus:outline-none focus:border-purple-400">
                                    <option value="">— Belum —</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" {{ $task->assigned_to == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold text-gray-500">Kandang</label>
                                <select name="unit_budidaya_id" class="w-full text-xs border border-gray-200 rounded-lg px-2.5 py-2 mt-0.5 focus:outline-none focus:border-purple-400">
                                    <option value="">— Umum —</option>
                                    @foreach ($barns as $barn)
                                        <option value="{{ $barn->id }}" {{ $task->unit_budidaya_id == $barn->id ? 'selected' : '' }}>{{ $barn->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="w-full text-[10px] font-semibold text-white bg-purple-600 hover:bg-purple-700 py-2 rounded-lg transition">Simpan Perubahan</button>
                        </form>
                    </div>
                @endif

                {{-- Delete --}}
                <form method="POST" action="{{ route('spk.tasks.destroy', $task->id) }}" onsubmit="return confirm('Hapus tugas ini secara permanen?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="w-full text-[10px] font-semibold text-red-600 bg-red-50 hover:bg-red-100 py-2.5 rounded-xl transition border border-red-100">
                        Hapus Tugas Permanen
                    </button>
                </form>
            </div>
        </div>

        {{-- ═══ REPORT MODAL ═══ --}}
        <div x-show="showReportModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showReportModal = false" style="display: none;">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg" @click.stop>
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">📝 Kirim Laporan Pengerjaan</h3>
                </div>
                <form method="POST" action="{{ route('spk.tasks.report', $task->id) }}" class="px-6 py-5 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan Pengerjaan <span class="text-red-500">*</span></label>
                        <textarea name="description" rows="3" required placeholder="Jelaskan apa yang sudah dikerjakan..." class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-blue-400"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">URL Bukti Foto (Opsional)</label>
                        <input type="text" name="photo" placeholder="https://..." class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-blue-400">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Update Status</label>
                        <select name="status_update" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:border-blue-400">
                            <option value="in_progress">Masih Dikerjakan</option>
                            <option value="done">Selesai</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showReportModal = false" class="text-xs text-gray-500 bg-gray-100 hover:bg-gray-200 px-4 py-2.5 rounded-lg transition">Batal</button>
                        <button type="submit" class="text-xs text-white bg-blue-600 hover:bg-blue-700 px-5 py-2.5 rounded-lg transition shadow-sm font-semibold">Kirim Laporan</button>
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
