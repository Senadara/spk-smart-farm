<?php

namespace Tests\Unit\Bva;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Illuminate\Support\Facades\Validator;

/**
 * BVA untuk nilai_skala AHP (skala Saaty 1/9 — 9).
 *
 * Rule dari SpkSupplierDssController.php: numeric|min:0.111|max:9
 * ε = 0.001 (float precision)
 *
 * TEMUAN: Validasi min:0.111|max:9 hanya ada di CONTROLLER.
 * AHPService sendiri tidak memvalidasi — nilai di luar range tetap diproses.
 */
class NilaiSkalaBvaTest extends TestCase
{
    #[DataProvider('nilaiSkalaProvider')]
    public static function test_nilai_skala_bva(float $value, bool $expectedValid, string $label)
    {
        $validator = Validator::make(['nilai_skala' => $value], [
            'nilai_skala' => 'numeric|min:0.111|max:9',
        ]);

        self::assertSame($expectedValid, $validator->passes(),
            "nilai_skala={$value}: expected valid=" . ($expectedValid ? 'true' : 'false') . " — {$label}"
        );
    }

    public static function nilaiSkalaProvider(): array
    {
        return [
            'AHP-01 0.110 (min-ε) invalid — < 0.111'  => [0.110, false,  '0,110 < 0,111 → reject (422)'],
            'AHP-02 0.111 (min on) valid = 1/9 Saaty' => [0.111, true,   '0,111 = 1/9 batas bawah Saaty'],
            'AHP-03 0.112 (min+ε) valid'              => [0.112, true,   '0,112 > 0,111 valid'],
            'AHP-04 4.555 (nominal) valid'            => [4.555, true,   'nilai tengah range'],
            'AHP-05 8.999 (max-ε) valid'              => [8.999, true,   '8,999 < 9 valid'],
            'AHP-06 9.000 (max on) valid = skala 9'   => [9.000, true,   '9,000 = batas atas'],
            'AHP-07 9.001 (max+ε) invalid — > 9'      => [9.001, false,  '9,001 > 9 → reject (422)'],
        ];
    }
}
