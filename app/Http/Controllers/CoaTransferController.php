<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\CoaTransfer;
use App\Models\JournalEntry;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CoaTransferController extends Controller
{
    /**
     * Display a listing of COA money transfers.
     */
    public function index(Request $request)
    {
        // Auto-purge account 1003 and any associated data if still present
        if (ChartOfAccount::where('code', '1003')->exists()) {
            ChartOfAccount::deleteAccountAndRelated('1003', true);
        }

        $query = CoaTransfer::with(['fromCoa', 'toCoa', 'creator', 'journalEntry'])->latest('transfer_date');

        // Filter: Search keyword
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('transfer_no', 'like', "%{$s}%")
                  ->orWhere('reference_no', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%")
                  ->orWhereHas('fromCoa', fn($c) => $c->where('code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"))
                  ->orWhereHas('toCoa', fn($c) => $c->where('code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"));
            });
        }

        // Filter: Source Account
        if ($request->filled('from_coa_id')) {
            $query->where('from_coa_id', $request->from_coa_id);
        }

        // Filter: Destination Account
        if ($request->filled('to_coa_id')) {
            $query->where('to_coa_id', $request->to_coa_id);
        }

        // Filter: Date Range
        if ($request->filled('date_from')) {
            $query->whereDate('transfer_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transfer_date', '<=', $request->date_to);
        }

        $transfers = $query->paginate(25)->withQueryString();

        // Summary Stats
        $stats = [
            'total_amount'   => CoaTransfer::where('status', 'completed')->sum('amount'),
            'total_count'    => CoaTransfer::count(),
            'today_amount'   => CoaTransfer::where('status', 'completed')->whereDate('transfer_date', now())->sum('amount'),
            'today_count'    => CoaTransfer::whereDate('transfer_date', now())->count(),
        ];

        $accounts = ChartOfAccount::where('is_active', true)->orderBy('code')->get();

        return view('finance.coa_transfers.index', compact('transfers', 'stats', 'accounts'));
    }

    /**
     * Show the form for creating a new COA fund transfer.
     */
    public function create(Request $request)
    {
        $accounts = ChartOfAccount::where('is_active', true)
            ->with('manager')
            ->orderBy('code')
            ->get();

        $preselectedFrom = $request->get('from_coa_id');
        $preselectedTo   = $request->get('to_coa_id');

        return view('finance.coa_transfers.create', compact('accounts', 'preselectedFrom', 'preselectedTo'));
    }

    /**
     * Store a newly created COA fund transfer in storage and post corresponding journal entry.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_coa_id'   => 'required|exists:chart_of_accounts,id|different:to_coa_id',
            'to_coa_id'     => 'required|exists:chart_of_accounts,id',
            'amount'        => 'required|numeric|min:0.01',
            'transfer_date' => 'required|date',
            'reference_no'  => 'nullable|string|max:100',
            'description'   => 'required|string|max:1000',
            'attachment'    => 'nullable|file|mimes:pdf,jpeg,png,jpg,webp|max:15360',
        ], [
            'from_coa_id.different' => 'Source and Destination Chart of Accounts must be different accounts.',
            'from_coa_id.required'  => 'Please select a source account (From Account).',
            'to_coa_id.required'    => 'Please select a destination account (To Account).',
            'amount.min'            => 'Transfer amount must be at least 0.01 ETB.',
        ]);

        $fromCoa = ChartOfAccount::findOrFail($validated['from_coa_id']);
        $toCoa   = ChartOfAccount::findOrFail($validated['to_coa_id']);
        $amount  = (float) $validated['amount'];

        // Handle attachment upload via FileUploadService
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = FileUploadService::upload($request->file('attachment'), 'coa_transfers');
        }

        try {
            $transfer = DB::transaction(function () use ($validated, $fromCoa, $toCoa, $amount, $attachmentPath) {
                // Generate sequential transfer number
                $dateCode = date('Ymd', strtotime($validated['transfer_date']));
                $countToday = CoaTransfer::whereDate('created_at', now())->count() + 1;
                $transferNo = 'TRF-' . $dateCode . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

                // 1. Create the CoaTransfer record
                $transfer = CoaTransfer::create([
                    'transfer_no'     => $transferNo,
                    'from_coa_id'     => $fromCoa->id,
                    'to_coa_id'       => $toCoa->id,
                    'amount'          => $amount,
                    'transfer_date'   => $validated['transfer_date'],
                    'reference_no'    => $validated['reference_no'] ?? null,
                    'description'     => $validated['description'],
                    'attachment_path' => $attachmentPath,
                    'created_by'      => Auth::id(),
                    'status'          => 'completed',
                ]);

                // 2. Adjust COA Balances
                // Source Account (From): normal debit accounts (asset/expense) decrease with credit
                if (in_array($fromCoa->type, ['asset', 'expense'])) {
                    $fromCoa->decrement('current_balance', $amount);
                } else {
                    $fromCoa->increment('current_balance', $amount);
                }

                // Destination Account (To): normal debit accounts (asset/expense) increase with debit
                if (in_array($toCoa->type, ['asset', 'expense'])) {
                    $toCoa->increment('current_balance', $amount);
                } else {
                    $toCoa->decrement('current_balance', $amount);
                }

                // 3. Create and post Journal Entry
                $jeCount = JournalEntry::count() + 1;
                $jeNo = 'JE-' . date('Ymd') . '-' . str_pad($jeCount, 4, '0', STR_PAD_LEFT);

                $journalEntry = JournalEntry::create([
                    'entry_no'       => $jeNo,
                    'entry_date'     => $validated['transfer_date'],
                    'reference_type' => 'coa_transfer',
                    'reference_id'   => $transfer->id,
                    'description'    => "COA Money Transfer: [{$fromCoa->code}] {$fromCoa->name} → [{$toCoa->code}] {$toCoa->name} | " . $validated['description'],
                    'status'         => 'posted',
                    'created_by'     => Auth::id(),
                    'approved_by'    => Auth::id(),
                    'posted_at'      => now(),
                ]);

                // Debit destination account (Funds received)
                $journalEntry->lines()->create([
                    'account_id'  => $toCoa->id,
                    'coa_id'      => $toCoa->id,
                    'side'        => 'debit',
                    'debit'       => $amount,
                    'credit'      => 0,
                    'amount'      => $amount,
                    'description' => "Transfer in from [{$fromCoa->code}] {$fromCoa->name} (Ref: {$transferNo})",
                ]);

                // Credit source account (Funds sent)
                $journalEntry->lines()->create([
                    'account_id'  => $fromCoa->id,
                    'coa_id'      => $fromCoa->id,
                    'side'        => 'credit',
                    'debit'       => 0,
                    'credit'      => $amount,
                    'amount'      => $amount,
                    'description' => "Transfer out to [{$toCoa->code}] {$toCoa->name} (Ref: {$transferNo})",
                ]);

                // 4. Link Journal Entry back to the transfer
                $transfer->update(['journal_entry_id' => $journalEntry->id]);

                return $transfer;
            });

            return redirect()->route('coa-transfers.show', $transfer)
                ->with('success', "Transfer {$transfer->transfer_no} of " . number_format($amount, 2) . " ETB completed and posted to general ledger successfully!");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('COA Transfer Failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return back()->withInput()->withErrors(['error' => 'Transfer failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified transfer voucher and audit trail.
     */
    public function show(CoaTransfer $coaTransfer)
    {
        $coaTransfer->load(['fromCoa', 'toCoa', 'creator', 'journalEntry.lines.account']);
        return view('finance.coa_transfers.show', compact('coaTransfer'));
    }
}
