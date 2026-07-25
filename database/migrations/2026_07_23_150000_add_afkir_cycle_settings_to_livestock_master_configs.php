<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('livestock_master_configs')) {
            return;
        }

        Schema::table('livestock_master_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('livestock_master_configs', 'afkir_label')) {
                $table->string('afkir_label', 80)->nullable()->after('notes');
            }

            if (! Schema::hasColumn('livestock_master_configs', 'afkir_target_weeks')) {
                $table->unsignedSmallInteger('afkir_target_weeks')->nullable()->after('afkir_label');
            }

            if (! Schema::hasColumn('livestock_master_configs', 'afkir_warning_weeks')) {
                $table->unsignedSmallInteger('afkir_warning_weeks')->default(4)->after('afkir_target_weeks');
            }
        });

        if (! Schema::hasTable('jenisBudidaya')) {
            return;
        }

        $rows = DB::table('livestock_master_configs as config')
            ->leftJoin('jenisBudidaya as type', 'type.id', '=', 'config.jenis_budidaya_id')
            ->get(['config.id', 'type.nama']);

        foreach ($rows as $row) {
            DB::table('livestock_master_configs')
                ->where('id', $row->id)
                ->whereNull('afkir_target_weeks')
                ->update($this->defaultsForTypeName((string) ($row->nama ?? '')));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('livestock_master_configs')) {
            return;
        }

        Schema::table('livestock_master_configs', function (Blueprint $table) {
            foreach (['afkir_warning_weeks', 'afkir_target_weeks', 'afkir_label'] as $column) {
                if (Schema::hasColumn('livestock_master_configs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function defaultsForTypeName(string $name): array
    {
        $name = strtolower($name);

        if (str_contains($name, 'petelur') || str_contains($name, 'layer')) {
            return [
                'afkir_label' => 'Afkir layer',
                'afkir_target_weeks' => 80,
                'afkir_warning_weeks' => 8,
            ];
        }

        if (str_contains($name, 'potong') || str_contains($name, 'broiler') || str_contains($name, 'pedaging')) {
            return [
                'afkir_label' => 'Akhir siklus panen',
                'afkir_target_weeks' => 6,
                'afkir_warning_weeks' => 1,
            ];
        }

        if (str_contains($name, 'lele') || str_contains($name, 'ikan')) {
            return [
                'afkir_label' => 'Akhir siklus panen',
                'afkir_target_weeks' => 12,
                'afkir_warning_weeks' => 2,
            ];
        }

        return [
            'afkir_label' => 'Afkir / akhir siklus',
            'afkir_target_weeks' => null,
            'afkir_warning_weeks' => 4,
        ];
    }
};
