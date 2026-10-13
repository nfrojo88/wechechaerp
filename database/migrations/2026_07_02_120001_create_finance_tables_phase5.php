<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFinanceTablesPhase5 extends Migration
{
    public function up()
    {
        // Drop tables if they exist to handle failed previous migrations
        Schema::dropIfExists('emergency_funds');
        Schema::dropIfExists('project_budgets');
        Schema::dropIfExists('income_records');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('chart_of_accounts');
        // Chart of Accounts
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense'])->index();
            $table->string('subtype', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['type', 'is_active']);
        });

        // Bank Accounts (needed before expenses/income)
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('account_name');
            $table->string('account_number', 50)->unique();
            $table->string('bank_name');
            $table->string('branch')->nullable();
            $table->string('account_type', 20)->default('checking');
            $table->string('currency', 3)->default('ETB');
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->foreignId('coa_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Journal Entries
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('entry_no', 50)->unique();
            $table->date('entry_date');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description');
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
            $table->index('entry_date');
        });

        // Journal Entry Lines
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->enum('side', ['debit', 'credit'])->index();
            $table->decimal('amount', 18, 2);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['journal_entry_id', 'side']);
        });

        // Bank Transactions
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->enum('type', ['deposit', 'withdrawal', 'transfer', 'fee', 'interest'])->index();
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->string('reference_no', 100)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_reconciled')->default(false);
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
        });

        // Income Records
        Schema::create('income_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('income_no', 50)->unique();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->enum('category', ['project_payment', 'advance', 'other'])->index();
            $table->date('income_date');
            $table->decimal('amount', 18, 2);
            $table->string('payment_method', 20)->default('bank');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'confirmed', 'reconciled', 'cancelled'])->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Project Budgets
        Schema::create('project_budgets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('category', 50);
            $table->decimal('budgeted_amount', 18, 2)->default(0);
            $table->decimal('committed_amount', 18, 2)->default(0);
            $table->decimal('actual_amount', 18, 2)->default(0);
            $table->decimal('remaining_amount', 18, 2)->storedAs('budgeted_amount - actual_amount');
            $table->decimal('variance', 18, 2)->storedAs('budgeted_amount - actual_amount');
            $table->string('period_type', 10)->default('total');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'category', 'period_type', 'period_start'], 'proj_budg_cat_per_unique');
        });

        // Emergency Funds
        Schema::create('emergency_funds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->decimal('requested_amount', 18, 2);
            $table->text('justification');
            $table->enum('status', ['pending', 'approved', 'rejected', 'utilized'])->default('pending')->index();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('emergency_funds');
        Schema::dropIfExists('project_budgets');
        Schema::dropIfExists('income_records');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('chart_of_accounts');
    }
}
