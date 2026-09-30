<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            if (!Schema::hasColumn('employees', 'dead_file_date')) {
                Schema::table('employees', function (Blueprint $table) {
                    $table->date('dead_file_date')->nullable()->index()->after('dead_file_at');
                });
            }

            try {
                DB::statement("UPDATE employees SET dead_file_date = DATE(dead_file_at) WHERE dead_file_at IS NOT NULL AND dead_file_date IS NULL");
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'dead_file_date')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('dead_file_date');
            });
        }
    }
};
