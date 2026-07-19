<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use App\Models\SpkActionReport;
use App\Models\SpkActionTask;
use App\Models\SpkFuzzyLog;
use App\Services\Fuzzy\NarrativeGenerator;
use App\Services\Health\BarnHealthContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SpkTaskController extends Controller
{
    public function __construct(
        protected BarnHealthContextService $barnHealthContextService,
    ) {}

    /**
     * Halaman utama penugasan.
     */
    public function index(Request $request)
    {
        $tab = $request->input('tab', 'active');
        $statusFilter = $request->input('status', 'all');
        $priorityFilter = $request->input('priority', 'all');
        $search = $request->input('search', '');
        $userFilter = $request->input('user_id', 'all');
        $startDate = $request->input('start_date', '');
        $endDate = $request->input('end_date', '');

        if ($this->role() === 'petugas') {
            $userFilter = $this->currentUserId();
        }

        $users = $this->petugasQuery()->get();

        $barns = DB::table('unitBudidaya')
            ->where('status', 1)
            ->where('isDeleted', 0)
            ->orderBy('nama')
            ->get(['id', 'nama']);

        $activeQuery = SpkActionTask::with(['assignee', 'assigner', 'unitBudidaya', 'fuzzyLog'])
            ->whereIn('status', ['todo', 'in_progress'])
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
            ->orderBy('due_date', 'asc')
            ->orderBy('createdAt', 'desc');

        if ($statusFilter !== 'all' && in_array($statusFilter, ['todo', 'in_progress'], true)) {
            $activeQuery->where('status', $statusFilter);
        }

        if ($priorityFilter !== 'all' && in_array($priorityFilter, ['urgent', 'high', 'medium', 'low'], true)) {
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
            'todo' => $activeTasks->where('status', 'todo')->values(),
            'in_progress' => $activeTasks->where('status', 'in_progress')->values(),
        ];

        $historyQuery = SpkActionTask::with(['assignee', 'assigner', 'unitBudidaya', 'fuzzyLog'])
            ->whereIn('status', ['done', 'cancelled'])
            ->orderBy('completed_at', 'desc')
            ->orderBy('createdAt', 'desc');

        if ($userFilter !== 'all') {
            $historyQuery->where('assigned_to', $userFilter);
        }

        if ($statusFilter !== 'all' && in_array($statusFilter, ['done', 'cancelled'], true)) {
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

        $stats = [
            'total' => SpkActionTask::count(),
            'todo' => SpkActionTask::where('status', 'todo')->count(),
            'in_progress' => SpkActionTask::where('status', 'in_progress')->count(),
            'done' => SpkActionTask::where('status', 'done')->count(),
            'overdue' => SpkActionTask::whereIn('status', ['todo', 'in_progress'])
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->count(),
        ];

        $recentSpks = SpkFuzzyLog::with('unitBudidaya')
            ->whereNotNull('narrative')
            ->when($barns->isNotEmpty(), function ($query) use ($barns) {
                $query->where(function ($q) use ($barns) {
                    $q->whereIn('unit_budidaya_id', $barns->pluck('id')->all())
                        ->orWhereNull('unit_budidaya_id');
                });
            })
            ->orderBy('createdAt', 'desc')
            ->limit(30)
            ->get();

        $taskPlans = $this->role() === 'pjawab'
            ? $this->buildTaskPlans($recentSpks, $users, $barns->pluck('nama', 'id'))
            : collect();
        $healthTaskPlans = $this->role() === 'pjawab'
            ? $this->barnHealthContextService->taskPlansForBarns($barns, $users)
            : collect();

        $prefill = [
            'showModal' => $request->has('create_task'),
            'spk_id' => $request->input('spk_id', ''),
            'coop_id' => $request->input('coop_id', ''),
            'desc' => $request->input('desc', ''),
            'title' => $request->input('title', ''),
            'priority' => $request->input('priority', 'medium'),
            'assigned_to' => $request->input('assigned_to', ''),
            'due_date' => $request->input('due_date', ''),
        ];

        return view('spk.penugasan', compact(
            'kanban',
            'historyTasks',
            'stats',
            'users',
            'barns',
            'recentSpks',
            'taskPlans',
            'healthTaskPlans',
            'tab',
            'statusFilter',
            'priorityFilter',
            'userFilter',
            'startDate',
            'endDate',
            'search',
            'prefill'
        ));
    }

    /**
     * Simpan tugas baru.
     */
    public function store(Request $request)
    {
        $this->requirePjawab();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:urgent,high,medium,low',
            'assigned_to' => ['nullable', 'string', Rule::in($this->petugasIds())],
            'unit_budidaya_id' => 'nullable|string',
            'spk_fuzzy_log_id' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        SpkActionTask::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'],
            'status' => 'todo',
            'assigned_to' => $validated['assigned_to'] ?? null,
            'assigned_by' => $this->currentUserId(),
            'unit_budidaya_id' => $validated['unit_budidaya_id'] ?? null,
            'spk_fuzzy_log_id' => $validated['spk_fuzzy_log_id'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
        ]);

        return redirect()->route('spk.tasks.index')
            ->with('success', 'Tugas berhasil dibuat.');
    }

    /**
     * Update status tugas.
     */
    public function updateStatus(Request $request, string $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:todo,in_progress,done,cancelled',
        ]);

        $task = SpkActionTask::findOrFail($id);
        $this->authorizeTaskWork($task);

        if ($this->role() === 'petugas' && $validated['status'] === 'cancelled') {
            abort(403, 'Petugas tidak dapat membatalkan tugas.');
        }

        $task->status = $validated['status'];

        if ($validated['status'] === 'done') {
            $task->completed_at = now();
        } elseif ($task->completed_at) {
            $task->completed_at = null;
        }

        $task->save();

        return redirect()->back()->with('success', 'Status tugas diperbarui.');
    }

    /**
     * Update tugas dari halaman detail.
     */
    public function update(Request $request, string $id)
    {
        $this->requirePjawab();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:urgent,high,medium,low',
            'assigned_to' => ['nullable', 'string', Rule::in($this->petugasIds())],
            'unit_budidaya_id' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        $task = SpkActionTask::findOrFail($id);
        $this->authorizeTaskOwner($task);

        $task->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'],
            'assigned_to' => $validated['assigned_to'] ?? null,
            'unit_budidaya_id' => $validated['unit_budidaya_id'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
        ]);

        return redirect()->route('spk.tasks.index')
            ->with('success', 'Tugas berhasil diperbarui.');
    }

    /**
     * Hapus tugas.
     */
    public function destroy(string $id)
    {
        $this->requirePjawab();

        $task = SpkActionTask::findOrFail($id);
        $this->authorizeTaskOwner($task);
        $task->delete();

        return redirect()->route('spk.tasks.index')
            ->with('success', 'Tugas berhasil dihapus.');
    }

    /**
     * Submit laporan pengerjaan tugas.
     */
    public function submitReport(Request $request, string $taskId)
    {
        $validated = $request->validate([
            'description' => 'required|string',
            'status_update' => 'required|in:in_progress,done',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $task = SpkActionTask::findOrFail($taskId);
        $this->authorizeTaskWork($task);
        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store("spk-reports/{$taskId}", 'public')
            : null;

        SpkActionReport::create([
            'task_id' => $taskId,
            'reported_by' => $this->currentUserId(),
            'description' => $validated['description'],
            'photo' => $photoPath,
            'status_update' => $validated['status_update'],
        ]);

        $task->status = $validated['status_update'];
        $task->completed_at = $validated['status_update'] === 'done' ? now() : null;
        $task->save();

        return redirect()->route('spk.tasks.index')
            ->with('success', 'Laporan pengerjaan berhasil disubmit.');
    }

    /**
     * Lihat detail tugas dan timeline laporan.
     */
    public function show(string $id)
    {
        $task = SpkActionTask::with(['assignee', 'assigner', 'unitBudidaya', 'fuzzyLog', 'reports.reporter'])
            ->findOrFail($id);

        $this->authorizeTaskAccess($task);

        $users = $this->petugasQuery()->get();

        $barns = DB::table('unitBudidaya')
            ->where('status', 1)
            ->where('isDeleted', 0)
            ->orderBy('nama')
            ->get(['id', 'nama']);

        return view('spk.penugasan-detail', compact('task', 'users', 'barns'));
    }

    private function buildTaskPlans(Collection $recentSpks, Collection $users, Collection $barnNames): Collection
    {
        if ($users->isEmpty()) {
            return collect();
        }

        $activeSpkIds = SpkActionTask::whereIn('status', ['todo', 'in_progress'])
            ->whereNotNull('spk_fuzzy_log_id')
            ->pluck('spk_fuzzy_log_id')
            ->all();

        $activeCounts = SpkActionTask::select('assigned_to', DB::raw('COUNT(*) as total'))
            ->whereIn('status', ['todo', 'in_progress'])
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        return $recentSpks
            ->reject(fn ($spk) => in_array($spk->id, $activeSpkIds, true))
            ->filter(fn ($spk) => $this->isActionableSpk($spk))
            ->unique(fn ($spk) => $spk->unit_budidaya_id ?: 'global')
            ->take(6)
            ->values()
            ->map(function ($spk) use ($users, $activeCounts, $barnNames) {
                $priority = $this->priorityForSpk($spk);
                $assignee = $users
                    ->sortBy(fn ($user) => (int) ($activeCounts[$user->id] ?? 0))
                    ->first();

                $barnName = $spk->unitBudidaya->nama
                    ?? ($spk->unit_budidaya_id ? ($barnNames->get($spk->unit_budidaya_id) ?? 'Kandang tidak dikenal') : 'Kandang umum');
                $score = is_numeric($spk->output_value) ? round((float) $spk->output_value, 1) : null;
                $reason = $this->reasonForSpk($spk, $score);
                $title = 'Tindak lanjut SPK - '.$barnName;
                $spkText = NarrativeGenerator::sanitizePlainText($spk->recommendation)
                    ?: NarrativeGenerator::sanitizePlainText($spk->narrative)
                    ?: 'Tindak lanjuti hasil analisa SPK.';
                $description = trim($spkText."\n\nKonteks: ".$reason);
                $dueDate = now()->addDays(match ($priority) {
                    'urgent' => 0,
                    'high' => 1,
                    'medium' => 3,
                    default => 7,
                })->toDateString();

                $params = [
                    'create_task' => 1,
                    'spk_id' => $spk->id,
                    'coop_id' => $spk->unit_budidaya_id,
                    'title' => $title,
                    'priority' => $priority,
                    'assigned_to' => $assignee?->id,
                    'due_date' => $dueDate,
                    'desc' => Str::limit($description, 500, ''),
                ];

                return [
                    'spk_id' => $spk->id,
                    'title' => $title,
                    'barn' => $barnName,
                    'score' => $score,
                    'reason' => $reason,
                    'priority' => $priority,
                    'priorityLabel' => $this->priorityLabel($priority),
                    'priorityClass' => $this->priorityClass($priority),
                    'assignee' => $assignee?->name ?? 'Belum ada petugas',
                    'due_date' => $dueDate,
                    'recommendation' => Str::limit(
                        NarrativeGenerator::sanitizePlainText($spk->recommendation)
                            ?: NarrativeGenerator::sanitizePlainText($spk->narrative)
                            ?: 'Tindak lanjuti hasil SPK.',
                        110
                    ),
                    'url' => route('spk.tasks.index', array_filter($params, fn ($value) => filled($value))),
                ];
            });
    }

    private function isActionableSpk(SpkFuzzyLog $spk): bool
    {
        $score = is_numeric($spk->output_value) ? (float) $spk->output_value : null;

        if ($score !== null && $score < 85) {
            return true;
        }

        $statuses = collect([$spk->status_lingkungan, $spk->status_kesehatan, $spk->output_label])
            ->filter()
            ->map(fn ($status) => Str::lower($status));

        return $statuses->contains(function ($status) {
            return Str::contains($status, ['waspada', 'buruk', 'kritis', 'darurat', 'rendah', 'tidak sehat']);
        });
    }

    private function priorityForSpk(SpkFuzzyLog $spk): string
    {
        $score = is_numeric($spk->output_value) ? (float) $spk->output_value : null;
        $statusText = Str::lower(implode(' ', array_filter([
            $spk->status_lingkungan,
            $spk->status_kesehatan,
            $spk->output_label,
        ])));

        if (Str::contains($statusText, ['buruk', 'kritis', 'darurat']) || ($score !== null && $score < 55)) {
            return 'urgent';
        }

        if (Str::contains($statusText, ['waspada', 'rendah', 'tidak sehat']) || ($score !== null && $score < 70)) {
            return 'high';
        }

        if ($score !== null && $score < 85) {
            return 'medium';
        }

        return 'low';
    }

    private function reasonForSpk(SpkFuzzyLog $spk, ?float $score): string
    {
        $parts = [];

        if ($score !== null) {
            $parts[] = 'Skor SPK '.$score;
        }

        if ($spk->status_lingkungan) {
            $parts[] = 'Lingkungan '.$spk->status_lingkungan;
        }

        if ($spk->status_kesehatan) {
            $parts[] = 'Kesehatan '.$spk->status_kesehatan;
        }

        if ($spk->output_label) {
            $parts[] = 'Output '.$spk->output_label;
        }

        return $parts ? implode(' | ', $parts) : 'Log SPK membutuhkan tindak lanjut.';
    }

    private function priorityLabel(string $priority): string
    {
        return [
            'urgent' => 'Urgent',
            'high' => 'Tinggi',
            'medium' => 'Sedang',
            'low' => 'Rendah',
        ][$priority] ?? 'Sedang';
    }

    private function priorityClass(string $priority): string
    {
        return [
            'urgent' => 'bg-red-50 text-red-700 border-red-200',
            'high' => 'bg-amber-50 text-amber-700 border-amber-200',
            'medium' => 'bg-blue-50 text-blue-700 border-blue-200',
            'low' => 'bg-slate-50 text-slate-600 border-slate-200',
        ][$priority] ?? 'bg-blue-50 text-blue-700 border-blue-200';
    }

    private function petugasQuery()
    {
        $query = DB::table('user')
            ->select('id', 'name', 'email')
            ->where('role', 'petugas')
            ->where('isActive', 1);

        if ($this->role() === 'pjawab' && $this->currentUserId()) {
            $query->where(function ($q) {
                $q->where('owner_id', $this->currentUserId())
                    ->orWhereNull('owner_id');
            });
        }

        return $query->orderBy('name');
    }

    private function petugasIds(): array
    {
        return $this->petugasQuery()->pluck('id')->all();
    }

    private function currentUser(): array
    {
        return session('user', []);
    }

    private function currentUserId(): ?string
    {
        return data_get($this->currentUser(), 'id') ?? session('user_id');
    }

    private function role(): ?string
    {
        return data_get($this->currentUser(), 'role');
    }

    private function requirePjawab(): void
    {
        abort_unless($this->role() === 'pjawab', 403, 'Hanya penanggung jawab yang dapat mengelola tugas.');
    }

    private function authorizeTaskAccess(SpkActionTask $task): void
    {
        if ($this->role() === 'pjawab') {
            $this->authorizeTaskOwner($task);

            return;
        }

        if ($this->role() === 'petugas') {
            abort_unless($task->assigned_to === $this->currentUserId(), 403, 'Tugas ini bukan milik Anda.');

            return;
        }

        abort(403, 'Anda tidak memiliki akses ke tugas ini.');
    }

    private function authorizeTaskOwner(SpkActionTask $task): void
    {
        abort_unless($task->assigned_by === $this->currentUserId(), 403, 'Tugas ini bukan milik Anda.');
    }

    private function authorizeTaskWork(SpkActionTask $task): void
    {
        if ($this->role() === 'pjawab') {
            $this->authorizeTaskOwner($task);

            return;
        }

        if ($this->role() === 'petugas') {
            abort_unless($task->assigned_to === $this->currentUserId(), 403, 'Tugas ini bukan milik Anda.');

            return;
        }

        abort(403, 'Anda tidak memiliki akses ke tugas ini.');
    }
}
