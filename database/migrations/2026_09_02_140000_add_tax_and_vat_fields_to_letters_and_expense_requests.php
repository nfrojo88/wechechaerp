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
        if (Schema::hasTable('letters')) {
            Schema::table('letters', function (Blueprint $table) {
                if (!Schema::hasColumn('letters', 'gross_amount')) {
                    $table->decimal('gross_amount', 15, 2)->nullable()->after('payment_amount');
                }
                if (!Schema::hasColumn('letters', 'vat_type')) {
                    $table->string('vat_type', 50)->default('none')->after('gross_amount');
                }
                if (!Schema::hasColumn('letters', 'vat_rate')) {
                    $table->decimal('vat_rate', 8, 2)->default(15.00)->after('vat_type');
                }
                if (!Schema::hasColumn('letters', 'vat_amount')) {
                    $table->decimal('vat_amount', 15, 2)->default(0)->after('vat_rate');
                }
                if (!Schema::hasColumn('letters', 'has_withholding')) {
                    $table->boolean('has_withholding')->default(false)->after('vat_amount');
                }
                if (!Schema::hasColumn('letters', 'withholding_rate')) {
                    $table->decimal('withholding_rate', 8, 2)->default(3.00)->after('has_withholding');
                }
                if (!Schema::hasColumn('letters', 'withholding_amount')) {
                    $table->decimal('withholding_amount', 15, 2)->default(0)->after('withholding_rate');
                }
                if (!Schema::hasColumn('letters', 'withholding_receipt')) {
                    $table->string('withholding_receipt', 500)->nullable()->after('withholding_amount');
                }
                if (!Schema::hasColumn('letters', 'withholding_receipt_number')) {
                    $table->string('withholding_receipt_number', 100)->nullable()->after('withholding_receipt');
                }
                if (!Schema::hasColumn('letters', 'net_amount')) {
                    $table->decimal('net_amount', 15, 2)->nullable()->after('withholding_receipt_number');
                }
            });
        }

        if (Schema::hasTable('expense_requests')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('expense_requests', 'letter_id')) {
                    $table->foreignId('letter_id')->nullable()->constrained('letters')->nullOnDelete()->after('purchase_request_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('expense_requests')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                if (Schema::hasColumn('expense_requests', 'letter_id')) {
                    $table->dropForeign(['letter_id']);
                    $table->dropColumn('letter_id');
                }
            });
        }

        if (Schema::hasTable('letters')) {
            Schema::table('letters', function (Blueprint $table) {
                $columns = [
                    'gross_amount',
                    'vat_type',
                    'vat_rate',
                    'vat_amount',
                    'has_withholding',
                    'withholding_rate',
                    'withholding_amount',
                    'withholding_receipt',
                    'withholding_receipt_number',
                    'net_amount',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('letters', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
