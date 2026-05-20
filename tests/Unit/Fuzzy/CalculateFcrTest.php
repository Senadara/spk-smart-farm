<?php

namespace Tests\Unit\Fuzzy;

use Mockery;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Services\Fuzzy\CalculateFcr;

class CalculateFcrTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Skenario: Menghitung FCR (Feed Conversion Ratio) per kandang
     *
     * Given total pakan 12 kg dan total egg-mass 6 kg untuk 'coop-1'
     * When menjalankan `CalculateFcr::handle('coop-1')`
     * Then mengembalikan nilai FCR yang benar (2.0)
     */
    public function test_hitung_fcr_per_kandang_menghasilkan_nilai_fcr(): void
    {
        $coopId = 'coop-1';

        $qbPakan = Mockery::mock();
        $qbPakan->shouldReceive('join')->andReturnSelf();
        $qbPakan->shouldReceive('where')->andReturnSelf();
        $qbPakan->shouldReceive('whereDate')->andReturnSelf();
        $qbPakan->shouldReceive('sum')->with('harianTernak.pakan')->andReturn(12.0);

        DB::shouldReceive('table')->with('harianTernak')->andReturn($qbPakan);

        $qbEgg = Mockery::mock();
        $qbEgg->shouldReceive('join')->andReturnSelf();
        $qbEgg->shouldReceive('where')->andReturnSelf();
        $qbEgg->shouldReceive('whereDate')->andReturnSelf();
        $qbEgg->shouldReceive('selectRaw')->andReturnSelf();
        $qbEgg->shouldReceive('value')->with('totalMass')->andReturn(6.0);

        DB::shouldReceive('table')->with('panen')->andReturn($qbEgg);

        $svc = new CalculateFcr();
        $result = $svc->handle($coopId);

        // 12.0 / 6.0 = 2.0 -> rounded to 3 decimals
        $this->assertSame(2.0, $result);
    }
}
