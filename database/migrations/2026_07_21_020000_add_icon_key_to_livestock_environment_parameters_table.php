<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('livestock_environment_parameters')
            || Schema::hasColumn('livestock_environment_parameters', 'icon_key')) {
            return;
        }

        Schema::table('livestock_environment_parameters', function (Blueprint $table) {
            $table->string('icon_key', 40)->default('sensor');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('livestock_environment_parameters')
            || ! Schema::hasColumn('livestock_environment_parameters', 'icon_key')) {
            return;
        }

        Schema::table('livestock_environment_parameters', function (Blueprint $table) {
            $table->dropColumn('icon_key');
        });
    }
};
