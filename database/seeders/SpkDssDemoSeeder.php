<?php

namespace Database\Seeders;

use App\Services\AHPService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpkDssDemoSeeder extends Seeder
{
    public function run(): void
    {
        $userId = DB::table('user')->orderBy('createdAt')->value('id');
        if ($userId === null) {
            return;
        }

        $parameterIds = DB::table('spk_parameters')
            ->whereIn('nama_parameter', ['Harga', 'Kualitas', 'Kecepatan Pengiriman'])
            ->pluck('id', 'nama_parameter');

        if ($parameterIds->count() < 3) {
            return;
        }

        $comparisons = [
            ['Harga', 'Kualitas', 0.5],
            ['Harga', 'Kecepatan Pengiriman', 1.0],
            ['Kualitas', 'Kecepatan Pengiriman', 2.0],
        ];

        foreach ($comparisons as [$left, $right, $scale]) {
            DB::table('spk_ahp_perbandingans')->updateOrInsert(
                [
                    'user_id' => $userId,
                    'parameter_1_id' => $parameterIds[$left],
                    'parameter_2_id' => $parameterIds[$right],
                ],
                [
                    'nilai_skala' => $scale,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        app(AHPService::class)->calculateAndSaveWeights((string) $userId);

        $this->command?->info('SpkDssDemoSeeder: bobot AHP demo berhasil disiapkan.');
    }
}
