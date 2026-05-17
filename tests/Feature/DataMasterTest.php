<?php

namespace Tests\Feature;

use Tests\TestCase;

class DataMasterTest extends TestCase
{
    public function test_halaman_data_master_ditampilkan_dengan_benar(): void
    {
        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);
        $response = $this->get('/data-master');

        $response->assertStatus(200);
        $response->assertViewHasAll(['users', 'blokKebun', 'roleOptions', 'jenisBudidayaOptions']);
    }
}
