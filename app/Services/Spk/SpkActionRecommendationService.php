<?php

namespace App\Services\Spk;

use App\Models\SpkActionRecommendation;
use App\Models\SpkActionTask;
use App\Models\SpkFuzzyLog;
use App\Services\Fuzzy\NarrativeGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SpkActionRecommendationService
{
    public function syncForLog(SpkFuzzyLog $log): ?SpkActionRecommendation
    {
        if (! $this->tableReady()) {
            return null;
        }

        if (! $this->needsAction($log)) {
            $this->disableOpenForScope($log, 'Hasil SPK terbaru sudah tidak membutuhkan tindakan.');

            return null;
        }

        if ($this->hasActiveTaskForScope($log->unit_budidaya_id, $log->id)) {
            $this->disableOpenForScope($log, 'Sudah ada tugas aktif untuk tindak lanjut SPK ini.', true);

            return null;
        }

        if ($this->hasHandledTaskForSameIssue($log)) {
            $this->disableOpenForScope($log, 'Isu SPK yang sama sudah pernah dikonversi menjadi penugasan.', true);

            return null;
        }

        $existing = SpkActionRecommendation::query()
            ->where('spk_fuzzy_log_id', $log->id)
            ->first();

        $this->disableOpenForScope($log, 'Digantikan oleh hasil SPK terbaru.');

        if ($existing) {
            return $existing->status === 'open' ? $existing : null;
        }

        return SpkActionRecommendation::create([
            'spk_fuzzy_log_id' => $log->id,
            'unit_budidaya_id' => $log->unit_budidaya_id,
            'commodity_id' => $log->commodity_id,
            'owner_id' => $this->resolveOwnerId($log),
            'status' => 'open',
            'priority' => $this->priorityFor($log),
            'score' => is_numeric($log->output_value) ? (float) $log->output_value : null,
            'title' => $this->titleFor($log),
            'description' => $this->descriptionFor($log),
            'fingerprint' => $this->fingerprint($log),
        ]);
    }

    public function syncOpenRecommendationsFromLogs(
        Collection $logs,
        ?string $commodityId = null,
        array $unitBudidayaIds = [],
        bool $includeGlobal = true,
        int $limit = 12
    ): Collection
    {
        if (! $this->tableReady()) {
            return collect();
        }

        $logs
            ->sortByDesc(fn (SpkFuzzyLog $log) => $log->createdAt?->timestamp ?? 0)
            ->unique(fn (SpkFuzzyLog $log) => $this->scopeKey($log->unit_budidaya_id, $log->commodity_id))
            ->each(fn (SpkFuzzyLog $log) => $this->syncForLog($log));

        return $this->openRecommendations($commodityId, $limit, $unitBudidayaIds, $includeGlobal);
    }

    public function openRecommendations(
        ?string $commodityId = null,
        int $limit = 12,
        array $unitBudidayaIds = [],
        bool $includeGlobal = true
    ): Collection
    {
        if (! $this->tableReady()) {
            return collect();
        }

        $this->reconcileOpenRecommendationsWithTasks();

        $recommendations = SpkActionRecommendation::with(['spkFuzzyLog.unitBudidaya', 'unitBudidaya'])
            ->where('status', 'open')
            ->when($commodityId, function ($query) use ($commodityId) {
                $query->where(function ($inner) use ($commodityId) {
                    $inner->where('commodity_id', $commodityId)
                        ->orWhereNull('commodity_id');
                });
            })
            ->when(! empty($unitBudidayaIds), function ($query) use ($unitBudidayaIds, $includeGlobal) {
                $unitBudidayaIds = array_values(array_unique(array_filter(array_map('strval', $unitBudidayaIds))));

                $query->where(function ($inner) use ($unitBudidayaIds, $includeGlobal) {
                    $inner->whereIn('unit_budidaya_id', $unitBudidayaIds);

                    if ($includeGlobal) {
                        $inner->orWhereNull('unit_budidaya_id');
                    }
                });
            })
            ->when(empty($unitBudidayaIds) && ! $includeGlobal, fn ($query) => $query->whereNotNull('unit_budidaya_id'))
            ->orderByDesc('createdAt')
            ->limit($limit)
            ->get();

        return $recommendations
            ->reject(function (SpkActionRecommendation $recommendation) {
                $hasActiveTask = $this->hasActiveTaskForScope(
                    $recommendation->unit_budidaya_id,
                    $recommendation->spk_fuzzy_log_id
                );

                if ($hasActiveTask) {
                    $recommendation->forceFill([
                        'status' => 'disabled',
                        'disable_reason' => 'Sudah ada tugas aktif untuk rekomendasi ini.',
                        'disabled_at' => now(),
                    ])->save();
                }

                return $hasActiveTask;
            })
            ->values();
    }

    public function markAssigned(?string $recommendationId, SpkActionTask $task): void
    {
        if (! $this->tableReady()) {
            return;
        }

        $recommendation = null;

        if (filled($recommendationId)) {
            $recommendation = SpkActionRecommendation::query()
                ->where('id', $recommendationId)
                ->first();
        }

        if (! $recommendation && filled($task->spk_fuzzy_log_id)) {
            $recommendation = SpkActionRecommendation::query()
                ->where('spk_fuzzy_log_id', $task->spk_fuzzy_log_id)
                ->whereIn('status', ['open', 'disabled'])
                ->first();
        }

        if (! $recommendation && filled($task->unit_budidaya_id)) {
            $recommendation = SpkActionRecommendation::query()
                ->where('unit_budidaya_id', $task->unit_budidaya_id)
                ->whereIn('status', ['open', 'disabled'])
                ->latest('createdAt')
                ->first();
        }

        if (! $recommendation) {
            return;
        }

        $this->assignRecommendationToTask($recommendation, $task);
    }

    public function reconcileOpenRecommendationsWithTasks(): void
    {
        if (! $this->tableReady()) {
            return;
        }

        SpkActionRecommendation::with('spkFuzzyLog')
            ->where('status', 'open')
            ->orderBy('createdAt')
            ->chunkById(100, function (Collection $recommendations) {
                foreach ($recommendations as $recommendation) {
                    $log = $recommendation->spkFuzzyLog;

                    if (! $log) {
                        $this->disableRecommendation($recommendation, 'Log SPK sumber rekomendasi tidak ditemukan.');

                        continue;
                    }

                    if (! $this->needsAction($log)) {
                        $this->disableRecommendation($recommendation, 'Hasil SPK terbaru sudah tidak membutuhkan tindakan.');

                        continue;
                    }

                    $task = $this->coveringActiveTaskForRecommendation($recommendation);
                    if ($task) {
                        $this->assignRecommendationToTask($recommendation, $task);

                        continue;
                    }

                    if ($this->hasHandledTaskForSameIssue($log)) {
                        $this->disableRecommendation($recommendation, 'Isu SPK yang sama sudah pernah ditindaklanjuti.');
                    }
                }
            });
    }

    private function assignRecommendationToTask(SpkActionRecommendation $recommendation, SpkActionTask $task): void
    {
        if (
            $recommendation->status === 'assigned'
            && filled($recommendation->assigned_task_id)
            && (string) $recommendation->assigned_task_id !== (string) $task->id
        ) {
            return;
        }

        $recommendation->forceFill([
            'status' => 'assigned',
            'assigned_task_id' => $task->id,
            'assigned_at' => $recommendation->assigned_at ?: now(),
            'disable_reason' => null,
            'disabled_at' => null,
        ])->save();

        SpkActionRecommendation::query()
            ->where('id', '<>', $recommendation->id)
            ->where('status', 'open')
            ->when($recommendation->unit_budidaya_id, function ($query) use ($recommendation) {
                $query->where('unit_budidaya_id', $recommendation->unit_budidaya_id);
            }, fn ($query) => $query->whereNull('unit_budidaya_id'))
            ->update([
                'status' => 'disabled',
                'disable_reason' => 'Sudah dikonversi menjadi tugas lain.',
                'disabled_at' => now(),
                'updatedAt' => now(),
            ]);
    }

    private function disableRecommendation(SpkActionRecommendation $recommendation, string $reason): void
    {
        $recommendation->forceFill([
            'status' => 'disabled',
            'disable_reason' => $reason,
            'disabled_at' => now(),
            'assigned_task_id' => null,
            'assigned_at' => null,
        ])->save();
    }

    private function tableReady(): bool
    {
        return Schema::hasTable('spk_action_recommendations');
    }

    private function needsAction(SpkFuzzyLog $log): bool
    {
        $score = is_numeric($log->output_value) ? (float) $log->output_value : null;
        $statusText = Str::lower(implode(' ', array_filter([
            $log->status_lingkungan,
            $log->status_kesehatan,
            $log->diagnosis_kausalitas,
            $log->output_label,
        ])));

        return ($score !== null && $score < 70)
            || Str::contains($statusText, ['waspada', 'buruk', 'kritis', 'darurat', 'tidak optimal', 'tidak sehat']);
    }

    private function hasActiveTaskForScope(?string $unitBudidayaId, ?string $spkLogId = null): bool
    {
        return (bool) $this->activeTaskForScope($unitBudidayaId, $spkLogId);
    }

    private function activeTaskForScope(?string $unitBudidayaId, ?string $spkLogId = null): ?SpkActionTask
    {
        if (! filled($unitBudidayaId) && ! filled($spkLogId)) {
            return null;
        }

        return SpkActionTask::withoutGlobalScopes()
            ->whereIn('status', ['todo', 'in_progress'])
            ->where(function ($query) use ($unitBudidayaId, $spkLogId) {
                if (filled($spkLogId)) {
                    $query->orWhere('spk_fuzzy_log_id', $spkLogId);
                }

                if (filled($unitBudidayaId)) {
                    $query->orWhere('unit_budidaya_id', $unitBudidayaId);
                }
            })
            ->orderByRaw('CASE WHEN spk_fuzzy_log_id = ? THEN 0 ELSE 1 END', [(string) $spkLogId])
            ->orderByDesc('createdAt')
            ->first();
    }

    private function coveringActiveTaskForRecommendation(SpkActionRecommendation $recommendation): ?SpkActionTask
    {
        return $this->activeTaskForScope(
            $recommendation->unit_budidaya_id,
            $recommendation->spk_fuzzy_log_id
        );
    }

    private function hasHandledTaskForSameIssue(SpkFuzzyLog $log): bool
    {
        $fingerprint = $this->fingerprint($log);

        $tasks = SpkActionTask::withoutGlobalScopes()
            ->with('fuzzyLog')
            ->where('status', '<>', 'cancelled')
            ->whereNotNull('spk_fuzzy_log_id')
            ->latest('createdAt')
            ->limit(100)
            ->get();

        return $tasks->contains(function (SpkActionTask $task) use ($log, $fingerprint) {
            if (! $task->fuzzyLog) {
                return false;
            }

            $taskScope = $task->unit_budidaya_id ?: $task->fuzzyLog->unit_budidaya_id;

            if (filled($log->unit_budidaya_id)) {
                if ((string) $taskScope !== (string) $log->unit_budidaya_id) {
                    return false;
                }
            } elseif (filled($taskScope)) {
                return false;
            }

            return $this->fingerprint($task->fuzzyLog) === $fingerprint;
        });
    }

    private function scopeKey(?string $unitBudidayaId, ?string $commodityId = null): string
    {
        return filled($unitBudidayaId)
            ? 'unit:'.$unitBudidayaId
            : 'global:'.($commodityId ?: 'all');
    }

    private function disableOpenForScope(SpkFuzzyLog $log, string $reason, bool $includeCurrentLog = false): void
    {
        SpkActionRecommendation::query()
            ->where('status', 'open')
            ->when($log->unit_budidaya_id, function ($query) use ($log) {
                $query->where('unit_budidaya_id', $log->unit_budidaya_id);
            }, fn ($query) => $query->whereNull('unit_budidaya_id'))
            ->when(! $includeCurrentLog, function ($query) use ($log) {
                $query->where(function ($inner) use ($log) {
                    $inner->whereNull('spk_fuzzy_log_id')
                        ->orWhere('spk_fuzzy_log_id', '<>', $log->id);
                });
            })
            ->update([
                'status' => 'disabled',
                'disable_reason' => $reason,
                'disabled_at' => now(),
                'updatedAt' => now(),
            ]);
    }

    private function resolveOwnerId(SpkFuzzyLog $log): ?string
    {
        if ($log->unit_budidaya_id) {
            return DB::table('unitBudidaya')->where('id', $log->unit_budidaya_id)->value('owner_id');
        }

        return null;
    }

    private function priorityFor(SpkFuzzyLog $log): string
    {
        $score = is_numeric($log->output_value) ? (float) $log->output_value : null;
        $statusText = Str::lower(implode(' ', array_filter([
            $log->status_lingkungan,
            $log->status_kesehatan,
            $log->diagnosis_kausalitas,
            $log->output_label,
        ])));

        if (Str::contains($statusText, ['buruk', 'kritis', 'darurat']) || ($score !== null && $score < 55)) {
            return 'urgent';
        }

        if (Str::contains($statusText, ['waspada', 'tidak sehat', 'tidak optimal']) || ($score !== null && $score < 70)) {
            return 'high';
        }

        return 'medium';
    }

    private function titleFor(SpkFuzzyLog $log): string
    {
        return collect([
            $log->diagnosis_kausalitas,
            $log->status_kesehatan,
            $log->status_lingkungan,
        ])->first(fn ($value) => filled($value) && ! in_array(Str::lower((string) $value), [
            'unknown',
            'tidak diketahui',
        ], true)) ?: 'Evaluasi SPK perlu ditindaklanjuti';
    }

    private function descriptionFor(SpkFuzzyLog $log): string
    {
        return NarrativeGenerator::sanitizePlainText($log->recommendation)
            ?: NarrativeGenerator::sanitizePlainText($log->narrative)
            ?: 'Tinjau hasil SPK dan tentukan tindak lanjut petugas.';
    }

    private function fingerprint(SpkFuzzyLog $log): string
    {
        return implode('|', [
            $log->unit_budidaya_id ?: 'global',
            Str::lower((string) ($log->status_lingkungan ?: '-')),
            Str::lower((string) ($log->status_kesehatan ?: '-')),
            Str::lower((string) ($log->diagnosis_kausalitas ?: '-')),
        ]);
    }
}
