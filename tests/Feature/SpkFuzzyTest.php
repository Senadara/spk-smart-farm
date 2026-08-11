<?php

namespace Tests\Feature;

use App\Models\SpkFuzzyProfile;
use App\Models\SpkFuzzyRule;
use App\Models\SpkFuzzyRuleCondition;
use App\Models\SpkFuzzySet;
use App\Models\SpkFuzzyVariable;
use App\Services\Fuzzy\FuzzyProfileTemplateService;
use App\Services\Fuzzy\MamdaniEngine;
use Database\Seeders\SpkFuzzySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class SpkFuzzyTest extends TestCase
{
    use DatabaseTransactions;
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpkFuzzySeeder::class);
    }

    /**
     * Test the fuzzy process endpoint execution without coopId (Global mode).
     * This tests the entire cascaded Mamdani engine flow:
     * InputResolver -> MamdaniEngine -> NarrativeGenerator -> SpkFuzzyLog.
     */
    public function test_fuzzy_process_returns_successful_response(): void
    {
        // Panggil endpoint POST /spk-fuzzy/process
        $response = $this->postJson('/spk-fuzzy/process');

        // Pastikan status sukses
        $response->assertStatus(200);

        // Pastikan struktur JSON kembalian sesuai yang diharapkan
        $response->assertJsonStructure([
            'success',
            'log_id',
            'inputs',
            'result' => [
                'status_lingkungan',
                'score_lingkungan',
                'status_kesehatan',
                'score_kesehatan',
                'diagnosis_kausalitas',
                'recommendation',
                'narrative',
            ]
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertIsArray($response->json('inputs'));

        $profile = SpkFuzzyProfile::resolveForContext();
        $expectedInputs = SpkFuzzyVariable::query()
            ->where('profile_id', $profile?->id)
            ->where('type', 'input')
            ->where('group', '!=', 'kausalitas')
            ->pluck('name');

        foreach ($expectedInputs as $inputName) {
            $this->assertArrayHasKey($inputName, $response->json('inputs'));
        }

        $this->assertNotNull($response->json('result.narrative'));
    }

    /**
     * Test history endpoint.
     */
    public function test_fuzzy_history_returns_successful_response(): void
    {
        // Panggil endpoint GET /spk-fuzzy/history
        $response = $this->getJson('/spk-fuzzy/history');

        // Pastikan status sukses
        $response->assertStatus(200);

        // Pastikan format history list ada dan berupa array
        $response->assertJsonStructure([
            'success',
            'total',
            'data' => [
                '*' => [
                    'id', 'date', 'time', 'mode', 'status', 'verdict', 'scores'
                ]
            ]
        ]);
        
        $this->assertTrue($response->json('success'));
    }

    public function test_kausalitas_lookup_matches_labels_to_their_variables(): void
    {
        $profile = SpkFuzzyProfile::query()
            ->where('name', 'Ayam Petelur - RFC v1')
            ->first();

        $this->assertNotNull($profile);

        $result = app(MamdaniEngine::class)->lookupKausalitas('Sangat Nyaman', 'Kritis', $profile->id);

        $this->assertSame('Indikasi Faktor Non-Lingkungan', $result['label']);
        $this->assertSame('Baik', $result['lookup_labels']['lingkungan']);
        $this->assertSame('Buruk', $result['lookup_labels']['kesehatan']);
    }

    public function test_validated_layer_default_template_is_seeded(): void
    {
        $profile = SpkFuzzyProfile::query()
            ->where('name', 'Ayam Petelur - RFC v1')
            ->first();

        $this->assertNotNull($profile);

        $this->assertSame(11, SpkFuzzyVariable::where('profile_id', $profile->id)->count());
        $this->assertSame(47, SpkFuzzySet::query()
            ->join('spk_fuzzy_variables', 'spk_fuzzy_variables.id', '=', 'spk_fuzzy_sets.variable_id')
            ->where('spk_fuzzy_variables.profile_id', $profile->id)
            ->count());
        $this->assertSame(45, SpkFuzzyRule::where('profile_id', $profile->id)->where('is_active', true)->count());

        $this->assertDatabaseHas('spk_fuzzy_sets', ['name' => 'Sangat Nyaman']);
        $this->assertDatabaseHas('spk_fuzzy_sets', ['name' => 'Kritis']);
        $this->assertDatabaseHas('spk_fuzzy_sets', ['name' => 'Evaluasi Produktivitas']);
        $this->assertDatabaseHas('spk_fuzzy_variables', [
            'profile_id' => $profile->id,
            'name' => 'fcr',
            'group' => 'kesehatan',
            'type' => 'input',
        ]);
        $this->assertDatabaseMissing('spk_fuzzy_variables', [
            'profile_id' => $profile->id,
            'name' => 'feed_intake',
            'group' => 'kesehatan',
            'type' => 'input',
        ]);
    }

    public function test_sync_repairs_missing_default_health_rules_with_fcr(): void
    {
        $profile = SpkFuzzyProfile::query()
            ->where('name', 'Ayam Petelur - RFC v1')
            ->first();

        $this->assertNotNull($profile);

        $healthRuleIds = SpkFuzzyRule::query()
            ->where('profile_id', $profile->id)
            ->where('group', 'kesehatan')
            ->pluck('id');

        SpkFuzzyRuleCondition::query()->whereIn('rule_id', $healthRuleIds)->delete();
        SpkFuzzyRule::query()->whereIn('id', $healthRuleIds)->delete();

        $this->assertSame(0, SpkFuzzyRule::where('profile_id', $profile->id)->where('group', 'kesehatan')->count());

        app(FuzzyProfileTemplateService::class)->syncFromMaster($profile->fresh());

        $this->assertSame(18, SpkFuzzyRule::where('profile_id', $profile->id)->where('group', 'kesehatan')->count());

        $healthConditionVariables = SpkFuzzyRuleCondition::query()
            ->join('spk_fuzzy_rules', 'spk_fuzzy_rules.id', '=', 'spk_fuzzy_rule_conditions.rule_id')
            ->join('spk_fuzzy_variables', 'spk_fuzzy_variables.id', '=', 'spk_fuzzy_rule_conditions.variable_id')
            ->where('spk_fuzzy_rules.profile_id', $profile->id)
            ->where('spk_fuzzy_rules.group', 'kesehatan')
            ->pluck('spk_fuzzy_variables.name')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['fcr', 'hdp', 'mortalitas'], $healthConditionVariables);
    }

    public function test_cascaded_process_uses_validated_excel_rules(): void
    {
        $profile = SpkFuzzyProfile::query()
            ->where('name', 'Ayam Petelur - RFC v1')
            ->first();

        $result = app(MamdaniEngine::class)->processCascaded([
            'suhu' => 24,
            'kelembapan' => 60,
            'amonia' => 5,
            'hdp' => 90,
            'fcr' => 2.2,
            'mortalitas' => 0.1,
        ], $profile?->id, $profile?->commodity_id);

        $this->assertSame('Sangat Nyaman', $result['lingkungan']['label']);
        $this->assertSame('Sangat Baik', $result['kesehatan']['label']);
        $this->assertSame('Kondisi Optimal', $result['kausalitas']['label']);
    }

    public function test_unconfigured_template_cannot_be_activated_for_livestock_type(): void
    {
        $activeProfile = SpkFuzzyProfile::query()
            ->where('name', 'Ayam Petelur - RFC v1')
            ->first();

        $this->assertNotNull($activeProfile);

        $blankProfile = SpkFuzzyProfile::create([
            'jenis_budidaya_id' => $activeProfile->jenis_budidaya_id,
            'commodity_id' => $activeProfile->commodity_id,
            'name' => 'Ayam Petelur - Template Kosong',
            'version' => 'draft-test',
            'status' => 'review',
            'is_active' => false,
            'notes' => 'Template tanpa variabel dan rule tidak boleh aktif.',
        ]);

        $response = $this->patch(route('settings.fuzzy.templates.activate'), [
            'jenis_budidaya_id' => $activeProfile->jenis_budidaya_id,
            'profile_id' => $blankProfile->id,
        ]);

        $response->assertSessionHasErrors('profile_id');
        $this->assertFalse($blankProfile->fresh()->is_active);
        $this->assertTrue($activeProfile->fresh()->is_active);
    }

    public function test_reset_default_preserves_other_profiles(): void
    {
        $otherProfile = SpkFuzzyProfile::create([
            'name' => 'Sapi Potong - Test',
            'version' => 'test',
            'status' => 'active',
            'is_active' => true,
            'notes' => 'Profile dummy untuk memastikan reset ayam petelur tidak menghapus template lain.',
        ]);

        $otherVariable = SpkFuzzyVariable::create([
            'profile_id' => $otherProfile->id,
            'name' => 'dummy_input',
            'group' => 'lingkungan',
            'type' => 'input',
            'unit' => 'unit',
            'description' => 'Dummy variable.',
        ]);

        $response = $this->post(route('settings.fuzzy.reset'));

        $response->assertRedirect();
        $this->assertDatabaseHas('spk_fuzzy_variables', ['id' => $otherVariable->id]);
        $this->assertDatabaseHas('spk_fuzzy_profiles', ['id' => $otherProfile->id]);
    }
}
