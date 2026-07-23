<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('livestock_master_configs')) {
            Schema::create('livestock_master_configs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('jenis_budidaya_id')->unique();
                $table->uuid('commodity_id')->nullable()->index();
                $table->string('status', 30)->default('draft')->index();
                $table->text('notes')->nullable();
                $table->uuid('configured_by')->nullable()->index();
                $table->timestamp('configured_at')->nullable();
                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            });
        }

        if (! Schema::hasTable('livestock_environment_parameters')) {
            Schema::create('livestock_environment_parameters', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('config_id')->index();
                $table->uuid('parameter_id')->nullable()->index();
                $table->string('parameter_code', 50);
                $table->string('parameter_name', 150);
                $table->string('unit', 30)->nullable();
                $table->double('min_value')->nullable();
                $table->double('max_value')->nullable();
                $table->double('fallback_value')->nullable();
                $table->unsignedInteger('stale_minutes')->default(30);
                $table->boolean('required_for_iot')->default(true);
                $table->boolean('required_for_fuzzy')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['config_id', 'parameter_code'], 'livestock_env_config_code_unique');
            });
        }

        if (! Schema::hasTable('livestock_productivity_functions')) {
            Schema::create('livestock_productivity_functions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 60)->unique();
                $table->string('name', 150);
                $table->string('service_class', 220);
                $table->string('output_unit', 30)->nullable();
                $table->text('description')->nullable();
                $table->json('required_inputs')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            });
        }

        if (! Schema::hasTable('livestock_productivity_function_configs')) {
            Schema::create('livestock_productivity_function_configs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('config_id')->index();
                $table->uuid('function_id')->index();
                $table->boolean('required_for_fuzzy')->default(true);
                $table->string('aggregation_scope', 30)->default('today');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['config_id', 'function_id'], 'livestock_function_config_unique');
            });
        }

        $this->seedProductivityCatalog();
    }

    public function down(): void
    {
        Schema::dropIfExists('livestock_productivity_function_configs');
        Schema::dropIfExists('livestock_productivity_functions');
        Schema::dropIfExists('livestock_environment_parameters');
        Schema::dropIfExists('livestock_master_configs');
    }

    private function seedProductivityCatalog(): void
    {
        if (! Schema::hasTable('livestock_productivity_functions')) {
            return;
        }

        $functions = [
            'hdp' => [
                'name' => 'HDP - Hen Day Production',
                'service_class' => 'App\\Services\\Fuzzy\\CalculateHdp',
                'output_unit' => '%',
                'description' => 'Persentase produksi harian terhadap populasi aktif.',
                'required_inputs' => ['panen.jumlah', 'unitBudidaya.jumlah'],
            ],
            'hhep' => [
                'name' => 'HHEP - Hen Housed Egg Production',
                'service_class' => 'App\\Services\\Fuzzy\\CalculateHhep',
                'output_unit' => '%',
                'description' => 'Persentase produksi harian terhadap estimasi populasi awal kandang.',
                'required_inputs' => ['panen.jumlah', 'unitBudidaya.jumlah', 'kematian.id'],
            ],
            'feed_intake' => [
                'name' => 'Pakan per ekor per hari',
                'service_class' => 'App\\Services\\Fuzzy\\CalculatePakan',
                'output_unit' => 'g/ekor',
                'description' => 'Estimasi konsumsi pakan harian per ekor dari laporan ternak.',
                'required_inputs' => ['harianTernak.pakan', 'unitBudidaya.jumlah'],
            ],
            'mortalitas' => [
                'name' => 'Mortalitas bulan berjalan',
                'service_class' => 'App\\Services\\Fuzzy\\CalculateMortalitas',
                'output_unit' => '%',
                'description' => 'Persentase kematian terhadap populasi pada periode berjalan.',
                'required_inputs' => ['kematian.id', 'unitBudidaya.jumlah'],
            ],
            'fcr' => [
                'name' => 'FCR - Feed Conversion Ratio',
                'service_class' => 'App\\Services\\Fuzzy\\CalculateFcr',
                'output_unit' => 'rasio',
                'description' => 'Rasio pakan terhadap egg mass dari panen.',
                'required_inputs' => ['harianTernak.pakan', 'panen.berat'],
            ],
            'egg_mass' => [
                'name' => 'Egg Mass / Berat panen harian',
                'service_class' => 'App\\Services\\Fuzzy\\CalculateEggMass',
                'output_unit' => 'kg',
                'description' => 'Total berat telur yang dipanen harian dari laporan mobile.',
                'required_inputs' => ['panen.berat'],
            ],
            'avg_egg_weight' => [
                'name' => 'Berat rata-rata telur',
                'service_class' => 'App\\Services\\Fuzzy\\CalculateAverageEggWeight',
                'output_unit' => 'g/butir',
                'description' => 'Rata-rata berat telur harian dari total berat panen dibagi jumlah butir.',
                'required_inputs' => ['panen.berat', 'panen.jumlah'],
            ],
            'flock_age' => [
                'name' => 'Umur biologis flock',
                'service_class' => 'App\\Services\\Fuzzy\\CalculateFlockAge',
                'output_unit' => 'minggu',
                'description' => 'Rata-rata umur biologis kandang aktif dari input mobile atau tanggal kandang dibuat.',
                'required_inputs' => ['unitBudidaya.umurMinggu', 'unitBudidaya.createdAt'],
            ],
        ];

        foreach ($functions as $code => $meta) {
            $id = DB::table('livestock_productivity_functions')->where('code', $code)->value('id') ?: (string) Str::uuid();

            DB::table('livestock_productivity_functions')->updateOrInsert(
                ['code' => $code],
                [
                    'id' => $id,
                    'name' => $meta['name'],
                    'service_class' => $meta['service_class'],
                    'output_unit' => $meta['output_unit'],
                    'description' => $meta['description'],
                    'required_inputs' => json_encode($meta['required_inputs']),
                    'is_active' => true,
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ]
            );
        }
    }
};
