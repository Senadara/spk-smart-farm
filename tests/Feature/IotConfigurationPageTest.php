<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class IotConfigurationPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_iot_config_redirects_to_setup_section(): void
    {
        $response = $this->actingAsPjawab()->get('/iot/config');

        $response->assertRedirect(route('iot.devices').'#advanced-iot-config');
    }

    public function test_pjawab_can_open_iot_setup_page(): void
    {
        $response = $this->actingAsPjawab()->get('/iot/devices');

        $response->assertOk();
        $response->assertSee('Setup IoT Kandang');
        $response->assertSee('Urutan setup yang benar');
    }

    private function actingAsPjawab(): self
    {
        return $this->withSession([
            'api_token' => 'testing-token',
            'user' => [
                'id' => 'fc571afa-e66b-437b-8b15-dce68edee3f3',
                'name' => 'Penanggung Jawab Demo',
                'email' => 'pjawab@email.com',
                'role' => 'pjawab',
            ],
        ]);
    }
}
