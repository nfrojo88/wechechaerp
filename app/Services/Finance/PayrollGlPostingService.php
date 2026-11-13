<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\Finance\JournalEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayrollGlPostingService
{
    protected JournalEngine $journalEngine;

    public function __construct(?JournalEngine $journalEngine = null)
    {
        $this->journalEngine = $journalEngine ?: new JournalEngine();
    }

    /**
     * Compute PAYE tax for a given taxable gross salary using active tax brackets.
     */
    public function computePaye(float $grossSalary): float
    {
        if ($grossSalary <= 600.00) {
            return 0.00;
        }

        $bracket = DB::table('payroll_tax_brackets')
            ->where('is_active', true)
            ->where('min_income', '<=', $grossSalary)
            ->where(function ($q) use ($grossSalary) {
                $q->whereNull('max_income')
                  ->orWhere('max_income', '>=', $grossSalary);
            })
            ->first();

        if (!$bracket) {
            return 0.00;
        }

        $tax = ($grossSalary * ((float)$bracket->tax_rate_percent / 100)) - (float)$bracket->deduction_amount;
        return max(0.00, round($tax, 2));
    }

    /**
     * Post authoritative Payroll journal into the General Ledger.
     * Prevents overlapping with unreversed salary accruals.
     *
     * DR 6100 Gross Wages
     * DR 6300 Employer Pension (11%)
     * CR 2210 Employee PAYE
     * CR 2220 Pension Fund Contribution (18%)
     * CR 2006 Net Salaries Payable
     */
    public function postPayroll(
        string $payrollPeriod,
        float $grossSalaries,
        ?float $employeePaye = null,
        ?float $employeePension = null,
        ?float $employerPension = null,
        ?string $pensionAgencyCode = 'POESSA',
        int $userId = 1,
        ?string $entryDate = null
    ): JournalEntry {
        if ($grossSalaries <= 0) {
            throw new InvalidArgumentException("Gross salaries must be greater than zero.");
        }

        $entryDate = $entryDate ?: Carbon::now()->toDateString();

        // 1. Check for duplicate posting for the same period
        $alreadyPosted = JournalEntry::where('reference_type', 'payroll')
            ->where('reference_id', crc32($payrollPeriod))
            ->where('status', 'posted')
            ->exists();
        if ($alreadyPosted) {
            throw new InvalidArgumentException("Payroll for period {$payrollPeriod} is already posted to the General Ledger.");
        }

        // 2. Fetch Pension Config
        $pensionConfig = DB::table('pension_agency_configs')
            ->where('agency_code', $pensionAgencyCode)
            ->first();

        $eeRate = $pensionConfig ? (float)$pensionConfig->employee_rate_percent : 7.00;
        $erRate = $pensionConfig ? (float)$pensionConfig->employer_rate_percent : 11.00;

        $calcEmployeePension = $employeePension !== null ? $employeePension : round($grossSalaries * ($eeRate / 100), 2);
        $calcEmployerPension = $employerPension !== null ? $employerPension : round($grossSalaries * ($erRate / 100), 2);
        $totalPension        = round($calcEmployeePension + $calcEmployerPension, 2);

        $calcPaye = $employeePaye !== null ? $employeePaye : $this->computePaye($grossSalaries);
        $netPay   = round($grossSalaries - $calcPaye - $calcEmployeePension, 2);

        // 3. Resolve GL Accounts
        $grossAcc     = ChartOfAccount::where('code', '6100')->firstOrFail();
        $erPensionAcc = ChartOfAccount::where('code', '6300')->firstOrFail();
        $payeAcc      = ChartOfAccount::where('code', '2210')->firstOrFail();
        $pensionLiab  = ChartOfAccount::where('code', '2220')->firstOrFail();
        $netPayAcc    = ChartOfAccount::where('code', '2006')->firstOrFail();

        // 4. Assemble Balanced Journal Lines
        // Debits: Gross Wages + Employer Pension
        // Credits: PAYE + Total Pension (7% + 11%) + Net Salaries Payable
        $lines = [
            ['account_id' => $grossAcc->id,     'side' => 'debit',  'amount' => $grossSalaries, 'description' => "Gross Wages for {$payrollPeriod}"],
            ['account_id' => $erPensionAcc->id, 'side' => 'debit',  'amount' => $calcEmployerPension, 'description' => "Employer Pension (11%) for {$payrollPeriod}"],
            ['account_id' => $payeAcc->id,      'side' => 'credit', 'amount' => $calcPaye, 'description' => "Employee Income Tax (PAYE) Withheld"],
            ['account_id' => $pensionLiab->id,  'side' => 'credit', 'amount' => $totalPension, 'description' => "Pension Fund Contribution Payable (18%) to {$pensionAgencyCode}"],
            ['account_id' => $netPayAcc->id,    'side' => 'credit', 'amount' => $netPay, 'description' => "Net Salaries Payable for {$payrollPeriod}"],
        ];

        $entry = $this->journalEngine->createEntry(
            'payroll',
            crc32($payrollPeriod),
            "Payroll GL Posting for {$payrollPeriod} ({$pensionAgencyCode})",
            $lines,
            $userId,
            $userId,
            $entryDate
        );

        // Auto-reverse any unapproved salary accruals for this period if they exist
        if (class_exists(\App\Models\AccrualEntry::class) && \Illuminate\Support\Facades\Schema::hasTable('accrual_entries')) {
            $unreversedSalaryAccruals = \App\Models\AccrualEntry::where('accrual_type', 'payroll_unapproved')
                ->where('is_reversed', false)
                ->whereHas('financialPeriod', function($q) use ($entryDate) {
                    $q->whereDate('start_date', '<=', $entryDate)
                      ->whereDate('end_date', '>=', $entryDate);
                })
                ->get();

            foreach ($unreversedSalaryAccruals as $accrual) {
                $originalJe = $accrual->journalEntry;
                if ($originalJe) {
                    $reversalJe = $this->journalEngine->createEntry(
                        'AccrualAutoReversal',
                        $accrual->id,
                        "Auto-Reversal of Salary Accrual upon Approved Payroll Posting [Ref: {$originalJe->entry_no}]",
                        [
                            [
                                'account_id'  => $accrual->accrual_account_id,
                                'side'        => 'debit',
                                'amount'      => (float) $accrual->amount,
                                'description' => "Reversal clearing accrued salary liability [2251]",
                            ],
                            [
                                'account_id'  => $accrual->expense_account_id,
                                'side'        => 'credit',
                                'amount'      => (float) $accrual->amount,
                                'description' => "Reversal offset of accrued salary expense [6100]",
                            ],
                        ],
                        $userId,
                        $userId,
                        $entryDate
                    );

                    $reversalJe->update([
                        'is_reversal'    => true,
                        'reversal_of_id' => $originalJe->id,
                    ]);
                    $originalJe->update([
                        'reversed_by_id' => $reversalJe->id,
                    ]);
                    $accrual->update([
                        'is_reversed'               => true,
                        'reversal_journal_entry_id' => $reversalJe->id,
                        'reversed_at'               => now(),
                        'reversed_by'               => $userId,
                    ]);
                }
            }
        }

        return $entry;
    }

    /**
     * Post PAYE Remittance to ERCA.
     * DR 2210 PAYE Payable / CR Bank
     */
    public function postPayeRemittance(float $amount, int $bankAccountId, int $userId = 1, ?string $date = null, ?string $refNo = null): JournalEntry
    {
        $payeAcc = ChartOfAccount::where('code', '2210')->firstOrFail();
        $bankAcc = ChartOfAccount::findOrFail($bankAccountId);

        $lines = [
            ['account_id' => $payeAcc->id, 'side' => 'debit',  'amount' => $amount, 'description' => "PAYE Remittance to ERCA (Ref: {$refNo})"],
            ['account_id' => $bankAcc->id, 'side' => 'credit', 'amount' => $amount, 'description' => "Bank payment for PAYE"],
        ];

        return $this->journalEngine->createEntry('paye_remittance', null, "PAYE Tax Remittance to ERCA", $lines, $userId, $userId, $date ?: now()->toDateString());
    }

    /**
     * Post Pension Contribution Remittance to POESSA or PSEPA.
     * DR 2220 Pension Payable / CR Bank
     */
    public function postPensionRemittance(float $amount, int $bankAccountId, string $agencyCode = 'POESSA', int $userId = 1, ?string $date = null, ?string $refNo = null): JournalEntry
    {
        $pensionAcc = ChartOfAccount::where('code', '2220')->firstOrFail();
        $bankAcc    = ChartOfAccount::findOrFail($bankAccountId);

        $lines = [
            ['account_id' => $pensionAcc->id, 'side' => 'debit',  'amount' => $amount, 'description' => "Pension Remittance (18%) to {$agencyCode} (Ref: {$refNo})"],
            ['account_id' => $bankAcc->id,    'side' => 'credit', 'amount' => $amount, 'description' => "Bank payment for Pension to {$agencyCode}"],
        ];

        return $this->journalEngine->createEntry('pension_remittance', null, "Pension Remittance to {$agencyCode}", $lines, $userId, $userId, $date ?: now()->toDateString());
    }

    /**
     * Post Net Salaries Bank Wire Transfer.
     * DR 2006 Net Salaries Payable / CR Bank
     */
    public function postNetSalaryDisbursement(float $amount, int $bankAccountId, int $userId = 1, ?string $date = null, ?string $refNo = null): JournalEntry
    {
        $netPayAcc = ChartOfAccount::where('code', '2006')->firstOrFail();
        $bankAcc   = ChartOfAccount::findOrFail($bankAccountId);

        $lines = [
            ['account_id' => $netPayAcc->id, 'side' => 'debit',  'amount' => $amount, 'description' => "Net Salaries Bank Wire Settlement (Ref: {$refNo})"],
            ['account_id' => $bankAcc->id,   'side' => 'credit', 'amount' => $amount, 'description' => "Bank payroll wire disbursement"],
        ];

        return $this->journalEngine->createEntry('salary_disbursement', null, "Net Salaries Wire Disbursement", $lines, $userId, $userId, $date ?: now()->toDateString());
    }
}
