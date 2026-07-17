<?php

namespace Tests\Unit\Bva;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Illuminate\Support\Facades\Validator;

/**
 * BVA untuk konfigurasi fuzzy.
 *
 * Rule ASLI dari FuzzyConfigController.php:510-511:
 *   'max_age_minutes'     => 'nullable|integer|min:1|max:10080'
 *   'offline_after_misses' => 'nullable|integer|min:1|max:20'
 *
 * CATATAN: Tidak ada FormRequest. Rule di-hardcode di controller.
 * Rekomendasi: ekstrak ke FormRequest agar rule dapat diuji ulang.
 */
class FuzzyConfigBvaTest extends TestCase
{
    private const RULE_MAX_AGE  = 'nullable|integer|min:1|max:10080';
    private const RULE_OFFLINE  = 'nullable|integer|min:1|max:20';

    #[DataProvider('maxAgeMinutesProvider')]
    public static function test_max_age_minutes_bva(int $value, bool $valid, string $label)
    {
        $v = Validator::make(['max_age_minutes' => $value], [
            'max_age_minutes' => self::RULE_MAX_AGE,
        ]);
        self::assertSame($valid, $v->passes(), "max_age_minutes={$value}: {$label}");
    }

    public static function maxAgeMinutesProvider(): array
    {
        return [
            'FUZ-01 0  (min-1) invalid'  => [0, false, '0 < 1 reject'],
            'FUZ-02 1  (min on) valid'    => [1, true,  '1 = batas bawah'],
            'FUZ-03 2  (min+1) valid'     => [2, true,  '2 > 1 valid'],
            'FUZ-04 5040 (nominal) valid' => [5040, true, 'nilai tengah'],
            'FUZ-05 10079 (max-1) valid'  => [10079, true, '10079 < 10080'],
            'FUZ-06 10080 (max on) valid' => [10080, true, '10080 = batas atas'],
            'FUZ-07 10081 (max+1) invalid'=> [10081, false, '10081 > 10080 reject'],
        ];
    }

    #[DataProvider('offlineAfterMissesProvider')]
    public static function test_offline_after_misses_bva(int $value, bool $valid, string $label)
    {
        $v = Validator::make(['offline_after_misses' => $value], [
            'offline_after_misses' => self::RULE_OFFLINE,
        ]);
        self::assertSame($valid, $v->passes(), "offline_after_misses={$value}: {$label}");
    }

    public static function offlineAfterMissesProvider(): array
    {
        return [
            'FUZ-08 0  (min-1) invalid' => [0, false, '0 < 1 reject'],
            'FUZ-09 1  (min on) valid'   => [1, true,  '1 = batas bawah'],
            'FUZ-10 2  (min+1) valid'    => [2, true,  '2 > 1 valid'],
            'FUZ-11 10 (nominal) valid'  => [10, true, 'nilai tengah'],
            'FUZ-12 19 (max-1) valid'    => [19, true, '19 < 20'],
            'FUZ-13 20 (max on) valid'   => [20, true, '20 = batas atas'],
            'FUZ-14 21 (max+1) invalid'  => [21, false, '21 > 20 reject'],
        ];
    }
}
