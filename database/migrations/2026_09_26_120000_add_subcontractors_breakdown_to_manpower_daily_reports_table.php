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
                if (!Schema::hasColumn('manpower_daily_reports', 'subcontractors_breakdown')) {
                    $table->json('subcontractors_breakdown')->nullable()->after('roles_breakdown');
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
                if (Schema::hasColumn('manpower_daily_reports', 'subcontractors_breakdown')) {
                    $table->dropColumn('subcontractors_breakdown');
                }
            });
        }
    }
};
