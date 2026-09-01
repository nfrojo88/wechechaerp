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
        if (Schema::hasTable('employee_experience')) {
            try {
                // Modify start_date column to be nullable using raw SQL for maximum compatibility
                DB::statement("ALTER TABLE `employee_experience` MODIFY `start_date` DATE NULL;");
            } catch (\Throwable $e) {
                // Fallback using schema builder
                try {
                    Schema::table('employee_experience', function (Blueprint $table) {
                        $table->date('start_date')->nullable()->change();
                    });
                } catch (\Throwable $ex) {}
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('employee_experience')) {
            try {
                DB::statement("ALTER TABLE `employee_experience` MODIFY `start_date` DATE NULL;");
            } catch (\Throwable $e) {}
        }
    }
};
