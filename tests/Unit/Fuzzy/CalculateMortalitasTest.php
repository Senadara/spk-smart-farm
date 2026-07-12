<?php

namespace Tests\Unit\Fuzzy;

use Mockery;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Services\Fuzzy\CalculateMortalitas;

class CalculateMortalitasTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Skenario: Menghitung mortalitas per kandang selama bulan berjalan
     *
     * Given ada 2 catatan kematian untuk kandang 'coop-1' dan populasi 40
     * When menjalankan `CalculateMortalitas::handle('coop-1')`
     * Then mengembalikan persentase mortalitas yang benar (5.0)
     */
    public function test_hitung_mortalitas_per_kandang_menghasilkan_persen(): void
    {
        $coopId = 'coop-1';

        $qbMati = Mockery::mock();
        $qbMati->shouldReceive('join')->andReturnSelf();
        $qbMati->shouldReceive('where')->andReturnSelf();
        $qbMati->shouldReceive('whereDate')->andReturnSelf();
        $qbMati->shouldReceive('count')->andReturn(2);

        DB::shouldReceive('table')->with('kematian')->andReturn($qbMati);

        $qbUnit = Mockery::mock();
        $qbUnit->shouldReceive('where')->andReturnSelf();
        $qbUnit->shouldReceive('value')->with('jumlah')->andReturn(40);

        DB::shouldReceive('table')->with('unitBudidaya')->andReturn($qbUnit);

        $svc = new CalculateMortalitas();
        $result = $svc->handle($coopId);

        // (2 / 40) * 100 = 5.0 -> rounded to 3 decimals
        $this->assertSame(5.0, $result);
    }
}
