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
        // 1. Add employee_id to letters table if missing
        if (Schema::hasTable('letters')) {
            Schema::table('letters', function (Blueprint $table) {
                if (!Schema::hasColumn('letters', 'employee_id')) {
                    $table->foreignId('employee_id')->nullable()->after('category')->constrained('employees')->nullOnDelete();
                }
            });
        }

        // 2. Create employee_salary_histories table if missing
        if (!Schema::hasTable('employee_salary_histories')) {
            Schema::create('employee_salary_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->decimal('old_basic_salary', 15, 2)->default(0);
                $table->decimal('new_basic_salary', 15, 2)->default(0);
                $table->decimal('old_transport_allowance', 15, 2)->default(0);
                $table->decimal('new_transport_allowance', 15, 2)->default(0);
                $table->decimal('old_house_allowance', 15, 2)->default(0);
                $table->decimal('new_house_allowance', 15, 2)->default(0);
                $table->decimal('old_position_allowance', 15, 2)->default(0);
                $table->decimal('new_position_allowance', 15, 2)->default(0);
                $table->decimal('old_gross_salary', 15, 2)->default(0);
                $table->decimal('new_gross_salary', 15, 2)->default(0);
                $table->date('effective_date');
                $table->string('reason')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('letter_id')->nullable()->constrained('letters')->nullOnDelete();
                $table->foreignId('employee_letter_id')->nullable()->constrained('employee_letters')->nullOnDelete();
                $table->string('attachment_path')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_salary_histories');

        if (Schema::hasTable('letters') && Schema::hasColumn('letters', 'employee_id')) {
            Schema::table('letters', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
                $table->dropColumn('employee_id');
            });
        }
    }
};
