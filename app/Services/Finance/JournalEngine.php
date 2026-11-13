<?php

namespace App\Services\Finance;

use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\FinancialAuditTrail;
use App\Models\FinancialPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class JournalEngine
{
    /**
     * Creates a new Journal Entry with lines.
     * Enforces double-entry: total debits == total credits.
     * Enforces period status: blocks posting into locked financial periods.
     */
    public function createEntry(
        string|array $referenceType,
        ?int $referenceId = null,
        ?string $description = null,
        ?array $lines = null,
        ?int $createdBy = null,
        ?int $approvedBy = null,
        ?string $entryDate = null,
        ?string $customEntryNo = null
    ): JournalEntry {
        if (is_array($referenceType)) {
            $data = $referenceType;
            $referenceType = $data['reference_type'] ?? 'manual';
            $referenceId   = $data['reference_id'] ?? null;
            $description   = $data['description'] ?? '';
            $lines         = $data['lines'] ?? [];
            $createdBy     = $data['created_by'] ?? 1;
            $entryDate     = $data['entry_date'] ?? now()->toDateString();
            $approvedBy    = $approvedBy ?? ($data['approved_by'] ?? $createdBy);
            $customEntryNo = $customEntryNo ?? ($data['entry_no'] ?? null);
        } else {
            $lines = $lines ?? [];
            $createdBy = $createdBy ?? 1;
        }

        $entryDate = $entryDate ?: now()->toDateString();

        // 1. Check if the entry date falls in a locked financial period
        if (\Illuminate\Support\Facades\Schema::hasTable('financial_periods')) {
            $lockedPeriod = FinancialPeriod::where('is_locked', true)
                ->whereDate('start_date', '<=', $entryDate)
                ->whereDate('end_date', '>=', $entryDate)
                ->first();

            if ($lockedPeriod) {
                throw new Exception("Cannot create journal entry: Accounting period [{$lockedPeriod->name}] is locked.");
            }
        }

        // 2. Validate double-entry
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        if (count($lines) < 2) {
            throw new Exception("Journal entry must contain at least 2 lines for double-entry integrity.");
        }

        foreach ($lines as $line) {
            $amt = (float)$line['amount'];
            if ($line['side'] === 'debit') {
                $totalDebit += $amt;
            } elseif ($line['side'] === 'credit') {
                $totalCredit += $amt;
            } else {
                throw new Exception("Invalid line side '{$line['side']}'. Must be 'debit' or 'credit'.");
            }
        }

        $totalDebit  = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        if (abs($totalDebit - $totalCredit) > 0.009) {
            throw new Exception("Journal entry unbalanced. Debits: {$totalDebit}, Credits: {$totalCredit}. Difference: " . abs($totalDebit - $totalCredit));
        }

        return DB::transaction(function () use (
            $referenceType, $referenceId, $description, $lines, $createdBy, $approvedBy, $entryDate, $totalDebit, $totalCredit, $customEntryNo
        ) {
            $entryNo = $customEntryNo ?: ('JE-' . date('Ymd', strtotime($entryDate)) . '-' . rand(1000, 9999));
            $entry = JournalEntry::create([
                'entry_no'       => $entryNo,
                'entry_date'     => $entryDate,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'description'    => $description,
                'status'         => 'draft',
                'created_by'     => $createdBy,
            ]);

            foreach ($lines as $line) {
                $payload = [
                    'account_id'  => $line['account_id'],
                    'side'        => $line['side'],
                    'amount'      => $line['amount'],
                    'description' => $line['description'] ?? null,
                ];
                if (!empty($line['original_account_id'])) {
                    $payload['original_account_id'] = $line['original_account_id'];
                }
                if (isset($line['project_id'])) {
                    $payload['project_id'] = $line['project_id'];
                }
                if (isset($line['contract_id'])) {
                    $payload['contract_id'] = $line['contract_id'];
                }
                if (isset($line['cost_center_id'])) {
                    $payload['cost_center_id'] = $line['cost_center_id'];
                }
                if (isset($line['site_id'])) {
                    $payload['site_id'] = $line['site_id'];
                }
                if (isset($line['party_type'])) {
                    $payload['party_type'] = $line['party_type'];
                }
                if (isset($line['party_id'])) {
                    $payload['party_id'] = $line['party_id'];
                }
                if (isset($line['is_installed'])) {
                    $payload['is_installed'] = (bool)$line['is_installed'];
                }
                if (isset($line['exclude_from_poc'])) {
                    $payload['exclude_from_poc'] = (bool)$line['exclude_from_poc'];
                }
                $entry->lines()->create($payload);
            }

            if ($approvedBy) {
                $this->postEntry($entry->id, $approvedBy);
                $entry->refresh();
            }

            return $entry;
        });
    }

    /**
     * Posts a Journal Entry, locking it and updating COA balances.
     * Enforces that posted entries become immutable.
     */
    public function postEntry(int $journalEntryId, int $approvedBy): JournalEntry
    {
        return DB::transaction(function () use ($journalEntryId, $approvedBy) {
            $entry = JournalEntry::with('lines.account')->findOrFail($journalEntryId);

            if ($entry->status !== 'draft') {
                throw new Exception("Only draft entries can be posted. Entry #{$entry->entry_no} has status: {$entry->status}.");
            }

            // Verify period lock
            if (\Illuminate\Support\Facades\Schema::hasTable('financial_periods')) {
                $lockedPeriod = FinancialPeriod::where('is_locked', true)
                    ->whereDate('start_date', '<=', $entry->entry_date)
                    ->whereDate('end_date', '>=', $entry->entry_date)
                    ->first();

                if ($lockedPeriod) {
                    throw new Exception("Cannot post journal entry: Accounting period [{$lockedPeriod->name}] is locked.");
                }
            }

            // Re-validate double-entry on lines
            $debits  = round((float)$entry->lines->where('side', 'debit')->sum('amount'), 2);
            $credits = round((float)$entry->lines->where('side', 'credit')->sum('amount'), 2);

            if (abs($debits - $credits) > 0.009 || $entry->lines->count() < 2) {
                throw new Exception("Cannot post unbalanced journal entry #{$entry->entry_no}. Debits: {$debits}, Credits: {$credits}.");
            }

            app()->instance('gl_posting_in_progress', true);
            try {
                DB::statement("SET @gl_posting_in_progress = 1;");
            } catch (\Throwable $e) {
            }

            try {
                foreach ($entry->lines as $line) {
                    $account = $line->account;
                    if (!$account) continue;
                    $amount  = (float)$line->amount;
                    $side    = $line->side;

                    $normalBalanceIsDebit = in_array($account->type, ['asset', 'expense']);

                    if ($normalBalanceIsDebit) {
                        if ($side === 'debit') {
                            $account->current_balance += $amount;
                        } else {
                            $account->current_balance -= $amount;
                        }
                    } else {
                        if ($side === 'credit') {
                            $account->current_balance += $amount;
                        } else {
                            $account->current_balance -= $amount;
                        }
                    }

                    $account->save();

                    // If account is linked to bank accounts, update bank_accounts.current_balance inside the same transaction.
                    // Exactly one bank account maps to one GL cash/bank account; use first() to prevent any loop multiplication.
                    if (method_exists($account, 'bankAccounts') && ($bankAccount = $account->bankAccounts()->first())) {
                        if ($side === 'debit') {
                            $bankAccount->current_balance += $amount;
                        } else {
                            $bankAccount->current_balance -= $amount;
                        }
                        $bankAccount->save();
                    }
                }
            } finally {
                app()->instance('gl_posting_in_progress', false);
                try {
                    DB::statement("SET @gl_posting_in_progress = 0;");
                } catch (\Throwable $e) {
                }
            }

            $entry->update([
                'status'      => 'posted',
                'approved_by' => $approvedBy,
                'posted_at'   => now(),
            ]);

            FinancialAuditTrail::logAction(
                $entry,
                'posted',
                User::find($approvedBy),
                "Posted journal entry {$entry->entry_no} (Debits: ETB {$debits}, Credits: ETB {$credits})",
                ['debits' => $debits, 'credits' => $credits]
            );

            return $entry;
        });
    }

    /**
     * Reverse a posted journal entry.
     * Posted entries are immutable; corrections are strictly made by reversing entries.
     */
    public function reverseEntry(int $journalEntryId, int $reversedBy, string $reason, ?string $reversalDate = null): JournalEntry
    {
        return DB::transaction(function () use ($journalEntryId, $reversedBy, $reason, $reversalDate) {
            $original = JournalEntry::with('lines.account')->findOrFail($journalEntryId);

            if ($original->status !== 'posted') {
                throw new Exception("Only posted journal entries can be reversed. Entry #{$original->entry_no} is {$original->status}.");
            }

            if ($original->reversed_by_id) {
                $rev = JournalEntry::find($original->reversed_by_id);
                throw new Exception("Journal entry #{$original->entry_no} has already been reversed by entry #{$rev?->entry_no}.");
            }

            $reversalDate = $reversalDate ?: now()->toDateString();

            // Verify period lock for reversal date
            if (\Illuminate\Support\Facades\Schema::hasTable('financial_periods')) {
                $lockedPeriod = FinancialPeriod::where('is_locked', true)
                    ->whereDate('start_date', '<=', $reversalDate)
                    ->whereDate('end_date', '>=', $reversalDate)
                    ->first();

                if ($lockedPeriod) {
                    throw new Exception("Cannot post reversal: Accounting period [{$lockedPeriod->name}] is locked.");
                }
            }

            // Create opposite reversing lines
            $reversingLines = [];
            foreach ($original->lines as $line) {
                $reversingLines[] = [
                    'account_id'  => $line->account_id,
                    'side'        => ($line->side === 'debit') ? 'credit' : 'debit', // Flipped
                    'amount'      => $line->amount,
                    'description' => "Reversal of line #{$line->id} ({$original->entry_no}): " . ($line->description ?? ''),
                ];
            }

            $reversalEntry = JournalEntry::create([
                'entry_no'       => 'REV-' . $original->entry_no . '-' . rand(100, 999),
                'entry_date'     => $reversalDate,
                'reference_type' => 'reversal',
                'reference_id'   => $original->id,
                'description'    => "Reversal of Entry {$original->entry_no}: {$reason}",
                'status'         => 'draft',
                'created_by'     => $reversedBy,
                'is_reversal'    => true,
                'reversal_of_id' => $original->id,
            ]);

            foreach ($reversingLines as $rLine) {
                $reversalEntry->lines()->create($rLine);
            }

            // Post the reversal
            $this->postEntry($reversalEntry->id, $reversedBy);
            $reversalEntry->refresh();

            // Link original to reversal
            $original->update([
                'reversed_by_id' => $reversalEntry->id,
            ]);

            FinancialAuditTrail::logAction(
                $original,
                'reversed',
                User::find($reversedBy),
                "Reversed by entry {$reversalEntry->entry_no}. Reason: {$reason}",
                ['reversal_entry_id' => $reversalEntry->id, 'reason' => $reason]
            );

            return $reversalEntry;
        });
    }

    /**
     * Cancels a draft entry.
     * Blocks cancelling posted entries (must use reverseEntry instead).
     */
    public function cancelDraftEntry(int $journalEntryId, int $userId, string $reason): JournalEntry
    {
        $entry = JournalEntry::findOrFail($journalEntryId);

        if ($entry->status === 'posted') {
            throw new Exception("Posted entries are immutable and cannot be cancelled or deleted. Use reverseEntry() instead.");
        }

        $entry->update([
            'status'      => 'cancelled',
            'description' => $entry->description . " (Cancelled: {$reason})"
        ]);

        FinancialAuditTrail::logAction(
            $entry,
            'cancelled',
            User::find($userId),
            "Cancelled draft journal entry. Reason: {$reason}"
        );

        return $entry;
    }
}
