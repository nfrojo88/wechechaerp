<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add contract_id to journal_entry_lines
        if (Schema::hasTable('journal_entry_lines') && !Schema::hasColumn('journal_entry_lines', 'contract_id')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $table->unsignedBigInteger('contract_id')->nullable()->after('project_id');
                $table->index('contract_id', 'idx_jel_contract');
            });
        }

        // 2. Create construction_contracts table (Contract Master)
        if (!Schema::hasTable('construction_contracts')) {
            Schema::create('construction_contracts', function (Blueprint $table) {
                $table->id();
                $table->string('contract_number', 50)->unique();
                $table->string('title', 200);
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedBigInteger('cost_center_id')->nullable()->index();
                $table->decimal('contract_value_exc_vat', 18, 2);
                $table->decimal('vat_rate_percent', 5, 2)->default(15.00);
                $table->decimal('retention_rate_percent', 5, 2)->default(5.00);
                $table->decimal('retention_cap_amount', 18, 2)->nullable();
                $table->decimal('advance_payment_amount', 18, 2)->default(0.00);
                $table->decimal('advance_recovery_rate_percent', 5, 2)->default(20.00);
                
                // Configurable VAT base with NO default: 'net_certified', 'full_certified', 'certified_less_advance_only'
                $table->string('vat_base_type', 50)->nullable();
                $table->string('vat_base_status', 30)->default('to_be_verified');
                
                $table->decimal('original_budget_cost', 18, 2)->default(0.00);
                $table->decimal('current_budget_cost', 18, 2)->default(0.00);
                $table->decimal('estimate_to_complete', 18, 2)->default(0.00);
                $table->string('status', 30)->default('active')->index();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->date('defect_liability_end_date')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // 3. Create contract_budget_revisions table (ETC & Variation Governance)
        if (!Schema::hasTable('contract_budget_revisions')) {
            Schema::create('contract_budget_revisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('contract_id')->index();
                $table->string('revision_no', 30);
                $table->string('revision_type', 50); // 'cost_budget_etc', 'scope_variation', 'price_escalation', 'claims'
                $table->decimal('previous_budget_cost', 18, 2);
                $table->decimal('revised_budget_cost', 18, 2);
                $table->decimal('previous_contract_value', 18, 2);
                $table->decimal('revised_contract_value', 18, 2);
                $table->text('justification');
                $table->boolean('is_approved')->default(false)->index();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->foreign('contract_id')
                      ->references('id')->on('construction_contracts')
                      ->cascadeOnDelete();
            });
        }

        // 4. Add contract_id and uninstalled material exclusion flags to material requisition items
        if (Schema::hasTable('material_request_items')) {
            Schema::table('material_request_items', function (Blueprint $table) {
                if (!Schema::hasColumn('material_request_items', 'contract_id')) {
                    $table->unsignedBigInteger('contract_id')->nullable()->index();
                }
                if (!Schema::hasColumn('material_request_items', 'is_installed')) {
                    $table->boolean('is_installed')->default(true);
                }
                if (!Schema::hasColumn('material_request_items', 'exclude_from_poc')) {
                    $table->boolean('exclude_from_poc')->default(false);
                }
            });
        }

        // 5. Ensure Phase 3 authoritative Chart of Accounts exist
        $chartAccounts = [
            ['code' => '1210', 'name' => 'Contract Asset (IFRS 15 Unbilled Revenue)',       'type' => 'asset',     'subtype' => 'current_asset',             'normal_side' => 'debit'],
            ['code' => '1215', 'name' => 'Client Retention Receivable',                      'type' => 'asset',     'subtype' => 'receivables',               'normal_side' => 'debit'],
            ['code' => '1260', 'name' => 'Construction Work-in-Progress (WIP)',             'type' => 'asset',     'subtype' => 'current_asset',             'normal_side' => 'debit'],
            ['code' => '2015', 'name' => 'Subcontractor Retention Payable',                  'type' => 'liability', 'subtype' => 'accounts_payable',          'normal_side' => 'credit'],
            ['code' => '2110', 'name' => 'Client Advance Payment Liability',                 'type' => 'liability', 'subtype' => 'other_current_liability',   'normal_side' => 'credit'],
            ['code' => '2280', 'name' => 'Contract Liability (IFRS 15 Billings in Excess)',  'type' => 'liability', 'subtype' => 'other_current_liability',   'normal_side' => 'credit'],
            ['code' => '2290', 'name' => 'Provision for Onerous Contracts (IAS 37)',        'type' => 'liability', 'subtype' => 'other_current_liability',   'normal_side' => 'credit'],
            ['code' => '5010', 'name' => 'Construction Cost of Sales',                       'type' => 'expense',   'subtype' => 'cost_of_sale',              'normal_side' => 'debit'],
            ['code' => '5015', 'name' => 'Provision for Onerous Contracts Expense',         'type' => 'expense',   'subtype' => 'cost_of_sale',              'normal_side' => 'debit'],
        ];

        foreach ($chartAccounts as $acc) {
            $existing = DB::table('chart_of_accounts')->where('code', $acc['code'])->first();
            if ($existing) {
                DB::table('chart_of_accounts')->where('id', $existing->id)->update([
                    'name'        => $acc['name'],
                    'type'        => $acc['type'],
                    'subtype'     => $acc['subtype'],
                    'normal_side' => $acc['normal_side'],
                    'updated_at'  => now(),
                ]);
            } else {
                DB::table('chart_of_accounts')->insert(array_merge($acc, [
                    'opening_balance' => 0.00,
                    'current_balance' => 0.00,
                    'is_active'       => true,
                    'is_system'       => true,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_budget_revisions');
        Schema::dropIfExists('construction_contracts');

        if (Schema::hasTable('journal_entry_lines') && Schema::hasColumn('journal_entry_lines', 'contract_id')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $table->dropIndex('idx_jel_contract');
                $table->dropColumn('contract_id');
            });
        }

        if (Schema::hasTable('material_request_items')) {
            Schema::table('material_request_items', function (Blueprint $table) {
                if (Schema::hasColumn('material_request_items', 'contract_id')) {
                    $table->dropColumn('contract_id');
                }
                if (Schema::hasColumn('material_request_items', 'is_installed')) {
                    $table->dropColumn('is_installed');
                }
                if (Schema::hasColumn('material_request_items', 'exclude_from_poc')) {
                    $table->dropColumn('exclude_from_poc');
                }
            });
        }
    }
};
