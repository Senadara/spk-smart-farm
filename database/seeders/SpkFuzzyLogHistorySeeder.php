<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SpkFuzzyLogHistorySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('spk_fuzzy_logs')->delete();

        $coops = DB::table('unitBudidaya')->get();

        foreach ($coops as $coop) {
            $this->seedLogsForCoop($coop->id);
        }
        // Also seed global logs
        $this->seedLogsForCoop(null);
    }

    private function seedLogsForCoop(?string $coopId)
    {
        $now = Carbon::now();
        for ($i = 15; $i >= 0; $i--) {
            $date = (clone $now)->subDays($i);
            
            // Random historical data simulation
            $hdp = 75 + rand(0, 20) + (rand(0, 10) / 10);
            $fcr = 1.9 + (rand(0, 30) / 100);
            $suhu = 25 + rand(0, 5) + (rand(0, 10) / 10);
            $amonia = 5 + rand(0, 15);
            $kelembapan = 60 + rand(0, 20);

            DB::table('spk_fuzzy_logs')->insert([
                'id' => (string) Str::uuid(),
                'unit_budidaya_id' => $coopId,
                'input_json' => json_encode([
                    'hdp' => $hdp,
                    'fcr' => $fcr,
                    'suhu' => $suhu,
                    'amonia' => $amonia,
                    'kelembapan' => $kelembapan,
                    'mortalitas' => rand(0, 5) / 10,
                    'pakan' => 110 + rand(0, 15)
                ]),
                'fuzzified_json' => json_encode([]),
                'rule_result_json' => json_encode([]),
                'status_lingkungan' => $suhu > 28 || $amonia > 15 ? 'Waspada' : 'Optimal',
                'status_kesehatan' => $hdp < 85 ? 'Waspada' : 'Optimal',
                'diagnosis_kausalitas' => $hdp < 85 ? 'Stres Lingkungan' : 'Kondisi Ideal',
                'output_value' => rand(70, 95),
                'output_label' => 'Kondisi Ideal',
                'narrative' => '<p>Simulasi hasil analisa historis. Kondisi dalam parameter yang terpantau.</p>',
                'recommendation' => 'Lanjutkan pemantauan rutin.',
                'createdAt' => $date,
                'updatedAt' => $date,
            ]);
        }
    }
}
