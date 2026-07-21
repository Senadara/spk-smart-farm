<?php

namespace Tests\Unit\Bva;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use App\Services\SupplierDistanceService;

/**
 * BVA untuk bracket jarak — memanggil SupplierDistanceService ASLI.
 *
 * - estimatedDeliveryDays()    → delivery days (SupplierDistanceService.php:86-92)
 * - estimatedDeliveryMinutes() → speed + handling (SupplierDistanceService.php:68-77)
 */
class DistanceBvaTest extends TestCase
{
    private static ?SupplierDistanceService $svc = null;

    public static function setUpBeforeClass(): void
    {
        self::$svc = new SupplierDistanceService();
    }

    /**
     * Speed bracket — memanggil estimatedDeliveryMinutes() asli.
     * Service: speed ≤15→25, ≤80→35, ≤200→45, >200→55 km/h
     *         handling ≤15→45, >15→90 menit
     * Rumus: max(30, ceil((jarak/speed)*60 + handling))
     */
    #[DataProvider('speedBracketProvider')]
    public static function test_estimated_delivery_minutes_bva(float $km, int $expectedMenit, string $label)
    {
        $aktual = self::$svc->estimatedDeliveryMinutes($km);
        self::assertEquals($expectedMenit, $aktual, "{$label}: {$km}km → {$aktual}menit, expected {$expectedMenit}");
    }

    public static function speedBracketProvider(): array
    {
        return [
            'DIS-13 14 km — speed 25, handling 45 → ceil((14/25)*60+45)=79'  => [14, 79, '≤15 speed 25'],
            'DIS-14 15 km (on-point ≤15) — speed 25, handling 45 → 81'       => [15, 81, 'on-point ≤15'],
            'DIS-15 16 km — speed 35, handling 90 → ceil((16/35)*60+90)=118' => [16, 118, '>15 speed 35'],
            'DIS-16 79 km — speed 35, handling 90 → ceil((79/35)*60+90)=226' => [79, 226, '≤80 speed 35'],
            'DIS-17 80 km (on-point ≤80) — speed 35, handling 90 → 228'     => [80, 228, 'on-point ≤80'],
            'DIS-18 81 km — speed 45, handling 90 → ceil((81/45)*60+90)=198' => [81, 198, '>80 speed 45'],
            'DIS-19 199 km — speed 45, handling 90 → ceil((199/45)*60+90)=356' => [199, 356, '≤200 speed 45'],
            'DIS-20 200 km (on-point ≤200) — speed 45, handling 90 → 357'    => [200, 357, 'on-point ≤200'],
            'DIS-21 201 km — speed 55, handling 90 → ceil((201/55)*60+90)=310' => [201, 310, '>200 speed 55'],
        ];
    }

    /**
     * Delivery days — memanggil estimatedDeliveryDays() asli.
     * Bracket: ≤40→0.5, ≤150→1.0, ≤350→2.0, ≤700→3.0, >700→min(7,max(4,ceil(n/300)))
     */
    #[DataProvider('deliveryDaysBracketProvider')]
    public function test_estimated_delivery_days_bva(float $km, float $expectedHari, string $label)
    {
        $aktual = self::$svc->estimatedDeliveryDays($km);
        $this->assertEquals($expectedHari, $aktual, "{$label}: {$km}km → {$aktual}hari, expected {$expectedHari}");
    }

    public static function deliveryDaysBracketProvider(): array
    {
        return [
            'DIS-01 39.9 km (<40) 0.5 hari'                 => [39.9, 0.5, 'bracket ≤40'],
            'DIS-02 40 km (on-point ≤40) 0.5 hari'          => [40, 0.5, 'on-point ≤40'],
            'DIS-03 40.1 km (>40) 1.0 hari'                  => [40.1, 1.0, 'bracket ≤150'],
            'DIS-04 149.9 km (<150) 1.0 hari'                => [149.9, 1.0, 'bracket ≤150'],
            'DIS-05 150 km (on-point ≤150) 1.0 hari'         => [150, 1.0, 'on-point ≤150'],
            'DIS-06 150.1 km (>150) 2.0 hari'                => [150.1, 2.0, 'bracket ≤350'],
            'DIS-07 349.9 km (<350) 2.0 hari'                => [349.9, 2.0, 'bracket ≤350'],
            'DIS-08 350 km (on-point ≤350) 2.0 hari'         => [350, 2.0, 'on-point ≤350'],
            'DIS-09 350.1 km (>350) 3.0 hari'                => [350.1, 3.0, 'bracket ≤700'],
            'DIS-10 699.9 km (<700) 3.0 hari'                => [699.9, 3.0, 'bracket ≤700'],
            'DIS-11 700 km (on-point ≤700) 3.0 hari'         => [700, 3.0, 'on-point ≤700'],
            'DIS-12 700.1 km (>700) min(7,max(4,ceil(701/300)))=4' => [700.1, 4.0, 'default >700'],
            'DIS-22 1200 km default: min(7,max(4,ceil(1200/300)))=4' => [1200, 4.0, 'default >700'],
            'DIS-23 2100 km default: capped at 7 -> min(7,max(4,ceil(2100/300)))=7' => [2100, 7.0, 'default cap 7'],
            'DIS-24 800 km default: min(7,max(4,ceil(800/300)))=4' => [800, 4.0, 'default >700'],
        ];
    }
}
