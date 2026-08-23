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
        if (!Schema::hasTable('payrolls')) {
            return;
        }

        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'company_pension')) {
                $table->decimal('company_pension', 15, 2)->default(0)->after('pension');
            }
            if (!Schema::hasColumn('payrolls', 'taxable_income')) {
                $table->decimal('taxable_income', 15, 2)->default(0)->after('company_pension');
            }
            if (!Schema::hasColumn('payrolls', 'loan_deduction')) {
                $table->decimal('loan_deduction', 15, 2)->default(0)->after('deductions');
            }
            if (!Schema::hasColumn('payrolls', 'absence_deduction')) {
                $table->decimal('absence_deduction', 15, 2)->default(0)->after('loan_deduction');
            }
            if (!Schema::hasColumn('payrolls', 'absent_days')) {
                $table->integer('absent_days')->default(0)->after('absence_deduction');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('payrolls')) {
            return;
        }

        Schema::table('payrolls', function (Blueprint $table) {
            $cols = ['company_pension', 'taxable_income', 'loan_deduction', 'absence_deduction', 'absent_days'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('payrolls', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
