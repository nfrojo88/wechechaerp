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
        if (!Schema::hasTable('petty_cash_material_purchases')) {
            Schema::create('petty_cash_material_purchases', function (Blueprint $table) {
                $table->id();
                $table->string('purchase_no', 50)->unique();
                $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
                $table->foreignId('chart_of_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->foreignId('purchased_by')->constrained('users')->cascadeOnDelete();
                $table->date('purchase_date');
                $table->string('supplier_name', 255)->nullable();
                $table->string('receipt_no', 100)->nullable();
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->text('notes')->nullable();
                $table->string('attachment_path')->nullable();
                $table->foreignId('delivery_receipt_id')->nullable()->constrained('delivery_receipts')->nullOnDelete();
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->string('status', 30)->default('completed')->index();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['store_id', 'purchase_date']);
                $table->index(['purchased_by', 'status']);
            });
        }

        if (!Schema::hasTable('petty_cash_material_purchase_items')) {
            Schema::create('petty_cash_material_purchase_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_id')->constrained('petty_cash_material_purchases')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('quantity', 14, 3);
                $table->string('unit', 30)->default('pcs');
                $table->decimal('unit_price', 18, 2)->default(0);
                $table->decimal('total_price', 18, 2)->default(0);
                $table->string('remarks', 255)->nullable();
                $table->timestamps();

                $table->index(['purchase_id', 'product_id'], 'pcmp_items_purchase_prod_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_material_purchase_items');
        Schema::dropIfExists('petty_cash_material_purchases');
    }
};
