<?php

namespace App\Services\Fuzzy;

/**
 * NarrativeGenerator — menghasilkan narasi AI-like dari hasil Mamdani engine.
 *
 * Prinsip:
 *   ✅ Gunakan dominant rule (alpha tertinggi) sebagai konteks utama
 *   ✅ Sertakan nilai aktual sensor & KPI secara eksplisit
 *   ✅ Bangun kalimat dari kombinasi kondisi, bukan template statis
 *   ✅ Variasikan struktur kalimat berdasarkan severity
 *   ❌ Bukan hanya "label: Waspada"
 *   ❌ Bukan template yang sama setiap saat
 */
class NarrativeGenerator
{
    /**
     * Generate narasi lengkap dari hasil processCascaded().
     *
     * @param  array $result  Output dari MamdaniEngine::processCascaded()
     * @param  string|null $barnName Nama kandang (opsional, untuk konteks)
     * @return string Narasi natural language
     */
    public function generate(array $result, ?string $barnName = null): string
    {
        $inputs     = $result['inputs'] ?? [];
        $lingkungan = $result['lingkungan'] ?? [];
        $kesehatan  = $result['kesehatan'] ?? [];
        $kausalitas = $result['kausalitas'] ?? [];

        $lingkLabel    = $lingkungan['label'] ?? 'Tidak Diketahui';
        $kesehatLabel  = $kesehatan['label'] ?? 'Tidak Diketahui';
        $diagnosisLabel= $kausalitas['label'] ?? 'Tidak Diketahui';
        $dominantLingk = $lingkungan['dominant_rule'] ?? null;
        $dominantKes   = $kesehatan['dominant_rule'] ?? null;

        $parts = [];

        // ── BAGIAN 1: Pembuka berbasis kondisi dan nama kandang ──────────
        $parts[] = $this->buildOpening($barnName, $inputs, $lingkLabel, $kesehatLabel);

        // ── BAGIAN 2: Analisis lingkungan dari dominant rule ─────────────
        $parts[] = $this->buildEnvironmentAnalysis($inputs, $lingkLabel, $dominantLingk, $lingkungan);

        // ── BAGIAN 3: Analisis produktivitas/kesehatan ───────────────────
        $parts[] = $this->buildHealthAnalysis($inputs, $kesehatLabel, $dominantKes, $kesehatan);

        // ── BAGIAN 4: Diagnosis kausalitas + rekomendasi ─────────────────
        $parts[] = $this->buildDiagnosis($diagnosisLabel, $kausalitas, $lingkLabel, $kesehatLabel);

        return implode(' ', array_filter($parts));
    }

    // ────────────────────────────────────────────────────────────────────
    // BAGIAN 1: PEMBUKA
    // ────────────────────────────────────────────────────────────────────

    private function buildOpening(?string $barnName, array $inputs, string $lingkLabel, string $kesehatLabel): string
    {
        $ctx = $barnName ? "Kandang {$barnName}" : "Kandang";

        // Pilih opener berdasarkan kombinasi severity
        $combined = $this->combinedSeverity($lingkLabel, $kesehatLabel);

        $openers = match ($combined) {
            'kritis' => [
                "{$ctx} saat ini berada dalam kondisi yang memerlukan perhatian segera.",
                "Sistem mendeteksi kondisi kritis pada {$ctx} berdasarkan data sensor terbaru.",
            ],
            'waspada' => [
                "{$ctx} menunjukkan beberapa parameter di luar zona ideal yang perlu diperhatikan.",
                "Pemantauan {$ctx} menunjukkan kondisi campuran yang membutuhkan monitoring ketat.",
            ],
            'baik' => [
                "{$ctx} beroperasi dalam kondisi yang relatif baik berdasarkan data terkini.",
                "Data sensor menunjukkan {$ctx} berada pada performa yang memadai.",
            ],
            'optimal' => [
                "{$ctx} saat ini berada pada kondisi optimal — seluruh parameter dalam zona ideal.",
                "Sistem mencatat performa puncak pada {$ctx} berdasarkan data multi-sensor.",
            ],
            default => "{$ctx} sedang dianalisis berdasarkan data sensor terkini.",
        };

        return $openers[array_rand($openers)];
    }

    // ────────────────────────────────────────────────────────────────────
    // BAGIAN 2: ANALISIS LINGKUNGAN
    // ────────────────────────────────────────────────────────────────────

    private function buildEnvironmentAnalysis(array $inputs, string $lingkLabel, ?array $dominant, array $lingkungan): string
    {
        $suhu      = isset($inputs['suhu']) ? round($inputs['suhu'], 1) . '°C' : null;
        $kelembapan= isset($inputs['kelembapan']) ? round($inputs['kelembapan'], 1) . '%' : null;
        $amonia    = isset($inputs['amonia']) ? round($inputs['amonia'], 1) . ' ppm' : null;

        $nilaiStr = implode(', ', array_filter([
            $suhu      ? "suhu {$suhu}" : null,
            $kelembapan? "kelembapan {$kelembapan}" : null,
            $amonia    ? "amonia {$amonia}" : null,
        ]));

        if (!$nilaiStr) {
            return '';
        }

        $diagnosis = $dominant['diagnosis'] ?? null;
        $alpha     = isset($dominant['alpha']) ? round($dominant['alpha'] * 100) : null;

        // Pilih struktur kalimat berdasarkan label
        $base = match ($lingkLabel) {
            'Optimal' => "Parameter lingkungan — {$nilaiStr} — seluruhnya berada dalam zona ideal.",
            'Baik'    => "Kondisi lingkungan secara umum terkendali: {$nilaiStr}.",
            'Waspada' => "Sensor lingkungan mencatat {$nilaiStr}, beberapa parameter mendekati batas ambang.",
            'Buruk'   => "Sensor lingkungan mendeteksi kondisi berbahaya: {$nilaiStr} melebihi ambang batas normal.",
            default   => "Data lingkungan tercatat: {$nilaiStr}.",
        };

        // Tambahkan konteks dari dominant rule jika ada
        if ($diagnosis && $alpha !== null) {
            $base .= " Rule dominan mengidentifikasi kondisi ini sebagai \"{$diagnosis}\" (kepercayaan {$alpha}%).";
        } elseif ($diagnosis) {
            $base .= " Identifikasi utama: \"{$diagnosis}\".";
        }

        return $base;
    }

    // ────────────────────────────────────────────────────────────────────
    // BAGIAN 3: ANALISIS KESEHATAN/PRODUKTIVITAS
    // ────────────────────────────────────────────────────────────────────

    private function buildHealthAnalysis(array $inputs, string $kesehatLabel, ?array $dominant, array $kesehatan): string
    {
        $hdp       = isset($inputs['hdp']) ? round($inputs['hdp'], 1) . '%' : null;
        $pakan     = isset($inputs['pakan']) ? round($inputs['pakan'], 1) . ' g/ekor' : null;
        $mortalitas= isset($inputs['mortalitas']) ? round($inputs['mortalitas'], 2) . '%' : null;
        $fcr       = isset($inputs['fcr']) ? round($inputs['fcr'], 2) : null;

        $nilaiStr = implode(', ', array_filter([
            $hdp       ? "HDP {$hdp}" : null,
            $fcr       ? "FCR {$fcr}" : null,
            $pakan     ? "konsumsi pakan {$pakan}" : null,
            $mortalitas? "mortalitas {$mortalitas}" : null,
        ]));

        if (!$nilaiStr) {
            return '';
        }

        $diagnosis = $dominant['diagnosis'] ?? null;

        $base = match ($kesehatLabel) {
            'Optimal' => "Di sisi produktivitas, semua indikator berada di puncak: {$nilaiStr}.",
            'Baik'    => "Performa produksi berjalan baik dengan indikator: {$nilaiStr}.",
            'Waspada' => "Indikator produktivitas menunjukkan ketidakstabilan: {$nilaiStr}.",
            'Buruk'   => "Data produktivitas mengkhawatirkan: {$nilaiStr} mengindikasikan gangguan serius.",
            default   => "Data produktivitas tercatat: {$nilaiStr}.",
        };

        if ($diagnosis) {
            $base .= " Pola yang terdeteksi: \"{$diagnosis}\".";
        }

        return $base;
    }

    // ────────────────────────────────────────────────────────────────────
    // BAGIAN 4: DIAGNOSIS KAUSALITAS + REKOMENDASI
    // ────────────────────────────────────────────────────────────────────

    private function buildDiagnosis(string $diagnosisLabel, array $kausalitas, string $lingkLabel, string $kesehatLabel): string
    {
        $recommendation = $kausalitas['recommendation'] ?? null;
        $ruleMatch      = $kausalitas['matched_rule'] ?? null;

        // Bangun kalimat berdasarkan diagnosis label
        $diagnosisMap = [
            'Krisis Total'            => "Sistem mendiagnosis kondisi ini sebagai **Krisis Total** — gabungan lingkungan buruk dan kesehatan buruk memerlukan intervensi darurat segera.",
            'Stres Lingkungan'        => "Diagnosis sistem: **Stres Lingkungan** — tekanan lingkungan yang buruk mulai mempengaruhi keseimbangan kandang meski ayam masih bertahan.",
            'Lingkungan Berisiko'     => "Meski kesehatan ayam masih terjaga, kondisi lingkungan yang buruk merupakan risiko laten. Diagnosis: **Lingkungan Berisiko**.",
            'Gangguan Non-Lingkungan' => "Lingkungan dalam kondisi waspada, namun gangguan kesehatan yang buruk menunjukkan masalah non-lingkungan. Diagnosis: **Gangguan Non-Lingkungan**.",
            'Performa Tidak Stabil'   => "Kedua dimensi (lingkungan dan kesehatan) berada di level waspada. Diagnosis: **Performa Tidak Stabil** — sistem manajemen perlu dievaluasi.",
            'Toleransi Baik'          => "Lingkungan dalam kondisi waspada namun ayam menunjukkan toleransi yang baik. Diagnosis: **Toleransi Baik** — terus monitoring.",
            'Wabah Internal'          => "Lingkungan baik namun kesehatan buruk — sistem menduga adanya masalah internal. Diagnosis: **Wabah Internal**, indikasikan penyakit.",
            'Inefisiensi Sistem'      => "Lingkungan baik, namun produktivitas belum optimal. Diagnosis: **Inefisiensi Sistem** — periksa manajemen pakan dan operasional.",
            'Stabil'                  => "Kondisi keseluruhan stabil. Diagnosis: **Stabil** — tidak ada tindakan darurat yang diperlukan.",
            'Anomali Medis'           => "Lingkungan optimal namun kesehatan memburuk — ini mengindikasikan anomali medis. Diagnosis: **Anomali Medis**, segera cek kemungkinan penyakit.",
            'Perlu Monitoring'        => "Kondisi mendekati optimal namun kesehatan perlu dipantau. Diagnosis: **Perlu Monitoring** — pantau tren 24–48 jam ke depan.",
            'Kondisi Baik'            => "Lingkungan optimal dan kesehatan baik. Diagnosis: **Kondisi Baik** — pertahankan manajemen saat ini.",
            'Kondisi Optimal'         => "Seluruh dimensi berada di level optimal. Diagnosis: **Kondisi Optimal** — pertahankan semua aspek manajemen.",
        ];

        $sentenceDiagnosis = $diagnosisMap[$diagnosisLabel]
            ?? "Diagnosis sistem: **{$diagnosisLabel}** berdasarkan kombinasi kondisi lingkungan ({$lingkLabel}) dan kesehatan ({$kesehatLabel}).";

        // Tambahkan rekomendasi jika ada
        if ($recommendation && $recommendation !== '-') {
            $sentenceDiagnosis .= " **Rekomendasi:** {$recommendation}.";
        }

        return $sentenceDiagnosis;
    }

    // ────────────────────────────────────────────────────────────────────
    // HELPER
    // ────────────────────────────────────────────────────────────────────

    private function combinedSeverity(string $lingkLabel, string $kesehatLabel): string
    {
        $severityMap = ['Buruk' => 1, 'Waspada' => 2, 'Baik' => 3, 'Optimal' => 4];

        $lingkScore   = $severityMap[$lingkLabel] ?? 2;
        $kesehatScore = $severityMap[$kesehatLabel] ?? 2;
        $avg          = ($lingkScore + $kesehatScore) / 2;

        if ($avg <= 1.5) return 'kritis';
        if ($avg <= 2.5) return 'waspada';
        if ($avg <= 3.5) return 'baik';
        return 'optimal';
    }
}
