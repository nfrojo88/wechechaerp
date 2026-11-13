<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            // 1. Add Dimension Columns if not present
            if (!Schema::hasColumn('journal_entry_lines', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->after('amount');
            }
            if (!Schema::hasColumn('journal_entry_lines', 'cost_center_id')) {
                $table->unsignedBigInteger('cost_center_id')->nullable()->after('project_id');
            }
            if (!Schema::hasColumn('journal_entry_lines', 'site_id')) {
                $table->unsignedBigInteger('site_id')->nullable()->after('cost_center_id');
            }
            if (!Schema::hasColumn('journal_entry_lines', 'party_type')) {
                $table->string('party_type', 50)->nullable()->after('site_id');
            }
            if (!Schema::hasColumn('journal_entry_lines', 'party_id')) {
                $table->unsignedBigInteger('party_id')->nullable()->after('party_type');
            }

            // 2. Add Composite and Dimension Indexes
            $table->index(['journal_entry_id', 'account_id'], 'idx_jel_entry_account');
            $table->index(['party_type', 'party_id'], 'idx_jel_party');
            $table->index('project_id', 'idx_jel_project');
            $table->index('cost_center_id', 'idx_jel_cost_center');
            $table->index('site_id', 'idx_jel_site');
        });

        // 3. Add Foreign Keys and CHECK constraint
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $table->foreign('journal_entry_id', 'fk_jel_journal_entry')
                      ->references('id')->on('journal_entries')
                      ->restrictOnDelete();

                $table->foreign('account_id', 'fk_jel_account')
                      ->references('id')->on('chart_of_accounts')
                      ->restrictOnDelete();
            });

            DB::statement("ALTER TABLE `journal_entry_lines` ADD CONSTRAINT `chk_jel_amount_positive` CHECK (`amount` > 0);");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            try {
                DB::statement("ALTER TABLE `journal_entry_lines` DROP CHECK `chk_jel_amount_positive`;");
            } catch (\Throwable $e) {}

            try {
                DB::statement("ALTER TABLE `journal_entry_lines` DROP FOREIGN KEY `fk_jel_journal_entry`;");
            } catch (\Throwable $e) {}

            try {
                DB::statement("ALTER TABLE `journal_entry_lines` DROP FOREIGN KEY `fk_jel_account`;");
            } catch (\Throwable $e) {}
        }

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_jel_entry_account');
            } catch (\Throwable $e) {}
            try {
                $table->dropIndex('idx_jel_party');
            } catch (\Throwable $e) {}
            try {
                $table->dropIndex('idx_jel_project');
            } catch (\Throwable $e) {}
            try {
                $table->dropIndex('idx_jel_cost_center');
            } catch (\Throwable $e) {}
            try {
                $table->dropIndex('idx_jel_site');
            } catch (\Throwable $e) {}

            $table->dropColumn([
                'project_id',
                'cost_center_id',
                'site_id',
                'party_type',
                'party_id'
            ]);
        });
    }
};
