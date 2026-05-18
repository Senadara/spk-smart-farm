<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use App\Models\SpkActionTask;
use App\Models\SpkActionReport;
use App\Models\SpkFuzzyLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpkTaskController extends Controller
{
    /**
     * Halaman utama Penugasan — Kanban + Tabel.
     */
    public function index(Request $request)
    {
        $tab            = $request->input('tab', 'active'); // active | history
        $statusFilter   = $request->input('status', 'all');
        $priorityFilter = $request->input('priority', 'all');
        $search         = $request->input('search', '');
        
        $userFilter     = $request->input('user_id', 'all');
        $startDate      = $request->input('start_date', '');
        $endDate        = $request->input('end_date', '');

        // Petugas & Kandang options
        $users = DB::table('users')->select('id', 'name', 'email')->get();
        $jenis = DB::table('jenisBudidaya')->where('nama', 'like', '%Ayam Petelur%')->where('isDeleted', 0)->first();
        $barns = DB::table('unitBudidaya')
            ->where('jenisBudidayaId', $jenis?->id)
            ->where('status', 1)
            ->where('isDeleted', 0)
            ->get(['id', 'nama']);

        // 1. ACTIVE TASKS (Kanban)
        $activeQuery = SpkActionTask::with(['assignee', 'assigner', 'unitBudidaya', 'fuzzyLog'])
            ->whereIn('status', ['todo', 'in_progress'])
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->orderBy('due_date', 'asc')
            ->orderBy('createdAt', 'desc');

        if ($statusFilter !== 'all' && in_array($statusFilter, ['todo', 'in_progress'])) {
            $activeQuery->where('status', $statusFilter);
        }
        if ($priorityFilter !== 'all') {
            $activeQuery->where('priority', $priorityFilter);
        }
        if ($userFilter !== 'all') {
            $activeQuery->where('assigned_to', $userFilter);
        }
        if ($search) {
            $activeQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        $activeTasks = $activeQuery->get();

        $kanban = [
            'todo'        => $activeTasks->where('status', 'todo')->values(),
            'in_progress' => $activeTasks->where('status', 'in_progress')->values(),
        ];

        // 2. HISTORY TASKS (Table)
        $historyQuery = SpkActionTask::with(['assignee', 'assigner', 'unitBudidaya', 'fuzzyLog'])
            ->whereIn('status', ['done', 'cancelled'])
            ->orderBy('completed_at', 'desc')
            ->orderBy('createdAt', 'desc');
            
        if ($userFilter !== 'all') {
            $historyQuery->where('assigned_to', $userFilter);
        }
        if ($statusFilter !== 'all' && in_array($statusFilter, ['done', 'cancelled'])) {
            $historyQuery->where('status', $statusFilter);
        }
        if ($startDate) {
            $historyQuery->whereDate('completed_at', '>=', $startDate);
        }
        if ($endDate) {
            $historyQuery->whereDate('completed_at', '<=', $endDate);
        }
        if ($search) {
            $historyQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        $historyTasks = $historyQuery->paginate(15)->withQueryString();

        // Stats
        $stats = [
            'total'       => SpkActionTask::count(),
            'todo'        => SpkActionTask::where('status', 'todo')->count(),
            'in_progress' => SpkActionTask::where('status', 'in_progress')->count(),
            'done'        => SpkActionTask::where('status', 'done')->count(),
            'overdue'     => SpkActionTask::whereIn('status', ['todo', 'in_progress'])
                                ->whereNotNull('due_date')
                                ->where('due_date', '<', now()->toDateString())
                                ->count(),
        ];

        // Recent SPK Log for selection
        $recentSpks = SpkFuzzyLog::with('unitBudidaya')
            ->whereNotNull('narrative')
            ->orderBy('createdAt', 'desc')
            ->limit(30)
            ->get();

        $prefill = [
            'showModal' => $request->has('create_task'),
            'spk_id'    => $request->input('spk_id', ''),
            'coop_id'   => $request->input('coop_id', ''),
            'desc'      => $request->input('desc', ''),
        ];

        return view('spk.penugasan', compact(
            'kanban', 'historyTasks', 'stats', 'users', 'barns', 'recentSpks',
            'tab', 'statusFilter', 'priorityFilter', 'userFilter', 'startDate', 'endDate', 'search', 'prefill'
        ));
    }

    /**
     * Simpan tugas baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority'    => 'required|in:urgent,high,medium,low',
            'assigned_to' => 'nullable|string',
            'unit_budidaya_id'  => 'nullable|string',
            'spk_fuzzy_log_id'  => 'nullable|string',
            'due_date'    => 'nullable|date',
        ]);

        $userId = session('user.id') ?? session('user_id');

        SpkActionTask::create([
            'title'             => $request->title,
            'description'       => $request->description,
            'priority'          => $request->priority,
            'status'            => 'todo',
            'assigned_to'       => $request->assigned_to,
            'assigned_by'       => $userId,
            'unit_budidaya_id'  => $request->unit_budidaya_id,
            'spk_fuzzy_log_id'  => $request->spk_fuzzy_log_id,
            'due_date'          => $request->due_date,
        ]);

        return redirect()->route('spk.tasks.index')
            ->with('success', 'Tugas berhasil dibuat.');
    }

    /**
     * Update status tugas (drag-drop kanban atau tombol).
     */
    public function updateStatus(Request $request, string $id)
    {
        $request->validate([
            'status' => 'required|in:todo,in_progress,done,cancelled',
        ]);

        $task = SpkActionTask::findOrFail($id);
        $task->status = $request->status;

        if ($request->status === 'done') {
            $task->completed_at = now();
        } elseif ($task->completed_at && $request->status !== 'done') {
            $task->completed_at = null;
        }

        $task->save();

        return redirect()->back()->with('success', 'Status tugas diperbarui.');
    }

    /**
     * Update tugas (edit detail).
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority'    => 'required|in:urgent,high,medium,low',
            'assigned_to' => 'nullable|string',
            'unit_budidaya_id' => 'nullable|string',
            'due_date'    => 'nullable|date',
        ]);

        $task = SpkActionTask::findOrFail($id);
        $task->update($request->only([
            'title', 'description', 'priority', 'assigned_to', 'unit_budidaya_id', 'due_date'
        ]));

        return redirect()->route('spk.tasks.index')
            ->with('success', 'Tugas berhasil diperbarui.');
    }

    /**
     * Hapus tugas.
     */
    public function destroy(string $id)
    {
        $task = SpkActionTask::findOrFail($id);
        $task->delete();

        return redirect()->route('spk.tasks.index')
            ->with('success', 'Tugas berhasil dihapus.');
    }

    /**
     * Submit laporan pengerjaan tugas.
     */
    public function submitReport(Request $request, string $taskId)
    {
        $request->validate([
            'description'   => 'required|string',
            'status_update' => 'required|in:in_progress,done',
            'photo'         => 'nullable|string|max:255',
        ]);

        $userId = session('user.id') ?? session('user_id');

        SpkActionReport::create([
            'task_id'       => $taskId,
            'reported_by'   => $userId,
            'description'   => $request->description,
            'photo'         => $request->photo,
            'status_update' => $request->status_update,
        ]);

        // Update task status
        $task = SpkActionTask::findOrFail($taskId);
        $task->status = $request->status_update;
        if ($request->status_update === 'done') {
            $task->completed_at = now();
        }
        $task->save();

        return redirect()->route('spk.tasks.index')
            ->with('success', 'Laporan pengerjaan berhasil disubmit.');
    }

    /**
     * Lihat detail tugas + timeline laporan.
     */
    public function show(string $id)
    {
        $task = SpkActionTask::with(['assignee', 'assigner', 'unitBudidaya', 'fuzzyLog', 'reports.reporter'])
            ->findOrFail($id);

        $users = DB::table('users')->select('id', 'name', 'email')->get();

        $jenis = DB::table('jenisBudidaya')->where('nama', 'like', '%Ayam Petelur%')->where('isDeleted', 0)->first();
        $barns = DB::table('unitBudidaya')
            ->where('jenisBudidayaId', $jenis?->id)
            ->where('status', 1)
            ->where('isDeleted', 0)
            ->get(['id', 'nama']);

        return view('spk.penugasan-detail', compact('task', 'users', 'barns'));
    }
}
