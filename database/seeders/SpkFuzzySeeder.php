<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SpkFuzzySeeder extends Seeder
{
    public function run(): void
    {
        // Bersihkan semua data fuzzy sebelumnya
        DB::table('spk_fuzzy_rule_conditions')->delete();
        DB::table('spk_fuzzy_rules')->delete();
        DB::table('spk_fuzzy_input_sources')->delete();
        DB::table('spk_fuzzy_sets')->delete();
        DB::table('spk_fuzzy_variables')->delete();

        // ── 1. VARIABEL ─────────────────────────────────────────────────
        // [group, name, type, unit, description]
        $variablesData = [
            ['lingkungan', 'suhu',                 'input',  '°C',    'Suhu udara dalam kandang'],
            ['lingkungan', 'kelembapan',            'input',  '%',     'Kelembapan relatif dalam kandang'],
            ['lingkungan', 'amonia',                'input',  'ppm',   'Kadar amonia udara kandang'],
            ['lingkungan', 'status_lingkungan',     'output', 'score', 'Status kondisi lingkungan kandang'],
            ['kesehatan',  'hdp',                   'input',  '%',     'Hen-Day Production - persentase produksi telur'],
            ['kesehatan',  'pakan',                 'input',  'g/ekor','Konsumsi pakan per ekor per hari'],
            ['kesehatan',  'mortalitas',            'input',  '%',     'Tingkat kematian ayam bulan ini'],
            ['kesehatan',  'indeks_kesehatan',      'output', 'score', 'Indeks produktivitas dan kesehatan'],
            ['kausalitas', 'label_lingkungan',      'input',  'label', 'Label output Engine 1 (Lingkungan)'],
            ['kausalitas', 'label_kesehatan',       'input',  'label', 'Label output Engine 2 (Kesehatan)'],
            ['kausalitas', 'diagnosis_kausalitas',  'output', 'label', 'Diagnosis kausalitas akhir'],
        ];

        $varIds = [];
        foreach ($variablesData as [$group, $name, $type, $unit, $desc]) {
            $id = (string) Str::uuid();
            DB::table('spk_fuzzy_variables')->insert([
                'id' => $id, 'group' => $group, 'name' => $name,
                'type' => $type, 'unit' => $unit, 'description' => $desc,
                'createdAt' => now(), 'updatedAt' => now(),
            ]);
            $varIds[$name] = $id;
        }

        // ── 2. SETS (MEMBERSHIP FUNCTIONS) ──────────────────────────────
        // [variable_name, set_name, shape, a, b, c, d]
        $setsData = [
            // SUHU (°C) — universe 0..50
            ['suhu', 'Dingin', 'trapezoid',  0,    0,    16,   22  ],
            ['suhu', 'Nyaman', 'triangle',   18,   24,   30,   null],
            ['suhu', 'Panas',  'trapezoid',  28,   32,   50,   50  ],
            // KELEMBAPAN (%) — universe 0..100
            ['kelembapan', 'Kering', 'trapezoid', 0,  0,  40,  55  ],
            ['kelembapan', 'Ideal',  'triangle',  50, 65, 80,  null],
            ['kelembapan', 'Basah',  'trapezoid', 70, 82, 100, 100 ],
            // AMONIA (ppm) — universe 0..50
            ['amonia', 'Aman',   'trapezoid', 0,  0,  8,  15 ],
            ['amonia', 'Tinggi', 'trapezoid', 12, 20, 50, 50 ],
            // STATUS LINGKUNGAN (score 0..100)
            ['status_lingkungan', 'Buruk',   'trapezoid', 0,  0,  15,  28 ],
            ['status_lingkungan', 'Waspada', 'triangle',  22, 38, 54,  null],
            ['status_lingkungan', 'Baik',    'triangle',  48, 64, 78,  null],
            ['status_lingkungan', 'Optimal', 'trapezoid', 72, 88, 100, 100],
            // HDP (%) — universe 0..100
            ['hdp', 'Rendah', 'trapezoid', 0,  0,  55, 70  ],
            ['hdp', 'Sedang', 'triangle',  60, 78, 92, null],
            ['hdp', 'Tinggi', 'trapezoid', 86, 93, 100, 100],
            // PAKAN (g/ekor) — universe 0..200
            ['pakan', 'Kurang',   'trapezoid', 0,   0,   90,  108 ],
            ['pakan', 'Normal',   'triangle',  100, 115, 130, null],
            ['pakan', 'Berlebih', 'trapezoid', 122, 138, 200, 200 ],
            // MORTALITAS (%) — universe 0..10
            ['mortalitas', 'Wajar',  'trapezoid', 0,   0,   0.3, 0.8],
            ['mortalitas', 'Tinggi', 'trapezoid', 0.5, 1.2, 10,  10 ],
            // INDEKS KESEHATAN (score 0..100)
            ['indeks_kesehatan', 'Buruk',   'trapezoid', 0,  0,  15,  28 ],
            ['indeks_kesehatan', 'Waspada', 'triangle',  22, 38, 54,  null],
            ['indeks_kesehatan', 'Baik',    'triangle',  48, 64, 78,  null],
            ['indeks_kesehatan', 'Optimal', 'trapezoid', 72, 88, 100, 100],
            // ENGINE 3 INPUT LABELS (label_lingkungan, label_kesehatan)
            // a,b,c,d dummy — lookup engine tidak pakai membership math
            ['label_lingkungan', 'Buruk',   'triangle', 0,  0,  1,  null],
            ['label_lingkungan', 'Waspada', 'triangle', 1,  2,  3,  null],
            ['label_lingkungan', 'Baik',    'triangle', 3,  4,  5,  null],
            ['label_lingkungan', 'Optimal', 'triangle', 5,  6,  7,  null],
            ['label_kesehatan',  'Buruk',   'triangle', 0,  0,  1,  null],
            ['label_kesehatan',  'Waspada', 'triangle', 1,  2,  3,  null],
            ['label_kesehatan',  'Baik',    'triangle', 3,  4,  5,  null],
            ['label_kesehatan',  'Optimal', 'triangle', 5,  6,  7,  null],
            // ENGINE 3 OUTPUT — diagnosis labels (dummy numeric, hanya nama yg dipakai)
            ['diagnosis_kausalitas', 'Krisis Total',            'triangle', 0,  0,  1,  null],
            ['diagnosis_kausalitas', 'Stres Lingkungan',        'triangle', 1,  2,  3,  null],
            ['diagnosis_kausalitas', 'Lingkungan Berisiko',     'triangle', 3,  4,  5,  null],
            ['diagnosis_kausalitas', 'Lingkungan Kritis',       'triangle', 5,  6,  7,  null],
            ['diagnosis_kausalitas', 'Gangguan Non-Lingkungan', 'triangle', 7,  8,  9,  null],
            ['diagnosis_kausalitas', 'Performa Tidak Stabil',   'triangle', 9,  10, 11, null],
            ['diagnosis_kausalitas', 'Toleransi Baik',          'triangle', 11, 12, 13, null],
            ['diagnosis_kausalitas', 'Toleransi Optimal',       'triangle', 13, 14, 15, null],
            ['diagnosis_kausalitas', 'Wabah Internal',          'triangle', 15, 16, 17, null],
            ['diagnosis_kausalitas', 'Inefisiensi Sistem',      'triangle', 17, 18, 19, null],
            ['diagnosis_kausalitas', 'Kondisi Ideal',           'triangle', 19, 20, 21, null],
            ['diagnosis_kausalitas', 'Stabil',                  'triangle', 21, 22, 23, null],
            ['diagnosis_kausalitas', 'Anomali Medis',           'triangle', 23, 24, 25, null],
            ['diagnosis_kausalitas', 'Perlu Monitoring',        'triangle', 25, 26, 27, null],
            ['diagnosis_kausalitas', 'Kondisi Baik',            'triangle', 27, 28, 29, null],
            ['diagnosis_kausalitas', 'Kondisi Optimal',         'triangle', 29, 30, 31, null],
        ];

        $setIds = []; // [var_name][set_name] => id
        foreach ($setsData as [$varName, $setName, $shape, $a, $b, $c, $d]) {
            $id = (string) Str::uuid();
            DB::table('spk_fuzzy_sets')->insert([
                'id' => $id, 'variable_id' => $varIds[$varName],
                'name' => $setName, 'shape' => $shape,
                'a' => $a, 'b' => $b, 'c' => $c, 'd' => $d,
                'createdAt' => now(), 'updatedAt' => now(),
            ]);
            $setIds[$varName][$setName] = $id;
        }

        // ── 3. INPUT SOURCES ────────────────────────────────────────────
        // [variable_name, source_type, source_name, field_name, function_name, extra_config]
        $sourcesData = [
            ['suhu',       'iot',      'iot_sensor_data', 'value', null,                                        ['parameterCode' => 'TEMP']],
            ['kelembapan', 'iot',      'iot_sensor_data', 'value', null,                                        ['parameterCode' => 'HUMID']],
            ['amonia',     'iot',      'iot_sensor_data', 'value', null,                                        ['parameterCode' => 'AMMON']],
            ['hdp',        'function', null,              null,    'App\\Services\\Fuzzy\\CalculateHdp',         null],
            ['pakan',      'function', null,              null,    'App\\Services\\Fuzzy\\CalculatePakan',       null],
            ['mortalitas', 'function', null,              null,    'App\\Services\\Fuzzy\\CalculateMortalitas',  null],
        ];
        foreach ($sourcesData as [$varName, $type, $srcName, $field, $fn, $extra]) {
            DB::table('spk_fuzzy_input_sources')->insert([
                'id' => (string) Str::uuid(), 'variable_id' => $varIds[$varName],
                'source_type' => $type, 'source_name' => $srcName,
                'field_name' => $field, 'function_name' => $fn,
                'extra_config' => $extra ? json_encode($extra) : null,
                'createdAt' => now(), 'updatedAt' => now(),
            ]);
        }

        // ── 4. RULES ────────────────────────────────────────────────────
        // [group, output_var, output_set, operator, diagnosis, [[cond_var, cond_set], ...]]
        $rulesData = [
            // ── ENGINE 1: STATUS LINGKUNGAN (18 rules) ──
            ['lingkungan','status_lingkungan','Waspada','AND','Risiko dehidrasi & dingin',       [['suhu','Dingin'],['kelembapan','Kering'],['amonia','Aman'  ]]],
            ['lingkungan','status_lingkungan','Buruk',  'AND','Dingin + udara tercemar',         [['suhu','Dingin'],['kelembapan','Kering'],['amonia','Tinggi']]],
            ['lingkungan','status_lingkungan','Baik',   'AND','Toleransi batas bawah',           [['suhu','Dingin'],['kelembapan','Ideal'], ['amonia','Aman'  ]]],
            ['lingkungan','status_lingkungan','Waspada','AND','Ventilasi kurang',                [['suhu','Dingin'],['kelembapan','Ideal'], ['amonia','Tinggi']]],
            ['lingkungan','status_lingkungan','Waspada','AND','Risiko jamur & lembap',           [['suhu','Dingin'],['kelembapan','Basah'], ['amonia','Aman'  ]]],
            ['lingkungan','status_lingkungan','Buruk',  'AND','Lingkungan tidak sehat',          [['suhu','Dingin'],['kelembapan','Basah'], ['amonia','Tinggi']]],
            ['lingkungan','status_lingkungan','Baik',   'AND','Sedikit kering, aman',            [['suhu','Nyaman'],['kelembapan','Kering'],['amonia','Aman'  ]]],
            ['lingkungan','status_lingkungan','Waspada','AND','Amonia mulai naik',               [['suhu','Nyaman'],['kelembapan','Kering'],['amonia','Tinggi']]],
            ['lingkungan','status_lingkungan','Optimal','AND','Kondisi ideal',                   [['suhu','Nyaman'],['kelembapan','Ideal'], ['amonia','Aman'  ]]],
            ['lingkungan','status_lingkungan','Waspada','AND','Kotoran menumpuk',                [['suhu','Nyaman'],['kelembapan','Ideal'], ['amonia','Tinggi']]],
            ['lingkungan','status_lingkungan','Waspada','AND','Kelembapan tinggi',               [['suhu','Nyaman'],['kelembapan','Basah'], ['amonia','Aman'  ]]],
            ['lingkungan','status_lingkungan','Buruk',  'AND','Risiko penyakit tinggi',          [['suhu','Nyaman'],['kelembapan','Basah'], ['amonia','Tinggi']]],
            ['lingkungan','status_lingkungan','Waspada','AND','Awal heat stress',                [['suhu','Panas'], ['kelembapan','Kering'],['amonia','Aman'  ]]],
            ['lingkungan','status_lingkungan','Buruk',  'AND','Heat stress + polusi',            [['suhu','Panas'], ['kelembapan','Kering'],['amonia','Tinggi']]],
            ['lingkungan','status_lingkungan','Waspada','AND','Perlu pendinginan',               [['suhu','Panas'], ['kelembapan','Ideal'], ['amonia','Aman'  ]]],
            ['lingkungan','status_lingkungan','Buruk',  'AND','Heat stress berat',               [['suhu','Panas'], ['kelembapan','Ideal'], ['amonia','Tinggi']]],
            ['lingkungan','status_lingkungan','Waspada','AND','Heat index tinggi',               [['suhu','Panas'], ['kelembapan','Basah'], ['amonia','Aman'  ]]],
            ['lingkungan','status_lingkungan','Buruk',  'AND','Kondisi ekstrem',                 [['suhu','Panas'], ['kelembapan','Basah'], ['amonia','Tinggi']]],
            // ── ENGINE 2: INDEKS KESEHATAN (18 rules) ──
            ['kesehatan','indeks_kesehatan','Waspada','AND','Nafsu makan turun',         [['hdp','Rendah'],['pakan','Kurang'],  ['mortalitas','Wajar' ]]],
            ['kesehatan','indeks_kesehatan','Buruk',  'AND','Indikasi penyakit serius',  [['hdp','Rendah'],['pakan','Kurang'],  ['mortalitas','Tinggi']]],
            ['kesehatan','indeks_kesehatan','Waspada','AND','Inefisiensi produksi',      [['hdp','Rendah'],['pakan','Normal'],  ['mortalitas','Wajar' ]]],
            ['kesehatan','indeks_kesehatan','Buruk',  'AND','Gangguan kesehatan',        [['hdp','Rendah'],['pakan','Normal'],  ['mortalitas','Tinggi']]],
            ['kesehatan','indeks_kesehatan','Waspada','AND','Pakan tidak efisien',       [['hdp','Rendah'],['pakan','Berlebih'],['mortalitas','Wajar' ]]],
            ['kesehatan','indeks_kesehatan','Buruk',  'AND','Gangguan metabolisme',      [['hdp','Rendah'],['pakan','Berlebih'],['mortalitas','Tinggi']]],
            ['kesehatan','indeks_kesehatan','Waspada','AND','Potensi penurunan',         [['hdp','Sedang'],['pakan','Kurang'],  ['mortalitas','Wajar' ]]],
            ['kesehatan','indeks_kesehatan','Buruk',  'AND','Gejala penyakit awal',      [['hdp','Sedang'],['pakan','Kurang'],  ['mortalitas','Tinggi']]],
            ['kesehatan','indeks_kesehatan','Baik',   'AND','Performa standar',          [['hdp','Sedang'],['pakan','Normal'],  ['mortalitas','Wajar' ]]],
            ['kesehatan','indeks_kesehatan','Waspada','AND','Investigasi kematian',      [['hdp','Sedang'],['pakan','Normal'],  ['mortalitas','Tinggi']]],
            ['kesehatan','indeks_kesehatan','Waspada','AND','Inefisiensi pakan',         [['hdp','Sedang'],['pakan','Berlebih'],['mortalitas','Wajar' ]]],
            ['kesehatan','indeks_kesehatan','Buruk',  'AND','Gangguan pencernaan',       [['hdp','Sedang'],['pakan','Berlebih'],['mortalitas','Tinggi']]],
            ['kesehatan','indeks_kesehatan','Waspada','AND','Anomali produksi',          [['hdp','Tinggi'],['pakan','Kurang'],  ['mortalitas','Wajar' ]]],
            ['kesehatan','indeks_kesehatan','Buruk',  'AND','Produksi dipaksakan',       [['hdp','Tinggi'],['pakan','Kurang'],  ['mortalitas','Tinggi']]],
            ['kesehatan','indeks_kesehatan','Optimal','AND','Kondisi ideal',             [['hdp','Tinggi'],['pakan','Normal'],  ['mortalitas','Wajar' ]]],
            ['kesehatan','indeks_kesehatan','Waspada','AND','Anomali mortalitas',        [['hdp','Tinggi'],['pakan','Normal'],  ['mortalitas','Tinggi']]],
            ['kesehatan','indeks_kesehatan','Baik',   'AND','Produksi tinggi',           [['hdp','Tinggi'],['pakan','Berlebih'],['mortalitas','Wajar' ]]],
            ['kesehatan','indeks_kesehatan','Waspada','AND','Risiko saat puncak',        [['hdp','Tinggi'],['pakan','Berlebih'],['mortalitas','Tinggi']]],
            // ── ENGINE 3: DIAGNOSIS KAUSALITAS (16 rules — lookup) ──
            // diagnosis field di sini = REKOMENDASI (bukan diagnosis singkat)
            ['kausalitas','diagnosis_kausalitas','Krisis Total',            'AND','Perbaikan total + tindakan darurat',                [['label_lingkungan','Buruk'],  ['label_kesehatan','Buruk'  ]]],
            ['kausalitas','diagnosis_kausalitas','Stres Lingkungan',        'AND','Perbaiki ventilasi & suhu',                        [['label_lingkungan','Buruk'],  ['label_kesehatan','Waspada']]],
            ['kausalitas','diagnosis_kausalitas','Lingkungan Berisiko',     'AND','Ayam masih tahan, segera perbaiki lingkungan',      [['label_lingkungan','Buruk'],  ['label_kesehatan','Baik'   ]]],
            ['kausalitas','diagnosis_kausalitas','Lingkungan Kritis',       'AND','Perbaiki lingkungan segera, pertahankan produksi',  [['label_lingkungan','Buruk'],  ['label_kesehatan','Optimal']]],
            ['kausalitas','diagnosis_kausalitas','Gangguan Non-Lingkungan', 'AND','Fokus medis & perbaikan pakan',                    [['label_lingkungan','Waspada'],['label_kesehatan','Buruk'  ]]],
            ['kausalitas','diagnosis_kausalitas','Performa Tidak Stabil',   'AND','Evaluasi manajemen kandang secara menyeluruh',      [['label_lingkungan','Waspada'],['label_kesehatan','Waspada']]],
            ['kausalitas','diagnosis_kausalitas','Toleransi Baik',          'AND','Monitoring kondisi, perbaiki lingkungan bertahap',  [['label_lingkungan','Waspada'],['label_kesehatan','Baik'   ]]],
            ['kausalitas','diagnosis_kausalitas','Toleransi Optimal',       'AND','Pertahankan produktivitas, perbaiki lingkungan',    [['label_lingkungan','Waspada'],['label_kesehatan','Optimal']]],
            ['kausalitas','diagnosis_kausalitas','Wabah Internal',          'AND','Indikasi penyakit, panggil dokter hewan',           [['label_lingkungan','Baik'],   ['label_kesehatan','Buruk'  ]]],
            ['kausalitas','diagnosis_kausalitas','Inefisiensi Sistem',      'AND','Audit pakan & manajemen operasional',               [['label_lingkungan','Baik'],   ['label_kesehatan','Waspada']]],
            ['kausalitas','diagnosis_kausalitas','Stabil',                  'AND','Kondisi aman, pertahankan manajemen',               [['label_lingkungan','Baik'],   ['label_kesehatan','Baik'   ]]],
            ['kausalitas','diagnosis_kausalitas','Kondisi Ideal',           'AND','Pertahankan semua aspek manajemen saat ini',        [['label_lingkungan','Baik'],   ['label_kesehatan','Optimal']]],
            ['kausalitas','diagnosis_kausalitas','Anomali Medis',           'AND','Cek penyakit serius, isolasi jika diperlukan',      [['label_lingkungan','Optimal'],['label_kesehatan','Buruk'  ]]],
            ['kausalitas','diagnosis_kausalitas','Perlu Monitoring',        'AND','Pantau tren 24-48 jam ke depan',                    [['label_lingkungan','Optimal'],['label_kesehatan','Waspada']]],
            ['kausalitas','diagnosis_kausalitas','Kondisi Baik',            'AND','Stabil & aman, optimalkan efisiensi',               [['label_lingkungan','Optimal'],['label_kesehatan','Baik'   ]]],
            ['kausalitas','diagnosis_kausalitas','Kondisi Optimal',         'AND','Pertahankan — seluruh sistem berjalan optimal',     [['label_lingkungan','Optimal'],['label_kesehatan','Optimal']]],
        ];

        foreach ($rulesData as $i => [$group, $outVar, $outSet, $operator, $diagnosis, $conditions]) {
            $ruleId = (string) Str::uuid();
            DB::table('spk_fuzzy_rules')->insert([
                'id'            => $ruleId,
                'name'          => "Rule-{$group}-" . ($i + 1),
                'operator'      => $operator,
                'output_set_id' => $setIds[$outVar][$outSet],
                'group'         => $group,
                'diagnosis'     => $diagnosis,
                'createdAt'    => now(),
                'updatedAt'    => now(),
            ]);

            foreach ($conditions as [$condVar, $condSet]) {
                DB::table('spk_fuzzy_rule_conditions')->insert([
                    'id'          => (string) Str::uuid(),
                    'rule_id'     => $ruleId,
                    'variable_id' => $varIds[$condVar],
                    'set_id'      => $setIds[$condVar][$condSet],
                    'createdAt'  => now(),
                    'updatedAt'  => now(),
                ]);
            }
        }

        $this->command->info('✅ SpkFuzzySeeder: ' . count($variablesData) . ' variabel, ' . count($setsData) . ' sets, ' . count($rulesData) . ' rules berhasil di-seed.');
    }
}
