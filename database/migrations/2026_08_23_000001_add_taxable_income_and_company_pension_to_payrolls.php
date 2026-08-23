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
            if (Schema::hasColumn('payrolls', 'company_pension')) {
                $table->dropColumn('company_pension');
            }
            if (Schema::hasColumn('payrolls', 'taxable_income')) {
                $table->dropColumn('taxable_income');
            }
        });
    }
};
