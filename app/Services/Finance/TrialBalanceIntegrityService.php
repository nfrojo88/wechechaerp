<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\FinancialPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class TrialBalanceIntegrityService
{
    /**
     * Compute a comprehensive double-entry Trial Balance.
     * Incorporates all 5 account types: Asset, Liability, Equity, Revenue, Expense.
     * Supports excluding demo/seed data.
     */
    public function getTrialBalance(?string $asOfDate = null, ?string $startDate = null, bool $excludeSeedData = false): array
    {
        $query = ChartOfAccount::where('is_active', true);
        $accounts = $query->orderBy('code')->get();

        // Query posted journal entry lines
        $linesQuery = JournalEntryLine::selectRaw("
                journal_entry_lines.account_id,
                SUM(CASE WHEN journal_entry_lines.side = 'debit'  THEN journal_entry_lines.amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN journal_entry_lines.side = 'credit' THEN journal_entry_lines.amount ELSE 0 END) as total_credit
            ")
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', 'posted');

        if ($excludeSeedData) {
            $linesQuery->where('journal_entries.is_seed', false);
        }

        if ($startDate) {
            $linesQuery->whereDate('journal_entries.entry_date', '>=', $startDate);
        }
        if ($asOfDate) {
            $linesQuery->whereDate('journal_entries.entry_date', '<=', $asOfDate);
        }

        $lines = $linesQuery->groupBy('journal_entry_lines.account_id')
            ->get()
            ->keyBy('account_id');

        // Check if an official opening balance journal entry exists in posted lines
        $hasOfficialOpening = JournalEntry::where('entry_no', 'JE-OPENING-FY2026')
            ->where('status', 'posted')
            ->exists();

        $rows = [];
        $totalDebit  = 0.0;
        $totalCredit = 0.0;

        foreach ($accounts as $acc) {
            $line = $lines->get($acc->id);
            $periodDebit  = $line ? (float)$line->total_debit : 0.0;
            $periodCredit = $line ? (float)$line->total_credit : 0.0;

            // Opening balances respect account normal side.
            // If official opening journal exists or seed data excluded, static column is not double-counted.
            $openingDebit = 0.0;
            $openingCredit = 0.0;
            $opBal = ($excludeSeedData || $hasOfficialOpening) ? 0.0 : (float)$acc->opening_balance;

            $normalSide = in_array($acc->type, ['liability', 'equity', 'revenue']) ? 'credit' : 'debit';
            if (!empty($acc->normal_side) && in_array($acc->normal_side, ['debit', 'credit'])) {
                $normalSide = $acc->normal_side;
            }

            if ($normalSide === 'debit') {
                if ($opBal >= 0) {
                    $openingDebit = $opBal;
                } else {
                    $openingCredit = abs($opBal);
                }
            } else {
                if ($opBal >= 0) {
                    $openingCredit = $opBal;
                } else {
                    $openingDebit = abs($opBal);
                }
            }

            $cumulativeDebit  = $openingDebit + $periodDebit;
            $cumulativeCredit = $openingCredit + $periodCredit;

            $netEndingDebit  = 0.0;
            $netEndingCredit = 0.0;

            if ($cumulativeDebit > $cumulativeCredit) {
                $netEndingDebit = round($cumulativeDebit - $cumulativeCredit, 2);
            } elseif ($cumulativeCredit > $cumulativeDebit) {
                $netEndingCredit = round($cumulativeCredit - $cumulativeDebit, 2);
            }

            // Include if there is any activity or ending balance
            if ($netEndingDebit > 0 || $netEndingCredit > 0 || $periodDebit > 0 || $periodCredit > 0 || abs($opBal) > 0) {
                $rows[] = [
                    'account_id'        => $acc->id,
                    'code'              => $acc->code,
                    'name'              => $acc->name,
                    'type'              => $acc->type,
                    'normal_side'       => $normalSide,
                    'opening_debit'     => $openingDebit,
                    'opening_credit'    => $openingCredit,
                    'period_debit'      => $periodDebit,
                    'period_credit'     => $periodCredit,
                    'ending_debit'      => $netEndingDebit,
                    'ending_credit'     => $netEndingCredit,
                ];

                $totalDebit  += $netEndingDebit;
                $totalCredit += $netEndingCredit;
            }
        }

        $totalDebit  = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);
        $difference  = round(abs($totalDebit - $totalCredit), 2);
        $isBalanced  = $difference < 0.01;

        return [
            'rows'         => collect($rows),
            'total_debit'  => $totalDebit,
            'total_credit' => $totalCredit,
            'difference'   => $difference,
            'is_balanced'  => $isBalanced,
            'as_of_date'   => $asOfDate,
            'start_date'   => $startDate,
        ];
    }

    /**
     * Compute authoritative net totals per account class (asset, liability, equity, revenue, expense)
     * derived strictly from posted ledger lines, eliminating reads from static current_balance.
     */
    public function getTotalsByType(): array
    {
        $tb = $this->getTrialBalance();
        $totals = ['asset' => 0.0, 'liability' => 0.0, 'equity' => 0.0, 'revenue' => 0.0, 'expense' => 0.0];
        foreach ($tb['rows'] as $r) {
            $type = $r['type'];
            if (isset($totals[$type])) {
                // For Assets and Expenses, net balance is Debit - Credit (properly netting contra-assets like accumulated depreciation)
                // For Liabilities, Equity, and Revenue, net balance is Credit - Debit
                if (in_array($type, ['asset', 'expense'])) {
                    $bal = (float)$r['ending_debit'] - (float)$r['ending_credit'];
                } else {
                    $bal = (float)$r['ending_credit'] - (float)$r['ending_debit'];
                }
                $totals[$type] += $bal;
            }
        }
        return [
            'asset'       => round($totals['asset'], 2),
            'liability'   => round($totals['liability'], 2),
            'equity'      => round($totals['equity'], 2),
            'revenue'     => round($totals['revenue'], 2),
            'expense'     => round($totals['expense'], 2),
        ];
    }

    /**
     * Integrity check that isolates discrepancies and causative unbalanced entries.
     */
    public function checkIntegrity(?string $asOfDate = null, ?string $startDate = null): array
    {
        $tb = $this->getTrialBalance($asOfDate, $startDate);
        $unbalancedEntries = $this->findUnbalancedJournalEntries($asOfDate, $startDate);

        $blockReasons = [];

        if (!$tb['is_balanced']) {
            $blockReasons[] = "Trial Balance does not balance. Total Debits: ETB " . number_format($tb['total_debit'], 2) . ", Total Credits: ETB " . number_format($tb['total_credit'], 2) . " (Variance: ETB " . number_format($tb['difference'], 2) . ").";
        }

        if ($unbalancedEntries->isNotEmpty()) {
            $count = $unbalancedEntries->count();
            $blockReasons[] = "Found {$count} posted journal entries where debits do not equal credits.";
        }

        return [
            'is_balanced'        => $tb['is_balanced'] && $unbalancedEntries->isEmpty(),
            'total_debit'        => $tb['total_debit'],
            'total_credit'       => $tb['total_credit'],
            'difference'         => $tb['difference'],
            'unbalanced_entries' => $unbalancedEntries,
            'can_close_period'   => $tb['is_balanced'] && $unbalancedEntries->isEmpty(),
            'block_reasons'      => $blockReasons,
        ];
    }

    /**
     * Locate all journal entries where debits != credits or lines count < 2.
     */
    public function findUnbalancedJournalEntries(?string $asOfDate = null, ?string $startDate = null): Collection
    {
        $query = JournalEntry::with(['lines.account', 'creator'])
            ->where('status', 'posted');

        if ($startDate) {
            $query->whereDate('entry_date', '>=', $startDate);
        }
        if ($asOfDate) {
            $query->whereDate('entry_date', '<=', $asOfDate);
        }

        return $query->get()->filter(function (JournalEntry $entry) {
            $debits  = (float)$entry->lines->where('side', 'debit')->sum('amount');
            $credits = (float)$entry->lines->where('side', 'credit')->sum('amount');
            $diff    = round(abs($debits - $credits), 2);

            $entry->total_debit  = $debits;
            $entry->total_credit = $credits;
            $entry->imbalance    = $diff;

            return $diff > 0.009 || $entry->lines->count() < 2;
        })->values();
    }

    /**
     * Hard-blocks period close if Trial Balance does not balance.
     * @throws Exception
     */
    public function enforcePeriodCloseIntegrity(FinancialPeriod $period): void
    {
        $check = $this->checkIntegrity(
            $period->end_date->toDateString(),
            $period->start_date->toDateString()
        );

        if (!$check['can_close_period']) {
            $reasons = implode('; ', $check['block_reasons']);
            throw new Exception("Period close blocked by Accounting Integrity Guard: {$reasons}");
        }

        $period->update([
            'tb_balanced_at_close' => true,
            'tb_total_debit'       => $check['total_debit'],
            'tb_total_credit'      => $check['total_credit'],
            'tb_difference'        => 0.00,
        ]);
    }
}
