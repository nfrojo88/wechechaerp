<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('utility_expenses')) {
            Schema::create('utility_expenses', function (Blueprint $table) {
                $table->id();
                $table->string('expense_number', 50)->unique();
                $table->string('utility_type', 50)->default('other'); // wifi, rental, electricity, water, telephone, sanitation_security, fuel_generator, other
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->string('billing_period', 100)->nullable();
                $table->string('reference_no', 100)->nullable();
                $table->date('due_date')->nullable();
                $table->decimal('amount', 15, 2);
                $table->unsignedBigInteger('coa_id');
                $table->unsignedBigInteger('bank_account_id')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('assigned_to'); // Person sent to pay
                $table->string('status', 30)->default('pending_payment'); // pending_payment, paid, cancelled
                $table->string('priority', 20)->default('normal');
                $table->string('bill_attachment', 255)->nullable();

                // Payment disbursement details
                $table->string('payment_method', 50)->nullable(); // cash, bank_transfer, check, telebirr, other
                $table->string('payment_reference', 100)->nullable();
                $table->date('payment_date')->nullable();
                $table->decimal('paid_amount', 15, 2)->nullable();
                $table->unsignedBigInteger('paid_by')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('payment_receipt', 255)->nullable();
                $table->text('payment_notes')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'created_at']);
                $table->index('assigned_to');
                $table->index('coa_id');
                $table->index('utility_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_expenses');
    }
};
