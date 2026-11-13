<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use Illuminate\Support\Collection;

class AbnormalBalanceService
{
    /**
     * Inspect all accounts and return those carrying balances on the wrong normal side.
     * Bank accounts (1010-1013) are permitted to have credit balances (presented as Overdraft liabilities).
     */
    public function getAbnormalAccounts(?string $asOfDate = null, bool $excludeSeedData = false): Collection
    {
        $query = ChartOfAccount::where('is_active', true);
        if ($excludeSeedData) {
            $query->where('is_seed', false);
        }
        $accounts = $query->orderBy('code')->get();

        $linesQuery = JournalEntryLine::selectRaw("
                account_id,
                SUM(CASE WHEN side = 'debit'  THEN amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN side = 'credit' THEN amount ELSE 0 END) as total_credit
            ")
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', 'posted');

        if ($asOfDate) {
            $linesQuery->whereDate('journal_entries.entry_date', '<=', $asOfDate);
        }

        $lines = $linesQuery->groupBy('account_id')->get()->keyBy('account_id');

        $abnormal = [];

        foreach ($accounts as $acc) {
            // Rule 5 (Correction): Only bank facility accounts (1011-1013) allow either side without an abnormal flag.
            // Account 1010 Petty Cash must be flagged when carrying a credit balance because physical cash on hand cannot be negative.
            if (in_array($acc->code, ['1011', '1012', '1013'])) {
                continue;
            }

            $line = $lines->get($acc->id);
            $debit  = $line ? (float)$line->total_debit : 0.0;
            $credit = $line ? (float)$line->total_credit : 0.0;
            $opBal  = (float)$acc->opening_balance;

            $normalSide = in_array($acc->type, ['liability', 'equity', 'revenue']) ? 'credit' : 'debit';
            if (!empty($acc->normal_side) && in_array($acc->normal_side, ['debit', 'credit'])) {
                $normalSide = $acc->normal_side;
            }

            // Compute net balance on the normal side
            $openingDebit  = ($normalSide === 'debit')  ? ($opBal >= 0 ? $opBal : 0) : ($opBal < 0 ? abs($opBal) : 0);
            $openingCredit = ($normalSide === 'credit') ? ($opBal >= 0 ? $opBal : 0) : ($opBal < 0 ? abs($opBal) : 0);

            $netDebit  = ($openingDebit + $debit) - ($openingCredit + $credit);
            $netCredit = ($openingCredit + $credit) - ($openingDebit + $debit);

            $isAbnormal = false;
            $abnormalSide = null;
            $abnormalAmount = 0.0;
            $reason = '';
            $riskLevel = 'medium';

            if ($normalSide === 'debit' && $netDebit < -0.01) {
                $isAbnormal     = true;
                $abnormalSide   = 'credit';
                $abnormalAmount = round(abs($netDebit), 2);

                if ($acc->code === '1010') {
                    $reason    = "Petty Cash carries an abnormal credit balance. Physical cash on hand cannot be negative. This indicates disbursements exceeding recorded cash replenishments or delayed surrender of expense vouchers.";
                    $riskLevel = 'high';
                } elseif ($acc->code === '1300') {
                    $reason    = "Withholding Tax Receivable carries an abnormal credit balance. WHT withheld by clients from contract billings is an asset/credit with the Ministry of Revenue. A credit balance indicates posting supplier withholding (liability 2600) into the receivable asset account.";
                    $riskLevel = 'high';
                } else {
                    $reason    = "Asset or expense account carrying a net credit balance. Requires investigation for excess credits, unposted invoices, or reversing entry errors.";
                    $riskLevel = 'medium';
                }
            } elseif ($normalSide === 'credit' && $netCredit < -0.01) {
                // Allow Retained Earnings (3152) to be in net deficit (debit) if company has accumulated losses
                if ($acc->code === '3152') {
                    continue;
                }

                $isAbnormal     = true;
                $abnormalSide   = 'debit';
                $abnormalAmount = round(abs($netCredit), 2);

                if ($acc->code === '2001' || in_array($acc->type, ['liability', 'payable'])) {
                    $reason    = "Accounts Payable / Liability carries an abnormal debit balance. Indicates vendor overpayment, unrecorded supplier purchase invoices, or debit notes awaiting reconciliation.";
                    $riskLevel = 'high';
                } elseif (in_array($acc->type, ['revenue', 'income'])) {
                    $reason    = "Revenue account has net debit balance. Indicates cancellation/credit notes exceeding billed revenue or wrong-side journal posting.";
                    $riskLevel = 'high';
                } else {
                    $reason    = "Liability or equity account carrying a net debit balance.";
                    $riskLevel = 'medium';
                }
            }

            if ($isAbnormal) {
                $abnormal[] = [
                    'account_id'          => $acc->id,
                    'code'                => $acc->code,
                    'name'                => $acc->name,
                    'type'                => $acc->type,
                    'normal_side'         => $normalSide,
                    'actual_side'         => $abnormalSide,
                    'abnormal_amount'     => $abnormalAmount,
                    'risk_level'          => $riskLevel,
                    'explanation'         => $reason,
                    'recommended_action'  => $this->getRecommendedAction($acc->code),
                ];
            }
        }

        return collect($abnormal);
    }

    private function getRecommendedAction(string $code): string
    {
        return match ($code) {
            '1300' => "Reclassify supplier withholding tax deductions to WHT Payable (2600); retain 1300 strictly for client-withheld tax certificates.",
            '2001' => "Reconcile vendor statement; identify unentered supplier invoices or reclassify debit balance to Supplier Advances (Asset 1250).",
            default => "Audit transaction history for {$code} to identify reversing errors or inverted journal lines.",
        };
    }
}
