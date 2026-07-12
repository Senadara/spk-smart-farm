<?php

namespace Database\Seeders;

use App\Services\AHPService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpkDssDemoSeeder extends Seeder
{
    public function run(): void
    {
        $userIds = collect([
            DB::table('user')->orderBy('createdAt')->value('id'),
            DB::table('user')->where('email', 'buyer.demo@smartfarm.test')->value('id'),
        ])->filter()->unique()->values();

        if ($userIds->isEmpty()) {
            return;
        }

        $parameterIds = DB::table('spk_parameters')
            ->whereIn('nama_parameter', ['Harga', 'Kualitas', 'Kecepatan Pengiriman', 'Jarak'])
            ->pluck('id', 'nama_parameter');

        if ($parameterIds->count() < 4) {
            return;
        }

        $weights = [
            'Harga' => 0.30,
            'Kualitas' => 0.35,
            'Kecepatan Pengiriman' => 0.20,
            'Jarak' => 0.15,
        ];

        foreach ($userIds as $userId) {
            $criteria = array_keys($weights);
            for ($i = 0; $i < count($criteria); $i++) {
                for ($j = $i + 1; $j < count($criteria); $j++) {
                    $left = $criteria[$i];
                    $right = $criteria[$j];

                    DB::table('spk_ahp_perbandingans')->updateOrInsert(
                        [
                            'user_id' => $userId,
                            'parameter_1_id' => $parameterIds[$left],
                            'parameter_2_id' => $parameterIds[$right],
                        ],
                        [
                            'nilai_skala' => $weights[$left] / $weights[$right],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }

            app(AHPService::class)->calculateAndSaveWeights((string) $userId);
        }

        $this->command?->info('SpkDssDemoSeeder: bobot AHP demo berhasil disiapkan untuk '.$userIds->count().' user.');
    }
}
