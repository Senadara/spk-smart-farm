<?php

namespace Tests\Unit\Sensor;

use App\Services\PeternakanService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PeternakanSensorTest extends TestCase
{
    private PeternakanService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new PeternakanService();
    }

    private function invoke(string $method, array $args)
    {
        $ref = new ReflectionMethod(PeternakanService::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($this->svc, $args);
    }

    public static function sensorStatusProvider(): array
    {
        return [
            //                                => [value,  min,   max,   expected]
            'value nol -> normal (BVA)'       => [ 0.0, 20.0, 30.0, 'normal'],
            'value negatif -> normal'         => [-5.0, 20.0, 30.0, 'normal'],
            'dalam rentang -> normal'         => [25.0, 20.0, 30.0, 'normal'],
            'tepat di min -> normal (BVA)'    => [20.0, 20.0, 30.0, 'normal'],
            'sedikit di bawah min -> warning' => [19.0, 20.0, 30.0, 'warning'],
            'jauh di bawah min -> danger'     => [10.0, 20.0, 30.0, 'danger'],
            'sedikit di atas max -> warning'  => [32.0, 20.0, 30.0, 'warning'],
            'jauh di atas max -> danger'      => [40.0, 20.0, 30.0, 'danger'],
            'gap tepat 0.1 -> warning (BVA)'  => [ 9.0, 10.0, 30.0, 'warning'],
            'tanpa threshold -> normal'       => [25.0, null, null, 'normal'],
        ];
    }

    #[DataProvider('sensorStatusProvider')]
    public function test_evaluateSensorStatus(float $value, ?float $min, ?float $max, string $expected): void
    {
        $this->assertSame($expected, $this->invoke('evaluateSensorStatus', [$value, $min, $max]));
    }

    public static function worstStatusProvider(): array
    {
        return [
            'ada danger -> danger'                => [['normal', 'warning', 'danger'], 'danger'],
            'ada warning tanpa danger -> warning' => [['normal', 'warning', 'normal'], 'warning'],
            'semua normal -> normal'              => [['normal', 'normal'], 'normal'],
            'kosong -> normal'                    => [[], 'normal'],
        ];
    }

    #[DataProvider('worstStatusProvider')]
    public function test_worstStatus(array $statuses, string $expected): void
    {
        $this->assertSame($expected, $this->invoke('worstStatus', $statuses));
    }

    public static function thresholdForProvider(): array
    {
        return [
            'code cocok langsung' => [
                ['TEMP' => ['min' => 18.0, 'max' => 30.0]], 'TEMP',
                ['min' => 18.0, 'max' => 30.0],
            ],
            'fallback ke AMMON' => [
                ['AMMON' => ['min' => 0.0, 'max' => 25.0]], 'GAS_XYZ',
                ['min' => 0.0, 'max' => 25.0],
            ],
            'tidak ada -> null null' => [
                ['TEMP' => ['min' => 18.0, 'max' => 30.0]], 'UNKNOWN',
                ['min' => null, 'max' => null],
            ],
        ];
    }

    #[DataProvider('thresholdForProvider')]
    public function test_thresholdFor(array $thresholds, string $code, array $expected): void
    {
        $this->assertSame($expected, $this->invoke('thresholdFor', [$thresholds, $code]));
    }

    public static function interpolateProvider(): array
    {
        return [
            'forward fill'          => [[1, null, 3, null], [1, 1, 3, 3]],
            'leading null jadi nol' => [[null, null, 5], [0, 0, 5]],
            'null di akhir'         => [[null, 2, null], [0, 2, 2]],
            'tanpa null'            => [[1, 2, 3], [1, 2, 3]],
            'array kosong'          => [[], []],
        ];
    }

    #[DataProvider('interpolateProvider')]
    public function test_interpolateArray(array $input, array $expected): void
    {
        $this->assertSame($expected, $this->invoke('interpolateArray', [$input]));
    }

    public static function calcTrendProvider(): array
    {
        return [
            'nol-nol -> stable' => [0.0, 0.0, 'higher_is_better',
                ['direction' => 'stable', 'value' => 'Stable', 'status' => 'neutral']],
            'naik, higher_is_better -> positive' => [10.0, 5.0, 'higher_is_better',
                ['direction' => 'up', 'value' => '5', 'status' => 'positive']],
            'turun, higher_is_better -> warning' => [5.0, 10.0, 'higher_is_better',
                ['direction' => 'down', 'value' => '5', 'status' => 'warning']],
            'turun, lower_is_better -> positive' => [5.0, 10.0, 'lower_is_better',
                ['direction' => 'down', 'value' => '5', 'status' => 'positive']],
            'naik, lower_is_better -> warning' => [10.0, 5.0, 'lower_is_better',
                ['direction' => 'up', 'value' => '5', 'status' => 'warning']],
            'diff kecil -> stable' => [5.0, 5.0, 'higher_is_better',
                ['direction' => 'stable', 'value' => 'Stable', 'status' => 'neutral']],
            'mode netral, diff besar -> warning' => [20.0, 5.0, 'apa_saja',
                ['direction' => 'up', 'value' => '15', 'status' => 'warning']],
            'mode netral, diff kecil -> neutral' => [8.0, 5.0, 'apa_saja',
                ['direction' => 'up', 'value' => '3', 'status' => 'neutral']],
        ];
    }

    #[DataProvider('calcTrendProvider')]
    public function test_calcTrend(float $current, float $previous, string $mode, array $expected): void
    {
        $this->assertSame($expected, $this->invoke('calcTrend', [$current, $previous, $mode]));
    }

    public static function mortalityTrendProvider(): array
    {
        return [
            'sama -> stable'    => [5, 5, ['direction' => 'stable', 'value' => 'Stable', 'status' => 'neutral']],
            'naik -> warning'   => [10, 5, ['direction' => 'up', 'value' => '5 ekor', 'status' => 'warning']],
            'turun -> positive' => [3, 8, ['direction' => 'down', 'value' => '5 ekor', 'status' => 'positive']],
        ];
    }

    #[DataProvider('mortalityTrendProvider')]
    public function test_calcMortalityTrend(int $thisMonth, int $lastMonth, array $expected): void
    {
        $this->assertSame($expected, $this->invoke('calcMortalityTrend', [$thisMonth, $lastMonth]));
    }
}