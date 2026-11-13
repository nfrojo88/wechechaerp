<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ensure Contra-Asset Accumulated Depreciation & Depreciation Expense Accounts exist
        $contraAccounts = [
            ['code' => '1335', 'name' => 'Accumulated Depr - Dump Truck',           'type' => 'asset',   'subtype' => 'accumulated_depreciation', 'normal_side' => 'credit'],
            ['code' => '1405', 'name' => 'Accumulated Depr - Sino Truck 1',         'type' => 'asset',   'subtype' => 'accumulated_depreciation', 'normal_side' => 'credit'],
            ['code' => '1455', 'name' => 'Accumulated Depr - Sino Truck 2 & Excavator','type' => 'asset','subtype' => 'accumulated_depreciation', 'normal_side' => 'credit'],
            ['code' => '1505', 'name' => 'Accumulated Depr - Building',             'type' => 'asset',   'subtype' => 'accumulated_depreciation', 'normal_side' => 'credit'],
            ['code' => '1605', 'name' => 'Accumulated Depr - Machinery',            'type' => 'asset',   'subtype' => 'accumulated_depreciation', 'normal_side' => 'credit'],
            ['code' => '1705', 'name' => 'Accumulated Depr - Vehicle',              'type' => 'asset',   'subtype' => 'accumulated_depreciation', 'normal_side' => 'credit'],
            ['code' => '1805', 'name' => 'Accumulated Depr - Generator',            'type' => 'asset',   'subtype' => 'accumulated_depreciation', 'normal_side' => 'credit'],
            ['code' => '1905', 'name' => 'Accumulated Depr - Office Equip & Computers','type' => 'asset','subtype' => 'accumulated_depreciation', 'normal_side' => 'credit'],
            ['code' => '6850', 'name' => 'Depreciation Expense',                    'type' => 'expense', 'subtype' => 'depreciation_expense',     'normal_side' => 'debit'],
            ['code' => '6651', 'name' => 'Advance & Performance Bond Expense',       'type' => 'expense', 'subtype' => 'bond_expense',             'normal_side' => 'debit'],
        ];

        foreach ($contraAccounts as $acc) {
            $exists = DB::table('chart_of_accounts')->where('code', $acc['code'])->exists();
            if (!$exists) {
                DB::table('chart_of_accounts')->insert([
                    'code'            => $acc['code'],
                    'name'            => $acc['name'],
                    'type'            => $acc['type'],
                    'subtype'         => $acc['subtype'],
                    'normal_side'     => $acc['normal_side'],
                    'opening_balance' => 0.00,
                    'current_balance' => 0.00,
                    'is_active'       => true,
                    'is_system'       => true,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }

        // 2. Fixed Asset Register Table
        if (!Schema::hasTable('fixed_asset_register')) {
            Schema::create('fixed_asset_register', function (Blueprint $table) {
                $table->id();
                $table->string('asset_code', 50)->unique();
                $table->string('name', 255);
                $table->unsignedBigInteger('asset_account_id');
                $table->unsignedBigInteger('accumulated_depr_account_id');
                $table->unsignedBigInteger('depreciation_expense_account_id');
                $table->date('acquisition_date');
                $table->decimal('historical_cost', 18, 2);
                $table->decimal('salvage_value', 18, 2)->default(0.00);
                $table->decimal('audited_cutover_accumulated_depreciation', 18, 2)->default(0.00);
                $table->decimal('current_accumulated_depreciation', 18, 2)->default(0.00);
                $table->enum('depreciation_method', ['straight_line', 'declining_balance', 'units_of_production'])->default('straight_line');
                $table->decimal('useful_life_years', 5, 2)->nullable();
                $table->decimal('declining_rate_percent', 5, 2)->nullable();
                $table->decimal('total_expected_units', 18, 2)->nullable(); // Total rated operating hours for machinery
                $table->decimal('cumulative_units_consumed', 18, 2)->default(0.00);
                $table->unsignedBigInteger('project_id')->nullable();
                $table->unsignedBigInteger('cost_center_id')->nullable();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('asset_code', 'idx_far_code');
                $table->index('asset_account_id', 'idx_far_asset_acc');
                $table->index('accumulated_depr_account_id', 'idx_far_acc_depr_acc');
            });
        }

        // 3. Performance Bond Amortization Schedules Table (Amortize Bond Fees Only)
        if (!Schema::hasTable('prepaid_bond_amortization_schedules')) {
            Schema::create('prepaid_bond_amortization_schedules', function (Blueprint $table) {
                $table->id();
                $table->string('schedule_no', 50)->unique();
                $table->string('bond_reference', 100);
                $table->string('name', 255);
                $table->unsignedBigInteger('prepayment_account_id'); // 1280 Prepaid Leases & Performance Bonds
                $table->unsignedBigInteger('expense_account_id');    // 6651 Advance & Performance Bond Expense
                $table->decimal('total_fee_amount', 18, 2);
                $table->decimal('amortized_amount', 18, 2)->default(0.00);
                $table->decimal('monthly_amortization_amount', 18, 2);
                $table->date('start_date');
                $table->date('end_date');
                $table->integer('validity_months')->default(12);
                $table->unsignedBigInteger('project_id')->nullable();
                $table->unsignedBigInteger('cost_center_id')->nullable();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('schedule_no', 'idx_pbas_no');
                $table->index('prepayment_account_id', 'idx_pbas_prep_acc');
            });
        }

        // 4. Period-End Accruals & Auto-Reversals Tracking Table
        if (!Schema::hasTable('accrual_entries')) {
            Schema::create('accrual_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('financial_period_id');
                $table->enum('accrual_type', [
                    'payroll_unapproved',
                    'utilities',
                    'bank_interest',
                    'audit_fees',
                    'subcontractor_unbilled'
                ]);
                $table->unsignedBigInteger('journal_entry_id');
                $table->unsignedBigInteger('reversal_journal_entry_id')->nullable();
                $table->decimal('amount', 18, 2);
                $table->unsignedBigInteger('expense_account_id');
                $table->unsignedBigInteger('accrual_account_id'); // 2251, 2252, 2253, 2254, 2255
                $table->unsignedBigInteger('project_id')->nullable();
                $table->unsignedBigInteger('cost_center_id')->nullable();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->boolean('is_reversed')->default(false);
                $table->timestamp('reversed_at')->nullable();
                $table->unsignedBigInteger('reversed_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('financial_period_id', 'idx_ae_period');
                $table->index('accrual_type', 'idx_ae_type');
                $table->index('journal_entry_id', 'idx_ae_je');
                $table->index('reversal_journal_entry_id', 'idx_ae_rev_je');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accrual_entries');
        Schema::dropIfExists('prepaid_bond_amortization_schedules');
        Schema::dropIfExists('fixed_asset_register');

        $codes = ['1335', '1405', '1455', '1505', '1605', '1705', '1805', '1905', '6850', '6651'];
        DB::table('chart_of_accounts')->whereIn('code', $codes)->delete();
    }
};
