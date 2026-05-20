<?php

namespace Tests\Unit\Fuzzy;

use Mockery;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Services\Fuzzy\CalculateHdp;

class CalculateHdpTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Skenario: Menghitung HDP (Hen-Day Production) per kandang
     *
     * Given kandang 'coop-1' memiliki populasi 10 dan panen total 85
     * When menjalankan `CalculateHdp::handle('coop-1')`
     * Then mengembalikan persentase HDP yang benar (850.0)
     */
    public function test_hitung_hdp_per_kandang_menghasilkan_persen(): void
    {
        $coopId = 'coop-1';

        $qbUnit = Mockery::mock();
        $qbUnit->shouldReceive('where')->andReturnSelf();
        $qbUnit->shouldReceive('first')->andReturn((object) ['jumlah' => 10]);

        DB::shouldReceive('table')->with('unitBudidaya')->andReturn($qbUnit);

        $qbPanen = Mockery::mock();
        $qbPanen->shouldReceive('join')->andReturnSelf();
        $qbPanen->shouldReceive('where')->andReturnSelf();
        $qbPanen->shouldReceive('whereDate')->andReturnSelf();
        $qbPanen->shouldReceive('sum')->with('panen.jumlah')->andReturn(85);

        DB::shouldReceive('table')->with('panen')->andReturn($qbPanen);

        $svc = new CalculateHdp();
        $result = $svc->handle($coopId);

        // (85 / 10) * 100 = 850.00 -> rounded to 2 decimals
        $this->assertSame(850.0, $result);
    }
}
