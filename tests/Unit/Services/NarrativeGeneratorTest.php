<?php

namespace Tests\Unit\Narrative;

use App\Services\Fuzzy\NarrativeGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class NarrativeGeneratorTest extends TestCase
{
    private NarrativeGenerator $gen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gen = new NarrativeGenerator();
    }

    private function invoke(string $method, array $args)
    {
        $ref = new ReflectionMethod(NarrativeGenerator::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($this->gen, $args);
    }

    public function test_severity_buruk_buruk_kritis(): void
    {
        // (1+1)/2 = 1.0 -> kritis
        $this->assertSame('kritis', $this->invoke('combinedSeverity', ['Buruk', 'Buruk']));
    }

    public function test_severity_buruk_waspada_batas_1_5_kritis(): void
    {
        // (1+2)/2 = 1.5 <= 1.5 -> kritis (nilai batas)
        $this->assertSame('kritis', $this->invoke('combinedSeverity', ['Buruk', 'Waspada']));
    }

    public function test_severity_waspada_waspada_waspada(): void
    {
        // (2+2)/2 = 2.0 -> waspada
        $this->assertSame('waspada', $this->invoke('combinedSeverity', ['Waspada', 'Waspada']));
    }

    public function test_severity_baik_waspada_batas_2_5_waspada(): void
    {
        // (3+2)/2 = 2.5 <= 2.5 -> waspada (nilai batas)
        $this->assertSame('waspada', $this->invoke('combinedSeverity', ['Baik', 'Waspada']));
    }

    public function test_severity_baik_baik_baik(): void
    {
        // (3+3)/2 = 3.0 -> baik
        $this->assertSame('baik', $this->invoke('combinedSeverity', ['Baik', 'Baik']));
    }

    public function test_severity_optimal_baik_batas_3_5_baik(): void
    {
        // (4+3)/2 = 3.5 <= 3.5 -> baik (nilai batas)
        $this->assertSame('baik', $this->invoke('combinedSeverity', ['Optimal', 'Baik']));
    }

    public function test_severity_optimal_optimal_optimal(): void
    {
        // (4+4)/2 = 4.0 -> optimal
        $this->assertSame('optimal', $this->invoke('combinedSeverity', ['Optimal', 'Optimal']));
    }

    public function test_severity_optimal_buruk_waspada(): void
    {
        // (4+1)/2 = 2.5 -> waspada
        $this->assertSame('waspada', $this->invoke('combinedSeverity', ['Optimal', 'Buruk']));
    }

    public function test_severity_label_tak_dikenal_default_waspada(): void
    {
        // label tak dikenal -> skor default 2, (2+2)/2 = 2.0 -> waspada
        $this->assertSame('waspada', $this->invoke('combinedSeverity', ['XXX', 'YYY']));
    }

    public function test_env_optimal_tanpa_dominant(): void
    {
        $inputs = ['suhu' => 32.54, 'kelembapan' => 68.0, 'amonia' => 15.2];
        $expected = 'Parameter lingkungan — suhu 32.5°C, kelembapan 68%, amonia 15.2 ppm — seluruhnya berada dalam zona ideal.';
        $this->assertSame($expected, $this->invoke('buildEnvironmentAnalysis', [$inputs, 'Optimal', null, []]));
    }

    public function test_env_buruk_dengan_dominant_dan_alpha(): void
    {
        $inputs = ['suhu' => 35.0];
        $dominant = ['diagnosis' => 'Heat Stress', 'alpha' => 0.8];
        $expected = 'Sensor lingkungan mendeteksi kondisi berbahaya: suhu 35°C melebihi ambang batas normal. Rule dominan mengidentifikasi kondisi ini sebagai "Heat Stress" (kepercayaan 80%).';
        $this->assertSame($expected, $this->invoke('buildEnvironmentAnalysis', [$inputs, 'Buruk', $dominant, []]));
    }

    public function test_env_waspada_dengan_diagnosis_tanpa_alpha(): void
    {
        $inputs = ['amonia' => 20.0];
        $dominant = ['diagnosis' => 'Amonia Tinggi'];
        $expected = 'Sensor lingkungan mencatat amonia 20 ppm, beberapa parameter mendekati batas ambang. Identifikasi utama: "Amonia Tinggi".';
        $this->assertSame($expected, $this->invoke('buildEnvironmentAnalysis', [$inputs, 'Waspada', $dominant, []]));
    }

    public function test_env_input_kosong_string_kosong(): void
    {
        $this->assertSame('', $this->invoke('buildEnvironmentAnalysis', [[], 'Optimal', null, []]));
    }

    public function test_health_optimal_tanpa_dominant(): void
    {
        $inputs = ['hdp' => 92.0, 'fcr' => 1.8, 'pakan' => 110.0, 'mortalitas' => 0.5];
        $expected = 'Di sisi produktivitas, semua indikator berada di puncak: HDP 92%, FCR 1.8, konsumsi pakan 110 g/ekor, mortalitas 0.5%.';
        $this->assertSame($expected, $this->invoke('buildHealthAnalysis', [$inputs, 'Optimal', null, []]));
    }

    public function test_health_buruk_dengan_diagnosis(): void
    {
        $inputs = ['hdp' => 60.0];
        $dominant = ['diagnosis' => 'Penyakit'];
        $expected = 'Data produktivitas mengkhawatirkan: HDP 60% mengindikasikan gangguan serius. Pola yang terdeteksi: "Penyakit".';
        $this->assertSame($expected, $this->invoke('buildHealthAnalysis', [$inputs, 'Buruk', $dominant, []]));
    }

    public function test_health_input_kosong_string_kosong(): void
    {
        $this->assertSame('', $this->invoke('buildHealthAnalysis', [[], 'Optimal', null, []]));
    }

    public function test_diagnosis_label_dikenal_dengan_rekomendasi(): void
    {
        $kausalitas = ['recommendation' => 'Panggil dokter hewan'];
        $expected = 'Sistem mendiagnosis kondisi ini sebagai **Krisis Total** — gabungan lingkungan buruk dan kesehatan buruk memerlukan intervensi darurat segera. **Rekomendasi:** Panggil dokter hewan.';
        $this->assertSame($expected, $this->invoke('buildDiagnosis', ['Krisis Total', $kausalitas, 'Buruk', 'Buruk']));
    }

    public function test_diagnosis_rekomendasi_strip_tidak_ditambahkan(): void
    {
        $kausalitas = ['recommendation' => '-'];
        $expected = 'Kondisi keseluruhan stabil. Diagnosis: **Stabil** — tidak ada tindakan darurat yang diperlukan.';
        $this->assertSame($expected, $this->invoke('buildDiagnosis', ['Stabil', $kausalitas, 'Baik', 'Baik']));
    }

    public function test_diagnosis_label_tak_dikenal_pakai_fallback(): void
    {
        $expected = 'Diagnosis sistem: **Label Aneh** berdasarkan kombinasi kondisi lingkungan (Baik) dan kesehatan (Waspada).';
        $this->assertSame($expected, $this->invoke('buildDiagnosis', ['Label Aneh', [], 'Baik', 'Waspada']));
    }
}