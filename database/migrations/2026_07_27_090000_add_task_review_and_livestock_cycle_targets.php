<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addTaskReviewColumns();
        $this->addLivestockCycleColumns();
        $this->addProductivityTargetColumns();
        $this->seedLivestockCycleDefaults();
        $this->seedProductivityTargets();
    }

    public function down(): void
    {
        if (Schema::hasTable('livestock_productivity_function_configs')) {
            Schema::table('livestock_productivity_function_configs', function (Blueprint $table) {
                foreach (['target_max_value', 'target_min_value'] as $column) {
                    if (Schema::hasColumn('livestock_productivity_function_configs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('livestock_master_configs')) {
            Schema::table('livestock_master_configs', function (Blueprint $table) {
                foreach (['production_decline_weeks', 'peak_end_weeks', 'peak_start_weeks', 'production_start_weeks'] as $column) {
                    if (Schema::hasColumn('livestock_master_configs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('spk_action_tasks')) {
            Schema::table('spk_action_tasks', function (Blueprint $table) {
                foreach ([
                    'system_validation_note',
                    'system_validation_status',
                    'review_note',
                    'reviewed_at',
                    'reviewed_by',
                    'review_status',
                    'completion_requested_at',
                ] as $column) {
                    if (Schema::hasColumn('spk_action_tasks', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function addTaskReviewColumns(): void
    {
        if (! Schema::hasTable('spk_action_tasks')) {
            return;
        }

        Schema::table('spk_action_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('spk_action_tasks', 'completion_requested_at')) {
                $table->timestamp('completion_requested_at')->nullable()->after('completed_at');
            }

            if (! Schema::hasColumn('spk_action_tasks', 'review_status')) {
                $table->string('review_status', 30)->default('none')->after('completion_requested_at');
            }

            if (! Schema::hasColumn('spk_action_tasks', 'reviewed_by')) {
                $table->char('reviewed_by', 36)->nullable()->after('review_status');
            }

            if (! Schema::hasColumn('spk_action_tasks', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }

            if (! Schema::hasColumn('spk_action_tasks', 'review_note')) {
                $table->text('review_note')->nullable()->after('reviewed_at');
            }

            if (! Schema::hasColumn('spk_action_tasks', 'system_validation_status')) {
                $table->string('system_validation_status', 30)->default('not_checked')->after('review_note');
            }

            if (! Schema::hasColumn('spk_action_tasks', 'system_validation_note')) {
                $table->text('system_validation_note')->nullable()->after('system_validation_status');
            }
        });
    }

    private function addLivestockCycleColumns(): void
    {
        if (! Schema::hasTable('livestock_master_configs')) {
            return;
        }

        Schema::table('livestock_master_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('livestock_master_configs', 'production_start_weeks')) {
                $table->unsignedSmallInteger('production_start_weeks')->nullable()->after('afkir_warning_weeks');
            }

            if (! Schema::hasColumn('livestock_master_configs', 'peak_start_weeks')) {
                $table->unsignedSmallInteger('peak_start_weeks')->nullable()->after('production_start_weeks');
            }

            if (! Schema::hasColumn('livestock_master_configs', 'peak_end_weeks')) {
                $table->unsignedSmallInteger('peak_end_weeks')->nullable()->after('peak_start_weeks');
            }

            if (! Schema::hasColumn('livestock_master_configs', 'production_decline_weeks')) {
                $table->unsignedSmallInteger('production_decline_weeks')->nullable()->after('peak_end_weeks');
            }
        });
    }

    private function addProductivityTargetColumns(): void
    {
        if (! Schema::hasTable('livestock_productivity_function_configs')) {
            return;
        }

        Schema::table('livestock_productivity_function_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('livestock_productivity_function_configs', 'target_min_value')) {
                $table->double('target_min_value')->nullable()->after('aggregation_scope');
            }

            if (! Schema::hasColumn('livestock_productivity_function_configs', 'target_max_value')) {
                $table->double('target_max_value')->nullable()->after('target_min_value');
            }
        });
    }

    private function seedLivestockCycleDefaults(): void
    {
        if (! Schema::hasTable('livestock_master_configs') || ! Schema::hasTable('jenisBudidaya')) {
            return;
        }

        $rows = DB::table('livestock_master_configs as config')
            ->leftJoin('jenisBudidaya as type', 'type.id', '=', 'config.jenis_budidaya_id')
            ->get(['config.id', 'type.nama']);

        foreach ($rows as $row) {
            $defaults = $this->cycleDefaultsForTypeName((string) ($row->nama ?? ''));

            DB::table('livestock_master_configs')
                ->where('id', $row->id)
                ->update(array_filter($defaults, fn ($value) => $value !== null));
        }
    }

    private function seedProductivityTargets(): void
    {
        if (! Schema::hasTable('livestock_productivity_functions') || ! Schema::hasTable('livestock_productivity_function_configs')) {
            return;
        }

        $targets = [
            'hdp' => [85, 100],
            'hhep' => [85, 100],
            'fcr' => [1.85, 2.55],
            'feed_intake' => [100, 130],
            'avg_egg_weight' => [53, 73],
            'mortalitas' => [0, 1],
        ];

        foreach ($targets as $code => [$min, $max]) {
            $functionId = DB::table('livestock_productivity_functions')->where('code', $code)->value('id');

            if (! $functionId) {
                continue;
            }

            DB::table('livestock_productivity_function_configs')
                ->where('function_id', $functionId)
                ->whereNull('target_min_value')
                ->whereNull('target_max_value')
                ->update([
                    'target_min_value' => $min,
                    'target_max_value' => $max,
                    'updatedAt' => now(),
                ]);
        }
    }

    private function cycleDefaultsForTypeName(string $name): array
    {
        $name = strtolower($name);

        if (str_contains($name, 'petelur') || str_contains($name, 'layer')) {
            return [
                'production_start_weeks' => 18,
                'peak_start_weeks' => 25,
                'peak_end_weeks' => 45,
                'production_decline_weeks' => 46,
            ];
        }

        return [
            'production_start_weeks' => null,
            'peak_start_weeks' => null,
            'peak_end_weeks' => null,
            'production_decline_weeks' => null,
        ];
    }
};
