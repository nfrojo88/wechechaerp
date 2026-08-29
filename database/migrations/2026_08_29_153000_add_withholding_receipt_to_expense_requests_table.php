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
        Schema::table('expense_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('expense_requests', 'withholding_receipt')) {
                $table->string('withholding_receipt', 500)->nullable()->after('withholding_amount');
            }
            if (!Schema::hasColumn('expense_requests', 'withholding_receipt_number')) {
                $table->string('withholding_receipt_number', 100)->nullable()->after('withholding_receipt');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            if (Schema::hasColumn('expense_requests', 'withholding_receipt')) {
                $table->dropColumn('withholding_receipt');
            }
            if (Schema::hasColumn('expense_requests', 'withholding_receipt_number')) {
                $table->dropColumn('withholding_receipt_number');
            }
        });
    }
};
