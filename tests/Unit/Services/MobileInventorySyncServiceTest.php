<?php

namespace Tests\Unit\Services;

use App\Services\Inventory\MobileInventorySyncService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MobileInventorySyncServiceTest extends TestCase
{
    public function test_it_uses_active_daily_usage_instead_of_dividing_by_fixed_window(): void
    {
        $this->assertSame(25.0, $this->usageRateFromDailyTotals([
            '2026-07-28' => 25,
        ]));
    }

    public function test_it_counts_same_day_usage_from_multiple_barns_for_the_same_inventory_item(): void
    {
        $this->assertSame(40.0, $this->usageRateFromDailyTotals([
            '2026-07-27' => 20,
            '2026-07-28' => 40,
        ]));
    }

    private function usageRateFromDailyTotals(array $dailyTotals): float
    {
        $service = new MobileInventorySyncService();
        $method = (new ReflectionClass($service))->getMethod('usageRateFromDailyTotals');
        $method->setAccessible(true);

        return $method->invoke($service, $dailyTotals);
    }
}
