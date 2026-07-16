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

        $webhookProtocols = DB::table('iot_protocol')
            ->where('protocolName', 'WEBHOOK')
            ->pluck('id');

        foreach ($webhookProtocols as $protocolId) {
            $isUsed = Schema::hasTable('iot_connection_config')
                && DB::table('iot_connection_config')->where('protocolId', $protocolId)->exists();

            if ($isUsed) {
                DB::table('iot_protocol')
                    ->where('id', $protocolId)
                    ->update([
                        'description' => 'Legacy endpoint webhook backend. Disembunyikan dari form koneksi baru.',
                    ]);

                continue;
            }

            DB::table('iot_protocol')->where('id', $protocolId)->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('iot_protocol')) {
            return;
        }

        if (DB::table('iot_protocol')->where('protocolName', 'WEBHOOK')->exists()) {
            return;
        }

        DB::table('iot_protocol')->insert([
            'id' => Str::uuid()->toString(),
            'protocolName' => 'WEBHOOK',
            'description' => 'Device mengirim data langsung ke endpoint webhook Laravel.',
            'createdAt' => now(),
        ]);
    }
};
