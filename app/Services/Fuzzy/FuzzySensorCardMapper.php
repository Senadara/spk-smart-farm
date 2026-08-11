<?php

namespace App\Services\Fuzzy;

use App\Models\SpkFuzzyVariable;
use Illuminate\Support\Str;

class FuzzySensorCardMapper
{
    private const GROUPS = ['lingkungan', 'kesehatan'];

    private const LABELS = [
        'suhu' => 'Suhu Udara',
        'kelembapan' => 'Kelembapan',
        'amonia' => 'Amonia',
        'hdp' => 'HDP',
        'feed_intake' => 'Konsumsi Pakan',
        'pakan' => 'Konsumsi Pakan',
        'mortalitas' => 'Mortalitas',
        'fcr' => 'FCR',
    ];

    private const ORDER = [
        'suhu' => 10,
        'kelembapan' => 20,
        'amonia' => 30,
        'hdp' => 10,
        'umur_biologis' => 20,
        'fcr' => 30,
        'feed_intake' => 40,
        'pakan' => 40,
        'mortalitas' => 50,
    ];

    /**
     * Build card data from active fuzzy variables, not from hardcoded sensor names.
     */
    public function fromResult(array $result): array
    {
        $inputs = $result['inputs'] ?? [];
        $profileId = $result['profile']['id'] ?? null;

        if (empty($inputs)) {
            return $this->emptyCards();
        }

        $variables = SpkFuzzyVariable::with('sets')
            ->where('type', 'input')
            ->whereIn('group', self::GROUPS)
            ->when($profileId, fn ($query) => $query->where('profile_id', $profileId))
            ->when(!$profileId, fn ($query) => $query->whereIn('name', array_keys($inputs)))
            ->get()
            ->sortBy(fn ($variable) => $this->sortKey($variable))
            ->values();

        $cards = $this->emptyCards();
        $seen = [];

        foreach ($variables as $variable) {
            if (!array_key_exists($variable->name, $inputs)) {
                continue;
            }

            $dedupeKey = $variable->group . ':' . $variable->name;
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $value = (float) $inputs[$variable->name];
            $fuzzified = $result[$variable->group]['fuzzified'][$variable->name] ?? [];
            $dominantSet = $this->dominantSet($fuzzified);
            $percent = $this->percentFromUniverse($value, $variable);

            $cards[$variable->group][] = [
                'key' => $variable->name,
                'code' => $this->codeFor($variable),
                'label' => $this->labelFor($variable),
                'percent' => $percent,
                'status' => $this->statusFor($variable, $dominantSet),
                'statusLabel' => $this->statusLabel($value, $variable->unit, $dominantSet),
                'value' => round($value, 2),
                'unit' => (string) ($variable->unit ?? ''),
                'set' => $dominantSet ?? '-',
            ];
        }

        $cards['produktivitas'] = $cards['kesehatan'];

        return $cards;
    }

    public function toIndicators(array $cards): array
    {
        return array_map(fn (array $card) => [
            'label' => $card['label'] ?? '-',
            'value' => $this->valueWithUnit((float) ($card['value'] ?? 0), (string) ($card['unit'] ?? '')),
            'color' => match ($card['status'] ?? 'normal') {
                'danger' => 'red',
                'warning' => 'amber',
                default => 'emerald',
            },
            'detail' => $card['set'] ?? '-',
            'score' => (int) ($card['percent'] ?? 0),
        ], $cards);
    }

    public function toSpider(array $cards): array
    {
        return [
            'labels' => array_values(array_map(fn (array $card) => $card['label'] ?? '-', $cards)),
            'values' => array_values(array_map(fn (array $card) => (int) ($card['percent'] ?? 0), $cards)),
        ];
    }

    private function emptyCards(): array
    {
        return [
            'lingkungan' => [],
            'kesehatan' => [],
            'produktivitas' => [],
        ];
    }

    private function sortKey(SpkFuzzyVariable $variable): string
    {
        $groupOrder = $variable->group === 'lingkungan' ? 1 : 2;
        $variableOrder = self::ORDER[$variable->name] ?? 999;

        return sprintf('%02d-%03d-%s', $groupOrder, $variableOrder, $variable->name);
    }

    private function labelFor(SpkFuzzyVariable $variable): string
    {
        if (isset(self::LABELS[$variable->name])) {
            return self::LABELS[$variable->name];
        }

        return Str::of($variable->name)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    private function codeFor(SpkFuzzyVariable $variable): string
    {
        $name = Str::lower($variable->name);

        return match (true) {
            str_contains($name, 'hhep') => 'hhep',
            str_contains($name, 'hdp') => 'hdp',
            str_contains($name, 'fcr') => 'fcr',
            str_contains($name, 'flock') || str_contains($name, 'umur') || str_contains($name, 'age') => 'flock_age',
            str_contains($name, 'egg_mass') || str_contains($name, 'massa_telur') => 'egg_mass',
            str_contains($name, 'avg_egg') || str_contains($name, 'berat_rata') => 'avg_egg_weight',
            str_contains($name, 'feed') || str_contains($name, 'pakan') => 'feed_intake',
            str_contains($name, 'mortal') || str_contains($name, 'kematian') => 'mortalitas',
            default => $name,
        };
    }

    private function dominantSet(array $fuzzified): ?string
    {
        if (empty($fuzzified)) {
            return null;
        }

        $maxValue = max($fuzzified);
        if ($maxValue <= 0) {
            return null;
        }

        return array_search($maxValue, $fuzzified, true) ?: null;
    }

    private function percentFromUniverse(float $value, SpkFuzzyVariable $variable): int
    {
        $sets = $variable->sets;
        if ($sets->isEmpty()) {
            return (int) max(0, min(100, round($value)));
        }

        $min = (float) $sets->min('a');
        $max = (float) $sets->map(fn ($set) => $set->d ?? $set->c)->max();

        if ($max <= $min) {
            return (int) max(0, min(100, round($value)));
        }

        return (int) max(0, min(100, round((($value - $min) / ($max - $min)) * 100)));
    }

    private function statusFor(SpkFuzzyVariable $variable, ?string $setName): string
    {
        if (!$setName) {
            return 'warning';
        }

        $variableName = Str::lower($variable->name);
        $set = Str::lower($setName);

        $normalByVariable = [
            'suhu' => ['nyaman'],
            'kelembapan' => ['ideal'],
            'amonia' => ['aman'],
            'hdp' => ['sedang', 'tinggi'],
            'feed_intake' => ['normal'],
            'pakan' => ['normal'],
            'fcr' => ['efisien', 'normal'],
            'mortalitas' => ['wajar'],
        ];

        if (in_array($set, $normalByVariable[$variableName] ?? [], true)) {
            return 'normal';
        }

        $dangerByVariable = [
            'amonia' => ['tinggi'],
            'feed_intake' => ['berlebih'],
            'fcr' => ['boros'],
            'mortalitas' => ['tinggi'],
        ];

        if (in_array($set, $dangerByVariable[$variableName] ?? [], true)) {
            return 'danger';
        }

        return 'warning';
    }

    private function statusLabel(float $value, ?string $unit, ?string $dominantSet): string
    {
        $label = $this->valueWithUnit($value, (string) $unit);

        return trim($label . ' - ' . ($dominantSet ?? '-'));
    }

    private function valueWithUnit(float $value, string $unit): string
    {
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return trim($formatted . ' ' . $unit);
    }
}
