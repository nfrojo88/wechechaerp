<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\FinancialAuditTrail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class OpeningBalanceService
{
    /**
     * Audit existing Chart of Accounts balances and classify source authenticity.
     * Separates P&L accounts into "Unsupported seed data - to be removed or documented".
     */
    public function auditExistingBalances(): array
    {
        $accounts = ChartOfAccount::orderBy('code')->get();

        $verified = [];
        $needsConfirmation = [];
        $unsupportedSeedPnl = [];

        foreach ($accounts as $acc) {
            $bal = (float)$acc->opening_balance;
            if (abs($bal) < 0.009 && abs((float)$acc->current_balance) < 0.009) {
                continue; // Zero-balance accounts
            }

            // Rule 6: P&L Accounts (4xxx - 6xxx) in seed data
            $isPnlAccount = in_array($acc->type, ['revenue', 'income', 'expense', 'cost_of_sale'])
                || preg_match('/^[456]/', $acc->code);

            if ($isPnlAccount) {
                $unsupportedSeedPnl[] = [
                    'account_id'       => $acc->id,
                    'code'             => $acc->code,
                    'name'             => $acc->name,
                    'type'             => $acc->type,
                    'unverified_value' => $bal != 0 ? $bal : (float)$acc->current_balance,
                    'classification'   => 'P&L Nominal Account',
                    'audit_status'     => 'Unsupported seed data - to be removed or documented',
                    'explanation'      => "Under IFRS accrual accounting, nominal income/expense accounts must not carry opening balances at the start of a fiscal year. Historical operational revenues and expenses must close into Retained Earnings (3152).",
                ];
                continue;
            }

            // Balance Sheet accounts originating from seeder
            $seedBsCodes = ['1010', '1011', '1012', '1013', '1300', '2001'];

            if (in_array($acc->code, $seedBsCodes)) {
                $reason = match ($acc->code) {
                    '1010' => "Originates from ChartOfAccountsSeeder (-ETB 742,800.00). Inverted negative balance representing unvouched site petty cash advances. Lacks audited closing trial balance schedule from external audit.",
                    '1012' => "Originates from ChartOfAccountsSeeder (-ETB 2,678,645.00). Overdraft credit facility on Cash/Bank ledger. Requires bank confirmation letter and facility schedule as of July 7, 2026.",
                    '1300' => "Originates from ChartOfAccountsSeeder (-ETB 36,000.00). Inverted credit balance on tax asset account. Requires client withholding tax certificates or Ministry of Revenue credit confirmation.",
                    '2001' => "Originates from ChartOfAccountsSeeder (-ETB 1,087,039.34). Trade payables. Requires audited accounts payable subledger aging and vendor statement reconciliation.",
                    '1011' => "Originates from ChartOfAccountsSeeder (ETB 1,354,000.00). CBE checking account. Requires CBE bank reconciliation statement as of July 7, 2026.",
                    default => "Legacy balance populated from initial seeder / SQL dump without verified auditor sign-off.",
                };

                $needsConfirmation[] = [
                    'account_id'       => $acc->id,
                    'code'             => $acc->code,
                    'name'             => $acc->name,
                    'type'             => $acc->type,
                    'unverified_value' => $bal != 0 ? $bal : (float)$acc->current_balance,
                    'source'           => 'ChartOfAccountsSeeder.php / chart_of_accounts.sql',
                    'audit_status'     => 'Needs Confirmation',
                    'reason'           => $reason,
                ];
            } else {
                $verified[] = [
                    'account_id'   => $acc->id,
                    'code'         => $acc->code,
                    'name'         => $acc->name,
                    'type'         => $acc->type,
                    'balance'      => $bal != 0 ? $bal : (float)$acc->current_balance,
                    'audit_status' => 'Verified / Manual Entry',
                ];
            }
        }

        return [
            'unsupported_seed_pnl' => collect($unsupportedSeedPnl),
            'needs_confirmation'   => collect($needsConfirmation),
            'verified'             => collect($verified),
        ];
    }

    /**
     * Post the official, dated, locked opening journal entry: JE-OPENING-FY2026.
     * Enforces Debits == Credits without ANY balancing plug account.
     * Rule 6: Strictly restricted to Balance Sheet accounts (Assets, Liabilities, Equity). P&L accounts are blocked.
     *
     * @param array $lines [ ['account_id' => int, 'side' => 'debit'|'credit', 'amount' => float, 'description' => string] ]
     * @throws Exception
     */
    public function postOpeningJournal(
        array $lines,
        User $creator,
        User $approver,
        string $effectiveDate = '2026-07-08',
        ?string $referenceNote = 'Audited Opening Balances as of 07 July 2026'
    ): JournalEntry {
        if (count($lines) < 2) {
            throw new Exception("Opening journal requires at least two balanced ledger lines.");
        }

        $totalDebit  = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $amt = (float)$line['amount'];
            if ($amt <= 0) {
                continue;
            }

            // Rule 6: Verify account is strictly a Balance Sheet account
            $acc = ChartOfAccount::findOrFail($line['account_id']);
            $isPnl = in_array($acc->type, ['revenue', 'income', 'expense', 'cost_of_sale']) || preg_match('/^[456]/', $acc->code);
            if ($isPnl) {
                throw new Exception("Opening journal vouchers are strictly restricted to Balance Sheet accounts. Account [{$acc->code}] {$acc->name} is a P&L account and cannot be included.");
            }

            if ($line['side'] === 'debit') {
                $totalDebit += $amt;
            } elseif ($line['side'] === 'credit') {
                $totalCredit += $amt;
            } else {
                throw new Exception("Invalid side: {$line['side']}. Must be 'debit' or 'credit'.");
            }
        }

        $totalDebit  = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);
        $difference  = round(abs($totalDebit - $totalCredit), 2);

        if ($difference > 0.009) {
            throw new Exception("Opening Trial Balance does not balance. Total Debits: ETB {$totalDebit}, Total Credits: ETB {$totalCredit} (Variance: ETB {$difference}). No balancing plug account is permitted.");
        }

        return DB::transaction(function () use (
            $lines, $creator, $approver, $effectiveDate, $referenceNote, $totalDebit, $totalCredit
        ) {
            $existing = JournalEntry::where('entry_no', 'JE-OPENING-FY2026')->first();
            if ($existing && $existing->is_locked) {
                throw new Exception("Opening journal [JE-OPENING-FY2026] is locked and already posted. Reopen with reason before modifying.");
            }

            if ($existing) {
                $existing->lines()->delete();
                $existing->delete();
            }

            $filteredLines = [];
            foreach ($lines as $lineData) {
                $amount = (float)$lineData['amount'];
                if ($amount > 0) {
                    $filteredLines[] = [
                        'account_id'  => $lineData['account_id'],
                        'side'        => $lineData['side'],
                        'amount'      => $amount,
                        'description' => $lineData['description'] ?? 'Audited FY2026 Opening Balance',
                    ];
                }
            }

            $journalEngine = app(\App\Services\Finance\JournalEngine::class);
            $journal = $journalEngine->createEntry(
                'audited_opening_balance',
                null,
                "Official Audited FY2026 Opening Balance Baseline | {$referenceNote}",
                $filteredLines,
                $creator->id,
                $approver->id,
                $effectiveDate,
                'JE-OPENING-FY2026'
            );

            foreach ($filteredLines as $lineData) {
                $amount = (float)$lineData['amount'];
                $acc = ChartOfAccount::findOrFail($lineData['account_id']);
                $normalSide = in_array($acc->type, ['liability', 'equity', 'revenue']) ? 'credit' : 'debit';
                if (!empty($acc->normal_side) && in_array($acc->normal_side, ['debit', 'credit'])) {
                    $normalSide = $acc->normal_side;
                }

                $signedAmount = ($lineData['side'] === $normalSide) ? $amount : -$amount;
                $acc->opening_balance = $signedAmount;
                $acc->save();
            }

            // Post and lock the balanced journal (verified by database-level trigger)
            $journal->is_locked = true;
            $journal->locked_at = now();
            $journal->locked_by = $approver->id;
            $journal->save();

            FinancialAuditTrail::logAction(
                $journal,
                'posted',
                $approver,
                "Posted and locked official opening balance voucher JE-OPENING-FY2026 (Debits: ETB {$totalDebit}, Credits: ETB {$totalCredit})",
                ['debits' => $totalDebit, 'credits' => $totalCredit, 'effective_date' => $effectiveDate]
            );

            return $journal;
        });
    }

    /**
     * Role-based reopen of locked opening journal entry with mandatory reason.
     */
    public function reopenOpeningJournal(User $user, string $reason): JournalEntry
    {
        $journal = JournalEntry::where('entry_no', 'JE-OPENING-FY2026')->firstOrFail();

        $role = strtolower($user->role ?? '');
        $hasSpatieRole = false;
        try {
            if (method_exists($user, 'hasRole') && \Illuminate\Support\Facades\Schema::hasTable('roles')) {
                $hasSpatieRole = $user->hasRole('cfo') || $user->hasRole('admin') || $user->hasRole('finance_manager');
            }
        } catch (\Throwable $e) {}

        $isAuthorized = (bool)($user->is_admin ?? false)
            || in_array($role, ['admin', 'global_admin', 'cfo', 'finance_manager'])
            || $hasSpatieRole;

        if (!$isAuthorized) {
            throw new Exception("Unauthorized. Only the CFO or Global Admin can reopen the locked opening balance entry.");
        }

        if (strlen(trim($reason)) < 10) {
            throw new Exception("A detailed accounting justification (minimum 10 characters) is required to reopen the opening balance.");
        }

        $journal->update([
            'is_locked'     => false,
            'reopened_at'   => now(),
            'reopened_by'   => $user->id,
            'reopen_reason' => $reason,
        ]);

        FinancialAuditTrail::logAction(
            $journal,
            'reopened',
            $user,
            "Reopened locked opening journal JE-OPENING-FY2026. Reason: {$reason}",
            ['reason' => $reason]
        );

        return $journal;
    }
}
