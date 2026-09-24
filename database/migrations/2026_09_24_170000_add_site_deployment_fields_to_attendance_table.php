<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('attendance')) {
            // Normalize status column to string so 'S' and 'site' can be stored without ENUM restriction
            try {
                $driver = DB::connection()->getDriverName();
                if ($driver !== 'sqlite') {
                    DB::statement("ALTER TABLE `attendance` MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'present'");
                }
            } catch (\Throwable $e) {}

            Schema::table('attendance', function (Blueprint $table) {
                if (!Schema::hasColumn('attendance', 'decided_by')) {
                    $table->foreignId('decided_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('attendance', 'decided_by_role')) {
                    $table->string('decided_by_role', 50)->nullable()->after('decided_by');
                }
                if (!Schema::hasColumn('attendance', 'site_project_id')) {
                    $table->foreignId('site_project_id')->nullable()->after('decided_by_role')->constrained('projects')->nullOnDelete();
                }
                if (!Schema::hasColumn('attendance', 'site_name')) {
                    $table->string('site_name', 255)->nullable()->after('site_project_id');
                }
                if (!Schema::hasColumn('attendance', 'site_task')) {
                    $table->text('site_task')->nullable()->after('site_name');
                }
                if (!Schema::hasColumn('attendance', 'site_start_date')) {
                    $table->date('site_start_date')->nullable()->after('site_task');
                }
                if (!Schema::hasColumn('attendance', 'site_end_date')) {
                    $table->date('site_end_date')->nullable()->after('site_start_date');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendance')) {
            Schema::table('attendance', function (Blueprint $table) {
                $cols = [
                    'site_end_date', 'site_start_date', 'site_task',
                    'site_name', 'site_project_id', 'decided_by_role', 'decided_by'
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('attendance', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
