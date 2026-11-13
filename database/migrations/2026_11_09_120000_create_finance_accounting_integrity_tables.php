<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Establishes Financial Periods, Petty Cash Subledger, Financial Audit Trails,
     * and adds strict double-entry & immutability tracking columns to journal entries and chart of accounts.
     */
    public function up(): void
    {
        // 1. Financial Periods Table (Enforces period locks and closing checks)
        if (!Schema::hasTable('financial_periods')) {
            Schema::create('financial_periods', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100); // e.g. "2019 EFY Meskerem" or "October 2026"
                $table->string('code', 50)->unique(); // e.g. "FP-2026-10"
                $table->date('start_date');
                $table->date('end_date');
                $table->string('fiscal_year', 20)->default('2026');
                $table->boolean('is_locked')->default(false)->index();
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('locked_by')->nullable();
                $table->timestamp('reopened_at')->nullable();
                $table->unsignedBigInteger('reopened_by')->nullable();
                $table->text('reopen_reason')->nullable();
                $table->boolean('tb_balanced_at_close')->default(false);
                $table->decimal('tb_total_debit', 18, 2)->nullable();
                $table->decimal('tb_total_credit', 18, 2)->nullable();
                $table->decimal('tb_difference', 18, 2)->default(0);
                $table->text('closing_notes')->nullable();
                $table->timestamps();

                $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('reopened_by')->references('id')->on('users')->nullOnDelete();
                $table->index(['start_date', 'end_date']);
            });
        }

        // 2. Petty Cash Subledger Table (Site & Custodian Employee floats tied to 1010 control account)
        if (!Schema::hasTable('petty_cash_subledgers')) {
            Schema::create('petty_cash_subledgers', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique(); // e.g. "PC-BOLE-CASHIER"
                $table->string('name', 150);
                $table->unsignedBigInteger('control_account_id'); // Points to ChartOfAccount code 1010
                $table->unsignedBigInteger('custodian_id')->nullable(); // Employee / User responsible
                $table->unsignedBigInteger('project_id')->nullable(); // Site / Project
                $table->unsignedBigInteger('store_id')->nullable(); // Site Store
                $table->decimal('imprest_limit', 18, 2)->default(0); // Approved float ceiling
                $table->decimal('opening_balance', 18, 2)->default(0);
                $table->decimal('current_balance', 18, 2)->default(0); // Unsurrendered advance balance
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('control_account_id')->references('id')->on('chart_of_accounts')->cascadeOnDelete();
                $table->foreign('custodian_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
                $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            });
        }

        // 3. Financial Audit Trails (Immutable log of financial lifecycle actions)
        if (!Schema::hasTable('financial_audit_trails')) {
            Schema::create('financial_audit_trails', function (Blueprint $table) {
                $table->id();
                $table->string('auditable_type', 100);
                $table->unsignedBigInteger('auditable_id');
                $table->string('action', 50); // created, posted, reversed, locked, reopened, adjusted
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_role', 50)->nullable();
                $table->text('reason')->nullable();
                $table->json('payload')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['auditable_type', 'auditable_id']);
                $table->index('action');
            });
        }

        // 4. Enhance journal_entries with locking, immutability, and reversal links
        Schema::table('journal_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('journal_entries', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('status')->index();
            }
            if (!Schema::hasColumn('journal_entries', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('is_locked');
            }
            if (!Schema::hasColumn('journal_entries', 'locked_by')) {
                $table->unsignedBigInteger('locked_by')->nullable()->after('locked_at');
                $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('journal_entries', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable()->after('locked_by');
            }
            if (!Schema::hasColumn('journal_entries', 'reopened_by')) {
                $table->unsignedBigInteger('reopened_by')->nullable()->after('reopened_at');
                $table->foreign('reopened_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('journal_entries', 'reopen_reason')) {
                $table->text('reopen_reason')->nullable()->after('reopened_by');
            }
            if (!Schema::hasColumn('journal_entries', 'is_reversal')) {
                $table->boolean('is_reversal')->default(false)->after('reopen_reason')->index();
            }
            if (!Schema::hasColumn('journal_entries', 'reversal_of_id')) {
                $table->unsignedBigInteger('reversal_of_id')->nullable()->after('is_reversal');
                $table->foreign('reversal_of_id')->references('id')->on('journal_entries')->nullOnDelete();
            }
            if (!Schema::hasColumn('journal_entries', 'reversed_by_id')) {
                $table->unsignedBigInteger('reversed_by_id')->nullable()->after('reversal_of_id');
                $table->foreign('reversed_by_id')->references('id')->on('journal_entries')->nullOnDelete();
            }
        });

        // 5. Enhance chart_of_accounts with control account & normal side definitions
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('chart_of_accounts', 'normal_side')) {
                $table->enum('normal_side', ['debit', 'credit'])->default('debit')->after('type');
            }
            if (!Schema::hasColumn('chart_of_accounts', 'is_control_account')) {
                $table->boolean('is_control_account')->default(false)->after('is_system');
            }
            if (!Schema::hasColumn('chart_of_accounts', 'subledger_type')) {
                $table->string('subledger_type', 50)->nullable()->after('is_control_account');
            }
        });

        // Set default normal side based on standard accounting principles
        DB::table('chart_of_accounts')
            ->whereIn('type', ['asset', 'expense'])
            ->update(['normal_side' => 'debit']);

        DB::table('chart_of_accounts')
            ->whereIn('type', ['liability', 'equity', 'revenue'])
            ->update(['normal_side' => 'credit']);

        // Mark 1010 as a control account for Petty Cash subledgers
        DB::table('chart_of_accounts')
            ->where('code', '1010')
            ->update([
                'is_control_account' => true,
                'subledger_type'     => 'petty_cash',
                'normal_side'        => 'debit',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Remove columns from chart_of_accounts
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('chart_of_accounts', 'subledger_type')) {
                $table->dropColumn('subledger_type');
            }
            if (Schema::hasColumn('chart_of_accounts', 'is_control_account')) {
                $table->dropColumn('is_control_account');
            }
            if (Schema::hasColumn('chart_of_accounts', 'normal_side')) {
                $table->dropColumn('normal_side');
            }
        });

        // 2. Remove columns from journal_entries
        Schema::table('journal_entries', function (Blueprint $table) {
            $foreignKeysToDrop = ['locked_by', 'reopened_by', 'reversal_of_id', 'reversed_by_id'];
            foreach ($foreignKeysToDrop as $col) {
                if (Schema::hasColumn('journal_entries', $col)) {
                    try {
                        $table->dropForeign(["{$col}"]);
                    } catch (\Throwable $e) {}
                }
            }

            $columnsToDrop = [
                'is_locked', 'locked_at', 'locked_by',
                'reopened_at', 'reopened_by', 'reopen_reason',
                'is_reversal', 'reversal_of_id', 'reversed_by_id'
            ];
            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('journal_entries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // 3. Drop created tables in reverse dependency order
        Schema::dropIfExists('financial_audit_trails');
        Schema::dropIfExists('petty_cash_subledgers');
        Schema::dropIfExists('financial_periods');
    }
};
