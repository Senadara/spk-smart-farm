<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pesanan') || ! Schema::hasColumn('pesanan', 'status')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `pesanan` MODIFY `status` ENUM('menunggu','diterima','selesai','ditolak','expired','dibatalkan') NOT NULL DEFAULT 'menunggu'"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('pesanan') || ! Schema::hasColumn('pesanan', 'status')) {
            return;
        }

        DB::table('pesanan')
            ->where('status', 'dibatalkan')
            ->update(['status' => 'ditolak']);

        DB::statement(
            "ALTER TABLE `pesanan` MODIFY `status` ENUM('menunggu','diterima','selesai','ditolak','expired') NOT NULL DEFAULT 'menunggu'"
        );
    }
};
