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
        if (Schema::hasTable('expense_hub_vat_decisions')) {
            Schema::table('expense_hub_vat_decisions', function (Blueprint $table) {
                if (!Schema::hasColumn('expense_hub_vat_decisions', 'custom_seller_name')) {
                    $table->string('custom_seller_name', 255)->nullable()->after('purchase_type');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('expense_hub_vat_decisions')) {
            Schema::table('expense_hub_vat_decisions', function (Blueprint $table) {
                if (Schema::hasColumn('expense_hub_vat_decisions', 'custom_seller_name')) {
                    $table->dropColumn('custom_seller_name');
                }
            });
        }
    }
};
