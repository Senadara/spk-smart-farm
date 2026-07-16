<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spk_parameters')) {
            return;
        }

        DB::transaction(function () {
            $legacyDelivery = DB::table('spk_parameters')
                ->where('nama_parameter', 'Kecepatan Pengiriman')
                ->first();
            $delivery = DB::table('spk_parameters')
                ->where('nama_parameter', 'Waktu Pengiriman')
                ->first();

            if ($legacyDelivery && ! $delivery) {
                DB::table('spk_parameters')
                    ->where('id', $legacyDelivery->id)
                    ->update([
                        'nama_parameter' => 'Waktu Pengiriman',
                        'tipe' => 'cost',
                        'deskripsi' => 'Estimasi waktu pengiriman dari seller ke peternakan dalam hari. Nilai 0.5 berarti same day; semakin kecil semakin baik.',
                        'updated_at' => now(),
                    ]);
                $deliveryId = $legacyDelivery->id;
            } elseif ($delivery) {
                $deliveryId = $delivery->id;
                DB::table('spk_parameters')
                    ->where('id', $deliveryId)
                    ->update([
                        'tipe' => 'cost',
                        'deskripsi' => 'Estimasi waktu pengiriman dari seller ke peternakan dalam hari. Nilai 0.5 berarti same day; semakin kecil semakin baik.',
                        'updated_at' => now(),
                    ]);

                if ($legacyDelivery) {
                    DB::table('spk_supplier_parameter_values')
                        ->where('parameter_id', $legacyDelivery->id)
                        ->update(['parameter_id' => $deliveryId]);
                    DB::table('spk_parameters')->where('id', $legacyDelivery->id)->delete();
                }
            } else {
                $deliveryId = DB::table('spk_parameters')->insertGetId([
                    'nama_parameter' => 'Waktu Pengiriman',
                    'tipe' => 'cost',
                    'deskripsi' => 'Estimasi waktu pengiriman dari seller ke peternakan dalam hari. Nilai 0.5 berarti same day; semakin kecil semakin baik.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $distance = DB::table('spk_parameters')->where('nama_parameter', 'Jarak')->first();
            if ($distance) {
                DB::table('spk_parameters')->where('id', $distance->id)->delete();
            }

            DB::table('spk_rankings')->delete();
            DB::table('spk_ahp_configurations')->delete();
            DB::table('spk_ahp_bobots')->delete();
            DB::table('spk_ahp_perbandingans')->delete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('spk_parameters')) {
            return;
        }

        DB::table('spk_parameters')
            ->where('nama_parameter', 'Waktu Pengiriman')
            ->update([
                'nama_parameter' => 'Kecepatan Pengiriman',
                'tipe' => 'benefit',
                'deskripsi' => 'Kecepatan pengiriman (hari, semakin cepat semakin baik - nilai = 1/estimasi hari)',
                'updated_at' => now(),
            ]);

        DB::table('spk_parameters')->updateOrInsert(
            ['nama_parameter' => 'Jarak'],
            [
                'tipe' => 'cost',
                'deskripsi' => 'Jarak dari lokasi operasional peternakan owner ke lokasi supplier. Nilai dihitung otomatis per user.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};
