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
        if (Schema::hasTable('receipts')) {
            Schema::table('receipts', function (Blueprint $table) {
                if (!Schema::hasColumn('receipts', 'purchasable_type') && !Schema::hasColumn('receipts', 'purchasable_id')) {
                    $table->nullableMorphs('purchasable');
                }
                if (!Schema::hasColumn('receipts', 'tin_valid')) {
                    $table->boolean('tin_valid')->default(true)->after('vendor_tin');
                }
                if (!Schema::hasColumn('receipts', 'fs_no_raw')) {
                    $table->string('fs_no_raw')->nullable()->after('fs_no');
                }
                if (!Schema::hasColumn('receipts', 'fs_no_valid')) {
                    $table->boolean('fs_no_valid')->default(true)->after('fs_no_raw');
                }
                if (!Schema::hasColumn('receipts', 'confidence_score')) {
                    $table->integer('confidence_score')->default(90)->after('confidence');
                }
                if (!Schema::hasColumn('receipts', 'qc_notes')) {
                    $table->text('qc_notes')->nullable()->after('notes');
                }
            });
        }

        if (Schema::hasTable('petty_cash_material_purchases')) {
            Schema::table('petty_cash_material_purchases', function (Blueprint $table) {
                if (!Schema::hasColumn('petty_cash_material_purchases', 'supplier_tin')) {
                    $table->string('supplier_tin', 20)->nullable()->after('supplier_name');
                }
                if (!Schema::hasColumn('petty_cash_material_purchases', 'fs_no')) {
                    $table->string('fs_no', 20)->nullable()->after('supplier_tin');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('receipts')) {
            Schema::table('receipts', function (Blueprint $table) {
                if (Schema::hasColumn('receipts', 'purchasable_type') && Schema::hasColumn('receipts', 'purchasable_id')) {
                    $table->dropMorphs('purchasable');
                }
            });
        }

        if (Schema::hasTable('petty_cash_material_purchases')) {
            Schema::table('petty_cash_material_purchases', function (Blueprint $table) {
                if (Schema::hasColumn('petty_cash_material_purchases', 'supplier_tin')) {
                    $table->dropColumn(['supplier_tin', 'fs_no']);
                }
            });
        }
    }
};
