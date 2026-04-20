<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;

class DashboardKandang extends TestCase
{
    // Menguji baris: $fuzzySensors['lingkungan'] = $barnEnvironment['barns'][0]['sensors'];
    // dan hardcode: $fuzzySensors['produktivitas'] = [...]
    public function test_logika_pemisahan_array_fuzzy_sensors_pada_method_index(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $response = $this->actingAs($user)->get('/peternakan');
        $response->assertStatus(200);

        // FASE RED: Ekspektasi variabel dikirimkan dengan nama yang salah, padahal dev mengirimkan 'fuzzySensors'
        $response->assertViewHas('fuzzySensors_yang_salah');
    }

    // Menguji baris: collect($barns)->first(fn($b) => $b['id'] == $id) ?? $barns[0];
    public function test_logika_fallback_kandang_pertama_jika_id_parameter_tidak_ditemukan()
    {
        $user = User::factory()->make(['id' => 1]);
        
        // Memasukkan ID Ngawur
        $response = $this->actingAs($user)->get('/peternakan/ID_NGAWUR_999');
        
        // FASE RED: Berharap mendapatkan error 404 Not Found, padahal developer membuat fallback ke $barns[0] sehingga statusnya 200 EK
        $response->assertStatus(404);
    }

    // Menguji baris: $iotDevices[0] ?? null;
    public function test_logika_pengambilan_iot_device_index_pertama_pada_method_show()
    {
        $user = User::factory()->make(['id' => 1]);
        $response = $this->actingAs($user)->get('/peternakan/1');
        
        // FASE RED: Ekspektasi view menerima 'iotDeviceSemua', padahal dev hanya mengirim 'iotDevice' indeks [0]
        $response->assertViewHas('iotDeviceSemua');
    }
}