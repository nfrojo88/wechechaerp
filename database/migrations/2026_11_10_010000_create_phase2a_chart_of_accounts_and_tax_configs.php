<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Working Capital, Tax & Accrual Accounts
        $accounts = [
            // Current Assets (12xx)
            ['code' => '1200', 'name' => 'Trade Accounts Receivable',           'type' => 'asset',     'subtype' => 'accounts_receivable', 'normal_side' => 'debit'],
            ['code' => '1210', 'name' => 'Retention Receivable from Clients',   'type' => 'asset',     'subtype' => 'retention_receivable', 'normal_side' => 'debit'],
            ['code' => '1230', 'name' => 'Input VAT Receivable (15%)',          'type' => 'asset',     'subtype' => 'tax_receivable',      'normal_side' => 'debit'],
            ['code' => '1250', 'name' => 'Inventory on Hand — Stores',          'type' => 'asset',     'subtype' => 'inventory',           'normal_side' => 'debit'],
            ['code' => '1260', 'name' => 'Construction Work-in-Progress (WIP)', 'type' => 'asset',     'subtype' => 'work_in_progress',    'normal_side' => 'debit'],
            ['code' => '1270', 'name' => 'Staff & Custodian Operating Advances','type' => 'asset',     'subtype' => 'advances',            'normal_side' => 'debit'],
            ['code' => '1280', 'name' => 'Prepaid Leases & Performance Bonds',  'type' => 'asset',     'subtype' => 'prepayments',         'normal_side' => 'debit'],
            ['code' => '1285', 'name' => 'Supplier Mobilization Advances',      'type' => 'asset',     'subtype' => 'supplier_advances',   'normal_side' => 'debit'],

            // Current Liabilities (20xx, 21xx, 22xx)
            ['code' => '2003', 'name' => 'Goods Received Not Invoiced (GRNI)',  'type' => 'liability', 'subtype' => 'grni_accrual',        'normal_side' => 'credit'],
            ['code' => '2005', 'name' => 'Subcontractor Payables',              'type' => 'liability', 'subtype' => 'subcontractor_ap',    'normal_side' => 'credit'],
            ['code' => '2006', 'name' => 'Net Salaries Payable',                'type' => 'liability', 'subtype' => 'payroll_payable',     'normal_side' => 'credit'],
            ['code' => '2110', 'name' => 'Client Advances (Contract Liabilities)','type' => 'liability','subtype' => 'contract_liability','normal_side' => 'credit'],
            ['code' => '2210', 'name' => 'Employee Income Tax (PAYE) Payable',  'type' => 'liability', 'subtype' => 'tax_payable',         'normal_side' => 'credit'],
            ['code' => '2220', 'name' => 'Pension Fund Contribution Payable',   'type' => 'liability', 'subtype' => 'pension_payable',     'normal_side' => 'credit'],
            ['code' => '2251', 'name' => 'Accrued Salaries & Wages',            'type' => 'liability', 'subtype' => 'accrued_expenses',    'normal_side' => 'credit'],
            ['code' => '2252', 'name' => 'Accrued Utilities',                   'type' => 'liability', 'subtype' => 'accrued_expenses',    'normal_side' => 'credit'],
            ['code' => '2253', 'name' => 'Accrued Bank Interest',               'type' => 'liability', 'subtype' => 'accrued_expenses',    'normal_side' => 'credit'],
            ['code' => '2254', 'name' => 'Accrued Audit & Legal Fees',          'type' => 'liability', 'subtype' => 'accrued_expenses',    'normal_side' => 'credit'],
            ['code' => '2255', 'name' => 'Accrued Subcontractor Costs',         'type' => 'liability', 'subtype' => 'accrued_expenses',    'normal_side' => 'credit'],
            ['code' => '2270', 'name' => 'Provisions (Warranty & Defects)',     'type' => 'liability', 'subtype' => 'provisions',          'normal_side' => 'credit'],
            ['code' => '2280', 'name' => 'Contract Liability (IFRS 15 Billings in Excess)', 'type' => 'liability', 'subtype' => 'contract_liability', 'normal_side' => 'credit'],
        ];

        foreach ($accounts as $acc) {
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

        // 2. Tax Rates Configuration Table
        if (!Schema::hasTable('tax_rates')) {
            Schema::create('tax_rates', function (Blueprint $table) {
                $table->id();
                $table->string('tax_code', 50)->unique();
                $table->string('name', 100);
                $table->enum('category', ['vat', 'withholding_goods', 'withholding_services']);
                $table->decimal('standard_rate_percent', 5, 2);
                $table->decimal('non_tin_rate_percent', 5, 2)->default(30.00); // Penalty rate for suppliers without TIN
                $table->decimal('threshold_amount', 18, 2)->default(0.00);    // Minimum amount before WHT triggers
                $table->enum('applies_to', ['goods', 'services', 'both'])->default('goods');
                $table->unsignedBigInteger('receivable_account_id')->nullable();
                $table->unsignedBigInteger('payable_account_id')->nullable();
                $table->date('effective_from')->default('2026-07-08');
                $table->date('effective_to')->nullable();
                $table->string('status', 30)->default('to_be_verified');
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });

            // Seed default rates per Ethiopian Tax Proclamations (pending tax advisor confirmation)
            $vatReceivable = DB::table('chart_of_accounts')->where('code', '1230')->value('id');
            $vatPayable    = DB::table('chart_of_accounts')->where('code', '2300')->value('id');
            $whtReceivable = DB::table('chart_of_accounts')->where('code', '1300')->value('id');
            $whtPayable    = DB::table('chart_of_accounts')->where('code', '2600')->value('id');

            DB::table('tax_rates')->insert([
                [
                    'tax_code'               => 'VAT_STANDARD',
                    'name'                   => 'Value Added Tax (15%)',
                    'category'               => 'vat',
                    'standard_rate_percent'  => 15.00,
                    'non_tin_rate_percent'   => 15.00,
                    'threshold_amount'       => 0.00,
                    'applies_to'             => 'both',
                    'receivable_account_id'  => $vatReceivable,
                    'payable_account_id'     => $vatPayable,
                    'effective_from'         => '2026-07-08',
                    'status'                 => 'to_be_verified',
                    'is_active'              => true,
                    'notes'                  => 'Subject to written sign-off by CFO / tax advisor',
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ],
                [
                    'tax_code'               => 'WHT_GOODS_2PCT',
                    'name'                   => 'Withholding Tax on Goods (2%)',
                    'category'               => 'withholding_goods',
                    'standard_rate_percent'  => 2.00,
                    'non_tin_rate_percent'   => 30.00,
                    'threshold_amount'       => 10000.00, // In Ethiopian law, applies to transactions > 10,000 ETB
                    'applies_to'             => 'goods',
                    'receivable_account_id'  => $whtReceivable,
                    'payable_account_id'     => $whtPayable,
                    'effective_from'         => '2026-07-08',
                    'status'                 => 'to_be_verified',
                    'is_active'              => true,
                    'notes'                  => 'Statutory 2% on supply of goods > ETB 10,000; 30% if unregistered/no TIN',
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ],
                [
                    'tax_code'               => 'WHT_SERVICES_3PCT',
                    'name'                   => 'Withholding Tax on Services (3%)',
                    'category'               => 'withholding_services',
                    'standard_rate_percent'  => 3.00,
                    'non_tin_rate_percent'   => 30.00,
                    'threshold_amount'       => 3000.00, // Applies to services > 3,000 ETB
                    'applies_to'             => 'services',
                    'receivable_account_id'  => $whtReceivable,
                    'payable_account_id'     => $whtPayable,
                    'effective_from'         => '2026-07-08',
                    'status'                 => 'to_be_verified',
                    'is_active'              => true,
                    'notes'                  => 'Statutory 3% on services > ETB 3,000; 30% if unregistered/no TIN',
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ],
            ]);
        }

        // 3. Payroll Tax Brackets Table (PAYE)
        if (!Schema::hasTable('payroll_tax_brackets')) {
            Schema::create('payroll_tax_brackets', function (Blueprint $table) {
                $table->id();
                $table->string('bracket_name', 50);
                $table->decimal('min_income', 18, 2);
                $table->decimal('max_income', 18, 2)->nullable();
                $table->decimal('tax_rate_percent', 5, 2);
                $table->decimal('deduction_amount', 18, 2)->default(0.00);
                $table->date('effective_from')->default('2026-07-08');
                $table->date('effective_to')->nullable();
                $table->string('status', 30)->default('to_be_verified'); // Marked to be verified until CFO signs
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Seed Ethiopian Personal Income Tax schedule (Proclamation 979/2016) - MARKED TO BE VERIFIED
            DB::table('payroll_tax_brackets')->insert([
                ['bracket_name' => 'Bracket 1 (0%)',   'min_income' => 0.00,    'max_income' => 600.00,   'tax_rate_percent' => 0.00,  'deduction_amount' => 0.00,    'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['bracket_name' => 'Bracket 2 (10%)',  'min_income' => 601.00,  'max_income' => 1650.00,  'tax_rate_percent' => 10.00, 'deduction_amount' => 60.00,   'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['bracket_name' => 'Bracket 3 (15%)',  'min_income' => 1651.00, 'max_income' => 3200.00,  'tax_rate_percent' => 15.00, 'deduction_amount' => 142.50,  'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['bracket_name' => 'Bracket 4 (20%)',  'min_income' => 3201.00, 'max_income' => 5250.00,  'tax_rate_percent' => 20.00, 'deduction_amount' => 302.50,  'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['bracket_name' => 'Bracket 5 (25%)',  'min_income' => 5251.00, 'max_income' => 7800.00,  'tax_rate_percent' => 25.00, 'deduction_amount' => 565.00,  'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['bracket_name' => 'Bracket 6 (30%)',  'min_income' => 7801.00, 'max_income' => 10900.00, 'tax_rate_percent' => 30.00, 'deduction_amount' => 955.00,  'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['bracket_name' => 'Bracket 7 (35%)',  'min_income' => 10901.00,'max_income' => null,     'tax_rate_percent' => 35.00, 'deduction_amount' => 1500.00, 'status' => 'to_be_verified', 'effective_from' => '2026-07-08', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // 4. Pension Agency Configuration Table
        if (!Schema::hasTable('pension_agency_configs')) {
            Schema::create('pension_agency_configs', function (Blueprint $table) {
                $table->id();
                $table->string('agency_code', 50)->unique();
                $table->string('agency_name', 150);
                $table->enum('sector', ['private', 'public'])->default('private');
                $table->decimal('employee_rate_percent', 5, 2)->default(7.00);
                $table->decimal('employer_rate_percent', 5, 2)->default(11.00);
                $table->decimal('total_rate_percent', 5, 2)->default(18.00);
                $table->string('bank_account_number', 50)->nullable();
                $table->string('bank_name', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default_target')->default(false);
                $table->timestamps();
            });

            DB::table('pension_agency_configs')->insert([
                [
                    'agency_code'           => 'POESSA',
                    'agency_name'           => 'Private Organizations Employees Social Security Agency',
                    'sector'                => 'private',
                    'employee_rate_percent' => 7.00,
                    'employer_rate_percent' => 11.00,
                    'total_rate_percent'    => 18.00,
                    'bank_account_number'   => '1000-POESSA-CBE',
                    'bank_name'             => 'Commercial Bank of Ethiopia',
                    'is_active'             => true,
                    'is_default_target'     => true,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ],
                [
                    'agency_code'           => 'PSEPA',
                    'agency_name'           => 'Public Sector Employees Pension Agency',
                    'sector'                => 'public',
                    'employee_rate_percent' => 7.00,
                    'employer_rate_percent' => 11.00,
                    'total_rate_percent'    => 18.00,
                    'bank_account_number'   => '1000-PSEPA-CBE',
                    'bank_name'             => 'Commercial Bank of Ethiopia',
                    'is_active'             => true,
                    'is_default_target'     => false,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pension_agency_configs');
        Schema::dropIfExists('payroll_tax_brackets');
        Schema::dropIfExists('tax_rates');

        $codes = [
            '1200', '1210', '1230', '1250', '1260', '1270', '1280', '1285',
            '2003', '2005', '2006', '2110', '2210', '2220', '2251', '2252',
            '2253', '2254', '2255', '2270'
        ];
        DB::table('chart_of_accounts')->whereIn('code', $codes)->delete();
    }
};
