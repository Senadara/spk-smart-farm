<?php

namespace App\Console\Commands;

use App\Services\LivestockMasterConfigService;
use App\Services\Notifications\SpkEnvironmentAlertService;
use App\Services\PeternakanService;
use App\Services\Spk\SpkFuzzyEvaluationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EvaluateEnvironmentSpk extends Command
{
    protected $signature = 'spk:evaluate-environment
        {--commodity= : UUID komoditas tertentu}
        {--coop= : UUID kandang tertentu}
        {--notify : Kirim push notification jika hasil SPK perlu perhatian}
        {--include-global : Sertakan evaluasi global semua kandang}';

    protected $description = 'Jalankan evaluasi SPK lingkungan berkala dan kirim notifikasi mobile untuk owner jika perlu.';

    public function handle(
        PeternakanService $peternakanService,
        SpkFuzzyEvaluationService $fuzzyEvaluationService,
        SpkEnvironmentAlertService $alertService,
    ): int {
        $commodityIds = $this->commodityTargets();
        $processed = 0;
        $errors = [];

        foreach ($commodityIds as $commodityId) {
            $peternakanService->forKomoditas($commodityId);
            $activeCommodityId = $peternakanService->getActiveKomoditasId();

            $coopIds = $this->option('coop')
                ? [(string) $this->option('coop')]
                : $peternakanService->getActiveCoopIds();

            if ($this->option('include-global')) {
                $coopIds[] = null;
            }

            foreach (array_values(array_unique($coopIds, SORT_REGULAR)) as $coopId) {
                try {
                    $log = $fuzzyEvaluationService->evaluateAndPersist($coopId, $activeCommodityId);

                    if ($this->option('notify')) {
                        $alertService->dispatchForLog($log);
                    }

                    $processed++;
                    $this->line(sprintf(
                        'SPK OK: commodity=%s coop=%s status=%s score=%s',
                        $activeCommodityId ?: '-',
                        $coopId ?: 'global',
                        $log->status_lingkungan ?: '-',
                        $log->output_value ?? '-'
                    ));
                } catch (\Throwable $e) {
                    $errors[] = [
                        'commodity_id' => $activeCommodityId,
                        'coop_id' => $coopId,
                        'message' => $e->getMessage(),
                    ];

                    $this->error(sprintf(
                        'SPK gagal: commodity=%s coop=%s message=%s',
                        $activeCommodityId ?: '-',
                        $coopId ?: 'global',
                        $e->getMessage()
                    ));
                }
            }
        }

        $this->info("Evaluasi SPK selesai. Diproses: {$processed}. Error: ".count($errors).'.');

        return empty($errors) ? self::SUCCESS : self::FAILURE;
    }

    private function commodityTargets(): array
    {
        if ($this->option('commodity')) {
            return [(string) $this->option('commodity')];
        }

        $livestockCommodityIds = app(LivestockMasterConfigService::class)->livestockCommodityIds();

        $profileCommodityIds = ! empty($livestockCommodityIds)
            ? DB::table('spk_fuzzy_profiles')
                ->where('is_active', true)
                ->whereIn('commodity_id', $livestockCommodityIds)
                ->pluck('commodity_id')
                ->filter()
                ->unique()
                ->values()
                ->all()
            : [];

        if (! empty($profileCommodityIds)) {
            return $profileCommodityIds;
        }

        $hasGlobalActiveProfile = DB::table('spk_fuzzy_profiles')
            ->where('is_active', true)
            ->whereNull('commodity_id')
            ->exists();

        if ($hasGlobalActiveProfile) {
            return [null];
        }

        $ids = $livestockCommodityIds;

        return ! empty($ids) ? $ids : [null];
    }
}
