<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSlipReceiptAndPhysicalStoreFields extends Migration
{
    public function up()
    {
        if (Schema::hasTable('delivery_receipts')) {
            Schema::table('delivery_receipts', function (Blueprint $table) {
                if (!Schema::hasColumn('delivery_receipts', 'slip_receipt_path')) {
                    $table->string('slip_receipt_path')->nullable()->after('notes');
                }
                if (!Schema::hasColumn('delivery_receipts', 'is_physical_store_only')) {
                    $table->boolean('is_physical_store_only')->default(false)->after('slip_receipt_path');
                }
                if (!Schema::hasColumn('delivery_receipts', 'physical_slip_notes')) {
                    $table->text('physical_slip_notes')->nullable()->after('is_physical_store_only');
                }
            });
        }

        if (Schema::hasTable('petty_cash_material_purchases')) {
            Schema::table('petty_cash_material_purchases', function (Blueprint $table) {
                if (!Schema::hasColumn('petty_cash_material_purchases', 'is_physical_store_only')) {
                    $table->boolean('is_physical_store_only')->default(false)->after('status');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('delivery_receipts')) {
            Schema::table('delivery_receipts', function (Blueprint $table) {
                if (Schema::hasColumn('delivery_receipts', 'physical_slip_notes')) {
                    $table->dropColumn('physical_slip_notes');
                }
                if (Schema::hasColumn('delivery_receipts', 'is_physical_store_only')) {
                    $table->dropColumn('is_physical_store_only');
                }
                if (Schema::hasColumn('delivery_receipts', 'slip_receipt_path')) {
                    $table->dropColumn('slip_receipt_path');
                }
            });
        }

        if (Schema::hasTable('petty_cash_material_purchases')) {
            Schema::table('petty_cash_material_purchases', function (Blueprint $table) {
                if (Schema::hasColumn('petty_cash_material_purchases', 'is_physical_store_only')) {
                    $table->dropColumn('is_physical_store_only');
                }
            });
        }
    }
}
