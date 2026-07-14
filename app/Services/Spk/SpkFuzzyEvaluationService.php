<?php

namespace App\Services\Spk;

use App\Models\SpkFuzzyLog;
use App\Models\SpkFuzzyProfile;
use App\Services\Fuzzy\InputResolver;
use App\Services\Fuzzy\MamdaniEngine;
use App\Services\Fuzzy\NarrativeGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SpkFuzzyEvaluationService
{
    public function __construct(
        private readonly InputResolver $inputResolver,
        private readonly MamdaniEngine $mamdaniEngine,
        private readonly NarrativeGenerator $narrativeGenerator,
    ) {}

    public function evaluate(?string $coopId = null, ?string $commodityId = null, ?string $profileId = null): array
    {
        $profile = SpkFuzzyProfile::resolveForContext($commodityId, $coopId, $profileId);
        $resolvedCommodityId = $commodityId ?: $profile?->commodity_id;
        $inputs = $this->inputResolver->resolve($coopId, $resolvedCommodityId, $profile?->id);
        $result = $this->mamdaniEngine->processCascaded($inputs, $profile?->id, $resolvedCommodityId, $coopId);
        $barnName = $coopId ? DB::table('unitBudidaya')->where('id', $coopId)->value('nama') : null;
        $narrative = $this->narrativeGenerator->generate($result, $barnName);

        return array_merge($result, [
            'inputs' => $inputs,
            'narrative' => $narrative,
            'profile' => $result['profile'] ?? ($profile ? $profile->toArray() : null),
        ]);
    }

    public function persist(?string $coopId, array $result, ?string $commodityId = null): SpkFuzzyLog
    {
        $log = SpkFuzzyLog::create([
            'unit_budidaya_id' => $coopId,
            'profile_id' => $result['profile']['id'] ?? null,
            'commodity_id' => $result['profile']['commodity_id'] ?? $commodityId,
            'input_json' => $result['inputs'] ?? [],
            'fuzzified_json' => [
                'lingkungan' => $result['lingkungan']['fuzzified'] ?? [],
                'kesehatan' => $result['kesehatan']['fuzzified'] ?? [],
            ],
            'rule_result_json' => [
                'lingkungan' => $result['lingkungan']['dominant_rule'] ?? null,
                'kesehatan' => $result['kesehatan']['dominant_rule'] ?? null,
                'kausalitas' => $result['kausalitas'] ?? null,
            ],
            'status_lingkungan' => $result['lingkungan']['label'] ?? null,
            'status_kesehatan' => $result['kesehatan']['label'] ?? null,
            'diagnosis_kausalitas' => $result['kausalitas']['label'] ?? null,
            'output_value' => min(
                (float) ($result['lingkungan']['value'] ?? 0),
                (float) ($result['kesehatan']['value'] ?? 0)
            ),
            'output_label' => $result['kausalitas']['label'] ?? null,
            'narrative' => $result['narrative'] ?? null,
            'recommendation' => $result['kausalitas']['recommendation'] ?? null,
        ]);

        $this->forgetPeternakanCache($log);

        return $log;
    }

    public function evaluateAndPersist(?string $coopId = null, ?string $commodityId = null, ?string $profileId = null): SpkFuzzyLog
    {
        $result = $this->evaluate($coopId, $commodityId, $profileId);

        return $this->persist($coopId, $result, $commodityId);
    }

    private function forgetPeternakanCache(SpkFuzzyLog $log): void
    {
        $commodity = $log->commodity_id ?: 'default';
        $scope = $log->unit_budidaya_id ?: 'all';

        Cache::forget("peternakan:{$commodity}:fuzzy:{$scope}");
    }
}
