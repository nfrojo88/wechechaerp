<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('credit_store_ledgers')) {
            Schema::create('credit_store_ledgers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
                $table->string('pr_no', 100)->nullable();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->string('supplier_name', 255)->nullable();
                $table->decimal('credit_amount', 18, 2)->default(0);
                $table->decimal('paid_amount', 18, 2)->default(0);
                $table->foreignId('coa_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->enum('status', ['outstanding', 'partially_paid', 'fully_paid'])->default('outstanding');
                $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('authorized_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('credit_store_payments')) {
            Schema::create('credit_store_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('credit_store_ledger_id')->constrained('credit_store_ledgers')->cascadeOnDelete();
                $table->date('payment_date');
                $table->decimal('amount', 18, 2)->default(0);
                $table->string('payment_method', 50)->default('bank_transfer'); // cash, bank_transfer, cheque, other
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->foreignId('coa_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->string('reference_no', 150)->nullable();
                $table->string('receipt_path', 500)->nullable();
                $table->string('original_filename', 255)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('credit_store_payments');
        Schema::dropIfExists('credit_store_ledgers');
    }
};
