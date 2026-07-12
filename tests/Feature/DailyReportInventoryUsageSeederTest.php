<?php

namespace Tests\Feature;

use Database\Seeders\AyamPetelurSeeder;
use Database\Seeders\DailyReportInventoryUsageSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyReportInventoryUsageSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_daily_feed_reports_are_synced_to_inventory_usage(): void
    {
        $this->seed(AyamPetelurSeeder::class);
        $this->seed(DailyReportInventoryUsageSeeder::class);

        $report = DB::table('harianTernak as ht')
            ->join('laporan as l', 'l.id', '=', 'ht.laporanId')
            ->join('unitBudidaya as ub', 'ub.id', '=', 'l.unitBudidayaId')
            ->where('ub.nama', 'Kandang Layer A')
            ->where('l.isDeleted', 0)
            ->where('ht.isDeleted', 0)
            ->where('ht.pakan', '>', 0)
            ->orderByDesc('l.createdAt')
            ->first([
                'l.id as laporan_id',
                'l.catatan',
                'ht.pakan',
            ]);

        $this->assertNotNull($report);
        $this->assertGreaterThan(0, (float) $report->pakan);

        $mobileUsage = DB::table('penggunaanInventaris as pi')
            ->join('inventaris as i', 'i.id', '=', 'pi.inventarisId')
            ->where('pi.laporanId', $report->laporan_id)
            ->where('i.nama', 'Pakan Layer Complete - Kandang Layer A')
            ->first(['pi.jumlah']);

        $this->assertNotNull($mobileUsage);
        $this->assertEqualsWithDelta((float) $report->pakan, (float) $mobileUsage->jumlah, 0.01);

        $webMovement = DB::table('inventory_movements as im')
            ->join('inventory_items as ii', 'ii.id', '=', 'im.inventory_item_id')
            ->where('ii.sku', 'LAYER-A-FEED-KG')
            ->where('im.note', 'like', '%'.$report->laporan_id.'%')
            ->first(['im.quantity']);

        $this->assertNotNull($webMovement);
        $this->assertEqualsWithDelta(-((float) $report->pakan), (float) $webMovement->quantity, 0.01);
        $this->assertStringContainsString('Inventaris pakan:', (string) DB::table('laporan')->where('id', $report->laporan_id)->value('catatan'));
    }

    public function test_layer_care_actions_seed_vaccine_and_vitamin_usage(): void
    {
        $this->seed(AyamPetelurSeeder::class);
        $this->seed(DailyReportInventoryUsageSeeder::class);

        $vaccineUsage = DB::table('vitamin as v')
            ->join('inventaris as i', 'i.id', '=', 'v.inventarisId')
            ->join('laporan as l', 'l.id', '=', 'v.laporanId')
            ->where('i.nama', 'Vaksin ND-IB 1000 Dosis')
            ->where('l.judul', 'like', '%Kandang Layer A%')
            ->where('v.tipe', 'vaksin')
            ->first(['v.jumlah', 'l.catatan']);

        $this->assertNotNull($vaccineUsage);
        $this->assertEqualsWithDelta(1.0, (float) $vaccineUsage->jumlah, 0.01);
        $this->assertStringContainsString('Inventaris:', (string) $vaccineUsage->catatan);
    }
}
