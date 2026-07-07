<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel API utama memakai `user.id` UUID/string. Migration ini memperbaiki
     * database dev lama yang telanjur membuat kolom DSS sebagai unsigned bigint.
     */
    public function up(): void
    {
        foreach ($this->tablesWithUserReference() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            $this->dropForeignKeysForColumn($table, 'user_id');
            DB::statement("ALTER TABLE `{$table}` MODIFY `user_id` CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL");
            $this->replaceInvalidUserIds($table);
            $this->ensureLeadingUserIdIndex($table);
            $this->addUserForeignKey($table);
        }

        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            DB::statement('ALTER TABLE `sessions` MODIFY `user_id` CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL');
            $this->ensureLeadingUserIdIndex('sessions');
        }
    }

    public function down(): void
    {
        foreach ($this->tablesWithUserReference() as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                $this->dropForeignKeysForColumn($table, 'user_id');
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function tablesWithUserReference(): array
    {
        return [
            'spk_ahp_perbandingans',
            'spk_ahp_bobots',
            'spk_rankings',
            'spk_ahp_configurations',
            'spk_supplier_selection_logs',
        ];
    }

    private function replaceInvalidUserIds(string $table): void
    {
        if (! Schema::hasTable('user')) {
            return;
        }

        $fallbackUserId = DB::table('user')->orderBy('createdAt')->value('id');

        if ($fallbackUserId === null) {
            DB::statement("UPDATE `{$table}` SET `user_id` = NULL WHERE `user_id` IS NOT NULL");
            return;
        }

        DB::statement(
            "UPDATE `{$table}` " .
            "SET `user_id` = ? " .
            "WHERE `user_id` IS NOT NULL " .
            "AND NOT EXISTS (SELECT 1 FROM `user` WHERE `user`.`id` = `{$table}`.`user_id`)",
            [$fallbackUserId]
        );
    }

    private function ensureLeadingUserIdIndex(string $table): void
    {
        $indexes = DB::select(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS ' .
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND SEQ_IN_INDEX = 1',
            [$table, 'user_id']
        );

        if ($indexes !== []) {
            return;
        }

        $indexName = "{$table}_user_id_index";
        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`user_id`)");
    }

    private function addUserForeignKey(string $table): void
    {
        if (! Schema::hasTable('user') || $this->foreignKeyForColumnExists($table, 'user_id')) {
            return;
        }

        $constraintName = "{$table}_user_id_foreign";
        DB::statement(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraintName}` " .
            'FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE'
        );
    }

    private function foreignKeyForColumnExists(string $table, string $column): bool
    {
        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE ' .
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? ' .
            'AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        );

        return $constraints !== [];
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE ' .
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? ' .
            'AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        );

        foreach ($constraints as $constraint) {
            $name = $constraint->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
        }
    }
};
