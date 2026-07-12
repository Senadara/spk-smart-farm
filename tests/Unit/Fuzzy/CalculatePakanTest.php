<?php

namespace Tests\Unit\Fuzzy;

use Mockery;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Services\Fuzzy\CalculatePakan;

class CalculatePakanTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Skenario: Menghitung konsumsi pakan per ekor untuk satu kandang
     *
     * Given ada data harian pakan 10 kg untuk kandang 'coop-1'
     * When menjalankan service `CalculatePakan::handle('coop-1')`
     * Then mengembalikan gram per ekor yang benar (2000.0)
     */
    public function test_hitung_pakan_per_kandang_menghasilkan_gram_per_ekor_yang_benar(): void
    {
        $coopId = 'coop-1';

        // mock query builder untuk tabel harianTernak
        $qbHarian = Mockery::mock();
        $qbHarian->shouldReceive('join')->andReturnSelf();
        $qbHarian->shouldReceive('where')->andReturnSelf();
        $qbHarian->shouldReceive('whereDate')->andReturnSelf();
        $qbHarian->shouldReceive('sum')->with('harianTernak.pakan')->andReturn(10.0);

        DB::shouldReceive('table')->with('harianTernak')->andReturn($qbHarian);

        // mock query builder untuk tabel unitBudidaya
        $qbUnit = Mockery::mock();
        $qbUnit->shouldReceive('where')->andReturnSelf();
        $qbUnit->shouldReceive('value')->with('jumlah')->andReturn(5);

        DB::shouldReceive('table')->with('unitBudidaya')->andReturn($qbUnit);

        $svc = new CalculatePakan();
        $result = $svc->handle($coopId);

        $this->assertSame(2000.0, $result);
    }
}
