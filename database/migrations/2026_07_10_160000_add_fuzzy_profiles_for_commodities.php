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
        if (! Schema::hasTable('spk_fuzzy_profiles')) {
            Schema::create('spk_fuzzy_profiles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('commodity_id')->nullable()->index();
                $table->string('name', 150);
                $table->string('version', 30)->default('v1');
                $table->string('status', 30)->default('draft')->index();
                $table->boolean('is_active')->default(false)->index();
                $table->string('reviewed_by', 150)->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            });
        }

        $profileId = $this->ensureDefaultProfile();

        $this->addProfileColumn('spk_fuzzy_variables');
        $this->addProfileColumn('spk_fuzzy_rules');
        $this->addProfileColumn('spk_fuzzy_input_sources');

        if (Schema::hasTable('spk_fuzzy_logs')) {
            if (! Schema::hasColumn('spk_fuzzy_logs', 'profile_id')) {
                Schema::table('spk_fuzzy_logs', function (Blueprint $table) {
                    $table->uuid('profile_id')->nullable()->after('unit_budidaya_id')->index();
                });
            }

            if (! Schema::hasColumn('spk_fuzzy_logs', 'commodity_id')) {
                Schema::table('spk_fuzzy_logs', function (Blueprint $table) {
                    $table->uuid('commodity_id')->nullable()->after('profile_id')->index();
                });
            }
        }

        foreach (['spk_fuzzy_variables', 'spk_fuzzy_rules', 'spk_fuzzy_logs'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'profile_id')) {
                DB::table($table)->whereNull('profile_id')->update(['profile_id' => $profileId]);
            }
        }

        if (Schema::hasTable('spk_fuzzy_input_sources') && Schema::hasColumn('spk_fuzzy_input_sources', 'profile_id')) {
            $sourceProfiles = DB::table('spk_fuzzy_input_sources')
                ->join('spk_fuzzy_variables', 'spk_fuzzy_variables.id', '=', 'spk_fuzzy_input_sources.variable_id')
                ->whereNull('spk_fuzzy_input_sources.profile_id')
                ->get([
                    'spk_fuzzy_input_sources.id',
                    'spk_fuzzy_variables.profile_id',
                ]);

            foreach ($sourceProfiles as $row) {
                DB::table('spk_fuzzy_input_sources')
                    ->where('id', $row->id)
                    ->update(['profile_id' => $row->profile_id ?: $profileId]);
            }
        }
    }

    public function down(): void
    {
        foreach (['spk_fuzzy_logs', 'spk_fuzzy_input_sources', 'spk_fuzzy_rules', 'spk_fuzzy_variables'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'profile_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('profile_id');
                });
            }
        }

        if (Schema::hasTable('spk_fuzzy_logs') && Schema::hasColumn('spk_fuzzy_logs', 'commodity_id')) {
            Schema::table('spk_fuzzy_logs', function (Blueprint $table) {
                $table->dropColumn('commodity_id');
            });
        }

        Schema::dropIfExists('spk_fuzzy_profiles');
    }

    private function addProfileColumn(string $table): void
    {
        if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'profile_id')) {
            Schema::table($table, function (Blueprint $table) {
                $table->uuid('profile_id')->nullable()->after('id')->index();
            });
        }
    }

    private function ensureDefaultProfile(): string
    {
        $existing = DB::table('spk_fuzzy_profiles')
            ->where('name', 'Ayam Petelur - RFC v1')
            ->value('id');

        if ($existing) {
            return (string) $existing;
        }

        $commodityId = null;
        if (Schema::hasTable('komoditas')) {
            $commodityId = DB::table('komoditas')
                ->where('isDeleted', 0)
                ->where(function ($query) {
                    $query->where('nama', 'like', '%Layer%')
                        ->orWhere('nama', 'like', '%Petelur%')
                        ->orWhere('nama', 'like', '%Ayam%');
                })
                ->orderByRaw("CASE WHEN nama LIKE '%Layer%' OR nama LIKE '%Petelur%' THEN 0 ELSE 1 END")
                ->value('id');
        }

        $profileId = (string) Str::uuid();

        DB::table('spk_fuzzy_profiles')->insert([
            'id' => $profileId,
            'commodity_id' => $commodityId,
            'name' => 'Ayam Petelur - RFC v1',
            'version' => 'v1',
            'status' => 'active',
            'is_active' => true,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'notes' => 'Konfigurasi awal Fuzzy Mamdani untuk studi kasus ayam petelur RFC.',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        return $profileId;
    }
};
