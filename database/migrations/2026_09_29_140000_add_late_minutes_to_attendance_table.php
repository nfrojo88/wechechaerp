<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance') && !Schema::hasColumn('attendance', 'late_minutes')) {
            Schema::table('attendance', function (Blueprint $table) {
                $table->integer('late_minutes')->default(0)->after('hours_worked');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance') && Schema::hasColumn('attendance', 'late_minutes')) {
            Schema::table('attendance', function (Blueprint $table) {
                $table->dropColumn('late_minutes');
            });
        }
    }
};
