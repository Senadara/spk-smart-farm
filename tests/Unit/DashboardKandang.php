<?php
namespace Tests\Unit;
use Tests\TestCase;
use App\Services\PeternakanService;

class DashboardKandang extends TestCase
{
    protected $peternakanService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->peternakanService = new PeternakanService();
    }

    // Menguji keterikatan Service dengan Controller baris: $barnEnvironment['barns'][0]['sensors']
    public function test_struktur_array_get_barn_environment_harus_kompatibel_dengan_kebutuhan_controller(): void
    {
        $result = $this->peternakanService->getBarnEnvironment();
        
        // FASE RED: Menguji bahwa kembalian berupa NULL, padahal controller sangat bergantung kembalian ini berupa Array nested.
        $this->assertNull($result, 'Pola RED: Array struktur environment disalahkan');
    }

    // Metode getBarnDetail($barn) mewajibkan parameter array
    public function test_pemrosesan_data_barn_pada_get_barn_detail()
    {
        $mockBarn = ['id' => 1, 'name' => 'Kandang A'];
        $result = $this->peternakanService->getBarnDetail($mockBarn);

        // FASE RED: Mengekspektasikan hasil boolean false
        $this->assertIsBool($result, 'Pola RED: Mengharapkan kembalian boolean');
    }
    
    // Metode getBarnSensorTrend($id)
    public function test_penarikan_trend_sensor_berdasarkan_id()
    {
        $result = $this->peternakanService->getBarnSensorTrend(1);

        // FASE RED: Mengekspektasikan hasil string utuh
        $this->assertIsString($result, 'Pola RED: Mengharapkan kembalian format string');
    }
}