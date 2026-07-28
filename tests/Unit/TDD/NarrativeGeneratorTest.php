<?php

namespace Tests\Unit\TDD;

use App\Services\Fuzzy\NarrativeGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

// Tujuan: memastikan hasil analisis fuzzy diterjemahkan ke dalam kalimat yang mudah dibaca peternak — mencakup kondisi lingkungan, kesehatan, dan rekomendasi tindakan.

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

    // ---------- combinedSeverity: menggabungkan dua label menjadi satu ----------

    public function test_lingkungan_buruk_dan_kesehatan_buruk_gabungannya_kritis(): void
    {
        $this->assertSame('kritis', $this->invoke('combinedSeverity', ['Buruk', 'Buruk']));
    }

    public function test_buruk_dan_waspada_termasuk_kritis(): void
    {
        $this->assertSame('kritis', $this->invoke('combinedSeverity', ['Buruk', 'Waspada']));
    }

    public function test_waspada_dan_waspada_hasilnya_waspada(): void
    {
        $this->assertSame('waspada', $this->invoke('combinedSeverity', ['Waspada', 'Waspada']));
    }

    public function test_baik_dan_waspada_masih_dianggap_waspada(): void
    {
        $this->assertSame('waspada', $this->invoke('combinedSeverity', ['Baik', 'Waspada']));
    }

    public function test_baik_dan_baik_hasilnya_baik(): void
    {
        $this->assertSame('baik', $this->invoke('combinedSeverity', ['Baik', 'Baik']));
    }

    public function test_optimal_dan_baik_masih_kategori_baik(): void
    {
        $this->assertSame('baik', $this->invoke('combinedSeverity', ['Optimal', 'Baik']));
    }

    public function test_optimal_dan_optimal_hasilnya_baik(): void
    {
        $this->assertSame('baik', $this->invoke('combinedSeverity', ['Optimal', 'Optimal']));
    }

    public function test_optimal_dan_buruk_hasilnya_waspada(): void
    {
        $this->assertSame('waspada', $this->invoke('combinedSeverity', ['Optimal', 'Buruk']));
    }

    public function test_label_tidak_dikenal_default_ke_waspada(): void
    {
        $this->assertSame('waspada', $this->invoke('combinedSeverity', ['XXX', 'YYY']));
    }

    // ---------- buildEnvironmentAnalysis: narasi kondisi lingkungan ----------

    public function test_kondisi_lingkungan_semua_ideal(): void
    {
        $inputs = ['suhu' => 32.54, 'kelembapan' => 68.0, 'amonia' => 15.2];
        $expected = 'Parameter lingkungan — suhu 32.5°C, kelembapan 68%, amonia 15.2 ppm — seluruhnya berada dalam zona ideal.';
        $this->assertSame($expected, $this->invoke('buildEnvironmentAnalysis', [$inputs, 'Optimal', null, []]));
    }

    public function test_kondisi_lingkungan_buruk_dan_terdeteksi_heat_stress(): void
    {
        $inputs = ['suhu' => 35.0];
        $dominant = ['diagnosis' => 'Heat Stress', 'alpha' => 0.8];
        $expected = 'Sensor lingkungan mendeteksi kondisi berbahaya: suhu 35°C melebihi ambang batas normal. Rule dominan mengidentifikasi kondisi ini sebagai "Heat Stress" (kepercayaan 80%).';
        $this->assertSame($expected, $this->invoke('buildEnvironmentAnalysis', [$inputs, 'Buruk', $dominant, []]));
    }

    public function test_kondisi_waspada_amonia_mendekati_batas(): void
    {
        $inputs = ['amonia' => 20.0];
        $dominant = ['diagnosis' => 'Amonia Tinggi'];
        $expected = 'Sensor lingkungan mencatat amonia 20 ppm, beberapa parameter mendekati batas ambang. Identifikasi utama: "Amonia Tinggi".';
        $this->assertSame($expected, $this->invoke('buildEnvironmentAnalysis', [$inputs, 'Waspada', $dominant, []]));
    }

    public function test_analisis_lingkungan_data_kosong_menghasilkan_kosong(): void
    {
        $this->assertSame('', $this->invoke('buildEnvironmentAnalysis', [[], 'Optimal', null, []]));
    }

    // ---------- buildHealthAnalysis: narasi kondisi kesehatan/produktivitas ----------

    public function test_produktivitas_semua_indikator_bagus(): void
    {
        $inputs = ['hdp' => 92.0, 'fcr' => 1.8, 'pakan' => 110.0, 'mortalitas' => 0.5];
        $expected = 'Di sisi produktivitas, semua indikator berada di puncak: HDP 92%, FCR 1.8, konsumsi pakan 110 g/ekor, mortalitas 0.5%.';
        $this->assertSame($expected, $this->invoke('buildHealthAnalysis', [$inputs, 'Optimal', null, []]));
    }

    public function test_produktivitas_buruk_terindikasi_penyakit(): void
    {
        $inputs = ['hdp' => 60.0];
        $dominant = ['diagnosis' => 'Penyakit'];
        $expected = 'Data produktivitas mengkhawatirkan: HDP 60% mengindikasikan gangguan serius. Pola yang terdeteksi: "Penyakit".';
        $this->assertSame($expected, $this->invoke('buildHealthAnalysis', [$inputs, 'Buruk', $dominant, []]));
    }

    public function test_analisis_kesehatan_data_kosong_menghasilkan_kosong(): void
    {
        $this->assertSame('', $this->invoke('buildHealthAnalysis', [[], 'Optimal', null, []]));
    }

    // ---------- buildDiagnosis: narasi diagnosis akhir ----------

    public function test_diagnosis_dengan_rekomendasi_tindakan(): void
    {
        $kausalitas = ['recommendation' => 'Panggil dokter hewan'];
        $expected = 'Sistem mendiagnosis kondisi ini sebagai Krisis Total. Gabungan lingkungan buruk dan kesehatan buruk memerlukan intervensi darurat segera. Rekomendasi: Panggil dokter hewan.';
        $this->assertSame($expected, $this->invoke('buildDiagnosis', ['Krisis Total', $kausalitas, 'Buruk', 'Buruk']));
    }

    public function test_rekomendasi_tidak_ada_dilewati(): void
    {
        $kausalitas = ['recommendation' => '-'];
        $expected = 'Kondisi keseluruhan stabil. Diagnosis: Stabil. Tidak ada tindakan darurat yang diperlukan.';
        $this->assertSame($expected, $this->invoke('buildDiagnosis', ['Stabil', $kausalitas, 'Baik', 'Baik']));
    }

    public function test_label_tidak_dikenal_tetap_ditampilkan(): void
    {
        $expected = 'Diagnosis sistem: Label Aneh berdasarkan kombinasi kondisi lingkungan (Baik) dan kesehatan (Waspada).';
        $this->assertSame($expected, $this->invoke('buildDiagnosis', ['Label Aneh', [], 'Baik', 'Waspada']));
    }
}
