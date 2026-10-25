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
        if (Schema::hasTable('material_requests')) {
            Schema::table('material_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('material_requests', 'purchase_method')) {
                    $table->string('purchase_method', 50)->nullable()->after('status')->index();
                }
            });
        }

        if (Schema::hasTable('petty_cash_material_purchases')) {
            Schema::table('petty_cash_material_purchases', function (Blueprint $table) {
                if (!Schema::hasColumn('petty_cash_material_purchases', 'material_request_id')) {
                    $table->foreignId('material_request_id')->nullable()->after('delivery_receipt_id')->constrained('material_requests')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('petty_cash_material_purchases') && Schema::hasColumn('petty_cash_material_purchases', 'material_request_id')) {
            Schema::table('petty_cash_material_purchases', function (Blueprint $table) {
                $table->dropForeign(['material_request_id']);
                $table->dropColumn('material_request_id');
            });
        }

        if (Schema::hasTable('material_requests') && Schema::hasColumn('material_requests', 'purchase_method')) {
            Schema::table('material_requests', function (Blueprint $table) {
                $table->dropColumn('purchase_method');
            });
        }
    }
};
