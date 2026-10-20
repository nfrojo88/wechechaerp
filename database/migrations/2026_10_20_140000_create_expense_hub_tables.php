<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExpenseHubTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. VAT Decisions table
        if (!Schema::hasTable('expense_hub_vat_decisions')) {
            Schema::create('expense_hub_vat_decisions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('source_type', 60)->index();
                $table->unsignedBigInteger('source_id')->index();
                $table->string('vat_status', 30)->default('pending_decision')->index(); // vat_applies, no_vat, pending_decision
                $table->decimal('vat_rate', 5, 2)->default(15.00);
                $table->boolean('is_inclusive')->default(true);
                $table->decimal('base_amount', 18, 2)->default(0.00);
                $table->decimal('vat_amount', 18, 2)->default(0.00);
                $table->decimal('total_amount', 18, 2)->default(0.00);
                $table->string('no_vat_reason_code', 50)->nullable()->index();
                $table->text('no_vat_reason_note')->nullable();
                $table->string('vat_category', 5)->default('G'); // G = Goods, S = Services
                $table->string('calendar_type', 5)->default('E'); // E = Ethiopian, G = Gregorian
                $table->tinyInteger('purchase_type')->default(3); // 1 = Capital local, 2 = Capital imp, 3 = Local inputs, 4 = Imp inputs, 5 = Overhead, 6 = Exempt
                $table->string('custom_seller_tin', 30)->nullable();
                $table->string('custom_fs_no', 50)->nullable();
                $table->string('custom_mrc_no', 50)->nullable();
                $table->string('custom_item_description', 255)->nullable();
                $table->string('uom', 20)->default('9');
                $table->decimal('quantity', 12, 2)->default(1.00);
                $table->decimal('unit_price', 18, 2)->default(0.00);
                $table->boolean('is_locked')->default(false)->index();
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->unsignedBigInteger('decided_by')->nullable()->index();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();

                $table->unique(['source_type', 'source_id'], 'expense_hub_source_unique');
                $table->index(['source_type', 'vat_status'], 'idx_hub_source_status');
            });
        }

        // 2. Full Audit Log table
        if (!Schema::hasTable('expense_hub_vat_audit_logs')) {
            Schema::create('expense_hub_vat_audit_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('decision_id')->nullable()->index();
                $table->string('source_type', 60)->index();
                $table->unsignedBigInteger('source_id')->index();
                $table->string('action', 50)->index(); // created, status_changed, rate_adjusted, bulk_updated, month_locked, month_unlocked
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->unsignedBigInteger('performed_by')->nullable()->index();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 3. Month Locks table (Closed Month Governance)
        if (!Schema::hasTable('expense_hub_month_locks')) {
            Schema::create('expense_hub_month_locks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->integer('year');
                $table->integer('month');
                $table->string('calendar', 10)->default('EC'); // EC or GC
                $table->boolean('is_locked')->default(true);
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('unlocked_by')->nullable();
                $table->timestamp('unlocked_at')->nullable();
                $table->text('unlock_reason')->nullable();
                $table->timestamps();

                $table->unique(['year', 'month', 'calendar'], 'expense_hub_month_lock_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_hub_month_locks');
        Schema::dropIfExists('expense_hub_vat_audit_logs');
        Schema::dropIfExists('expense_hub_vat_decisions');
    }
}
