<?php

namespace Tests\Feature;

use App\Models\SpkFuzzyRule;
use App\Models\SpkFuzzySet;
use App\Models\SpkFuzzyVariable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SettingsFuzzyTest extends TestCase
{
    use DatabaseTransactions;

    private function authSession(): array
    {
        return [
            'api_token' => 'fake-token',
            'user'      => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
            '_token'    => 'csrf-token'
        ];
    }

    public function test_halaman_konfigurasi_fuzzy_menampilkan_stats_dan_koleksi_data(): void
    {
        SpkFuzzyVariable::create([
            'name'  => 'suhu_kandang',
            'group' => 'lingkungan',
            'type'  => 'input',
        ]);

        $response = $this->withSession($this->authSession())->get(route('settings.fuzzy.index'));

        $response->assertStatus(200);
        $response->assertViewIs('settings.fuzzy');
        $response->assertViewHasAll(['variables', 'rules', 'inputSources', 'allSets', 'stats']);
    }

    public function test_tambah_variabel_fuzzy_menolak_nama_duplikat_dalam_group_yang_sama(): void
    {
        SpkFuzzyVariable::create([
            'name'  => 'suhu',
            'group' => 'lingkungan',
            'type'  => 'input'
        ]);

        $response = $this->withSession($this->authSession())
                         ->post(route('settings.fuzzy.variables.store'), [
                             '_token'      => 'csrf-token',
                             'name'        => 'suhu',
                             'group'       => 'lingkungan',
                             'type'        => 'input',
                             'unit'        => 'C',
                             'description' => 'Suhu udara',
                         ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('name');
    }

    public function test_tambah_set_fuzzy_menolak_parameter_triangle_yang_tidak_valid(): void
    {
        $variable = SpkFuzzyVariable::create([
            'name'  => 'kelembapan',
            'group' => 'lingkungan',
            'type'  => 'input'
        ]);

        $response = $this->withSession($this->authSession())
                         ->post(route('settings.fuzzy.sets.store'), [
                             '_token'      => 'csrf-token',
                             'variable_id' => $variable->id,
                             'name'        => 'kering',
                             'shape'       => 'triangle',
                             'a'           => 30, 
                             'b'           => 20, 
                             'c'           => 10,
                         ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('shape');
    }

    public function test_reset_konfigurasi_fuzzy_memanggil_seeder_default(): void
    {
        \Illuminate\Support\Facades\Artisan::shouldReceive('call')
            ->once()
            ->with('db:seed', ['--class' => 'SpkFuzzySeeder', '--force' => true])
            ->andReturn(0);

        $response = $this->withSession($this->authSession())
                         ->post(route('settings.fuzzy.reset'), [
                             '_token' => 'csrf-token',
                         ]);

        $response->assertRedirect(route('settings.fuzzy.index'));
    }
}
