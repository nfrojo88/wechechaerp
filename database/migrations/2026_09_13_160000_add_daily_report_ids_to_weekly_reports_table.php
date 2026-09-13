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
        if (Schema::hasTable('weekly_reports')) {
            Schema::table('weekly_reports', function (Blueprint $table) {
                if (!Schema::hasColumn('weekly_reports', 'daily_report_ids')) {
                    $table->json('daily_report_ids')->nullable()->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('weekly_reports')) {
            Schema::table('weekly_reports', function (Blueprint $table) {
                if (Schema::hasColumn('weekly_reports', 'daily_report_ids')) {
                    $table->dropColumn('daily_report_ids');
                }
            });
        }
    }
};
