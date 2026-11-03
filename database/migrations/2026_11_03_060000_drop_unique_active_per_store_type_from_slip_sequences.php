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
        if (Schema::hasTable('slip_sequences')) {
            // Drop flawed unique constraint unique_active_per_store_type.
            // In MySQL, composite unique (store_id, slip_type, status) erroneously prevents
            // having multiple 'inactive' or 'full' history books for the same store & slip type.
            try {
                Schema::table('slip_sequences', function (Blueprint $table) {
                    $table->dropUnique('unique_active_per_store_type');
                });
            } catch (\Throwable $e) {
                try {
                    DB::statement("ALTER TABLE slip_sequences DROP INDEX unique_active_per_store_type");
                } catch (\Throwable $e2) {
                    // Index already dropped or not present
                }
            }

            // Ensure non-unique performance index exists on store_id and slip_type
            try {
                Schema::table('slip_sequences', function (Blueprint $table) {
                    $table->index(['store_id', 'slip_type'], 'idx_store_slip_type');
                });
            } catch (\Throwable $e) {
                // Index already exists
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Do not re-add broken composite unique index that includes status
    }
};
