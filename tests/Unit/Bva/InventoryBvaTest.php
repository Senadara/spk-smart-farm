<?php

namespace Tests\Unit\Bva;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Illuminate\Support\Facades\Validator;

/**
 * BVA untuk field inventory.
 *
 * Rule dari InventoryController.php:
 * - lead_time_days:       integer|min:1|max:60
 * - safety_stock_days:    integer|min:0|max:60
 * - reorder_point_override: numeric|min:0|max:999999999
 *
 * TEMUAN T-01: stock TIDAK memiliki validasi min:0 di Controller.
 * Kolom decimal(12,2) signed → nilai negatif TERSIMPAN tanpa error.
 */
class InventoryBvaTest extends TestCase
{
    #[DataProvider('leadTimeDaysProvider')]
    public function test_lead_time_days_validation(int $value, bool $expectedValid, string $label)
    {
        $validator = Validator::make(['lead_time_days' => $value], [
            'lead_time_days' => 'integer|min:1|max:60',
        ]);
        $this->assertSame($expectedValid, $validator->passes(),
            "lead_time_days={$value}: expected valid=" . ($expectedValid ? 'true' : 'false') . " — {$label}"
        );
    }

    public static function leadTimeDaysProvider(): array
    {
        return [
            'INV-01 0  (min-1) invalid'  => [0, false,   '0 < 1 → reject'],
            'INV-02 1  (min on) valid'   => [1, true,    '1 = batas bawah'],
            'INV-03 2  (min+1) valid'    => [2, true,    '2 > 1 valid'],
            'INV-04 30 (nominal) valid'  => [30, true,   'nilai tengah'],
            'INV-05 59 (max-1) valid'    => [59, true,   '59 < 60'],
            'INV-06 60 (max on) valid'   => [60, true,   '60 = batas atas'],
            'INV-07 61 (max+1) invalid'  => [61, false,  '61 > 60 → reject'],
        ];
    }

    #[DataProvider('safetyStockDaysProvider')]
    public function test_safety_stock_days_validation(int $value, bool $expectedValid, string $label)
    {
        $validator = Validator::make(['safety_stock_days' => $value], [
            'safety_stock_days' => 'integer|min:0|max:60',
        ]);
        $this->assertSame($expectedValid, $validator->passes(),
            "safety_stock_days={$value}: expected valid=" . ($expectedValid ? 'true' : 'false') . " — {$label}"
        );
    }

    public static function safetyStockDaysProvider(): array
    {
        return [
            'INV-08 -1 (min-1) invalid' => [-1, false,  '-1 < 0 → reject'],
            'INV-09 0  (min on) valid'  => [0, true,    '0 = batas bawah'],
            'INV-10 1  (min+1) valid'   => [1, true,    '1 > 0 valid'],
            'INV-11 30 (nominal) valid' => [30, true,   'nilai tengah'],
            'INV-12 59 (max-1) valid'   => [59, true,   '59 < 60'],
            'INV-13 60 (max on) valid'  => [60, true,   '60 = batas atas'],
            'INV-14 61 (max+1) invalid' => [61, false,  '61 > 60 → reject'],
        ];
    }

    #[DataProvider('reorderPointProvider')]
    public function test_reorder_point_validation(float|int $value, bool $expectedValid, string $label)
    {
        $validator = Validator::make(['reorder_point_override' => $value], [
            'reorder_point_override' => 'numeric|min:0|max:999999999',
        ]);
        $this->assertSame($expectedValid, $validator->passes(),
            "reorder_point={$value}: expected valid=" . ($expectedValid ? 'true' : 'false') . " — {$label}"
        );
    }

    public static function reorderPointProvider(): array
    {
        return [
            'INV-22 -1             (min-1) invalid'     => [-1, false,         '-1 < 0 → reject'],
            'INV-23 0              (min on) valid'      => [0, true,           '0 = batas bawah'],
            'INV-24 1              (min+1) valid'       => [1, true,           '1 > 0 valid'],
            'INV-25 500000000      (nominal) valid'    => [500000000, true,   'nilai tengah'],
            'INV-26 999999998      (max-1) valid'      => [999999998, true,   '999999998 < 999999999'],
            'INV-27 999999999      (max on) valid'     => [999999999, true,   '999999999 = batas atas'],
            'INV-28 1000000000     (max+1) invalid'    => [1000000000, false, '1000000000 > 999999999 → reject'],
        ];
    }

    /**
     * TEMUAN T-01: stock TIDAK memiliki validasi min:0 di Controller.
     * Kolom decimal(12,2) signed → nilai negatif tersimpan tanpa error.
     */
    public function test_stock_temuan_tidak_ada_validasi_min0()
    {
        // Simulasi: input stock = -50 lewat tanpa error (tanpa validasi min)
        $validator = Validator::make(['stock' => -50], []);

        // Tidak ada rule → validasi passes (true positive = temuan defect)
        $this->assertTrue($validator->passes(),
            'TEMUAN: field stock tidak memiliki validasi — nilai -50 lolos tanpa error'
        );
    }
}
