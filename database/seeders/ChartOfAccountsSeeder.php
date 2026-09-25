<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Map old category names → new system type values.
     */
    private function mapType(string $category, string $nature): string
    {
        $cat = strtolower(trim($category));

        if (str_contains($cat, 'cash') || str_contains($cat, 'bank'))        return 'asset';
        if (str_contains($cat, 'receivable') || str_contains($cat, 'current asset')) return 'asset';
        if (str_contains($cat, 'fixed asset'))                                return 'asset';
        if (str_contains($cat, 'liabilit') || str_contains($cat, 'payable')) return 'liability';
        if (str_contains($cat, 'equity') || str_contains($cat, 'capital'))   return 'equity';
        if (str_contains($cat, 'income') || str_contains($cat, 'revenue'))   return 'revenue';
        if (str_contains($cat, 'cost'))                                       return 'expense';
        if (str_contains($cat, 'expens') || str_contains($cat, 'expenss'))   return 'expense';

        // Fallback by debit/credit nature
        return strtolower($nature) === 'credit' ? 'liability' : 'asset';
    }

    public function run(): void
    {
        // Raw data extracted from chart_of_accounts.sql
        $rows = [
            ['code' => '1011', 'name' => 'CBE (Commercial Bank of Ethiopia)',    'category' => 'Cash and Bank',             'balance' => 1354000.00,   'nature' => 'DEBIT'],
            ['code' => '1012', 'name' => 'Bank of Abyssinia-27',                 'category' => 'Cash and Bank',             'balance' => -2678645.00,  'nature' => 'DEBIT'],
            ['code' => '1013', 'name' => 'Bank of Abyssinia-59',                 'category' => 'Cash and Bank',             'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '2001', 'name' => 'Accounts Payable',                     'category' => 'Liabilities',               'balance' => -1087039.34,  'nature' => 'CREDIT'],
            ['code' => '2002', 'name' => 'Short-term Loans',                     'category' => 'Liabilities',               'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '2003', 'name' => 'Accrued Expenses',                     'category' => 'Liabilities',               'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '1010', 'name' => 'Petty Cash',                           'category' => 'Cash and Bank',             'balance' => -742800.00,   'nature' => 'DEBIT'],
            ['code' => '1100', 'name' => 'Saving Addis Capital',                 'category' => 'Cash and Bank',             'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '1330', 'name' => 'Dump Truck',                           'category' => 'Fixed Assets',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '1350', 'name' => 'Cash Register',                        'category' => 'Fixed Assets',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '1400', 'name' => 'Sino Truck 1',                         'category' => 'Fixed Assets',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '1450', 'name' => 'Sino Truck 2',                         'category' => 'Fixed Assets',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '1451', 'name' => 'Wheel Excavator',                      'category' => 'Fixed Assets',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '1500', 'name' => 'Building',                             'category' => 'Fixed Assets',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '1550', 'name' => 'Transformer',                          'category' => 'Fixed Assets',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '1600', 'name' => 'Machinery',                            'category' => 'Fixed Assets',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '1700', 'name' => 'Vehicle',                              'category' => 'Fixed Assets',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '1800', 'name' => 'Generator',                            'category' => 'Fixed Assets',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '1900', 'name' => 'Computer & Accessories',               'category' => 'Fixed Assets',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '1901', 'name' => 'Office Equipment',                     'category' => 'Fixed Assets',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '1902', 'name' => 'Roto',                                 'category' => 'Fixed Assets',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '2100', 'name' => 'Unearned Revenue',                     'category' => 'Other current liablity',    'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '2200', 'name' => 'Profit Tax Payable',                   'category' => 'Other current liablity',    'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '2250', 'name' => 'Accrued Payable',                      'category' => 'Accounts Payable',          'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '6980', 'name' => 'Bid Bond Expense',                     'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6975', 'name' => 'Material Testing Expense',             'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6970', 'name' => 'Licence & Related Expense',            'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6965', 'name' => 'Bank Commission Expense',              'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6960', 'name' => 'Security Expense',                     'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6950', 'name' => 'Penalty & Interest Expense',           'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6940', 'name' => 'Audit Fee',                            'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6930', 'name' => 'Copy, Printing & Internet Expense',    'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6925', 'name' => 'Afer Geber',                           'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6920', 'name' => 'Lease Expense',                        'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6910', 'name' => "Rebate Expense (Gov't)",               'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6901', 'name' => 'Supply Expense',                       'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6900', 'name' => 'Panel & Machine Rent Expense',         'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6850', 'name' => 'Depreciation Expense',                 'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6800', 'name' => 'Miscellaneous Expense',                'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6750', 'name' => 'Fuel Expense',                         'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6720', 'name' => 'Tyre Expense',                         'category' => 'Expenss',                   'balance' => 169043.44,    'nature' => 'DEBIT'],
            ['code' => '6700', 'name' => 'R/Maintenance Expense',                'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6650', 'name' => 'Insurance Expense',                    'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6651', 'name' => 'Advance & Performance Bond Expense',   'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6600', 'name' => 'Interest Expense',                     'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6550', 'name' => 'Service Charge Expense',               'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6540', 'name' => 'Stamp Duty',                           'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6500', 'name' => 'Stationery Expense',                   'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6450', 'name' => 'Audit Expense',                        'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6420', 'name' => 'Loading/Unloading Expense',            'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6415', 'name' => 'Labour',                               'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6410', 'name' => 'Telephone & WiFi Expense',             'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6405', 'name' => 'Hidase Bond',                          'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6400', 'name' => 'Utility Expense',                      'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6350', 'name' => 'Transport Allowance',                  'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6300', 'name' => 'Pension Expense',                      'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6250', 'name' => 'Rent Expense',                         'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6230', 'name' => 'Staff Loan',                           'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6200', 'name' => 'Allowance Expense',                    'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6100', 'name' => 'Salary & Wage Expense',                'category' => 'Expenss',                   'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '6000', 'name' => 'Operating Expenses',                   'category' => 'Expenss',                   'balance' => 124800.00,    'nature' => 'DEBIT'],
            ['code' => '5400', 'name' => 'Machine Rent Cost',                    'category' => 'Cost of sale',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '5350', 'name' => 'Transportation Cost',                  'category' => 'Cost of sale',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '5300', 'name' => 'Cost of Fuel',                         'category' => 'Cost of sale',              'balance' => 137984.00,    'nature' => 'DEBIT'],
            ['code' => '5250', 'name' => 'Cylinder Gas',                         'category' => 'Cost of sale',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '5200', 'name' => 'Cost of Labor',                        'category' => 'Cost of sale',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '5150', 'name' => 'Sub Contract',                         'category' => 'Cost of sale',              'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '5100', 'name' => 'Cost of Material',                     'category' => 'Cost of sale',              'balance' => 7899243.48,   'nature' => 'DEBIT'],
            ['code' => '4400', 'name' => 'Other Income',                         'category' => 'Income',                    'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '4301', 'name' => 'Rent Income',                          'category' => 'Income',                    'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '4300', 'name' => 'Gain/Loss of Fixed Asset',             'category' => 'Income',                    'balance' => 200000.00,    'nature' => 'CREDIT'],
            ['code' => '4200', 'name' => 'Machine Rent Income',                  'category' => 'Income',                    'balance' => 2200000.00,   'nature' => 'CREDIT'],
            ['code' => '4100', 'name' => 'Construction Income',                  'category' => 'Income',                    'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '3150', 'name' => 'Legal Reserve',                        'category' => 'equity dose not closed',    'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '3100', 'name' => 'Registered Capital',                   'category' => 'equity dose not closed',    'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '3154', 'name' => "Member's Draw",                        'category' => 'equity dose not closed',    'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '3153', 'name' => "Member's Contribution",                'category' => 'equity dose not closed',    'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '3152', 'name' => 'Beginning Equity',                     'category' => 'equity-retend earning',     'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '3151', 'name' => 'Beginning Balance Equity',             'category' => 'equity dose not closed',    'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '3149', 'name' => 'Beginning Balance Equity (2)',          'category' => 'equity dose not closed',    'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '2900', 'name' => 'Advance 1/4',                          'category' => 'Receivables',               'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '2802', 'name' => 'Advance Purchase - Tseday',            'category' => 'Receivables',               'balance' => 0.00,         'nature' => 'DEBIT'],
            ['code' => '2700', 'name' => 'Advance Payment',                      'category' => 'Accounts Payable',          'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '2600', 'name' => 'WHT Payable',                          'category' => 'Other current liablity',    'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '2500', 'name' => 'Abyssinia Loan',                       'category' => 'Accounts Payable',          'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '2400', 'name' => 'Oromiya Bank Loan (ODYU)',             'category' => 'Accounts Payable',          'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '2350', 'name' => 'Account Payable',                      'category' => 'Accounts Payable',          'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '2300', 'name' => 'VAT Payable',                          'category' => 'Other current liablity',    'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '2260', 'name' => 'Oromia Bank Loan',                     'category' => 'Accounts Payable',          'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '2000', 'name' => 'Addis Bank Loan',                      'category' => 'Accounts Payable',          'balance' => 0.00,         'nature' => 'CREDIT'],
            ['code' => '1300', 'name' => 'Withholding Tax Receivable',           'category' => 'Current Asset',             'balance' => -36000.00,    'nature' => 'DEBIT'],
        ];

        $now = now();
        $inserted = 0;
        $skipped  = 0;

        foreach ($rows as $row) {
            // Skip if code already exists
            if (DB::table('chart_of_accounts')->where('code', $row['code'])->exists()) {
                $skipped++;
                continue;
            }

            $type    = $this->mapType($row['category'], $row['nature']);
            $balance = (float) $row['balance'];

            DB::table('chart_of_accounts')->insert([
                'code'            => $row['code'],
                'name'            => $row['name'],
                'type'            => $type,
                'subtype'         => strtolower(str_replace(' ', '_', $row['category'])),
                'description'     => $row['category'],
                'opening_balance' => $balance,
                'current_balance' => $balance,
                'is_active'       => true,
                'is_system'       => false,
                'sort_order'      => (int) $row['code'],
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
            $inserted++;
        }

        $this->command->info("✅ Chart of Accounts seeded: {$inserted} inserted, {$skipped} skipped (already exist).");
    }
}
