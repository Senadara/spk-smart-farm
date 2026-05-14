<?php

namespace Tests\Feature;

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
            'inputs' => [
                'suhu', 'kelembapan', 'amonia', 'hdp', 'pakan', 'mortalitas'
            ],
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
}
