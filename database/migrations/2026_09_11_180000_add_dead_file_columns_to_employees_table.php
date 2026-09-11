<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'is_dead_file')) {
                $table->boolean('is_dead_file')->default(false)->index()->after('status');
            }
            if (!Schema::hasColumn('employees', 'dead_file_at')) {
                $table->timestamp('dead_file_at')->nullable()->after('is_dead_file');
            }
            if (!Schema::hasColumn('employees', 'dead_file_reason')) {
                $table->string('dead_file_reason', 150)->nullable()->after('dead_file_at');
            }
            if (!Schema::hasColumn('employees', 'dead_file_notes')) {
                $table->text('dead_file_notes')->nullable()->after('dead_file_reason');
            }
            if (!Schema::hasColumn('employees', 'dead_file_by')) {
                $table->foreignId('dead_file_by')->nullable()->constrained('users')->nullOnDelete()->after('dead_file_notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('employees', 'dead_file_by')) {
                $columnsToDrop[] = 'dead_file_by';
            }
            if (Schema::hasColumn('employees', 'dead_file_notes')) {
                $columnsToDrop[] = 'dead_file_notes';
            }
            if (Schema::hasColumn('employees', 'dead_file_reason')) {
                $columnsToDrop[] = 'dead_file_reason';
            }
            if (Schema::hasColumn('employees', 'dead_file_at')) {
                $columnsToDrop[] = 'dead_file_at';
            }
            if (Schema::hasColumn('employees', 'is_dead_file')) {
                $columnsToDrop[] = 'is_dead_file';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
