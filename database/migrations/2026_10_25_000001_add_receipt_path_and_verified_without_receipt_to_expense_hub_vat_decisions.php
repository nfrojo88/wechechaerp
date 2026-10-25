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
                if (!Schema::hasColumn('expense_hub_vat_decisions', 'receipt_path')) {
                    $table->string('receipt_path', 500)->nullable()->after('custom_fs_no');
                }
                if (!Schema::hasColumn('expense_hub_vat_decisions', 'is_verified_without_receipt')) {
                    $table->boolean('is_verified_without_receipt')->default(false)->after('no_vat_reason_note')->index();
                }
                if (!Schema::hasColumn('expense_hub_vat_decisions', 'ocr_confidence_score')) {
                    $table->integer('ocr_confidence_score')->nullable()->after('receipt_path');
                }
                if (!Schema::hasColumn('expense_hub_vat_decisions', 'raw_ocr_payload')) {
                    $table->longText('raw_ocr_payload')->nullable()->after('ocr_confidence_score');
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
                $columnsToDrop = [];
                if (Schema::hasColumn('expense_hub_vat_decisions', 'raw_ocr_payload')) {
                    $columnsToDrop[] = 'raw_ocr_payload';
                }
                if (Schema::hasColumn('expense_hub_vat_decisions', 'ocr_confidence_score')) {
                    $columnsToDrop[] = 'ocr_confidence_score';
                }
                if (Schema::hasColumn('expense_hub_vat_decisions', 'is_verified_without_receipt')) {
                    $columnsToDrop[] = 'is_verified_without_receipt';
                }
                if (Schema::hasColumn('expense_hub_vat_decisions', 'receipt_path')) {
                    $columnsToDrop[] = 'receipt_path';
                }
                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
