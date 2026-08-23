<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssignedAccountController extends Controller
{
    public function index()
    {
        // Get accounts assigned to the logged-in user
        $accounts = ChartOfAccount::where('assigned_to', auth()->id())->get();
        return view('finance.assigned_accounts.index', compact('accounts'));
    }

    public function show($id, Request $request)
    {
        $account = ChartOfAccount::where('id', $id)->where('assigned_to', auth()->id())->firstOrFail();

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = JournalEntryLine::with(['journalEntry.creator'])
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->select('journal_entry_lines.*', 'journal_entries.entry_date', 'journal_entries.entry_no as reference', 'journal_entries.description as je_description', 'journal_entries.created_by')
            ->where('account_id', $account->id)
            ->orderBy('journal_entries.entry_date', 'desc')
            ->orderBy('journal_entries.id', 'desc');

        if ($startDate) {
            $query->whereDate('journal_entries.entry_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('journal_entries.entry_date', '<=', $endDate);
        }

        $entries = $query->paginate(20);

        // Get all other active accounts for the payment form
        $targetAccounts = ChartOfAccount::where('is_active', true)->where('id', '!=', $account->id)->orderBy('name')->get();

        return view('finance.assigned_accounts.show', compact('account', 'entries', 'startDate', 'endDate', 'targetAccounts'));
    }

    public function pay(Request $request, $id)
    {
        $account = ChartOfAccount::where('id', $id)->where('assigned_to', auth()->id())->firstOrFail();

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:payment,receipt',
            'target_account_id' => 'required|exists:chart_of_accounts,id',
            'description' => 'required|string',
            'reference' => 'nullable|string'
        ]);

        DB::transaction(function () use ($request, $account) {
            $entry = JournalEntry::create([
                'entry_date' => now(),
                'reference' => $request->reference ?? 'PAY-' . strtoupper(uniqid()),
                'description' => $request->description,
                'created_by' => auth()->id(),
            ]);

            $isAssetOrExpense = in_array($account->type, ['asset', 'expense']);
            
            if ($request->type === 'payment') {
                // If it's a payment out of this account
                $thisAccountSide = $isAssetOrExpense ? 'credit' : 'debit';
                $targetAccountSide = $thisAccountSide === 'credit' ? 'debit' : 'credit';
            } else {
                // If it's a receipt into this account
                $thisAccountSide = $isAssetOrExpense ? 'debit' : 'credit';
                $targetAccountSide = $thisAccountSide === 'debit' ? 'credit' : 'debit';
            }

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $account->id,
                'description' => $request->description,
                'side' => $thisAccountSide,
                'amount' => $request->amount,
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $request->target_account_id,
                'description' => $request->description,
                'side' => $targetAccountSide,
                'amount' => $request->amount,
            ]);

            // Update balances (simplified logic for current_balance)
            $targetAccount = ChartOfAccount::find($request->target_account_id);
            
            $account->current_balance += ($thisAccountSide === 'debit' ? $request->amount : -$request->amount) * ($isAssetOrExpense ? 1 : -1);
            $account->save();
            
            $isTargetAssetOrExpense = in_array($targetAccount->type, ['asset', 'expense']);
            $targetAccount->current_balance += ($targetAccountSide === 'debit' ? $request->amount : -$request->amount) * ($isTargetAssetOrExpense ? 1 : -1);
            $targetAccount->save();
        });

        return redirect()->back()->with('success', 'Transaction completed successfully.');
    }
}
