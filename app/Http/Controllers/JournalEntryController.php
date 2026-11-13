<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Services\Finance\JournalEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class JournalEntryController extends Controller
{
    protected JournalEngine $journalEngine;

    public function __construct(JournalEngine $journalEngine)
    {
        $this->journalEngine = $journalEngine;
    }

    public function index(Request $request)
    {
        $query = JournalEntry::with(['createdBy', 'approvedBy', 'lines.account'])
            ->latest('entry_date')
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('entry_no', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $entries = $query->paginate(20);
        return view('finance.journal-entries.index', compact('entries'));
    }

    public function create()
    {
        $accounts = ChartOfAccount::where('is_active', true)->orderBy('code')->get();
        return view('finance.journal-entries.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'entry_date'        => 'required|date',
            'description'       => 'required|string|max:1000',
            'lines'             => 'required|array|min:2',
            'lines.*.account_id'=> 'required|exists:chart_of_accounts,id',
            'lines.*.side'      => 'required|in:debit,credit',
            'lines.*.amount'    => 'required|numeric|min:0.01',
            'lines.*.description'=> 'nullable|string|max:255',
        ]);

        try {
            $entry = $this->journalEngine->createEntry(
                'manual',
                null,
                $request->description,
                $request->lines,
                Auth::id(),
                Auth::id(), // Auto-post upon validation
                $request->entry_date
            );

            return redirect()->route('journal-entries.index')
                ->with('success', "Journal Entry #{$entry->entry_no} posted successfully.");
        } catch (Exception $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }
    }

    public function show(JournalEntry $journalEntry)
    {
        $journalEntry->load(['lines.account', 'createdBy', 'approvedBy']);
        return view('finance.journal-entries.show', compact('journalEntry'));
    }

    /**
     * Reverse a posted journal entry (posted entries are immutable).
     */
    public function reverse(Request $request, JournalEntry $journalEntry)
    {
        $request->validate([
            'reason'        => 'required|string|min:5|max:1000',
            'reversal_date' => 'nullable|date',
        ]);

        try {
            $reversal = $this->journalEngine->reverseEntry(
                $journalEntry->id,
                Auth::id(),
                $request->reason,
                $request->reversal_date
            );

            return redirect()->route('journal-entries.show', $reversal->id)
                ->with('success', "Reversing Journal Entry #{$reversal->entry_no} has been posted. Original entry #{$journalEntry->entry_no} is now marked as reversed.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
