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
        if (!Schema::hasTable('tax_settlements')) {
            Schema::create('tax_settlements', function (Blueprint $table) {
                $table->id();
                $table->string('settlement_number', 50)->unique();
                $table->string('tax_type', 30)->default('both'); // both, vat, withholding
                $table->timestamp('period_from')->nullable();
                $table->timestamp('period_to')->nullable();
                $table->decimal('total_base_amount', 14, 2)->default(0);
                $table->decimal('vat_amount', 14, 2)->default(0);
                $table->decimal('withholding_amount', 14, 2)->default(0);
                $table->decimal('total_tax_paid', 14, 2)->default(0);
                $table->integer('records_count')->default(0);
                $table->string('status', 30)->default('pending_payment'); // pending_payment, paid, cancelled
                
                // Workflow actors
                $table->foreignId('finance_head_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_finance_staff_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('paid_at')->nullable();

                // Payment details
                $table->string('payment_method', 50)->nullable();
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->foreignId('coa_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->string('payment_reference', 100)->nullable();
                $table->string('attachment', 255)->nullable();
                $table->text('payment_notes')->nullable();

                $table->timestamps();
            });
        }

        if (Schema::hasTable('expense_requests')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('expense_requests', 'tax_settlement_id')) {
                    $table->unsignedBigInteger('tax_settlement_id')->nullable()->after('status')->index();
                }
                if (!Schema::hasColumn('expense_requests', 'vat_settled')) {
                    $table->boolean('vat_settled')->default(false)->after('tax_settlement_id')->index();
                }
                if (!Schema::hasColumn('expense_requests', 'withholding_settled')) {
                    $table->boolean('withholding_settled')->default(false)->after('vat_settled')->index();
                }
                if (!Schema::hasColumn('expense_requests', 'tax_settled_at')) {
                    $table->timestamp('tax_settled_at')->nullable()->after('withholding_settled');
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
                if (Schema::hasColumn('expense_requests', 'tax_settled_at')) {
                    $table->dropColumn('tax_settled_at');
                }
                if (Schema::hasColumn('expense_requests', 'withholding_settled')) {
                    $table->dropColumn('withholding_settled');
                }
                if (Schema::hasColumn('expense_requests', 'vat_settled')) {
                    $table->dropColumn('vat_settled');
                }
                if (Schema::hasColumn('expense_requests', 'tax_settlement_id')) {
                    $table->dropColumn('tax_settlement_id');
                }
            });
        }

        Schema::dropIfExists('tax_settlements');
    }
};
