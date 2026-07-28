<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('iot_protocol')) {
            return;
        }

        $protocols = [
            'API' => 'Laravel menarik data dari REST API seperti Antares.',
            'MQTT' => 'Laravel subscribe topic MQTT dari broker.',
        ];

        foreach ($protocols as $name => $description) {
            $existing = DB::table('iot_protocol')->where('protocolName', $name)->first();

            if ($existing) {
                DB::table('iot_protocol')
                    ->where('id', $existing->id)
                    ->update(['description' => $existing->description ?: $description]);
                continue;
            }

            DB::table('iot_protocol')->insert([
                'id' => Str::uuid()->toString(),
                'protocolName' => $name,
                'description' => $description,
                'createdAt' => now(),
                'updatedAt' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Protokol tidak dihapus agar koneksi/device yang sudah memakai data ini tetap aman.
    }
};
