<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create client_ipcs table if not exists or ensure required Stage 3C columns
        if (!Schema::hasTable('client_ipcs')) {
            Schema::create('client_ipcs', function (Blueprint $table) {
                $table->id();
                $table->string('ipc_no', 50)->unique();
                $table->unsignedBigInteger('contract_id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('financial_period_id');
                $table->integer('ipc_number')->default(1);
                $table->date('period_from');
                $table->date('period_to');
                $table->date('submission_date')->nullable();
                $table->json('measurement_sheet_summary')->nullable();
                $table->enum('certification_status', ['draft', 'submitted', 'under_review', 'certified', 'approved', 'rejected'])
                      ->default('draft')->index();
                $table->unsignedBigInteger('certified_by')->nullable();
                $table->timestamp('certified_at')->nullable();
                $table->decimal('gross_certified_amount', 18, 2)->default(0.00);
                $table->decimal('retention_rate_percent', 5, 2)->default(5.00);
                $table->decimal('retention_amount', 18, 2)->default(0.00);
                $table->decimal('advance_recovery_rate_percent', 5, 2)->default(20.00);
                $table->decimal('advance_recovery_amount', 18, 2)->default(0.00);
                $table->string('vat_base_type', 50)->default('net_certified');
                $table->string('vat_base_status', 30)->default('to_be_verified');
                $table->decimal('vat_rate_percent', 5, 2)->default(15.00);
                $table->decimal('vat_amount', 18, 2)->default(0.00);
                $table->decimal('net_certified_payable', 18, 2)->default(0.00);
                $table->decimal('net_payable_amount', 18, 2)->default(0.00);
                $table->string('status', 30)->default('draft');
                $table->text('notes')->nullable();
                $table->boolean('is_posted')->default(false);
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['contract_id', 'ipc_number'], 'uniq_ipc_contract_number');
                $table->foreign('contract_id')->references('id')->on('construction_contracts')->cascadeOnDelete();
            });
        } else {
            Schema::table('client_ipcs', function (Blueprint $table) {
                if (!Schema::hasColumn('client_ipcs', 'contract_id')) {
                    $table->unsignedBigInteger('contract_id')->nullable()->after('ipc_no');
                }
                if (!Schema::hasColumn('client_ipcs', 'financial_period_id')) {
                    $table->unsignedBigInteger('financial_period_id')->nullable()->after('project_id');
                }
                if (!Schema::hasColumn('client_ipcs', 'measurement_sheet_summary')) {
                    $table->json('measurement_sheet_summary')->nullable()->after('submission_date');
                }
                if (!Schema::hasColumn('client_ipcs', 'certification_status')) {
                    $table->string('certification_status', 30)->default('draft')->after('measurement_sheet_summary');
                }
                if (!Schema::hasColumn('client_ipcs', 'certified_by')) {
                    $table->unsignedBigInteger('certified_by')->nullable()->after('certification_status');
                }
                if (!Schema::hasColumn('client_ipcs', 'certified_at')) {
                    $table->timestamp('certified_at')->nullable()->after('certified_by');
                }
                if (!Schema::hasColumn('client_ipcs', 'gross_certified_amount')) {
                    $table->decimal('gross_certified_amount', 18, 2)->default(0.00)->after('certified_at');
                }
                if (!Schema::hasColumn('client_ipcs', 'advance_recovery_rate_percent')) {
                    $table->decimal('advance_recovery_rate_percent', 5, 2)->default(20.00)->after('retention_amount');
                }
                if (!Schema::hasColumn('client_ipcs', 'advance_recovery_amount')) {
                    $table->decimal('advance_recovery_amount', 18, 2)->default(0.00)->after('advance_recovery_rate_percent');
                }
                if (!Schema::hasColumn('client_ipcs', 'vat_base_type')) {
                    $table->string('vat_base_type', 50)->default('net_certified')->after('advance_recovery_amount');
                }
                if (!Schema::hasColumn('client_ipcs', 'vat_base_status')) {
                    $table->string('vat_base_status', 30)->default('to_be_verified')->after('vat_base_type');
                }
                if (!Schema::hasColumn('client_ipcs', 'vat_rate_percent')) {
                    $table->decimal('vat_rate_percent', 5, 2)->default(15.00)->after('vat_base_status');
                }
                if (!Schema::hasColumn('client_ipcs', 'vat_amount')) {
                    $table->decimal('vat_amount', 18, 2)->default(0.00)->after('vat_rate_percent');
                }
                if (!Schema::hasColumn('client_ipcs', 'net_certified_payable')) {
                    $table->decimal('net_certified_payable', 18, 2)->default(0.00)->after('vat_amount');
                }
                if (!Schema::hasColumn('client_ipcs', 'is_posted')) {
                    $table->boolean('is_posted')->default(false)->after('net_certified_payable');
                }
                if (!Schema::hasColumn('client_ipcs', 'journal_entry_id')) {
                    $table->unsignedBigInteger('journal_entry_id')->nullable()->index()->after('is_posted');
                }
            });
        }

        // 2. Create client_ipc_collections table (Receipt of IPC Payment with Client WHT Deduction)
        if (!Schema::hasTable('client_ipc_collections')) {
            Schema::create('client_ipc_collections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_ipc_id');
                $table->unsignedBigInteger('bank_account_id');
                $table->date('collection_date');
                $table->decimal('gross_collected_amount', 18, 2);
                $table->decimal('client_wht_rate_percent', 5, 2)->default(2.00);
                $table->decimal('client_wht_amount', 18, 2)->default(0.00);
                $table->string('wht_receipt_number', 100);
                $table->decimal('net_bank_amount', 18, 2);
                $table->boolean('is_posted')->default(false);
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->foreign('client_ipc_id')->references('id')->on('client_ipcs')->cascadeOnDelete();
            });
        }

        // 3. Create contract_retention_releases table (Retention Release Governance)
        if (!Schema::hasTable('contract_retention_releases')) {
            Schema::create('contract_retention_releases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('contract_id');
                $table->enum('release_stage', ['substantial_completion', 'final_completion']);
                $table->decimal('release_amount', 18, 2);
                $table->date('release_date');
                $table->string('certificate_reference', 100)->nullable();
                $table->boolean('is_posted')->default(false);
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->foreign('contract_id')->references('id')->on('construction_contracts')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_retention_releases');
        Schema::dropIfExists('client_ipc_collections');
        Schema::dropIfExists('client_ipcs');
    }
};