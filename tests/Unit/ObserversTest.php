<?php

namespace Tests\Unit;

use App\Models\InventorySupplierProduk;
use App\Models\SpkAhpBobot;
use App\Models\SpkRanking;
use App\Models\SpkSupplierParameterValue;
use App\Observers\InventorySupplierProdukObserver;
use App\Observers\SpkAhpBobotObserver;
use App\Observers\SpkSupplierParameterValueObserver;
use Mockery;
use Tests\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class ObserversTest extends TestCase
{
    public function test_observer_inventory_supplier_produk_menonaktifkan_ranking_terkait(): void
    {
        $rankingMock = Mockery::mock('alias:' . SpkRanking::class);
        $rankingMock->shouldReceive('where')->with('produk_id', 'prd-1')->andReturnSelf();
        $rankingMock->shouldReceive('update')->with(['is_valid' => false])->twice();

        $observer = new InventorySupplierProdukObserver();
        $pivot = new InventorySupplierProduk(['produk_id' => 'prd-1']);

        $observer->saved($pivot);
        $observer->deleted($pivot);
    }

    public function test_observer_spk_supplier_parameter_value_menonaktifkan_ranking_terkait(): void
    {
        $rankingMock = Mockery::mock('alias:' . SpkRanking::class);
        $rankingMock->shouldReceive('where')->with('produk_id', 'prd-1')->andReturnSelf();
        $rankingMock->shouldReceive('update')->with(['is_valid' => false])->twice();

        $observer = new SpkSupplierParameterValueObserver();
        $value = new SpkSupplierParameterValue(['produk_id' => 'prd-1']);

        $observer->saved($value);
        $observer->deleted($value);
    }

    public function test_observer_spk_ahp_bobot_menonaktifkan_ranking_pengguna(): void
    {
        $rankingMock = Mockery::mock('alias:' . SpkRanking::class);
        $rankingMock->shouldReceive('where')->with('user_id', 1)->andReturnSelf();
        $rankingMock->shouldReceive('update')->with(['is_valid' => false])->twice();

        $observer = new SpkAhpBobotObserver();
        $bobot = new SpkAhpBobot(['user_id' => 1]);

        $observer->saved($bobot);
        $observer->deleted($bobot);
    }
}