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
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'contract_duration_type')) {
                $table->string('contract_duration_type', 40)->default('fixed_date')->after('contract_end_date');
            }
            if (!Schema::hasColumn('employees', 'is_project_based')) {
                $table->boolean('is_project_based')->default(false)->after('contract_duration_type');
            }
        });

        if (Schema::hasTable('employee_contracts')) {
            Schema::table('employee_contracts', function (Blueprint $table) {
                if (!Schema::hasColumn('employee_contracts', 'duration_type')) {
                    $table->string('duration_type', 40)->default('fixed_date')->after('end_date');
                }
                if (!Schema::hasColumn('employee_contracts', 'project_id')) {
                    $table->foreignId('project_id')->nullable()->after('employee_id')->constrained('projects')->nullOnDelete();
                }
                if (!Schema::hasColumn('employee_contracts', 'is_project_based')) {
                    $table->boolean('is_project_based')->default(false)->after('duration_type');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'contract_duration_type')) {
                $table->dropColumn('contract_duration_type');
            }
            if (Schema::hasColumn('employees', 'is_project_based')) {
                $table->dropColumn('is_project_based');
            }
        });

        if (Schema::hasTable('employee_contracts')) {
            Schema::table('employee_contracts', function (Blueprint $table) {
                if (Schema::hasColumn('employee_contracts', 'project_id')) {
                    $table->dropConstrainedForeignId('project_id');
                }
                if (Schema::hasColumn('employee_contracts', 'duration_type')) {
                    $table->dropColumn('duration_type');
                }
                if (Schema::hasColumn('employee_contracts', 'is_project_based')) {
                    $table->dropColumn('is_project_based');
                }
            });
        }
    }
};
