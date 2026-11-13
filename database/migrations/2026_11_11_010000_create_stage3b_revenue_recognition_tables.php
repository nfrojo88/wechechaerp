<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ensure journal_entry_lines has is_installed and exclude_from_poc
        if (Schema::hasTable('journal_entry_lines')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('journal_entry_lines', 'is_installed')) {
                    $table->boolean('is_installed')->default(true)->after('description');
                }
                if (!Schema::hasColumn('journal_entry_lines', 'exclude_from_poc')) {
                    $table->boolean('exclude_from_poc')->default(false)->after('is_installed');
                }
            });
        }

        // 2. Create revenue_recognition_runs table (Audit, Idempotency & Catch-Up Tracking)
        if (!Schema::hasTable('revenue_recognition_runs')) {
            Schema::create('revenue_recognition_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('contract_id');
                $table->unsignedBigInteger('financial_period_id');
                $table->date('as_of_date');
                $table->decimal('contract_price', 18, 2);
                $table->decimal('current_budget_cost', 18, 2);
                $table->decimal('qualifying_incurred_cost', 18, 2);
                $table->decimal('uninstalled_material_cost', 18, 2)->default(0.00);
                $table->decimal('poc_percentage', 8, 4);
                $table->decimal('cumulative_revenue', 18, 2);
                $table->decimal('previously_recognised_revenue', 18, 2)->default(0.00);
                $table->decimal('catch_up_revenue', 18, 2);
                $table->decimal('cumulative_billed', 18, 2)->default(0.00);
                $table->decimal('net_asset_amount', 18, 2)->default(0.00);
                $table->decimal('net_liability_amount', 18, 2)->default(0.00);
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
                $table->boolean('is_posted')->default(false);
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->unique(['contract_id', 'financial_period_id'], 'uniq_rev_rec_contract_period');
                $table->foreign('contract_id')->references('id')->on('construction_contracts')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_recognition_runs');

        if (Schema::hasTable('journal_entry_lines')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                if (Schema::hasColumn('journal_entry_lines', 'is_installed')) {
                    $table->dropColumn('is_installed');
                }
                if (Schema::hasColumn('journal_entry_lines', 'exclude_from_poc')) {
                    $table->dropColumn('exclude_from_poc');
                }
            });
        }
    }
};
