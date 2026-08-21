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
        if (Schema::hasTable('employee_experience')) {
            Schema::table('employee_experience', function (Blueprint $table) {
                if (!Schema::hasColumn('employee_experience', 'experience_letter')) {
                    $table->string('experience_letter')->nullable()->after('responsibilities');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('employee_experience')) {
            Schema::table('employee_experience', function (Blueprint $table) {
                if (Schema::hasColumn('employee_experience', 'experience_letter')) {
                    $table->dropColumn('experience_letter');
                }
            });
        }
    }
};
