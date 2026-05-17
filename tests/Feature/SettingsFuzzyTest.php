<?php

namespace Tests\Feature;

use App\Models\SpkFuzzyInputSource;
use App\Models\SpkFuzzyRule;
use App\Models\SpkFuzzySet;
use App\Models\SpkFuzzyVariable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\PresenceVerifierInterface;
use Mockery;
use Tests\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class SettingsFuzzyTest extends TestCase
{
    private function authSession(): array
    {
        return [
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
        ];
    }

    public function test_halaman_konfigurasi_fuzzy_menampilkan_stats_dan_koleksi_data(): void
    {
        $this->withoutExceptionHandling();

        $variableQuery = Mockery::mock();
        $variableQuery->shouldReceive('orderByRaw')->andReturnSelf();
        $variableQuery->shouldReceive('get')->andReturn(collect([
            (object) ['sets' => collect([1, 2])],
            (object) ['sets' => collect([1])],
        ]));
        $variableMock = Mockery::mock('alias:' . SpkFuzzyVariable::class);
        $variableMock->shouldReceive('with')->with(['sets', 'inputSource'])->andReturn($variableQuery);

        $ruleQuery = Mockery::mock();
        $ruleQuery->shouldReceive('orderByRaw')->andReturnSelf();
        $ruleQuery->shouldReceive('get')->andReturn(collect([
            (object) ['id' => 'rule-1'],
        ]));
        $ruleMock = Mockery::mock('alias:' . SpkFuzzyRule::class);
        $ruleMock->shouldReceive('with')->with(['conditions.variable', 'conditions.set', 'outputSet.variable'])->andReturn($ruleQuery);

        $sourceQuery = Mockery::mock();
        $sourceQuery->shouldReceive('get')->andReturn(collect([
            (object) [
                'id' => 'src-1',
                'source_type' => 'iot',
                'source_name' => 'sensor_unit',
                'field_name' => 'temperature',
                'function_name' => null,
                'extra_config' => [],
                'variable' => (object) ['name' => 'suhu'],
            ],
            (object) [
                'id' => 'src-2',
                'source_type' => 'function',
                'source_name' => null,
                'field_name' => null,
                'function_name' => 'App\\Services\\DummyResolver',
                'extra_config' => [],
                'variable' => (object) ['name' => 'hdp'],
            ],
        ]));
        $sourceMock = Mockery::mock('alias:' . SpkFuzzyInputSource::class);
        $sourceMock->shouldReceive('with')->with('variable')->andReturn($sourceQuery);

        $setQuery = Mockery::mock();
        $setQuery->shouldReceive('get')->andReturn(collect([
            (object) ['variable_id' => 'var-1'],
            (object) ['variable_id' => 'var-1'],
            (object) ['variable_id' => 'var-2'],
        ]));
        $setMock = Mockery::mock('alias:' . SpkFuzzySet::class);
        $setMock->shouldReceive('with')->with('variable')->andReturn($setQuery);

        $response = $this->withSession($this->authSession())->get('/settings/fuzzy');

        $response->assertStatus(200);
        $response->assertViewIs('settings.fuzzy');
        $response->assertViewHasAll(['variables', 'rules', 'inputSources', 'allSets', 'stats']);
    }

    public function test_tambah_variabel_fuzzy_menolak_nama_duplikat_dalam_group_yang_sama(): void
    {
        $variableQuery = Mockery::mock();
        $variableQuery->shouldReceive('where')->andReturnSelf();
        $variableQuery->shouldReceive('exists')->andReturnTrue();
        $variableMock = Mockery::mock('alias:' . SpkFuzzyVariable::class);
        $variableMock->shouldReceive('where')->andReturn($variableQuery);

        $response = $this->withSession(array_merge($this->authSession(), ['_token' => 'csrf-token']))->post('/settings/fuzzy/variables', [
            '_token' => 'csrf-token',
            'name' => 'suhu',
            'group' => 'lingkungan',
            'type' => 'input',
            'unit' => 'C',
            'description' => 'Suhu udara',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('name');
    }

    public function test_tambah_set_fuzzy_menolak_parameter_triangle_yang_tidak_valid(): void
    {
        $presenceVerifier = Mockery::mock(PresenceVerifierInterface::class);
        $presenceVerifier->shouldReceive('getCount')->andReturn(1);
        $presenceVerifier->shouldReceive('getMultiCount')->andReturn(1);
        $this->app['validator']->setPresenceVerifier($presenceVerifier);

        $response = $this->withSession(array_merge($this->authSession(), ['_token' => 'csrf-token']))->post('/settings/fuzzy/sets', [
            '_token' => 'csrf-token',
            'variable_id' => 'var-1',
            'name' => 'hangat',
            'shape' => 'triangle',
            'a' => 30,
            'b' => 20,
            'c' => 10,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('shape');
    }

    public function test_reset_konfigurasi_fuzzy_memanggil_seeder_default(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('db:seed', ['--class' => 'SpkFuzzySeeder', '--force' => true])
            ->andReturn(0);

        $response = $this->withSession(array_merge($this->authSession(), ['_token' => 'csrf-token']))->post('/settings/fuzzy/reset', [
            '_token' => 'csrf-token',
        ]);

        $response->assertRedirect(route('settings.fuzzy.index'));
    }
}