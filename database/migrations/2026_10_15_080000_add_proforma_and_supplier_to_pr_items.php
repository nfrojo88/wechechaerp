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
                if (!Schema::hasColumn('purchase_request_items', 'proforma_invoice_id')) {
                    $table->unsignedBigInteger('proforma_invoice_id')->nullable()->after('estimated_unit_cost');
                }
                if (!Schema::hasColumn('purchase_request_items', 'supplier_id')) {
                    $table->unsignedBigInteger('supplier_id')->nullable()->after('proforma_invoice_id');
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
                if (Schema::hasColumn('purchase_request_items', 'supplier_id')) {
                    $table->dropColumn('supplier_id');
                }
                if (Schema::hasColumn('purchase_request_items', 'proforma_invoice_id')) {
                    $table->dropColumn('proforma_invoice_id');
                }
            });
        }
    }
};
