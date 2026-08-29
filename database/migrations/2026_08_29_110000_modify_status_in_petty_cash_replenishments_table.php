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
        try {
            DB::statement("ALTER TABLE petty_cash_replenishments MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending'");
        } catch (\Throwable $e) {
            // Fallback for non-MySQL or if doctrine/dbal is used
            try {
                Schema::table('petty_cash_replenishments', function (Blueprint $table) {
                    $table->string('status', 50)->default('pending')->change();
                });
            } catch (\Throwable $ex) {
                // Ignore
            }
        }

        try {
            DB::statement("ALTER TABLE petty_cash_replenishment_items MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending'");
        } catch (\Throwable $e) {
            try {
                Schema::table('petty_cash_replenishment_items', function (Blueprint $table) {
                    $table->string('status', 50)->default('pending')->change();
                });
            } catch (\Throwable $ex) {
                // Ignore
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
