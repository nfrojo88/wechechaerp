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
        if (Schema::hasTable('purchase_request_items')) {
            Schema::table('purchase_request_items', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_request_items', 'payment_method')) {
                    $table->string('payment_method', 30)->nullable()->default(null)->after('estimated_unit_cost');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_request_items')) {
            Schema::table('purchase_request_items', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_request_items', 'payment_method')) {
                    $table->dropColumn('payment_method');
                }
            });
        }
    }
};
