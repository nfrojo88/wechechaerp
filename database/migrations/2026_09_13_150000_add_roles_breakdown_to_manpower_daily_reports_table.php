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
        if (Schema::hasTable('manpower_daily_reports')) {
            Schema::table('manpower_daily_reports', function (Blueprint $table) {
                if (!Schema::hasColumn('manpower_daily_reports', 'roles_breakdown')) {
                    $table->json('roles_breakdown')->nullable()->after('subcontractor_workers');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('manpower_daily_reports')) {
            Schema::table('manpower_daily_reports', function (Blueprint $table) {
                if (Schema::hasColumn('manpower_daily_reports', 'roles_breakdown')) {
                    $table->dropColumn('roles_breakdown');
                }
            });
        }
    }
};
