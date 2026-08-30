<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\ExpenseRequest;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PettyCashReplenishment;
use App\Models\PettyCashReplenishmentItem;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Carbon\Carbon;

class AssignedAccountController extends Controller
{
    /**
     * Check if the authenticated user is a Finance Head, Finance Manager, or Admin
     */
    private function isFinanceHeadUser(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['Finance head', 'finance_head', 'finance_manager', 'finance', 'admin', 'global_admin']);
        }

        return false;
    }

    /**
     * Check if the authenticated user is an Auditor or has audit rights
     */
    private function isAuditorUser(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['auditor', 'audit', 'internal_auditor', 'admin', 'global_admin'])) {
            return true;
        }

        if (method_exists($user, 'can') && ($user->can('audit.view') || $user->can('finance.audit.view') || $user->can('admin.audit.view'))) {
            return true;
        }

        return false;
    }


    /**
     * Self-healing schema check to guarantee tables exist without manual intervention
     */
    protected static function ensureSchema(): void
    {
        try {
            if (!Schema::hasTable('petty_cash_replenishments')) {
                Schema::create('petty_cash_replenishments', function (Blueprint $table) {
                    $table->id();
                    $table->string('request_no', 50)->unique();
                    $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
                    $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                    $table->decimal('requested_amount', 18, 2);
                    $table->decimal('current_balance_at_request', 18, 2)->default(0);
                    $table->decimal('total_expenses_amount', 18, 2)->default(0);
                    $table->timestamp('period_start_date')->nullable();
                    $table->timestamp('period_end_date')->nullable();
                    $table->unsignedBigInteger('start_journal_line_id')->nullable();
                    $table->unsignedBigInteger('end_journal_line_id')->nullable();
                    $table->string('status', 30)->default('pending')->index();
                    $table->text('notes')->nullable();
                    $table->string('attachment_path')->nullable();
                    $table->foreignId('finance_head_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->decimal('fulfilled_amount', 18, 2)->nullable();
                    $table->foreignId('source_coa_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                    $table->text('finance_notes')->nullable();
                    $table->text('audit_notes')->nullable();
                    $table->foreignId('audited_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamp('audited_at')->nullable();
                    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->timestamp('reviewed_at')->nullable();
                    $table->string('fulfillment_reference', 100)->nullable();
                    $table->timestamp('fulfilled_at')->nullable();
                    $table->timestamp('rejected_at')->nullable();
                    $table->text('rejection_reason')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                    $table->index(['chart_of_account_id', 'status']);
                });
            } else {
                Schema::table('petty_cash_replenishments', function (Blueprint $table) {
                    if (!Schema::hasColumn('petty_cash_replenishments', 'audit_notes')) {
                        $table->text('audit_notes')->nullable();
                    }
                    if (!Schema::hasColumn('petty_cash_replenishments', 'audited_by')) {
                        $table->foreignId('audited_by')->nullable()->constrained('users')->nullOnDelete();
                    }
                    if (!Schema::hasColumn('petty_cash_replenishments', 'audited_at')) {
                        $table->timestamp('audited_at')->nullable();
                    }
                    if (!Schema::hasColumn('petty_cash_replenishments', 'reviewed_by')) {
                        $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                    }
                    if (!Schema::hasColumn('petty_cash_replenishments', 'reviewed_at')) {
                        $table->timestamp('reviewed_at')->nullable();
                    }
                });

                try {
                    \Illuminate\Support\Facades\DB::statement("ALTER TABLE petty_cash_replenishments MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending'");
                } catch (\Throwable $ex) {
                    // Ignore
                }
            }

            if (!Schema::hasTable('petty_cash_replenishment_items')) {
                Schema::create('petty_cash_replenishment_items', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('petty_cash_replenishment_id')->constrained('petty_cash_replenishments')->cascadeOnDelete();
                    $table->unsignedBigInteger('journal_entry_line_id')->nullable();
                    $table->date('entry_date')->nullable();
                    $table->string('reference', 100)->nullable();
                    $table->text('description')->nullable();
                    $table->string('target_account_name')->nullable();
                    $table->decimal('amount', 18, 2);
                    $table->string('side', 20)->default('credit');
                    $table->string('status', 30)->default('pending');
                    $table->text('rejection_reason')->nullable();
                    $table->text('inquiry_note')->nullable();
                    $table->text('custodian_reply')->nullable();
                    $table->timestamps();
                    $table->index('petty_cash_replenishment_id', 'pcr_items_replenish_id_idx');
                });
            } else {
                Schema::table('petty_cash_replenishment_items', function (Blueprint $table) {
                    if (!Schema::hasColumn('petty_cash_replenishment_items', 'status')) {
                        $table->string('status', 30)->default('pending');
                    }
                    if (!Schema::hasColumn('petty_cash_replenishment_items', 'rejection_reason')) {
                        $table->text('rejection_reason')->nullable();
                    }
                    if (!Schema::hasColumn('petty_cash_replenishment_items', 'inquiry_note')) {
                        $table->text('inquiry_note')->nullable();
                    }
                    if (!Schema::hasColumn('petty_cash_replenishment_items', 'custodian_reply')) {
                        $table->text('custodian_reply')->nullable();
                    }
                });

                try {
                    \Illuminate\Support\Facades\DB::statement("ALTER TABLE petty_cash_replenishment_items MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending'");
                } catch (\Throwable $ex) {
                    // Ignore
                }
            }
        } catch (\Throwable $e) {
            // Log or continue silently if schema cannot be checked/created
        }
    }



    public function index(Request $request)
    {
        self::ensureSchema();

        $isFinanceHead = $this->isFinanceHeadUser();
        $authId = auth()->id();
        
        $viewAll = $isFinanceHead && $request->input('view') === 'all';

        if ($viewAll) {
            // Finance Head viewing all accounts assigned across company
            $accounts = ChartOfAccount::with('manager')
                ->whereNotNull('assigned_to')
                ->orderBy('code')
                ->get();
        } else {
            // Strictly show ONLY the accounts assigned to the logged-in user
            $accounts = ChartOfAccount::with('manager')
                ->where('assigned_to', $authId)
                ->orderBy('code')
                ->get();
        }

        return view('finance.assigned_accounts.index', compact('accounts', 'isFinanceHead', 'viewAll'));
    }

    public function show(int|string $id, Request $request)
    {
        self::ensureSchema();

        $isFinanceHead = $this->isFinanceHeadUser();
        $authId = auth()->id();

        $account = ChartOfAccount::where('id', $id)
            ->when(!$isFinanceHead, function ($q) use ($authId) {
                $q->where('assigned_to', $authId);
            })
            ->firstOrFail();

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

        $entries = $query->paginate(20, ['*'], 'entries_page');

        // All active accounts for the payment form (target accounts)
        $targetAccounts = ChartOfAccount::where('is_active', true)->where('id', '!=', $account->id)->orderBy('name')->get();

        // Source Accounts for Replenishment Fulfillment (Bank accounts & cash accounts)
        $sourceAccounts = ChartOfAccount::where('is_active', true)
            ->where('id', '!=', $account->id)
            ->where(function ($q) {
                $q->where('type', 'asset')
                  ->orWhereIn('subtype', ['cash_and_bank', 'current_asset', 'bank', 'cash']);
            })
            ->orderBy('name')
            ->get();

        if ($sourceAccounts->isEmpty()) {
            $sourceAccounts = $targetAccounts;
        }

        // ─────────────────────────────────────────────────────────────────────────────
        // IMPREST REPLENISHMENT CYCLE TRACKING
        // "when they next ask next start from this"
        // ─────────────────────────────────────────────────────────────────────────────
        $lastFulfilled = PettyCashReplenishment::where('chart_of_account_id', $account->id)
            ->where('status', PettyCashReplenishment::STATUS_FULFILLED)
            ->latest('fulfilled_at')
            ->first();

        $cycleStartDate = $lastFulfilled ? $lastFulfilled->fulfilled_at : ($account->created_at ?? Carbon::now()->subMonths(6));

        // 1. Fetch Paid Expense Requests in active cycle
        $isPettyCashAccount = (str_contains(strtolower($account->name), 'petty') || $account->code === '1010');

        $paidExpenseRequests = ExpenseRequest::with(['user', 'employee', 'paidBy'])
            ->where(function ($q) {
                $q->where('status', ExpenseRequest::STATUS_PAID)
                  ->orWhere('status', 'Paid')
                  ->orWhere('status', 'paid');
            })
            ->where(function ($q) use ($account, $isPettyCashAccount) {
                $q->where('chart_of_account_id', $account->id)
                  ->orWhere('coa_id', $account->id);

                if ($isPettyCashAccount) {
                    $q->orWhereNull('bank_account_id');
                }
            })
            ->where(function ($q) use ($cycleStartDate) {
                $q->where('paid_at', '>=', $cycleStartDate)
                  ->orWhere(function ($sq) use ($cycleStartDate) {
                      $sq->whereNull('paid_at')->where('updated_at', '>=', $cycleStartDate);
                  });
            })
            ->latest('paid_at')
            ->get();

        // 2. Fetch Direct Ledger Payments (Journal Entry Lines)
        $paymentSide = in_array($account->type, ['asset', 'expense']) ? 'credit' : 'debit';
        $paymentLines = JournalEntryLine::with(['journalEntry.lines.account', 'journalEntry.creator'])
            ->where('account_id', $account->id)
            ->where('side', $paymentSide)
            ->whereHas('journalEntry', function ($q) use ($cycleStartDate) {
                $q->where('created_at', '>=', $cycleStartDate);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // 3. Unify into a single structured collection of active cycle payment items
        $unreplenishedExpenses = collect();

        foreach ($paidExpenseRequests as $req) {
            $applicantName = $req->employee ? $req->employee->full_name : ($req->user->name ?? 'Employee');
            $dept = $req->employee->department ?? ($req->user->department ?? 'General');
            $categoryName = $req->category . ($req->other_reason ? ' (' . $req->other_reason . ')' : '');

            $unreplenishedExpenses->push((object) [
                'source_type'       => 'expense_request',
                'source_id'         => $req->id,
                'date'              => $req->paid_at ?? $req->created_at,
                'reference'         => $req->request_number ?? ('REQ-' . $req->id),
                'requester'         => $applicantName,
                'department'        => $dept,
                'category'          => $categoryName ?: 'Expense Request',
                'description'       => $req->description,
                'amount'            => (float) $req->amount,
                'target_account'    => $categoryName ?: 'Expense Request',
                'payment_reference' => $req->payment_reference,
                'paid_by_name'      => $req->paidBy->name ?? 'Finance',
            ]);
        }

        foreach ($paymentLines as $line) {
            $jeRef = $line->journalEntry?->entry_no ?? '';
            if ($line->journalEntry?->reference_type === 'ExpenseRequest' || $unreplenishedExpenses->contains('reference', $jeRef)) {
                continue;
            }

            $otherLine = $line->journalEntry?->lines?->where('id', '!=', $line->id)->first();
            $targetName = $otherLine?->account ? ($otherLine->account->code . ' - ' . $otherLine->account->name) : 'General Expense';

            $unreplenishedExpenses->push((object) [
                'source_type'       => 'journal_line',
                'source_id'         => $line->id,
                'date'              => $line->journalEntry?->entry_date ?? $line->created_at,
                'reference'         => $line->journalEntry?->entry_no ?? ('REF-' . $line->id),
                'requester'         => $line->journalEntry?->creator->name ?? 'Direct Voucher',
                'department'        => 'Finance / Operations',
                'category'          => 'Direct Cash Payment',
                'description'       => $line->description ?? $line->journalEntry?->description,
                'amount'            => (float) $line->amount,
                'target_account'    => $targetName,
                'payment_reference' => $line->journalEntry?->entry_no,
                'paid_by_name'      => $line->journalEntry?->creator->name ?? 'Custodian',
            ]);
        }

        $unreplenishedExpenses = $unreplenishedExpenses->sortByDesc('date')->values();
        $unreplenishedExpensesTotal = $unreplenishedExpenses->sum('amount');
        $unreplenishedCount = $unreplenishedExpenses->count();

        // Check if there is currently a pending replenishment request
        $pendingReplenishment = PettyCashReplenishment::with(['requester', 'items'])
            ->where('chart_of_account_id', $account->id)
            ->where('status', PettyCashReplenishment::STATUS_PENDING)
            ->latest()
            ->first();

        // Replenishment History list
        $replenishments = PettyCashReplenishment::with(['requester', 'financeHead', 'sourceCoa', 'items'])
            ->where('chart_of_account_id', $account->id)
            ->latest()
            ->paginate(10, ['*'], 'replenishments_page');

        return view('finance.assigned_accounts.show', compact(
            'account',
            'entries',
            'startDate',
            'endDate',
            'targetAccounts',
            'sourceAccounts',
            'lastFulfilled',
            'cycleStartDate',
            'unreplenishedExpenses',
            'unreplenishedExpensesTotal',
            'unreplenishedCount',
            'pendingReplenishment',
            'replenishments',
            'isFinanceHead'
        ));
    }

    public function pay(Request $request, int|string $id)
    {
        self::ensureSchema();

        $isFinanceHead = $this->isFinanceHeadUser();
        $authId = auth()->id();

        $account = ChartOfAccount::where('id', $id)
            ->when(!$isFinanceHead, function ($q) use ($authId) {
                $q->where('assigned_to', $authId);
            })
            ->firstOrFail();

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:payment,receipt',
            'target_account_id' => 'required|exists:chart_of_accounts,id',
            'description' => 'required|string',
            'reference' => 'nullable|string'
        ]);

        DB::transaction(function () use ($request, $account, $authId) {
            $entryCount = JournalEntry::count() + 1;
            $entryNo = 'JE-' . date('Ymd') . '-' . str_pad($entryCount, 4, '0', STR_PAD_LEFT);

            $entry = JournalEntry::create([
                'entry_no' => $entryNo,
                'entry_date' => now(),
                'reference_type' => 'assigned_account_transaction',
                'reference_id' => $account->id,
                'description' => $request->description,
                'status' => 'posted',
                'created_by' => $authId,
                'posted_at' => now(),
            ]);

            $isAssetOrExpense = in_array($account->type, ['asset', 'expense']);
            
            if ($request->type === 'payment') {
                // Payment out of this account
                $thisAccountSide = $isAssetOrExpense ? 'credit' : 'debit';
                $targetAccountSide = $thisAccountSide === 'credit' ? 'debit' : 'credit';
            } else {
                // Receipt into this account
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

            // Update balances
            $targetAccount = ChartOfAccount::find($request->target_account_id);
            
            $account->current_balance += ($thisAccountSide === 'debit' ? $request->amount : -$request->amount) * ($isAssetOrExpense ? 1 : -1);
            $account->save();
            
            $isTargetAssetOrExpense = in_array($targetAccount->type, ['asset', 'expense']);
            $targetAccount->current_balance += ($targetAccountSide === 'debit' ? $request->amount : -$request->amount) * ($isTargetAssetOrExpense ? 1 : -1);
            $targetAccount->save();
        });

        return redirect()->back()->with('success', 'Transaction completed successfully.');
    }

    /**
     * Custodian requests replenishment / asks money from Finance Head.
     * Bundles all payment history since last fulfillment into the request.
     */
    public function requestReplenishment(Request $request, int|string $id)
    {
        self::ensureSchema();

        $isFinanceHead = $this->isFinanceHeadUser();
        $authId = auth()->id();

        $account = ChartOfAccount::where('id', $id)
            ->when(!$isFinanceHead, function ($q) use ($authId) {
                $q->where('assigned_to', $authId);
            })
            ->firstOrFail();

        $request->validate([
            'requested_amount' => 'required|numeric|min:0.01',
            'notes'            => 'nullable|string|max:1000',
            'attachment'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        // Check if there is already a pending replenishment
        $existingPending = PettyCashReplenishment::where('chart_of_account_id', $account->id)
            ->where('status', PettyCashReplenishment::STATUS_PENDING)
            ->first();

        if ($existingPending) {
            return redirect()->back()->with('error', 'There is already a pending replenishment request (' . $existingPending->request_no . ') awaiting Finance Head review.');
        }

        $lastFulfilled = PettyCashReplenishment::where('chart_of_account_id', $account->id)
            ->where('status', PettyCashReplenishment::STATUS_FULFILLED)
            ->latest('fulfilled_at')
            ->first();

        $cycleStartDate = $lastFulfilled ? $lastFulfilled->fulfilled_at : ($account->created_at ?? Carbon::now()->subMonths(6));

        // 1. Fetch Paid Expense Requests
        $isPettyCashAccount = (str_contains(strtolower($account->name), 'petty') || $account->code === '1010');

        $paidExpenseRequests = ExpenseRequest::with(['user', 'employee', 'paidBy'])
            ->where(function ($q) {
                $q->where('status', ExpenseRequest::STATUS_PAID)
                  ->orWhere('status', 'Paid')
                  ->orWhere('status', 'paid');
            })
            ->where(function ($q) use ($account, $isPettyCashAccount) {
                $q->where('chart_of_account_id', $account->id)
                  ->orWhere('coa_id', $account->id);

                if ($isPettyCashAccount) {
                    $q->orWhereNull('bank_account_id');
                }
            })
            ->where(function ($q) use ($cycleStartDate) {
                $q->where('paid_at', '>=', $cycleStartDate)
                  ->orWhere(function ($sq) use ($cycleStartDate) {
                      $sq->whereNull('paid_at')->where('updated_at', '>=', $cycleStartDate);
                  });
            })
            ->latest('paid_at')
            ->get();

        // 2. Fetch Direct Ledger Payments (Journal Entry Lines)
        $paymentSide = in_array($account->type, ['asset', 'expense']) ? 'credit' : 'debit';
        $paymentLines = JournalEntryLine::with(['journalEntry.lines.account', 'journalEntry.creator'])
            ->where('account_id', $account->id)
            ->where('side', $paymentSide)
            ->whereHas('journalEntry', function ($q) use ($cycleStartDate) {
                $q->where('created_at', '>=', $cycleStartDate);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $unreplenishedExpenses = collect();

        foreach ($paidExpenseRequests as $req) {
            $applicantName = $req->employee ? $req->employee->full_name : ($req->user->name ?? 'Employee');
            $dept = $req->employee->department ?? ($req->user->department ?? 'General');
            $categoryName = $req->category . ($req->other_reason ? ' (' . $req->other_reason . ')' : '');

            $unreplenishedExpenses->push((object) [
                'source_type'       => 'expense_request',
                'source_id'         => $req->id,
                'date'              => $req->paid_at ?? $req->created_at,
                'reference'         => $req->request_number ?? ('REQ-' . $req->id),
                'requester'         => $applicantName,
                'department'        => $dept,
                'category'          => $categoryName ?: 'Expense Request',
                'description'       => $req->description,
                'amount'            => (float) $req->amount,
                'target_account'    => $categoryName ?: 'Expense Request',
            ]);
        }

        foreach ($paymentLines as $line) {
            $jeRef = $line->journalEntry?->entry_no ?? '';
            if ($line->journalEntry?->reference_type === 'ExpenseRequest' || $unreplenishedExpenses->contains('reference', $jeRef)) {
                continue;
            }

            $otherLine = $line->journalEntry?->lines?->where('id', '!=', $line->id)->first();
            $targetName = $otherLine?->account ? ($otherLine->account->code . ' - ' . $otherLine->account->name) : 'General Expense';

            $unreplenishedExpenses->push((object) [
                'source_type'       => 'journal_line',
                'source_id'         => $line->id,
                'date'              => $line->journalEntry?->entry_date ?? $line->created_at,
                'reference'         => $line->journalEntry?->entry_no ?? ('REF-' . $line->id),
                'requester'         => $line->journalEntry?->creator->name ?? 'Direct Voucher',
                'department'        => 'Finance / Operations',
                'category'          => 'Direct Cash Payment',
                'description'       => $line->description ?? $line->journalEntry?->description,
                'amount'            => (float) $line->amount,
                'target_account'    => $targetName,
            ]);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = FileUploadService::upload($request->file('attachment'), 'petty_cash_replenishments');
        }

        DB::transaction(function () use ($account, $request, $unreplenishedExpenses, $cycleStartDate, $attachmentPath, $authId) {
            $today = date('Ymd');
            $countToday = PettyCashReplenishment::whereDate('created_at', now())->count() + 1;
            $requestNo = 'PCR-' . $today . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

            $totalExpenses = $unreplenishedExpenses->sum('amount');

            $replenishment = PettyCashReplenishment::create([
                'request_no'                 => $requestNo,
                'chart_of_account_id'        => $account->id,
                'requested_by'               => $authId,
                'requested_amount'           => $request->requested_amount,
                'current_balance_at_request' => $account->current_balance,
                'total_expenses_amount'      => $totalExpenses,
                'period_start_date'          => $cycleStartDate,
                'period_end_date'            => now(),
                'status'                     => PettyCashReplenishment::STATUS_PENDING,
                'notes'                      => $request->notes,
                'attachment_path'            => $attachmentPath,
            ]);

            foreach ($unreplenishedExpenses as $item) {
                PettyCashReplenishmentItem::create([
                    'petty_cash_replenishment_id' => $replenishment->id,
                    'journal_entry_line_id'       => $item->source_type === 'journal_line' ? $item->source_id : null,
                    'entry_date'                  => $item->date,
                    'reference'                   => $item->reference,
                    'description'                 => ($item->requester ? '[' . $item->requester . '] ' : '') . $item->description,
                    'target_account_name'         => $item->category ?: $item->target_account,
                    'amount'                      => $item->amount,
                    'side'                        => 'credit',
                ]);
            }

            // Log to Audit Trail
            \App\Models\ActivityLog::log(
                'created',
                "Petty Cash Replenishment #{$replenishment->request_no} submitted by " . (auth()->user()->name ?? 'Custodian') . " for ETB " . number_format($replenishment->requested_amount, 2) . " ([{$account->code}] {$account->name}) with " . $unreplenishedExpenses->count() . " attached expense vouchers.",
                'Finance & Petty Cash Audit',
                $replenishment,
                [
                    'request_no'       => $replenishment->request_no,
                    'account'          => "[{$account->code}] {$account->name}",
                    'requested_amount' => (float)$replenishment->requested_amount,
                    'expenses_count'   => $unreplenishedExpenses->count(),
                    'total_expenses'   => (float)$unreplenishedExpenses->sum('amount'),
                ]
            );
        });

        return redirect()->back()->with('success', 'Petty Cash replenishment request sent to Finance Head with ' . $unreplenishedExpenses->count() . ' attached expense records (ETB ' . number_format($unreplenishedExpenses->sum('amount'), 2) . ').');
    }

    /**
     * Finance Head approves & fulfills money into the Petty Cash account.
     * Transfers money from source account (Bank/Cash), creates posted Journal Entry, and updates milestone.
     */
    public function fulfillReplenishment(Request $request, int|string $id, int|string $replenishmentId)
    {
        self::ensureSchema();

        $isFinanceHead = $this->isFinanceHeadUser();
        if (!$isFinanceHead) {
            abort(403, 'Unauthorized. Only Finance Head or Admin can fulfill replenishment requests.');
        }

        $account = ChartOfAccount::findOrFail($id);
        $replenishment = PettyCashReplenishment::where('id', $replenishmentId)
            ->where('chart_of_account_id', $account->id)
            ->where('status', PettyCashReplenishment::STATUS_PENDING)
            ->firstOrFail();

        $request->validate([
            'source_coa_id'    => 'required|exists:chart_of_accounts,id',
            'fulfilled_amount' => 'required|numeric|min:0.01',
            'reference'        => 'nullable|string|max:100',
            'finance_notes'    => 'nullable|string|max:1000',
        ]);

        $sourceCoa = ChartOfAccount::findOrFail($request->source_coa_id);
        $amount = (float) $request->fulfilled_amount;
        $authId = auth()->id();

        DB::transaction(function () use ($account, $sourceCoa, $replenishment, $amount, $request, $authId) {
            // 1. Update balances
            // Source Account (Bank/Cash) is credited (decreased if asset)
            $isSourceAssetOrExpense = in_array($sourceCoa->type, ['asset', 'expense']);
            if ($isSourceAssetOrExpense) {
                $sourceCoa->decrement('current_balance', $amount);
            } else {
                $sourceCoa->increment('current_balance', $amount);
            }

            // Petty Cash Account is debited (increased if asset)
            $isPettyAssetOrExpense = in_array($account->type, ['asset', 'expense']);
            if ($isPettyAssetOrExpense) {
                $account->increment('current_balance', $amount);
            } else {
                $account->decrement('current_balance', $amount);
            }

            // 2. Create Journal Entry
            $jeCount = JournalEntry::count() + 1;
            $jeNo = 'JE-' . date('Ymd') . '-' . str_pad($jeCount, 4, '0', STR_PAD_LEFT);

            $journalEntry = JournalEntry::create([
                'entry_no'       => $jeNo,
                'entry_date'     => now(),
                'reference_type' => 'petty_cash_replenishment',
                'reference_id'   => $replenishment->id,
                'description'    => "Petty Cash Replenishment #{$replenishment->request_no}: [{$sourceCoa->code}] {$sourceCoa->name} → [{$account->code}] {$account->name} | " . ($request->finance_notes ?? 'Imprest Replenishment Fulfillment'),
                'status'         => 'posted',
                'created_by'     => $authId,
                'approved_by'    => $authId,
                'posted_at'      => now(),
            ]);

            // Debit Petty Cash (Asset increase)
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id'       => $account->id,
                'description'      => "Replenishment Top-up from [{$sourceCoa->code}] {$sourceCoa->name}",
                'side'             => $isPettyAssetOrExpense ? 'debit' : 'credit',
                'amount'           => $amount,
            ]);

            // Credit Source Account (Asset decrease)
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id'       => $sourceCoa->id,
                'description'      => "Disbursement for Petty Cash Replenishment #{$replenishment->request_no}",
                'side'             => $isSourceAssetOrExpense ? 'credit' : 'debit',
                'amount'           => $amount,
            ]);

            // 3. Mark replenishment as fulfilled
            $replenishment->update([
                'status'                => PettyCashReplenishment::STATUS_FULFILLED,
                'finance_head_id'       => $authId,
                'fulfilled_amount'      => $amount,
                'source_coa_id'         => $sourceCoa->id,
                'journal_entry_id'      => $journalEntry->id,
                'finance_notes'         => $request->finance_notes,
                'fulfillment_reference' => $request->reference,
                'fulfilled_at'          => now(),
            ]);

            // Sync with ExpenseRequest if exists
            $expReq = \App\Models\ExpenseRequest::where('request_number', 'EXP-PCR-' . $replenishment->request_no)->first();
            if ($expReq) {
                $expReq->update([
                    'status'            => \App\Models\ExpenseRequest::STATUS_PAID,
                    'paid_by'           => $authId,
                    'paid_at'           => now(),
                    'payment_reference' => $request->reference ?? $jeNo,
                    'bank_account_id'   => \App\Models\BankAccount::where('coa_id', $sourceCoa->id)->value('id'),
                ]);
            }

            // 4. Log to Audit Trail / ActivityLog for Auditor oversight
            \App\Models\ActivityLog::log(
                'approved',
                "Petty Cash Replenishment #{$replenishment->request_no} approved & fulfilled: Disbursed ETB " . number_format($amount, 2) . " from [{$sourceCoa->code}] {$sourceCoa->name} into [{$account->code}] {$account->name} for custodian " . ($replenishment->requester->name ?? 'Staff') . " ({$replenishment->items()->count()} attached expense records reconciled). Journal Entry: {$jeNo}",
                'Finance & Petty Cash Audit',
                $replenishment,
                [
                    'request_no'        => $replenishment->request_no,
                    'fulfilled_amount'  => $amount,
                    'petty_cash_account'=> "[{$account->code}] {$account->name}",
                    'source_account'    => "[{$sourceCoa->code}] {$sourceCoa->name}",
                    'custodian'         => $replenishment->requester->name ?? 'Staff',
                    'vouchers_count'    => $replenishment->items()->count(),
                    'journal_entry'     => $jeNo,
                    'reference'         => $request->reference,
                    'finance_head'      => auth()->user()->name ?? 'Finance Head',
                ]
            );
        });

        return redirect()->back()->with('success', "Replenishment #{$replenishment->request_no} fulfilled successfully! ETB " . number_format($amount, 2) . " disbursed into {$account->name}. Next cycle will start from this fulfillment point.");
    }

    /**
     * Finance Head rejects replenishment request.
     */
    public function rejectReplenishment(Request $request, int|string $id, int|string $replenishmentId)
    {
        self::ensureSchema();

        $isFinanceHead = $this->isFinanceHeadUser();
        if (!$isFinanceHead) {
            abort(403, 'Unauthorized. Only Finance Head or Admin can reject replenishment requests.');
        }

        $account = ChartOfAccount::findOrFail($id);
        $replenishment = PettyCashReplenishment::where('id', $replenishmentId)
            ->where('chart_of_account_id', $account->id)
            ->where('status', PettyCashReplenishment::STATUS_PENDING)
            ->firstOrFail();

        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $replenishment->update([
            'status'           => PettyCashReplenishment::STATUS_REJECTED,
            'finance_head_id'  => auth()->id(),
            'rejected_at'      => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        // Log to Audit Trail
        \App\Models\ActivityLog::log(
            'rejected',
            "Petty Cash Replenishment #{$replenishment->request_no} (ETB " . number_format($replenishment->requested_amount, 2) . ") rejected by Finance Head. Reason: {$request->rejection_reason}",
            'Finance & Petty Cash Audit',
            $replenishment,
            [
                'request_no'        => $replenishment->request_no,
                'requested_amount'  => (float)$replenishment->requested_amount,
                'petty_cash_account'=> "[{$account->code}] {$account->name}",
                'custodian'         => $replenishment->requester->name ?? 'Staff',
                'rejection_reason'  => $request->rejection_reason,
            ]
        );

        return redirect()->back()->with('warning', "Replenishment #{$replenishment->request_no} has been rejected.");
    }

    /**
     * Get replenishment details & items JSON for modal view.
     */
    public function getReplenishmentDetails(int|string $id, int|string $replenishmentId)
    {
        self::ensureSchema();

        $isFinanceHead = $this->isFinanceHeadUser();
        $isAuditor = $this->isAuditorUser();
        $authId = auth()->id();

        $account = ChartOfAccount::where('id', $id)
            ->when(!$isFinanceHead && !$isAuditor, function ($q) use ($authId) {
                $q->where('assigned_to', $authId);
            })
            ->firstOrFail();

        $replenishment = PettyCashReplenishment::with(['requester', 'financeHead', 'sourceCoa', 'items'])
            ->where('chart_of_account_id', $account->id)
            ->where('id', $replenishmentId)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'replenishment' => $replenishment,
            'items' => $replenishment->items,
            'attachment_url' => $replenishment->attachment_url,
        ]);
    }

    /**
     * Finance Head/Auditor reviews vouchers and sends replenishment to Audit Team.
     */
    public function sendReplenishmentToAudit(Request $request, int|string $id, int|string $replenishmentId)
    {
        self::ensureSchema();

        $isFinanceHead = $this->isFinanceHeadUser();
        $isAuditor = $this->isAuditorUser();
        if (!$isFinanceHead && !$isAuditor) {
            abort(403, 'Unauthorized.');
        }

        $account = ChartOfAccount::findOrFail($id);
        $replenishment = PettyCashReplenishment::where('id', $replenishmentId)
            ->where('chart_of_account_id', $account->id)
            ->firstOrFail();

        $request->validate([
            'audit_notes' => 'nullable|string|max:2000',
        ]);

        $replenishment->update([
            'status'      => PettyCashReplenishment::STATUS_UNDER_AUDIT,
            'audit_notes' => $request->audit_notes,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        \App\Models\ActivityLog::log(
            'audited',
            "Petty Cash Replenishment #{$replenishment->request_no} reviewed and sent to Internal Audit Team by " . (auth()->user()->name ?? 'Finance') . ". Notes: " . ($request->audit_notes ?? 'None'),
            'Finance & Petty Cash Audit',
            $replenishment,
            [
                'request_no'       => $replenishment->request_no,
                'requested_amount' => (float)$replenishment->requested_amount,
                'account'          => "[{$account->code}] {$account->name}",
                'custodian'        => $replenishment->requester->name ?? 'Staff',
                'audit_notes'      => $request->audit_notes,
            ]
        );

        return redirect()->back()->with('success', "Replenishment #{$replenishment->request_no} has been reviewed and successfully routed to Audit Team!");
    }

    /**
     * Internal Auditor grants Audit Clearance / Approval for replenishment.
     */
    public function auditApproveReplenishment(Request $request, int|string $id, int|string $replenishmentId)
    {
        self::ensureSchema();

        $isFinanceHead = $this->isFinanceHeadUser();
        $isAuditor = $this->isAuditorUser();
        if (!$isFinanceHead && !$isAuditor) {
            abort(403, 'Unauthorized. Only internal audit and authorized finance managers can approve audit clearance.');
        }

        $account = ChartOfAccount::findOrFail($id);
        $replenishment = PettyCashReplenishment::where('id', $replenishmentId)
            ->where('chart_of_account_id', $account->id)
            ->firstOrFail();

        $request->validate([
            'replenishment_amount' => 'nullable|numeric|min:0.01',
            'audit_notes'          => 'nullable|string|max:2000',
        ]);

        $approvedAmount = $request->filled('replenishment_amount') 
            ? (float)$request->replenishment_amount 
            : ((float)($replenishment->requested_amount ?: $replenishment->total_expenses_amount));

        if ($approvedAmount <= 0) {
            $approvedAmount = (float)$replenishment->total_expenses_amount;
        }

        $replenishment->update([
            'requested_amount' => $approvedAmount,
            'status'           => PettyCashReplenishment::STATUS_PENDING, // Ready for GM review / Finance Head fulfillment
            'audited_by'       => auth()->id(),
            'audited_at'       => now(),
            'audit_notes'      => $request->audit_notes ?: $replenishment->audit_notes,
        ]);

        // Route / Create Expense Request for GM Approval in Expense Approvals section
        $expReqNumber = 'EXP-PCR-' . $replenishment->request_no;
        \App\Models\ExpenseRequest::updateOrCreate(
            [
                'request_number' => $expReqNumber,
            ],
            [
                'user_id'             => $replenishment->requested_by ?? auth()->id(),
                'employee_id'         => $replenishment->requester?->employee?->id ?? null,
                'category'            => \App\Models\ExpenseRequest::CATEGORY_OTHER,
                'other_reason'        => 'Petty Cash Replenishment',
                'amount'              => $approvedAmount,
                'gross_amount'        => $approvedAmount,
                'net_amount'          => $approvedAmount,
                'description'         => "Petty Cash Replenishment #{$replenishment->request_no} for [{$account->code}] {$account->name}. Verified & Cleared by Internal Audit (" . (auth()->user()->name ?? 'Auditor') . ")." . ($request->audit_notes ? " Audit Remarks: " . $request->audit_notes : ""),
                'status'              => \App\Models\ExpenseRequest::STATUS_PENDING_GM,
                'chart_of_account_id' => $account->id,
                'coa_id'              => $account->id,
            ]
        );

        \App\Models\ActivityLog::log(
            'audit_cleared',
            "Petty Cash Replenishment #{$replenishment->request_no} cleared and approved by Internal Auditor " . (auth()->user()->name ?? 'Auditor') . " for ETB " . number_format($approvedAmount, 2) . " and routed to GM Expense Approvals queue. Audit Notes: " . ($request->audit_notes ?? 'No observations.'),
            'Finance & Petty Cash Audit',
            $replenishment,
            [
                'request_no'       => $replenishment->request_no,
                'requested_amount' => $approvedAmount,
                'account'          => "[{$account->code}] {$account->name}",
                'custodian'        => $replenishment->requester->name ?? 'Staff',
                'auditor'          => auth()->user()->name ?? 'Auditor',
                'audit_notes'      => $request->audit_notes,
                'expense_request'  => $expReqNumber,
            ]
        );

        return redirect()->back()->with('success', "Replenishment #{$replenishment->request_no} (ETB " . number_format($approvedAmount, 2) . ") has been audited & sent to General Manager (GM) for approval in the Expense Approval section!");
    }

    /**
     * Internal Auditor rejects replenishment cycle back to custodian.
     */
    public function auditRejectReplenishment(Request $request, int|string $id, int|string $replenishmentId)
    {
        self::ensureSchema();

        $isFinanceHead = $this->isFinanceHeadUser();
        $isAuditor = $this->isAuditorUser();
        if (!$isFinanceHead && !$isAuditor) {
            abort(403, 'Unauthorized. Only internal audit and authorized finance managers can reject replenishments.');
        }

        $account = ChartOfAccount::findOrFail($id);
        $replenishment = PettyCashReplenishment::where('id', $replenishmentId)
            ->where('chart_of_account_id', $account->id)
            ->firstOrFail();

        $request->validate([
            'rejection_reason' => 'required|string|max:1500',
        ]);

        $replenishment->update([
            'status'           => PettyCashReplenishment::STATUS_REJECTED,
            'audited_by'       => auth()->id(),
            'audited_at'       => now(),
            'rejected_at'      => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        \App\Models\ActivityLog::log(
            'audit_rejected',
            "Petty Cash Replenishment #{$replenishment->request_no} rejected during Internal Audit by " . (auth()->user()->name ?? 'Auditor') . ". Reason: " . $request->rejection_reason,
            'Finance & Petty Cash Audit',
            $replenishment,
            [
                'request_no'       => $replenishment->request_no,
                'account'          => "[{$account->code}] {$account->name}",
                'custodian'        => $replenishment->requester->name ?? 'Staff',
                'rejection_reason' => $request->rejection_reason,
            ]
        );

        return redirect()->back()->with('warning', "Replenishment cycle #{$replenishment->request_no} rejected by Internal Audit.");
    }

    /**
     * Reject an individual voucher item from replenishment.
     */
    public function rejectVoucherItem(Request $request, int|string $itemId)

    {
        self::ensureSchema();

        $item = PettyCashReplenishmentItem::with('replenishment.account')->findOrFail($itemId);
        $replenishment = $item->replenishment;

        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $item->update([
            'status'           => PettyCashReplenishmentItem::STATUS_REJECTED,
            'rejection_reason' => $request->rejection_reason,
        ]);

        // Recalculate valid total expenses for the replenishment
        $validTotal = $replenishment->items()->where('status', '!=', PettyCashReplenishmentItem::STATUS_REJECTED)->sum('amount');
        $replenishment->update([
            'total_expenses_amount' => $validTotal,
        ]);

        \App\Models\ActivityLog::log(
            'rejected',
            "Voucher #{$item->reference} (ETB " . number_format($item->amount, 2) . ") in Replenishment #{$replenishment->request_no} was rejected. Reason: {$request->rejection_reason}",
            'Finance & Petty Cash Audit',
            $replenishment
        );

        return redirect()->back()->with('warning', "Voucher #{$item->reference} (ETB " . number_format($item->amount, 2) . ") rejected and excluded from replenishment cycle.");
    }

    /**
     * Ask description / request clarification on an individual voucher item.
     */
    public function askVoucherDescription(Request $request, int|string $itemId)
    {
        self::ensureSchema();

        $item = PettyCashReplenishmentItem::with('replenishment.account')->findOrFail($itemId);
        $replenishment = $item->replenishment;

        $request->validate([
            'inquiry_note' => 'required|string|max:1000',
        ]);

        $item->update([
            'status'       => PettyCashReplenishmentItem::STATUS_CLARIFICATION_NEEDED,
            'inquiry_note' => $request->inquiry_note,
        ]);

        \App\Models\ActivityLog::log(
            'clarification_requested',
            "Clarification / description requested on Voucher #{$item->reference} (Replenishment #{$replenishment->request_no}): {$request->inquiry_note}",
            'Finance & Petty Cash Audit',
            $replenishment
        );

        return redirect()->back()->with('info', "Description / clarification request recorded for Voucher #{$item->reference}.");
    }

    /**
     * Approve / restore a voucher item.
     */
    public function approveVoucherItem(Request $request, int|string $itemId)
    {
        self::ensureSchema();

        $item = PettyCashReplenishmentItem::with('replenishment.account')->findOrFail($itemId);
        $replenishment = $item->replenishment;

        $item->update([
            'status'           => PettyCashReplenishmentItem::STATUS_APPROVED,
            'rejection_reason' => null,
        ]);

        $validTotal = $replenishment->items()->where('status', '!=', PettyCashReplenishmentItem::STATUS_REJECTED)->sum('amount');
        $replenishment->update([
            'total_expenses_amount' => $validTotal,
        ]);

        return redirect()->back()->with('success', "Voucher #{$item->reference} verified and restored.");
    }

    /**
     * Bulk action on selected vouchers (bulk reject or bulk ask description).
     */
    public function bulkVoucherAction(Request $request, int|string $id, int|string $replenishmentId)
    {
        self::ensureSchema();

        $request->validate([
            'voucher_ids'   => 'required|array|min:1',
            'voucher_ids.*' => 'exists:petty_cash_replenishment_items,id',
            'bulk_action'   => 'required|in:reject,ask_description,approve',
            'bulk_note'     => 'nullable|string|max:1000',
        ]);

        $replenishment = PettyCashReplenishment::where('id', $replenishmentId)->firstOrFail();
        $items = PettyCashReplenishmentItem::whereIn('id', $request->voucher_ids)
            ->where('petty_cash_replenishment_id', $replenishment->id)
            ->get();

        if ($request->bulk_action === 'reject') {
            $reason = $request->bulk_note ?: 'Excluded during finance review.';
            foreach ($items as $item) {
                $item->update([
                    'status'           => PettyCashReplenishmentItem::STATUS_REJECTED,
                    'rejection_reason' => $reason,
                ]);
            }
            $validTotal = $replenishment->items()->where('status', '!=', PettyCashReplenishmentItem::STATUS_REJECTED)->sum('amount');
            $replenishment->update(['total_expenses_amount' => $validTotal]);
            return redirect()->back()->with('warning', count($items) . " voucher(s) rejected and excluded from replenishment.");
        } elseif ($request->bulk_action === 'ask_description') {
            $inquiry = $request->bulk_note ?: 'Please provide more details on this expenditure.';
            foreach ($items as $item) {
                $item->update([
                    'status'       => PettyCashReplenishmentItem::STATUS_CLARIFICATION_NEEDED,
                    'inquiry_note' => $inquiry,
                ]);
            }
            return redirect()->back()->with('info', "Clarification requested on " . count($items) . " voucher(s).");
        } else {
            foreach ($items as $item) {
                $item->update([
                    'status'           => PettyCashReplenishmentItem::STATUS_APPROVED,
                    'rejection_reason' => null,
                ]);
            }
            $validTotal = $replenishment->items()->where('status', '!=', PettyCashReplenishmentItem::STATUS_REJECTED)->sum('amount');
            $replenishment->update(['total_expenses_amount' => $validTotal]);
            return redirect()->back()->with('success', count($items) . " voucher(s) approved and verified.");
        }
    }

    /**
     * Finance Head & Auditor: Central Petty Cash Replenishments Oversight & Approval Hub
     */
    public function replenishmentsIndex(Request $request)
    {
        self::ensureSchema();

        $isFinanceHead = $this->isFinanceHeadUser();
        $isAuditor = $this->isAuditorUser();
        if (!$isFinanceHead && !$isAuditor) {
            abort(403, 'Unauthorized. Only Finance Head, Auditor, or Admin can access the Replenishments Hub.');
        }


        $query = PettyCashReplenishment::with(['chartOfAccount.manager', 'requester', 'financeHead', 'sourceCoa', 'items']);

        // Quick Filter Tabs: 'pending' (Finance Head queue) vs 'history' (All processed/in-progress cycles)
        $activeTab = $request->input('tab', 'pending');
        if ($activeTab === 'pending') {
            $query->where('status', PettyCashReplenishment::STATUS_PENDING);
        } elseif ($activeTab === 'history') {
            $query->where('status', '!=', PettyCashReplenishment::STATUS_PENDING);
        } elseif ($activeTab === 'under_audit') {
            $query->where('status', PettyCashReplenishment::STATUS_UNDER_AUDIT);
        } elseif ($activeTab === 'fulfilled') {
            $query->where('status', PettyCashReplenishment::STATUS_FULFILLED);
        } elseif ($activeTab === 'rejected') {
            $query->where('status', PettyCashReplenishment::STATUS_REJECTED);
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('requester', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('chartOfAccount', function ($caq) use ($search) {
                      $caq->where('name', 'like', "%{$search}%")
                          ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }

        // Date Filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $replenishments = $query->latest()->paginate(15)->withQueryString();

        // Metrics & Tab counts
        $tabCounts = [
            'all'         => PettyCashReplenishment::count(),
            'pending'     => PettyCashReplenishment::where('status', PettyCashReplenishment::STATUS_PENDING)->count(),
            'history'     => PettyCashReplenishment::where('status', '!=', PettyCashReplenishment::STATUS_PENDING)->count(),
            'under_audit' => PettyCashReplenishment::where('status', PettyCashReplenishment::STATUS_UNDER_AUDIT)->count(),
            'fulfilled'   => PettyCashReplenishment::where('status', PettyCashReplenishment::STATUS_FULFILLED)->count(),
            'rejected'    => PettyCashReplenishment::where('status', PettyCashReplenishment::STATUS_REJECTED)->count(),
        ];

        $pendingAmount = PettyCashReplenishment::where('status', PettyCashReplenishment::STATUS_PENDING)->sum('requested_amount');
        $fulfilledMonthAmount = PettyCashReplenishment::where('status', PettyCashReplenishment::STATUS_FULFILLED)
            ->whereMonth('fulfilled_at', Carbon::now()->month)
            ->whereYear('fulfilled_at', Carbon::now()->year)
            ->sum('fulfilled_amount');

        // Source Accounts for Disbursement Top-up
        $sourceAccounts = ChartOfAccount::where('is_active', true)
            ->where(function ($q) {
                $q->where('type', 'asset')
                  ->orWhere('name', 'like', '%Cash%')
                  ->orWhere('name', 'like', '%Bank%')
                  ->orWhere('subtype', 'like', '%Cash%')
                  ->orWhere('subtype', 'like', '%Bank%');
            })
            ->orderBy('name')
            ->get();


        return view('finance.replenishments.index', compact(
            'replenishments',
            'activeTab',
            'tabCounts',
            'pendingAmount',
            'fulfilledMonthAmount',
            'sourceAccounts'
        ));
    }
}


