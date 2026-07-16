<?php

namespace Tests\Feature;

use App\Models\SpkFuzzyProfile;
use App\Models\SpkFuzzyVariable;
use App\Services\Fuzzy\MamdaniEngine;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class SpkFuzzyTest extends TestCase
{
    use WithoutMiddleware;
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
            ->where('name', 'like', '%Petelur%')
            ->first();

        $this->assertNotNull($profile);

        $result = app(MamdaniEngine::class)->lookupKausalitas('Optimal', 'Buruk', $profile->id);

        $this->assertSame('Anomali Medis', $result['label']);
    }
}
