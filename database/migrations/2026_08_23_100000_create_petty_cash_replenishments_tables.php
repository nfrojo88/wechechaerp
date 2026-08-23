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
        if (!Schema::hasTable('petty_cash_replenishments')) {
            Schema::create('petty_cash_replenishments', function (Blueprint $table) {
                $table->id();
                $table->string('request_no', 50)->unique();
                $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->decimal('requested_amount', 18, 2);
                $table->decimal('current_balance_at_request', 18, 2)->default(0);
                $table->decimal('total_expenses_amount', 18, 2)->default(0);
                $table->timestamp('period_start_date')->nullable();
                $table->timestamp('period_end_date')->nullable();
                $table->unsignedBigInteger('start_journal_line_id')->nullable();
                $table->unsignedBigInteger('end_journal_line_id')->nullable();
                $table->enum('status', ['pending', 'fulfilled', 'rejected'])->default('pending')->index();
                $table->text('notes')->nullable();
                $table->string('attachment_path')->nullable();
                
                // Fulfillment Details by Finance Head
                $table->foreignId('finance_head_id')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('fulfilled_amount', 18, 2)->nullable();
                $table->foreignId('source_coa_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->text('finance_notes')->nullable();
                $table->string('fulfillment_reference', 100)->nullable();
                $table->timestamp('fulfilled_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                
                $table->timestamps();
                $table->softDeletes();

                $table->index(['chart_of_account_id', 'status']);
            });
        }

        if (!Schema::hasTable('petty_cash_replenishment_items')) {
            Schema::create('petty_cash_replenishment_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('petty_cash_replenishment_id')->constrained('petty_cash_replenishments')->cascadeOnDelete();
                $table->unsignedBigInteger('journal_entry_line_id')->nullable();
                $table->date('entry_date')->nullable();
                $table->string('reference', 100)->nullable();
                $table->text('description')->nullable();
                $table->string('target_account_name')->nullable();
                $table->decimal('amount', 18, 2);
                $table->string('side', 20)->default('credit');
                $table->timestamps();

                $table->index('petty_cash_replenishment_id', 'pcr_items_replenish_id_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_replenishment_items');
        Schema::dropIfExists('petty_cash_replenishments');
    }
};
