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
        if (Schema::hasTable('letter_recipients')) {
            try {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE letter_recipients MODIFY action VARCHAR(50) NOT NULL DEFAULT 'initial_sent'");
                    DB::statement("ALTER TABLE letter_recipients MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending'");
                }
            } catch (\Throwable $e) {
                // Ignore if driver differs or already modified
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('letter_recipients')) {
            try {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE letter_recipients MODIFY action ENUM('initial_sent', 'redirected', 'viewed', 'closed') NOT NULL DEFAULT 'initial_sent'");
                    DB::statement("ALTER TABLE letter_recipients MODIFY status ENUM('pending', 'viewed', 'redirected', 'closed') NOT NULL DEFAULT 'pending'");
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }
};
