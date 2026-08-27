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
        Schema::create('office_material_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('office_purpose')->nullable(); // e.g. Stationery, Printing, Pantry, etc.
            $table->text('justification')->nullable();
            $table->date('required_date')->nullable();
            $table->string('urgency')->default('normal'); // normal, urgent, emergency
            $table->string('attachment')->nullable();

            // Status: pending_hr, approved_by_hr, assigned_to_finance, paid, rejected
            $table->string('status')->default('pending_hr');

            // Step 2: HR / Coordinator Review & Money Addition
            $table->decimal('amount', 14, 2)->nullable(); // Added by HR during approval
            $table->foreignId('hr_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hr_reviewed_at')->nullable();
            $table->text('hr_notes')->nullable();

            // Step 3: Finance Head Assignment
            $table->foreignId('finance_head_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('coa_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('assigned_finance_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finance_assigned_at')->nullable();
            $table->text('finance_head_notes')->nullable();

            // Step 4: Finance Staff / Cashier Payment
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('payment_notes')->nullable();

            // Rejection
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });

        Schema::create('office_material_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_material_request_id')->constrained('office_material_requests')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('item_name');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit')->default('pcs');
            $table->text('specifications')->nullable();
            $table->decimal('estimated_unit_price', 14, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('office_material_request_items');
        Schema::dropIfExists('office_material_requests');
    }
};
