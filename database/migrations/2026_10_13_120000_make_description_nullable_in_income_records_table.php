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
        if (Schema::hasTable('income_records')) {
            try {
                DB::statement("ALTER TABLE `income_records` MODIFY `description` TEXT NULL;");
            } catch (\Throwable $e) {
                try {
                    Schema::table('income_records', function (Blueprint $table) {
                        $table->text('description')->nullable()->change();
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
        // Safe no-op to prevent data truncation
    }
};
