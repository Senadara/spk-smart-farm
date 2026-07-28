<?php

namespace App\Services\Fuzzy;

class LayerChickenFuzzyTemplateDefinition
{
    public const PROFILE_NAME = 'Ayam Petelur - RFC v1';
    public const VERSION = 'v1-pakar';

    public static function masterEnvironmentRows(): array
    {
        return [
            [
                'parameter_code' => 'TEMP',
                'parameter_name' => 'Suhu',
                'unit' => 'C',
                'icon_key' => 'thermometer',
                'min_value' => 18,
                'max_value' => 27,
                'fallback_value' => 24,
                'stale_minutes' => 30,
                'required_for_iot' => true,
                'required_for_fuzzy' => true,
            ],
            [
                'parameter_code' => 'HUMID',
                'parameter_name' => 'Kelembapan',
                'unit' => '%',
                'icon_key' => 'droplets',
                'min_value' => 50,
                'max_value' => 70,
                'fallback_value' => 60,
                'stale_minutes' => 30,
                'required_for_iot' => true,
                'required_for_fuzzy' => true,
            ],
            [
                'parameter_code' => 'AMMON',
                'parameter_name' => 'Amonia',
                'unit' => 'ppm',
                'icon_key' => 'cloud-alert',
                'min_value' => 0,
                'max_value' => 20,
                'fallback_value' => 5,
                'stale_minutes' => 30,
                'required_for_iot' => true,
                'required_for_fuzzy' => true,
            ],
            [
                'parameter_code' => 'LIGHT',
                'parameter_name' => 'Cahaya',
                'unit' => 'lx',
                'icon_key' => 'sun',
                'min_value' => 15,
                'max_value' => 50,
                'fallback_value' => 250,
                'stale_minutes' => 30,
                'required_for_iot' => true,
                'required_for_fuzzy' => false,
            ],
        ];
    }

    public static function fuzzyProductivityCodes(): array
    {
        return ['hdp', 'fcr', 'mortalitas'];
    }

    public static function variables(): array
    {
        return [
            ['group' => 'lingkungan', 'name' => 'suhu', 'type' => 'input', 'unit' => 'C', 'description' => 'Suhu udara dalam kandang ayam petelur.'],
            ['group' => 'lingkungan', 'name' => 'kelembapan', 'type' => 'input', 'unit' => '%', 'description' => 'Kelembapan relatif kandang ayam petelur.'],
            ['group' => 'lingkungan', 'name' => 'amonia', 'type' => 'input', 'unit' => 'ppm', 'description' => 'Kadar amonia udara kandang ayam petelur.'],
            ['group' => 'lingkungan', 'name' => 'status_lingkungan', 'type' => 'output', 'unit' => 'score', 'description' => 'Status lingkungan berdasarkan rule validasi pakar.'],
            ['group' => 'kesehatan', 'name' => 'hdp', 'type' => 'input', 'unit' => '%', 'description' => 'Hen-Day Production harian.'],
            ['group' => 'kesehatan', 'name' => 'fcr', 'type' => 'input', 'unit' => 'rasio', 'description' => 'Feed Conversion Ratio sebagai indikator efisiensi pakan terhadap berat panen telur.'],
            ['group' => 'kesehatan', 'name' => 'mortalitas', 'type' => 'input', 'unit' => '% per minggu', 'description' => 'Mortalitas ayam petelur pada periode berjalan.'],
            ['group' => 'kesehatan', 'name' => 'indeks_kesehatan', 'type' => 'output', 'unit' => 'score', 'description' => 'Indeks produktivitas dan kesehatan berdasarkan rule validasi pakar.'],
            ['group' => 'kausalitas', 'name' => 'label_lingkungan', 'type' => 'input', 'unit' => 'label', 'description' => 'Kategori ringkas lingkungan untuk integrasi kausalitas.'],
            ['group' => 'kausalitas', 'name' => 'label_kesehatan', 'type' => 'input', 'unit' => 'label', 'description' => 'Kategori ringkas produktivitas/kesehatan untuk integrasi kausalitas.'],
            ['group' => 'kausalitas', 'name' => 'diagnosis_kausalitas', 'type' => 'output', 'unit' => 'label', 'description' => 'Diagnosis akhir hasil integrasi lingkungan dan produktivitas.'],
        ];
    }

    public static function sets(): array
    {
        return [
            'suhu' => self::temperatureSets(),
            'kelembapan' => self::humiditySets(),
            'amonia' => self::ammoniaSets(),
            'status_lingkungan' => self::environmentOutputSets(),
            'hdp' => self::hdpSets(),
            'fcr' => self::fcrSets(),
            'mortalitas' => self::mortalitySets(),
            'indeks_kesehatan' => self::healthOutputSets(),
            'label_lingkungan' => self::causalityInputSets(),
            'label_kesehatan' => self::causalityInputSets(),
            'diagnosis_kausalitas' => self::causalityOutputSets(),
        ];
    }

    public static function sourceDefinitions(): array
    {
        return [
            'suhu' => [
                'source_type' => 'iot',
                'source_name' => 'iot_sensor_data',
                'field_name' => 'value',
                'function_name' => null,
                'extra_config' => ['parameterCode' => 'TEMP', 'maxAgeMinutes' => 30],
            ],
            'kelembapan' => [
                'source_type' => 'iot',
                'source_name' => 'iot_sensor_data',
                'field_name' => 'value',
                'function_name' => null,
                'extra_config' => ['parameterCode' => 'HUMID', 'maxAgeMinutes' => 30],
            ],
            'amonia' => [
                'source_type' => 'iot',
                'source_name' => 'iot_sensor_data',
                'field_name' => 'value',
                'function_name' => null,
                'extra_config' => ['parameterCode' => 'AMMON', 'maxAgeMinutes' => 30],
            ],
            'hdp' => [
                'source_type' => 'function',
                'source_name' => null,
                'field_name' => null,
                'function_name' => 'App\\Services\\Fuzzy\\CalculateHdp',
                'extra_config' => null,
            ],
            'fcr' => [
                'source_type' => 'function',
                'source_name' => null,
                'field_name' => null,
                'function_name' => 'App\\Services\\Fuzzy\\CalculateFcr',
                'extra_config' => null,
            ],
            'mortalitas' => [
                'source_type' => 'function',
                'source_name' => null,
                'field_name' => null,
                'function_name' => 'App\\Services\\Fuzzy\\CalculateMortalitas',
                'extra_config' => null,
            ],
        ];
    }

    public static function environmentSetsForCode(string $code): array
    {
        return match (strtoupper($code)) {
            'TEMP', 'TEMPERATURE', 'SUHU' => self::temperatureSets(),
            'HUMID', 'HUMIDITY', 'RH' => self::humiditySets(),
            'AMMON', 'AMMONIA', 'NH3' => self::ammoniaSets(),
            default => [],
        };
    }

    public static function productivitySetsForCode(string $code): array
    {
        return match (strtolower($code)) {
            'hdp', 'hhep' => self::hdpSets(),
            'feed_intake' => self::feedSets(),
            'fcr' => self::fcrSets(),
            'mortalitas' => self::mortalitySets(),
            default => [],
        };
    }

    public static function environmentOutputSets(): array
    {
        return [
            ['name' => 'Sangat Bahaya', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 10, 'd' => 22],
            ['name' => 'Bahaya', 'shape' => 'triangle', 'a' => 16, 'b' => 32, 'c' => 48],
            ['name' => 'Waspada', 'shape' => 'triangle', 'a' => 40, 'b' => 55, 'c' => 70],
            ['name' => 'Nyaman', 'shape' => 'triangle', 'a' => 62, 'b' => 76, 'c' => 90],
            ['name' => 'Sangat Nyaman', 'shape' => 'trapezoid', 'a' => 84, 'b' => 94, 'c' => 100, 'd' => 100],
        ];
    }

    public static function healthOutputSets(): array
    {
        return self::spreadLabelSets([
            'Sakit Kritis',
            'Sakit Berat',
            'Sakit Sedang',
            'Kurang Sehat',
            'Sangat Boros',
            'Inefisiensi',
            'Waspada',
            'FCR Boros',
            'Efisien Positif',
            'Sehat',
            'Sangat Sehat',
        ]);
    }

    public static function causalityInputSets(): array
    {
        return [
            ['name' => 'Buruk', 'shape' => 'triangle', 'a' => 0, 'b' => 0, 'c' => 1],
            ['name' => 'Sedang', 'shape' => 'triangle', 'a' => 1, 'b' => 2, 'c' => 3],
            ['name' => 'Baik', 'shape' => 'triangle', 'a' => 3, 'b' => 4, 'c' => 5],
        ];
    }

    public static function causalityOutputSets(): array
    {
        return self::spreadLabelSets([
            'Krisis Total',
            'Stres Lingkungan',
            'Daya Tahan Baik',
            'Sakit Non-Cuaca',
            'Performa Stagnan',
            'Toleransi Baik',
            'Wabah Internal',
            'Inefisiensi FCR',
            'Kondisi Optimal',
        ]);
    }

    public static function rules(): array
    {
        return array_merge(
            self::environmentRules(),
            self::healthRules(),
            self::causalityRules()
        );
    }

    public static function environmentRules(): array
    {
        return [
            ['output_set' => 'Waspada', 'diagnosis' => 'Risiko dehidrasi dan kedinginan', 'conditions' => [['suhu', 'Dingin'], ['kelembapan', 'Kering'], ['amonia', 'Aman']]],
            ['output_set' => 'Bahaya', 'diagnosis' => 'Kualitas udara buruk dan kondisi dingin', 'conditions' => [['suhu', 'Dingin'], ['kelembapan', 'Kering'], ['amonia', 'Bahaya']]],
            ['output_set' => 'Nyaman', 'diagnosis' => 'Toleransi batas bawah', 'conditions' => [['suhu', 'Dingin'], ['kelembapan', 'Ideal'], ['amonia', 'Aman']]],
            ['output_set' => 'Waspada', 'diagnosis' => 'Masalah ventilasi karena amonia naik', 'conditions' => [['suhu', 'Dingin'], ['kelembapan', 'Ideal'], ['amonia', 'Bahaya']]],
            ['output_set' => 'Waspada', 'diagnosis' => 'Risiko jamur atau lembap dingin', 'conditions' => [['suhu', 'Dingin'], ['kelembapan', 'Basah'], ['amonia', 'Aman']]],
            ['output_set' => 'Bahaya', 'diagnosis' => 'Lingkungan sangat tidak sehat', 'conditions' => [['suhu', 'Dingin'], ['kelembapan', 'Basah'], ['amonia', 'Bahaya']]],
            ['output_set' => 'Nyaman', 'diagnosis' => 'Kondisi optimal', 'conditions' => [['suhu', 'Nyaman'], ['kelembapan', 'Kering'], ['amonia', 'Aman']]],
            ['output_set' => 'Waspada', 'diagnosis' => 'Perlu cek sirkulasi udara', 'conditions' => [['suhu', 'Nyaman'], ['kelembapan', 'Kering'], ['amonia', 'Bahaya']]],
            ['output_set' => 'Sangat Nyaman', 'diagnosis' => 'Kondisi target/ideal', 'conditions' => [['suhu', 'Nyaman'], ['kelembapan', 'Ideal'], ['amonia', 'Aman']]],
            ['output_set' => 'Waspada', 'diagnosis' => 'Indikasi kotoran menumpuk', 'conditions' => [['suhu', 'Nyaman'], ['kelembapan', 'Ideal'], ['amonia', 'Bahaya']]],
            ['output_set' => 'Nyaman', 'diagnosis' => 'Masih dalam toleransi', 'conditions' => [['suhu', 'Nyaman'], ['kelembapan', 'Basah'], ['amonia', 'Aman']]],
            ['output_set' => 'Bahaya', 'diagnosis' => 'Risiko penyakit pernapasan', 'conditions' => [['suhu', 'Nyaman'], ['kelembapan', 'Basah'], ['amonia', 'Bahaya']]],
            ['output_set' => 'Waspada', 'diagnosis' => 'Awal heat stress', 'conditions' => [['suhu', 'Panas'], ['kelembapan', 'Kering'], ['amonia', 'Aman']]],
            ['output_set' => 'Bahaya', 'diagnosis' => 'Heat stress dan polusi udara', 'conditions' => [['suhu', 'Panas'], ['kelembapan', 'Kering'], ['amonia', 'Bahaya']]],
            ['output_set' => 'Waspada', 'diagnosis' => 'Butuh pendinginan segera', 'conditions' => [['suhu', 'Panas'], ['kelembapan', 'Ideal'], ['amonia', 'Aman']]],
            ['output_set' => 'Bahaya', 'diagnosis' => 'Severe heat stress', 'conditions' => [['suhu', 'Panas'], ['kelembapan', 'Ideal'], ['amonia', 'Bahaya']]],
            ['output_set' => 'Bahaya', 'diagnosis' => 'Indeks panas ekstrem', 'conditions' => [['suhu', 'Panas'], ['kelembapan', 'Basah'], ['amonia', 'Aman']]],
            ['output_set' => 'Sangat Bahaya', 'diagnosis' => 'Risiko kematian mendadak tinggi', 'conditions' => [['suhu', 'Panas'], ['kelembapan', 'Basah'], ['amonia', 'Bahaya']]],
        ];
    }

    public static function healthRules(): array
    {
        return [
            ['output_set' => 'Kurang Sehat', 'diagnosis' => 'Produksi rendah meskipun FCR efisien, perlu cek stres atau kualitas produksi', 'conditions' => [['hdp', 'Rendah'], ['fcr', 'Efisien'], ['mortalitas', 'Wajar']]],
            ['output_set' => 'Sakit Kritis', 'diagnosis' => 'Indikasi wabah penyakit dengan produksi rendah', 'conditions' => [['hdp', 'Rendah'], ['fcr', 'Efisien'], ['mortalitas', 'Tinggi']]],
            ['output_set' => 'Inefisiensi', 'diagnosis' => 'Masalah kualitas pakan, genetik, atau manajemen produksi', 'conditions' => [['hdp', 'Rendah'], ['fcr', 'Normal'], ['mortalitas', 'Wajar']]],
            ['output_set' => 'Sakit Berat', 'diagnosis' => 'Produksi rendah dengan mortalitas tinggi', 'conditions' => [['hdp', 'Rendah'], ['fcr', 'Normal'], ['mortalitas', 'Tinggi']]],
            ['output_set' => 'Sangat Boros', 'diagnosis' => 'FCR boros dan produksi rendah', 'conditions' => [['hdp', 'Rendah'], ['fcr', 'Boros'], ['mortalitas', 'Wajar']]],
            ['output_set' => 'Sakit Berat', 'diagnosis' => 'Gangguan metabolisme berat atau kualitas pakan buruk', 'conditions' => [['hdp', 'Rendah'], ['fcr', 'Boros'], ['mortalitas', 'Tinggi']]],
            ['output_set' => 'Waspada', 'diagnosis' => 'Produksi sedang dengan FCR efisien, pantau konsistensi panen', 'conditions' => [['hdp', 'Sedang'], ['fcr', 'Efisien'], ['mortalitas', 'Wajar']]],
            ['output_set' => 'Sakit Sedang', 'diagnosis' => 'Gejala awal penyakit pada produksi sedang', 'conditions' => [['hdp', 'Sedang'], ['fcr', 'Efisien'], ['mortalitas', 'Tinggi']]],
            ['output_set' => 'Sehat', 'diagnosis' => 'Performa standar rata-rata', 'conditions' => [['hdp', 'Sedang'], ['fcr', 'Normal'], ['mortalitas', 'Wajar']]],
            ['output_set' => 'Waspada', 'diagnosis' => 'Cek penyebab kematian fisik', 'conditions' => [['hdp', 'Sedang'], ['fcr', 'Normal'], ['mortalitas', 'Tinggi']]],
            ['output_set' => 'FCR Boros', 'diagnosis' => 'FCR boros, perlu audit pakan dan berat panen', 'conditions' => [['hdp', 'Sedang'], ['fcr', 'Boros'], ['mortalitas', 'Wajar']]],
            ['output_set' => 'Sakit Sedang', 'diagnosis' => 'Indikasi masalah pencernaan atau kualitas pakan', 'conditions' => [['hdp', 'Sedang'], ['fcr', 'Boros'], ['mortalitas', 'Tinggi']]],
            ['output_set' => 'Efisien Positif', 'diagnosis' => 'FCR sangat baik', 'conditions' => [['hdp', 'Tinggi'], ['fcr', 'Efisien'], ['mortalitas', 'Wajar']]],
            ['output_set' => 'Waspada', 'diagnosis' => 'Produksi tinggi tetapi mortalitas naik', 'conditions' => [['hdp', 'Tinggi'], ['fcr', 'Efisien'], ['mortalitas', 'Tinggi']]],
            ['output_set' => 'Sangat Sehat', 'diagnosis' => 'Kondisi target/ideal', 'conditions' => [['hdp', 'Tinggi'], ['fcr', 'Normal'], ['mortalitas', 'Wajar']]],
            ['output_set' => 'Waspada', 'diagnosis' => 'Anomali, cek faktor non-medis', 'conditions' => [['hdp', 'Tinggi'], ['fcr', 'Normal'], ['mortalitas', 'Tinggi']]],
            ['output_set' => 'Sehat', 'diagnosis' => 'Produksi tinggi tetapi FCR mulai boros', 'conditions' => [['hdp', 'Tinggi'], ['fcr', 'Boros'], ['mortalitas', 'Wajar']]],
            ['output_set' => 'Waspada', 'diagnosis' => 'Mortalitas tinggi saat puncak produksi', 'conditions' => [['hdp', 'Tinggi'], ['fcr', 'Boros'], ['mortalitas', 'Tinggi']]],
        ];
    }

    public static function causalityRules(): array
    {
        return [
            ['environment' => 'Buruk', 'productivity' => 'Buruk', 'output_set' => 'Krisis Total', 'recommendation' => 'Darurat: evakuasi atau perbaikan total sarana. Cek kesehatan massal dan sanitasi kandang.'],
            ['environment' => 'Buruk', 'productivity' => 'Sedang', 'output_set' => 'Stres Lingkungan', 'recommendation' => 'Prioritas: perbaiki suhu atau sirkulasi segera. Beri vitamin anti-stres untuk mencegah penurunan produksi.'],
            ['environment' => 'Buruk', 'productivity' => 'Baik', 'output_set' => 'Daya Tahan Baik', 'recommendation' => 'Peringatan: ayam masih kuat, tetapi lingkungan berbahaya. Segera nyalakan kipas sebelum produksi turun.'],
            ['environment' => 'Sedang', 'productivity' => 'Buruk', 'output_set' => 'Sakit Non-Cuaca', 'recommendation' => 'Medis: lingkungan cukup aman. Fokus isolasi ayam sakit dan evaluasi kualitas pakan/air.'],
            ['environment' => 'Sedang', 'productivity' => 'Sedang', 'output_set' => 'Performa Stagnan', 'recommendation' => 'Evaluasi: cek manajemen harian. Bersihkan kandang dan optimalkan program pencahayaan.'],
            ['environment' => 'Sedang', 'productivity' => 'Baik', 'output_set' => 'Toleransi Baik', 'recommendation' => 'Monitoring: kondisi stabil. Jaga kebersihan kandang agar amonia tidak naik.'],
            ['environment' => 'Baik', 'productivity' => 'Buruk', 'output_set' => 'Wabah Internal', 'recommendation' => 'Kritis medis: lingkungan ideal tetapi ayam mati/drop. Indikasi kuat virus/bakteri. Panggil dokter hewan.'],
            ['environment' => 'Baik', 'productivity' => 'Sedang', 'output_set' => 'Inefisiensi FCR', 'recommendation' => 'Manajemen: cek FCR, egg mass, dan takaran pakan harian. Pastikan data panen dan pakan tercatat pada periode yang sama.'],
            ['environment' => 'Baik', 'productivity' => 'Baik', 'output_set' => 'Kondisi Optimal', 'recommendation' => 'Pertahankan: lanjutkan SOP saat ini. Cek stok logistik untuk periode depan.'],
        ];
    }

    public static function causalityLookupLabel(string $label, string $dimension): string
    {
        $key = strtolower(trim($label));

        $environment = [
            'sangat bahaya' => 'Buruk',
            'bahaya' => 'Buruk',
            'buruk' => 'Buruk',
            'waspada' => 'Sedang',
            'sedang' => 'Sedang',
            'nyaman' => 'Baik',
            'sangat nyaman' => 'Baik',
            'baik' => 'Baik',
            'optimal' => 'Baik',
        ];

        $productivity = [
            'sakit kritis' => 'Buruk',
            'sakit berat' => 'Buruk',
            'sakit sedang' => 'Buruk',
            'buruk' => 'Buruk',
            'kurang sehat' => 'Sedang',
            'inefisiensi' => 'Sedang',
            'sangat boros' => 'Sedang',
            'waspada' => 'Sedang',
            'fcr boros' => 'Sedang',
            'boros pakan' => 'Sedang',
            'sedang' => 'Sedang',
            'efisien positif' => 'Baik',
            'sehat' => 'Baik',
            'sangat sehat' => 'Baik',
            'baik' => 'Baik',
            'optimal' => 'Baik',
        ];

        $map = $dimension === 'lingkungan' ? $environment : $productivity;

        return $map[$key] ?? $label;
    }

    private static function temperatureSets(): array
    {
        return [
            ['name' => 'Dingin', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 17, 'd' => 19],
            ['name' => 'Nyaman', 'shape' => 'trapezoid', 'a' => 18, 'b' => 20, 'c' => 25, 'd' => 27],
            ['name' => 'Panas', 'shape' => 'trapezoid', 'a' => 26, 'b' => 28, 'c' => 50, 'd' => 50],
        ];
    }

    private static function humiditySets(): array
    {
        return [
            ['name' => 'Kering', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 48, 'd' => 52],
            ['name' => 'Ideal', 'shape' => 'trapezoid', 'a' => 50, 'b' => 55, 'c' => 65, 'd' => 70],
            ['name' => 'Basah', 'shape' => 'trapezoid', 'a' => 68, 'b' => 72, 'c' => 100, 'd' => 100],
        ];
    }

    private static function ammoniaSets(): array
    {
        return [
            ['name' => 'Aman', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 15, 'd' => 20],
            ['name' => 'Bahaya', 'shape' => 'trapezoid', 'a' => 18, 'b' => 20, 'c' => 50, 'd' => 50],
        ];
    }

    private static function hdpSets(): array
    {
        return [
            ['name' => 'Rendah', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 70, 'd' => 77],
            ['name' => 'Sedang', 'shape' => 'triangle', 'a' => 75, 'b' => 80, 'c' => 85],
            ['name' => 'Tinggi', 'shape' => 'trapezoid', 'a' => 83, 'b' => 85, 'c' => 100, 'd' => 100],
        ];
    }

    private static function feedSets(): array
    {
        return [
            ['name' => 'Kurang', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 95, 'd' => 105],
            ['name' => 'Normal', 'shape' => 'trapezoid', 'a' => 100, 'b' => 108, 'c' => 122, 'd' => 130],
            ['name' => 'Berlebih', 'shape' => 'trapezoid', 'a' => 122, 'b' => 130, 'c' => 200, 'd' => 200],
        ];
    }

    private static function fcrSets(): array
    {
        return [
            ['name' => 'Efisien', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 1.85, 'd' => 2.1],
            ['name' => 'Normal', 'shape' => 'triangle', 'a' => 1.95, 'b' => 2.25, 'c' => 2.55],
            ['name' => 'Boros', 'shape' => 'trapezoid', 'a' => 2.4, 'b' => 2.75, 'c' => 6, 'd' => 6],
        ];
    }

    private static function mortalitySets(): array
    {
        return [
            ['name' => 'Wajar', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 0.35, 'd' => 0.55],
            ['name' => 'Tinggi', 'shape' => 'trapezoid', 'a' => 0.45, 'b' => 0.6, 'c' => 10, 'd' => 10],
        ];
    }

    private static function spreadLabelSets(array $labels): array
    {
        $last = max(count($labels) - 1, 1);
        $step = 100 / $last;

        return array_map(function (string $label, int $index) use ($labels, $last, $step) {
            $b = round($index * $step, 2);
            $a = $index === 0 ? 0 : round(max(0, $b - $step), 2);
            $c = $index === $last ? 100 : round(min(100, $b + $step), 2);

            return [
                'name' => $label,
                'shape' => 'triangle',
                'a' => $a,
                'b' => $b,
                'c' => $c,
                'd' => null,
            ];
        }, $labels, array_keys($labels));
    }
}
