<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterRecipient;
use App\Models\LetterNotification;
use App\Models\User;
use App\Models\LetterActionLog;
use App\Models\LetterSequence;
use App\Services\ProcurementSmsService;
use App\Services\SmsEthiopiaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class LetterController extends Controller
{
    /**
     * Correspondence Dashboard (Secretary & Admin)
     */
    public function dashboard()
    {
        $user = Auth::user();

        $metrics = [
            'total'     => Letter::count(),
            'incoming'  => Letter::where('type', Letter::TYPE_INCOMING)->count(),
            'outgoing'  => Letter::where('type', Letter::TYPE_OUTGOING)->count(),
            'pending'   => Letter::whereIn('status', [Letter::STATUS_PENDING, Letter::STATUS_REDIRECTED])->count(),
            'closed'    => Letter::where('status', Letter::STATUS_CLOSED)->count(),
            'my_inbox'  => $this->getMyInboxQuery($user)->where('letters.status', '!=', Letter::STATUS_CLOSED)->count(),
        ];

        // Recent Letters
        $recentLetters = Letter::with(['creator', 'latestRecipient.toUser'])
            ->latest('id')
            ->take(10)
            ->get();

        // Monthly Breakdown for chart (last 6 months)
        $monthlyStats = Letter::selectRaw("DATE_FORMAT(date, '%Y-%m') as month_year, type, COUNT(*) as count")
            ->where('date', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month_year', 'type')
            ->orderBy('month_year', 'asc')
            ->get();

        return view('letters.dashboard', compact('metrics', 'recentLetters', 'monthlyStats'));
    }

    /**
     * Correspondence Inbox & Listing (All Users)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $tab = $request->query('tab', 'inbox'); // 'inbox', 'sent', 'all'
        $userRoles = $user->getRoleNames()->toArray();
        $isAdminOrSecretary = $user->hasRole(['admin', 'global_admin', 'secretary']);

        // Build query based on active tab
        if ($tab === 'sent') {
            $query = Letter::with(['creator', 'latestRecipient.toUser', 'attachments'])
                ->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                        ->orWhereHas('recipients', fn($rq) => $rq->where('from_user_id', $user->id));
                });
        } elseif ($tab === 'all' && $isAdminOrSecretary) {
            $query = Letter::with(['creator', 'latestRecipient.toUser', 'attachments']);
        } else {
            // My Inbox: letters directly assigned to user OR assigned to one of user's roles
            $tab = 'inbox';
            $query = $this->getMyInboxQuery($user);
        }

        // Filters
        if ($request->filled('type')) {
            $query->where('letters.type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('letters.status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('letters.priority', $request->priority);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('letters.date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('letters.date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('letters.letter_number', 'like', "%{$search}%")
                    ->orWhere('letters.subject', 'like', "%{$search}%")
                    ->orWhere('letters.sender', 'like', "%{$search}%")
                    ->orWhere('letters.sender_department', 'like', "%{$search}%")
                    ->orWhere('letters.recipient_organization', 'like', "%{$search}%")
                    ->orWhere('letters.specification', 'like', "%{$search}%");
            });
        }

        $letters = $query->latest('letters.id')->paginate(15)->withQueryString();

        // Counters for tabs
        $inboxCount = $this->getMyInboxQuery($user)->where('letters.status', '!=', Letter::STATUS_CLOSED)->count();
        $sentCount = Letter::where('created_by', $user->id)
            ->orWhereHas('recipients', fn($rq) => $rq->where('from_user_id', $user->id))
            ->count();
        $allCount = $isAdminOrSecretary ? Letter::count() : 0;

        return view('letters.index', compact('letters', 'tab', 'inboxCount', 'sentCount', 'allCount', 'isAdminOrSecretary'));
    }

    /**
     * Show Letter Composition Form (Secretary & Admin)
     */
    public function create(Request $request)
    {
        $defaultType = $request->query('type', Letter::TYPE_INCOMING);
        $suggestedNumber = Letter::generateSuggestedNumber($defaultType);

        $users = User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        try {
            $roles = Role::orderBy('name')->pluck('name')->toArray();
        } catch (\Throwable $e) {
            $roles = ['admin', 'manager', 'secretary', 'finance', 'site_engineer', 'hr', 'planning', 'store_manager'];
        }

        return view('letters.create', compact('defaultType', 'suggestedNumber', 'users', 'roles'));
    }

    /**
     * Store New Letter & Multi-File Attachments
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'                   => 'required|in:incoming,outgoing',
            'letter_number'          => 'required|string|max:60|unique:letters,letter_number',
            'date'                   => 'required|date',
            'subject'                => 'required|string|max:255',
            'specification'          => 'required|string',
            'sender'                 => 'nullable|string|max:255',
            'sender_department'      => 'nullable|string|max:100',
            'recipient_organization' => 'nullable|string|max:255',
            'priority'               => 'required|in:normal,urgent',
            'send_target_type'       => 'required|in:user,role',
            'to_user_id'             => 'required_if:send_target_type,user|nullable|exists:users,id',
            'to_role_name'           => 'required_if:send_target_type,role|nullable|string',
            'initial_notes'          => 'nullable|string|max:1000',
            'attachments.*'          => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:10240', // max 10MB per file
        ]);

        $user = Auth::user();

        DB::beginTransaction();
        try {
            // 1. Create Letter Record
            $letter = Letter::create([
                'letter_number'          => $validated['letter_number'],
                'type'                   => $validated['type'],
                'date'                   => $validated['date'],
                'subject'                => $validated['subject'],
                'specification'          => $validated['specification'],
                'sender'                 => $validated['sender'] ?? null,
                'sender_department'      => $validated['sender_department'] ?? null,
                'recipient_organization' => $validated['recipient_organization'] ?? null,
                'priority'               => $validated['priority'],
                'status'                 => Letter::STATUS_PENDING,
                'created_by'             => $user->id,
            ]);

            // 2. Handle Multi-File Attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if ($file->isValid()) {
                        $originalName = $file->getClientOriginalName();
                        $ext = strtolower($file->getClientOriginalExtension());
                        $size = $file->getSize();

                        $path = \App\Services\FileUploadService::upload($file, 'correspondence');

                        LetterAttachment::create([
                            'letter_id'   => $letter->id,
                            'file_path'   => $path,
                            'file_name'   => $originalName,
                            'file_type'   => $ext,
                            'file_size'   => $size,
                            'uploaded_by' => $user->id,
                        ]);
                    }
                }
            }

            // 3. Create Routing / Recipient Entry
            $toUserId = ($validated['send_target_type'] === 'user') ? $validated['to_user_id'] : null;
            $toRoleName = ($validated['send_target_type'] === 'role') ? $validated['to_role_name'] : null;

            LetterRecipient::create([
                'letter_id'    => $letter->id,
                'from_user_id' => $user->id,
                'to_user_id'   => $toUserId,
                'to_role_name' => $toRoleName,
                'action'       => 'initial_sent',
                'notes'        => $validated['initial_notes'] ?? null,
                'status'       => Letter::STATUS_PENDING,
            ]);

            // 4. Create In-App Notifications & Send SMS for Target Users
            $this->createNotificationsForRecipient(
                $letter,
                $toUserId,
                $toRoleName,
                "New letter {$letter->letter_number} sent to you by {$user->name}: {$letter->subject}",
                'initial_sent',
                $validated['initial_notes'] ?? null
            );

            DB::commit();

            return redirect()->route('letters.show', $letter->id)
                ->with('success', "Letter {$letter->letter_number} created and dispatched successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to create letter: ' . $e->getMessage());
        }
    }

    /**
     * Show Letter Detail View, Attachments Preview & Routing Timeline
     */
    public function show(Letter $letter)
    {
        $user = Auth::user();

        // Access check
        if (!$letter->isAccessibleBy($user)) {
            abort(403, 'Unauthorized access to this letter.');
        }

        $letter->load([
            'creator',
            'closer',
            'payer',
            'chartOfAccount',
            'bankAccount',
            'expenseRequest',
            'expense',
            'attachments.uploader',
            'recipients.fromUser',
            'recipients.toUser',
            'addressedTo',
            'registeredBy',
            'actionLogs.user',
        ]);

        // Mark viewed for current user if not marked yet
        $userRoles = $user->getRoleNames()->toArray();
        $unviewed = $letter->recipients()
            ->where(function ($q) use ($user, $userRoles) {
                $q->where('to_user_id', $user->id)
                    ->orWhereIn('to_role_name', $userRoles);
            })
            ->whereNull('viewed_at')
            ->first();

        if ($unviewed) {
            $unviewed->update(['viewed_at' => now(), 'status' => Letter::STATUS_VIEWED]);
            if ($letter->status === Letter::STATUS_PENDING) {
                $letter->update(['status' => Letter::STATUS_VIEWED]);
            }
        }

        // Mark notifications as read for current user
        LetterNotification::where('letter_id', $letter->id)
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        // Prepare users and roles for redirect modal
        $users = User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        try {
            $roles = Role::orderBy('name')->pluck('name')->toArray();
        } catch (\Throwable $e) {
            $roles = ['admin', 'manager', 'secretary', 'finance', 'site_engineer', 'hr', 'planning', 'store_manager'];
        }

        // Cash and Bank accounts from Chart of Accounts for payment disbursement
        $cashAndBankAccounts = \App\Models\ChartOfAccount::where('is_active', true)
            ->where(function ($q) {
                $q->where('subtype', 'Cash and Bank')
                    ->orWhere('subtype', 'like', '%Cash%')
                    ->orWhere('subtype', 'like', '%Bank%')
                    ->orWhere('type', 'Asset');
            })
            ->orderBy('name')
            ->get();

        $bankAccounts = \App\Models\BankAccount::where('is_active', true)->orderBy('bank_name')->get();

        $expenseCategories = [
            'Service'            => 'Service (አገልግሎት)',
            'Transport'          => 'Transport (ትራንስፖርት)',
            'Loading & Unloading' => 'Loading & Unloading (መጫን እና ማውረድ)',
            'Contract Work'      => 'Contract Work (የኮንትራት ስራ)',
            'Office Material'    => 'Office Material (የቢሮ እቃ)',
            'Maintenance'        => 'Maintenance & Repairs (ጥገና)',
            'Other'              => 'Other Expense (ሌሎች ወጪዎች)',
        ];

        $projects = \App\Models\Project::where('status', '!=', 'cancelled')->orderBy('name')->get();

        $isFinanceOrAdmin = $user->hasAnyRole([
            'admin',
            'global_admin',
            'gm',
            'finance_head',
            'finance_manager',
            'finance',
            'finance_staff',
            'accountant',
            'cashier'
        ]);

        $isRedirectedToFinance = $letter->recipients()
            ->where(function ($q) {
                $q->where('to_role_name', 'like', '%finance%')
                    ->orWhere('to_role_name', 'like', '%cashier%')
                    ->orWhere('to_role_name', 'like', '%accountant%');
            })
            ->exists();

        return view('letters.show', compact(
            'letter',
            'users',
            'roles',
            'cashAndBankAccounts',
            'bankAccounts',
            'expenseCategories',
            'projects',
            'isFinanceOrAdmin',
            'isRedirectedToFinance'
        ));
    }

    /**
     * Redirect / Forward Letter to Another Person or Role
     */
    public function redirectLetter(Request $request, Letter $letter)
    {
        $user = Auth::user();

        if (!$letter->isAccessibleBy($user)) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'send_target_type' => 'required|in:user,role',
            'to_user_id'       => 'required_if:send_target_type,user|nullable|exists:users,id',
            'to_role_name'     => 'required_if:send_target_type,role|nullable|string',
            'redirection_notes' => 'required|string|max:1000',
        ]);

        $toUserId = ($validated['send_target_type'] === 'user') ? $validated['to_user_id'] : null;
        $toRoleName = ($validated['send_target_type'] === 'role') ? $validated['to_role_name'] : null;

        DB::beginTransaction();
        try {
            // Add routing log entry
            LetterRecipient::create([
                'letter_id'    => $letter->id,
                'from_user_id' => $user->id,
                'to_user_id'   => $toUserId,
                'to_role_name' => $toRoleName,
                'action'       => 'redirected',
                'notes'        => $validated['redirection_notes'],
                'status'       => Letter::STATUS_PENDING,
            ]);

            // Update letter status to redirected
            $letter->update(['status' => Letter::STATUS_REDIRECTED]);

            // Notify recipient(s) & Send SMS
            $this->createNotificationsForRecipient(
                $letter,
                $toUserId,
                $toRoleName,
                "Letter {$letter->letter_number} was redirected to you by {$user->name}: {$validated['redirection_notes']}",
                'redirected',
                $validated['redirection_notes'] ?? null
            );

            DB::commit();

            return back()->with('success', 'Letter redirected successfully with your notes recorded in the timeline.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Redirection failed: ' . $e->getMessage());
        }
    }

    /**
     * Mark Letter as Reviewed / Actioned / Closed
     */
    public function closeLetter(Request $request, Letter $letter)
    {
        $user = Auth::user();

        if (!$letter->isAccessibleBy($user)) {
            abort(403, 'Unauthorized action.');
        }

        // Strict Rule: Secretary role can only create & send/redirect letters, NOT make decisions or close letters.
        $isSecretaryOnly = $user->hasRole('secretary') && !$user->hasAnyRole(['admin', 'global_admin', 'gm', 'manager', 'director', 'hr_manager', 'finance_head']);
        if ($isSecretaryOnly) {
            return back()->with('error', 'Strict Policy Restriction: The Secretary role is authorized to create, register, and forward letters only. Decision-making and closing must be completed by the assigned manager or executive.');
        }

        $validated = $request->validate([
            'closing_notes'              => 'required|string|max:1000',
            'record_payment'             => 'nullable|boolean',
            'payment_amount'             => 'nullable|numeric|min:0.01',
            'gross_amount'               => 'nullable|numeric|min:0.01',
            'vat_type'                   => 'nullable|string|in:none,exclusive,inclusive,vat_b',
            'vat_rate'                   => 'nullable|numeric|min:0',
            'vat_amount'                 => 'nullable|numeric|min:0',
            'has_withholding'            => 'nullable|boolean',
            'withholding_rate'           => 'nullable|numeric|min:0',
            'withholding_amount'         => 'nullable|numeric|min:0',
            'withholding_receipt'        => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp|max:10240',
            'withholding_receipt_number' => 'nullable|string|max:100',
            'net_amount'                 => 'nullable|numeric|min:0',
            'payment_reference'          => 'nullable|string|max:100',
            'chart_of_account_id'        => 'nullable|exists:chart_of_accounts,id',
            'bank_account_id'            => 'nullable|exists:bank_accounts,id',
            'expense_category'           => 'nullable|string|max:100',
            'project_id'                 => 'nullable|exists:projects,id',
            'payment_voucher'            => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ]);

        $hasPayment = !empty($request->boolean('record_payment')) && (!empty($validated['payment_amount']) || !empty($validated['gross_amount']));

        DB::beginTransaction();
        try {
            $paidFromAccountName = null;
            $voucherPath = null;
            $withholdingReceiptPath = null;
            $expenseRequestId = null;
            $expenseId = null;
            $disbursedAmount = null;
            $paymentAmount = null;
            $gross = null;
            $vatType = 'none';
            $vatRate = 15.00;
            $vatAmount = 0.0;
            $hasWithholding = false;
            $withholdingRate = 3.00;
            $withholdingAmount = 0.0;
            $netAmount = null;

            if ($hasPayment) {
                // 1. Calculate Tax Breakdown
                $gross = isset($validated['gross_amount']) && (float)$validated['gross_amount'] > 0
                    ? (float)$validated['gross_amount']
                    : (float)($validated['payment_amount'] ?? 0);

                $vatType = $validated['vat_type'] ?? 'none';
                $vatRate = isset($validated['vat_rate']) ? (float)$validated['vat_rate'] : 15.00;
                $hasWithholding = $request->boolean('has_withholding');
                $withholdingRate = 3.00;

                $vatAmount = 0.0;
                $baseAmount = $gross;
                $withholdingAmount = 0.0;
                $netAmount = $gross;

                if ($vatType === 'exclusive') {
                    $vatAmount = round($gross * ($vatRate / 100), 2);
                    $baseAmount = $gross;
                    $totalGrossWithVat = $gross + $vatAmount;
                    if ($hasWithholding) {
                        $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
                    }
                    $netAmount = $totalGrossWithVat - $withholdingAmount;
                } elseif ($vatType === 'inclusive' || $vatType === 'vat_b') {
                    $baseAmount = round($gross / (1 + ($vatRate / 100)), 2);
                    $vatAmount = round($gross - $baseAmount, 2);
                    if ($hasWithholding) {
                        $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
                    }
                    $netAmount = $gross - $withholdingAmount;
                } else {
                    $baseAmount = $gross;
                    $vatAmount = 0.0;
                    if ($hasWithholding) {
                        $withholdingAmount = round($baseAmount * ($withholdingRate / 100), 2);
                    }
                    $netAmount = $gross - $withholdingAmount;
                }

                // Final net disbursed amount to deduct from funding account
                $disbursedAmount = isset($validated['net_amount']) && (float)$validated['net_amount'] > 0
                    ? (float)$validated['net_amount']
                    : $netAmount;

                if ($disbursedAmount <= 0) {
                    $disbursedAmount = $gross;
                }

                // Upload payment voucher if present
                if ($request->hasFile('payment_voucher')) {
                    $voucherPath = \App\Services\FileUploadService::upload($request->file('payment_voucher'), 'correspondence_vouchers');
                }

                // Upload withholding receipt if present
                if ($request->hasFile('withholding_receipt')) {
                    $withholdingReceiptPath = \App\Services\FileUploadService::upload($request->file('withholding_receipt'), 'expense_withholding_receipts');
                }

                // Resolve Chart of Account and deduct balance
                if (!empty($validated['chart_of_account_id'])) {
                    $coa = \App\Models\ChartOfAccount::find($validated['chart_of_account_id']);
                    if ($coa) {
                        $paidFromAccountName = "{$coa->code} - {$coa->name}";
                        $coa->decrement('current_balance', $disbursedAmount);
                    }
                } elseif (!empty($validated['bank_account_id'])) {
                    $bankAcc = \App\Models\BankAccount::with('chartOfAccount')->find($validated['bank_account_id']);
                    if ($bankAcc) {
                        $paidFromAccountName = "{$bankAcc->bank_name} ({$bankAcc->account_number})";
                        if ($bankAcc->chartOfAccount) {
                            $bankAcc->chartOfAccount->decrement('current_balance', $disbursedAmount);
                        }
                    }
                }

                // 2. Create official ExpenseRequest record (shows in "Ask Money", Paid Expense history, and VAT Report)
                $categoryName = $validated['expense_category'] ?? \App\Models\ExpenseRequest::CATEGORY_SERVICE;
                $expenseReq = \App\Models\ExpenseRequest::create([
                    'request_number'            => 'EXP-LTR-' . strtoupper(\Illuminate\Support\Str::random(4)) . '-' . $letter->id,
                    'user_id'                   => $letter->created_by ?: $user->id,
                    'employee_id'               => $user->employee->id ?? null,
                    'letter_id'                 => $letter->id,
                    'project_id'                => $validated['project_id'] ?? null,
                    'category'                  => $categoryName,
                    'amount'                    => $disbursedAmount,
                    'gross_amount'              => $gross,
                    'vat_type'                  => $vatType,
                    'vat_rate'                  => $vatRate,
                    'vat_amount'                => $vatAmount,
                    'has_withholding'           => $hasWithholding,
                    'withholding_rate'          => $withholdingRate,
                    'withholding_amount'        => $withholdingAmount,
                    'withholding_receipt'       => $withholdingReceiptPath,
                    'withholding_receipt_number' => $validated['withholding_receipt_number'] ?? null,
                    'net_amount'                => $disbursedAmount,
                    'description'               => "Direct Payment Settlement for Letter #{$letter->letter_number}: {$letter->subject}",
                    'status'                    => \App\Models\ExpenseRequest::STATUS_PAID,
                    'hr_reviewer_id'            => $user->id,
                    'hr_reviewed_at'            => now(),
                    'gm_reviewer_id'            => $user->id,
                    'gm_approver_id'            => $user->id,
                    'gm_reviewed_at'            => now(),
                    'gm_approved_at'            => now(),
                    'finance_head_id'           => $user->id,
                    'paid_by'                   => $user->id,
                    'paid_at'                   => now(),
                    'chart_of_account_id'       => $validated['chart_of_account_id'] ?? null,
                    'coa_id'                    => $validated['chart_of_account_id'] ?? null,
                    'bank_account_id'           => $validated['bank_account_id'] ?? null,
                    'payment_reference'         => $validated['payment_reference'] ?? ('LTR-' . $letter->id),
                    'payment_notes'             => $validated['closing_notes'],
                    'attachment'                => $voucherPath,
                ]);
                $expenseRequestId = $expenseReq->id;

                // 3. Also create Expense entry for project budget tracking if project or category provided
                try {
                    $expense = \App\Models\Expense::create([
                        'project_id'   => $validated['project_id'] ?? null,
                        'category'     => strtolower($categoryName) === 'maintenance' ? 'equipment' : (in_array(strtolower($categoryName), ['labour', 'material', 'equipment', 'overhead', 'subcontractor']) ? strtolower($categoryName) : 'other'),
                        'description'  => "Settlement for Letter #{$letter->letter_number}: {$letter->subject}",
                        'amount'       => $disbursedAmount,
                        'expense_date' => now()->toDateString(),
                        'status'       => 'approved',
                        'created_by'   => $user->id,
                        'approved_by'  => $user->id,
                        'approved_at'  => now(),
                        'notes'        => "Payment disbursed for Letter #{$letter->letter_number}. Ref: " . ($validated['payment_reference'] ?? 'N/A'),
                    ]);
                    $expenseId = $expense->id;
                } catch (\Throwable $ex) {
                    \Illuminate\Support\Facades\Log::warning("Expense model creation fallback: " . $ex->getMessage());
                }
            }

            // Update letter
            $letter->update([
                'status'                    => Letter::STATUS_CLOSED,
                'closed_by'                 => $user->id,
                'closed_at'                 => now(),
                'closing_notes'             => $validated['closing_notes'],
                'payment_amount'            => $hasPayment ? $disbursedAmount : null,
                'gross_amount'              => $hasPayment ? $gross : null,
                'vat_type'                  => $hasPayment ? $vatType : 'none',
                'vat_rate'                  => $hasPayment ? $vatRate : 15.00,
                'vat_amount'                => $hasPayment ? $vatAmount : 0,
                'has_withholding'           => $hasPayment ? $hasWithholding : false,
                'withholding_rate'          => $hasPayment ? $withholdingRate : 3.00,
                'withholding_amount'        => $hasPayment ? $withholdingAmount : 0,
                'withholding_receipt'       => $withholdingReceiptPath,
                'withholding_receipt_number' => $validated['withholding_receipt_number'] ?? null,
                'net_amount'                => $hasPayment ? $disbursedAmount : null,
                'payment_reference'         => $validated['payment_reference'] ?? null,
                'paid_from_account'         => $paidFromAccountName,
                'chart_of_account_id'       => $validated['chart_of_account_id'] ?? null,
                'bank_account_id'           => $validated['bank_account_id'] ?? null,
                'expense_request_id'        => $expenseRequestId,
                'expense_id'                => $expenseId,
                'payment_voucher_path'      => $voucherPath,
                'paid_at'                   => $hasPayment ? now() : null,
                'paid_by'                   => $hasPayment ? $user->id : null,
            ]);

            // Log in routing table
            $taxDetails = ($hasPayment && ($vatAmount > 0 || $withholdingAmount > 0))
                ? (" [VAT: ETB " . number_format($vatAmount, 2) . ", WHT: ETB " . number_format($withholdingAmount, 2) . "]")
                : "";

            $routingNotes = $hasPayment
                ? ("Payment Disbursed: ETB " . number_format($disbursedAmount, 2) . $taxDetails . ($paidFromAccountName ? " from {$paidFromAccountName}" : "") . ($validated['payment_reference'] ? " (Ref: {$validated['payment_reference']})" : "") . ". Final Decision: " . $validated['closing_notes'])
                : ('Closed with decision/resolution: ' . $validated['closing_notes']);

            LetterRecipient::create([
                'letter_id'    => $letter->id,
                'from_user_id' => $user->id,
                'to_user_id'   => null,
                'to_role_name' => null,
                'action'       => 'closed',
                'notes'        => $routingNotes,
                'status'       => Letter::STATUS_CLOSED,
            ]);

            // Notify Creator
            if ($letter->created_by && $letter->created_by !== $user->id) {
                $notifMsg = $hasPayment
                    ? "Letter {$letter->letter_number} was finalized by {$user->name} with an authorized payment disbursement of ETB " . number_format($paymentAmount, 2) . "."
                    : "Letter {$letter->letter_number} decision was recorded and marked as Closed by {$user->name}.";

                LetterNotification::create([
                    'user_id'   => $letter->created_by,
                    'letter_id' => $letter->id,
                    'message'   => $notifMsg,
                ]);
            }

            DB::commit();

            $successMsg = $hasPayment
                ? "Payment of ETB " . number_format($paymentAmount, 2) . " disbursed and letter closed successfully! Expense recorded in Company Expenses."
                : 'Letter decision recorded and marked as Reviewed & Closed.';

            return back()->with('success', $successMsg);
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to close letter: ' . $e->getMessage());
        }
    }

    /**
     * Preview Attachment File (Inline PDF / Image streaming)
     */
    public function previewAttachment(LetterAttachment $attachment)
    {
        $user = Auth::user();
        $letter = $attachment->letter;

        if (!$letter || !$letter->isAccessibleBy($user)) {
            abort(403, 'Unauthorized to view this attachment.');
        }

        $rawPath = $attachment->file_path;

        // If remote URL (Cloudinary, etc.)
        if (\Illuminate\Support\Str::startsWith($rawPath, ['http://', 'https://', '//'])) {
            return redirect($rawPath);
        }

        // Local file locations
        $candidates = [
            public_path($rawPath),
            public_path('uploads/' . ltrim($rawPath, '/')),
            public_path('storage/' . ltrim($rawPath, '/')),
            storage_path('app/public/' . ltrim($rawPath, '/')),
            storage_path('app/' . ltrim($rawPath, '/')),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_file($candidate)) {
                $mime = $attachment->is_pdf ? 'application/pdf' : ($attachment->is_image ? ('image/' . $attachment->file_type) : mime_content_type($candidate));
                return response()->file($candidate, [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline; filename="' . addslashes($attachment->file_name) . '"'
                ]);
            }
        }

        // Fallback to FileUploadService URL
        $fallbackUrl = \App\Services\FileUploadService::url($rawPath);
        if ($fallbackUrl && \Illuminate\Support\Str::startsWith($fallbackUrl, ['http://', 'https://'])) {
            return redirect($fallbackUrl);
        }

        abort(404, 'Attachment file could not be located on the server.');
    }

    /**
     * Download Attachment File
     */
    public function downloadAttachment(LetterAttachment $attachment)
    {
        $user = Auth::user();
        $letter = $attachment->letter;

        if (!$letter || !$letter->isAccessibleBy($user)) {
            abort(403, 'Unauthorized to download this attachment.');
        }

        $rawPath = $attachment->file_path;

        // If remote URL (Cloudinary, etc.)
        if (\Illuminate\Support\Str::startsWith($rawPath, ['http://', 'https://', '//'])) {
            return redirect($rawPath);
        }

        // Local candidate locations
        $candidates = [
            public_path($rawPath),
            public_path('uploads/' . ltrim($rawPath, '/')),
            public_path('storage/' . ltrim($rawPath, '/')),
            storage_path('app/public/' . ltrim($rawPath, '/')),
            storage_path('app/' . ltrim($rawPath, '/')),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_file($candidate)) {
                return response()->download($candidate, $attachment->file_name);
            }
        }

        // Fallback: Redirect to public asset URL
        $fallbackUrl = \App\Services\FileUploadService::url($rawPath);
        if ($fallbackUrl) {
            return redirect($fallbackUrl);
        }

        abort(404, 'Attachment file not found on server.');
    }

    /**
     * AJAX Endpoint: Get Next Suggested Letter Number
     */
    public function getSuggestedNumber(Request $request)
    {
        $type = $request->query('type', 'incoming');
        $suggested = Letter::generateSuggestedNumber($type);
        return response()->json(['suggested_number' => $suggested]);
    }

    /**
     * Helper: Query for user's personal inbox
     */
    private function getMyInboxQuery(User $user)
    {
        $userRoles = $user->getRoleNames()->toArray();

        return Letter::with(['creator', 'latestRecipient.toUser', 'attachments'])
            ->whereHas('recipients', function ($q) use ($user, $userRoles) {
                $q->where('to_user_id', $user->id)
                    ->orWhereIn('to_role_name', $userRoles);
            });
    }

    /**
     * Helper: Create notifications for target user or role and send SMS alert
     */
    private function createNotificationsForRecipient(Letter $letter, ?int $userId, ?string $roleName, string $message, string $actionType = 'initial_sent', ?string $notes = null): void
    {
        $sender = Auth::user();
        $senderName = $sender ? $sender->name : 'System';

        if ($userId) {
            $targetUser = User::with('employee')->find($userId);
            if ($targetUser) {
                LetterNotification::create([
                    'user_id'   => $targetUser->id,
                    'letter_id' => $letter->id,
                    'message'   => $message,
                ]);

                // Send SMS notification
                $this->sendLetterSms($targetUser, $letter, $senderName, $actionType, $notes);
            }
            return;
        }

        if ($roleName) {
            try {
                $roleUsers = User::role($roleName)->where('is_active', true)->with('employee')->get();
                foreach ($roleUsers as $u) {
                    LetterNotification::create([
                        'user_id'   => $u->id,
                        'letter_id' => $letter->id,
                        'message'   => $message,
                    ]);

                    $this->sendLetterSms($u, $letter, $senderName, $actionType, $notes);
                }
            } catch (\Throwable $e) {
                Log::warning("Letter role notification error: " . $e->getMessage());
            }
        }
    }

    /**
     * Send SMS notification to recipient user
     */
    private function sendLetterSms(User $user, Letter $letter, string $senderName, string $actionType = 'initial_sent', ?string $notes = null): void
    {
        try {
            $phone = $this->getUserPhone($user);
            if (empty($phone)) {
                Log::info("Letter SMS skipped: No phone number registered for user {$user->name} (ID: {$user->id})");
                return;
            }

            $smsService = app(SmsEthiopiaService::class);
            $subjectSnippet = \Illuminate\Support\Str::limit($letter->subject, 50);

            if ($actionType === 'redirected') {
                $notesSnippet = $notes ? (' - Note: ' . \Illuminate\Support\Str::limit($notes, 40)) : '';
                $smsMessage = "Wechacha Construction: Letter #{$letter->letter_number} ({$subjectSnippet}) was forwarded to you by {$senderName}{$notesSnippet}. Check ERP inbox: " . url('/letters/' . $letter->id);
            } else {
                $smsMessage = "Wechacha Construction: You have a new letter (#{$letter->letter_number}) in your ERP inbox from {$senderName}. Subject: {$subjectSnippet}. View: " . url('/letters/' . $letter->id);
            }

            $smsService->sendNotification($phone, $smsMessage);
        } catch (\Throwable $e) {
            Log::error("Failed to send letter SMS to user {$user->id}: " . $e->getMessage());
        }
    }

    /**
     * Resolve phone number for a user
     */
    private function getUserPhone(User $user): ?string
    {
        if (!empty($user->phone)) {
            return $user->phone;
        }
        if ($user->employee && !empty($user->employee->phone)) {
            return $user->employee->phone;
        }

        return \App\Models\Employee::where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->value('phone');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // EMPLOYEE SELF-SERVICE: MY LETTERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Display the authenticated employee's "My Letters" list.
     * Strictly scoped to the employee's own letters (never other employees' letters).
     */
    public function myLetters(Request $request)
    {
        $user = Auth::user();

        $query = Letter::with(['attachments', 'addressedTo', 'registeredBy'])
            ->where('created_by', $user->id);

        // Filter by Status (Draft, Sent, Registered)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by Category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Search in subject / reference number / body
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('letter_number', 'like', "%{$search}%")
                  ->orWhere('specification', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        $letters = $query->latest('id')->paginate(15)->withQueryString();

        // Metrics for employee's own letters
        $stats = [
            'total'      => Letter::where('created_by', $user->id)->count(),
            'draft'      => Letter::where('created_by', $user->id)->where('status', Letter::STATUS_DRAFT)->count(),
            'sent'       => Letter::where('created_by', $user->id)->where('status', Letter::STATUS_SENT)->count(),
            'registered' => Letter::where('created_by', $user->id)->where('status', Letter::STATUS_REGISTERED)->count(),
        ];

        return view('letters.my-letters.index', compact('letters', 'stats'));
    }

    /**
     * Show form for employee to compose a new letter (save as draft or send directly to secretary).
     */
    public function createDraft()
    {
        return view('letters.my-letters.create');
    }

    /**
     * Store new employee letter (as Draft or Sent directly to Secretary).
     * No auto-category and no auto-person selection.
     */
    public function storeDraft(Request $request)
    {
        $validated = $request->validate([
            'subject'       => 'required|string|max:255',
            'specification' => 'required|string',
            'priority'      => 'nullable|in:normal,urgent',
            'submit_action' => 'required|in:save_draft,send_secretary',
            'attachments.*' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:10240',
        ]);

        $user = Auth::user();
        $isSending = ($validated['submit_action'] === 'send_secretary');

        DB::beginTransaction();
        try {
            $letter = Letter::create([
                'letter_number'          => null, // Reference number is assigned later by secretary
                'is_reference_locked'    => false,
                'type'                   => Letter::TYPE_INCOMING,
                'date'                   => now()->toDateString(),
                'subject'                => $validated['subject'],
                'specification'          => $validated['specification'],
                'category'               => null, // No automatic category
                'addressed_to_user_id'   => null, // No automatic person selection
                'sender'                 => $user->name,
                'sender_department'      => $user->employee?->department?->name ?? null,
                'priority'               => $validated['priority'] ?? Letter::PRIORITY_NORMAL,
                'status'                 => $isSending ? Letter::STATUS_SENT : Letter::STATUS_DRAFT,
                'sent_at'                => $isSending ? now() : null,
                'created_by'             => $user->id,
            ]);

            // Handle optional attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if ($file->isValid()) {
                        $path = \App\Services\FileUploadService::upload($file, 'correspondence');
                        LetterAttachment::create([
                            'letter_id'   => $letter->id,
                            'file_path'   => $path,
                            'file_name'   => $file->getClientOriginalName(),
                            'file_type'   => strtolower($file->getClientOriginalExtension()),
                            'file_size'   => $file->getSize(),
                            'uploaded_by' => $user->id,
                        ]);
                    }
                }
            }

            if ($isSending) {
                $this->dispatchToSecretary($letter, $user);
                LetterActionLog::record($letter->id, $user->id, 'sent', "Letter created and sent directly to Secretary Inbox by {$user->name}.");
            } else {
                LetterActionLog::record($letter->id, $user->id, 'draft_saved', "Letter saved as draft by {$user->name}.");
            }

            DB::commit();

            $msg = $isSending
                ? 'Your letter has been sent directly to the Secretary Inbox.'
                : 'Letter draft saved successfully. You can review or edit it before sending.';

            return redirect()->route('letters.my-letters.index')->with('success', $msg);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to store employee letter: " . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to save letter: ' . $e->getMessage());
        }
    }

    /**
     * Show form to edit an existing draft letter.
     */
    public function editDraft(Letter $letter)
    {
        $user = Auth::user();

        // Drafts are strictly private to creator
        if ($letter->created_by !== $user->id || $letter->status !== Letter::STATUS_DRAFT) {
            abort(403, 'Unauthorized access or this letter has already been sent.');
        }

        return view('letters.my-letters.edit', compact('letter'));
    }

    /**
     * Update an existing draft letter (save draft or send to secretary).
     */
    public function updateDraft(Request $request, Letter $letter)
    {
        $user = Auth::user();

        if ($letter->created_by !== $user->id || $letter->status !== Letter::STATUS_DRAFT) {
            abort(403, 'Unauthorized access or this letter has already been sent.');
        }

        $validated = $request->validate([
            'subject'       => 'required|string|max:255',
            'specification' => 'required|string',
            'priority'      => 'nullable|in:normal,urgent',
            'submit_action' => 'required|in:save_draft,send_secretary',
            'attachments.*' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:10240',
        ]);

        $isSending = ($validated['submit_action'] === 'send_secretary');

        DB::beginTransaction();
        try {
            $letter->update([
                'subject'       => $validated['subject'],
                'specification' => $validated['specification'],
                'priority'      => $validated['priority'] ?? Letter::PRIORITY_NORMAL,
                'status'        => $isSending ? Letter::STATUS_SENT : Letter::STATUS_DRAFT,
                'sent_at'       => $isSending ? now() : null,
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if ($file->isValid()) {
                        $path = \App\Services\FileUploadService::upload($file, 'correspondence');
                        LetterAttachment::create([
                            'letter_id'   => $letter->id,
                            'file_path'   => $path,
                            'file_name'   => $file->getClientOriginalName(),
                            'file_type'   => strtolower($file->getClientOriginalExtension()),
                            'file_size'   => $file->getSize(),
                            'uploaded_by' => $user->id,
                        ]);
                    }
                }
            }

            if ($isSending) {
                $this->dispatchToSecretary($letter, $user);
                LetterActionLog::record($letter->id, $user->id, 'sent', "Draft submitted and sent to Secretary Inbox by {$user->name}.");
            } else {
                LetterActionLog::record($letter->id, $user->id, 'draft_updated', "Draft updated by {$user->name}.");
            }

            DB::commit();

            $msg = $isSending
                ? 'Your letter has been sent directly to the Secretary Inbox.'
                : 'Draft updated successfully.';

            return redirect()->route('letters.my-letters.index')->with('success', $msg);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to update letter draft: " . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to update draft: ' . $e->getMessage());
        }
    }

    /**
     * Send an existing draft letter directly to the Secretary Inbox.
     */
    public function sendDraftToSecretary(Letter $letter)
    {
        $user = Auth::user();

        if ($letter->created_by !== $user->id || $letter->status !== Letter::STATUS_DRAFT) {
            abort(403, 'Unauthorized access or this letter is not in draft status.');
        }

        DB::beginTransaction();
        try {
            $letter->update([
                'status'  => Letter::STATUS_SENT,
                'sent_at' => now(),
            ]);

            $this->dispatchToSecretary($letter, $user);
            LetterActionLog::record($letter->id, $user->id, 'sent', "Draft sent to Secretary Inbox by {$user->name}.");

            DB::commit();

            return redirect()->route('letters.my-letters.index')
                ->with('success', 'Letter sent directly to the Secretary Inbox.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to send draft to secretary: " . $e->getMessage());
            return back()->with('error', 'Failed to send letter: ' . $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECRETARY INBOX & MANUAL REGISTRATION WORKFLOW
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Secretary Inbox: Sees all letters sent to the secretary role.
     * Can filter by category and status.
     */
    public function secretaryInbox(Request $request)
    {
        $user = Auth::user();

        // Only secretary, admin, or global_admin can access the secretary inbox
        if (!$user->hasAnyRole(['secretary', 'Secretary', 'admin', 'global_admin'])) {
            abort(403, 'Unauthorized. Access to Secretary Inbox is reserved for the Secretary role.');
        }

        $query = Letter::with(['creator', 'attachments', 'addressedTo', 'registeredBy'])
            ->whereIn('status', [Letter::STATUS_SENT, Letter::STATUS_REGISTERED, Letter::STATUS_PENDING, Letter::STATUS_VIEWED, Letter::STATUS_REDIRECTED]);

        // Filter by Category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter by Status (sent / registered)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('letter_number', 'like', "%{$search}%")
                  ->orWhere('specification', 'like', "%{$search}%")
                  ->orWhereHas('creator', fn($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        $letters = $query->latest('id')->paginate(15)->withQueryString();

        $stats = [
            'sent'       => Letter::whereIn('status', [Letter::STATUS_SENT, Letter::STATUS_PENDING])->count(),
            'registered' => Letter::where('status', Letter::STATUS_REGISTERED)->count(),
            'total'      => Letter::whereIn('status', [Letter::STATUS_SENT, Letter::STATUS_REGISTERED, Letter::STATUS_PENDING, Letter::STATUS_VIEWED, Letter::STATUS_REDIRECTED])->count(),
        ];

        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);
        $categories = Letter::CATEGORIES;
        $nextSuggestedNumber = LetterSequence::peekNext();

        return view('letters.secretary.inbox', compact('letters', 'stats', 'users', 'categories', 'nextSuggestedNumber'));
    }

    /**
     * Step 1: Secretary gives the letter a reference number from a unique sequential numbering
     * (per year, never reused, locked after assignment).
     */
    public function assignReferenceNumber(Request $request, Letter $letter)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['secretary', 'Secretary', 'admin', 'global_admin'])) {
            abort(403, 'Unauthorized.');
        }

        // Locked check: once assigned, reference number is locked
        if ($letter->is_reference_locked && !empty($letter->letter_number)) {
            return back()->with('error', "Reference number is already assigned and locked ({$letter->letter_number}). It cannot be changed or reused.");
        }

        $request->validate([
            'letter_number' => 'nullable|string|max:60|unique:letters,letter_number,' . $letter->id,
        ]);

        DB::beginTransaction();
        try {
            // Either secretary provided custom sequential number, or generate next atomically
            $newNumber = $request->filled('letter_number')
                ? trim($request->letter_number)
                : LetterSequence::generateNext();

            $letter->update([
                'letter_number'       => $newNumber,
                'is_reference_locked' => true,
            ]);

            LetterActionLog::record(
                $letter->id,
                $user->id,
                'numbered',
                "Reference number '{$newNumber}' was assigned and locked by {$user->name}.",
                ['reference_number' => $newNumber]
            );

            DB::commit();

            return back()->with('success', "Reference number {$newNumber} has been successfully assigned and locked.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to assign letter reference number: " . $e->getMessage());
            return back()->with('error', 'Failed to assign reference number: ' . $e->getMessage());
        }
    }

    /**
     * Step 2: Secretary chooses one category:
     * Leave Letter, Advance Loan Letter, Payment, Government, Bank & Insurance.
     */
    public function setCategory(Request $request, Letter $letter)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['secretary', 'Secretary', 'admin', 'global_admin'])) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'category' => 'required|in:' . implode(',', Letter::CATEGORIES),
        ]);

        DB::beginTransaction();
        try {
            $letter->update([
                'category' => $request->category,
            ]);

            LetterActionLog::record(
                $letter->id,
                $user->id,
                'categorized',
                "Category set to '{$request->category}' by {$user->name}.",
                ['category' => $request->category]
            );

            DB::commit();

            return back()->with('success', "Category set to '{$request->category}'.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to set letter category: " . $e->getMessage());
            return back()->with('error', 'Failed to set category: ' . $e->getMessage());
        }
    }

    /**
     * Step 3: Secretary selects the person the letter is addressed to or handled by, from user list.
     */
    public function setHandledPerson(Request $request, Letter $letter)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['secretary', 'Secretary', 'admin', 'global_admin'])) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'addressed_to_user_id' => 'required|exists:users,id',
        ]);

        $targetUser = User::findOrFail($request->addressed_to_user_id);

        DB::beginTransaction();
        try {
            $letter->update([
                'addressed_to_user_id' => $targetUser->id,
            ]);

            LetterActionLog::record(
                $letter->id,
                $user->id,
                'person_selected',
                "Addressed / handled person set to {$targetUser->name} by {$user->name}.",
                ['user_id' => $targetUser->id, 'user_name' => $targetUser->name]
            );

            DB::commit();

            return back()->with('success', "Letter addressed to {$targetUser->name}.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to set handled person: " . $e->getMessage());
            return back()->with('error', 'Failed to set handled person: ' . $e->getMessage());
        }
    }

    /**
     * Mark Letter as Registered.
     * STRICT REQUIREMENT: Only after all three (Reference Number, Category, Handled Person) are set
     * can the secretary mark the letter Registered.
     */
    public function markRegistered(Request $request, Letter $letter)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['secretary', 'Secretary', 'admin', 'global_admin'])) {
            abort(403, 'Unauthorized. Only the secretary or admin can register letters.');
        }

        // Verify that all 3 manual fields are set
        $missing = [];
        if (empty($letter->letter_number)) {
            $missing[] = 'Reference Number';
        }
        if (empty($letter->category)) {
            $missing[] = 'Category';
        }
        if (empty($letter->addressed_to_user_id)) {
            $missing[] = 'Addressed / Handled Person';
        }

        if (!empty($missing)) {
            return back()->with('error', 'Registration blocked: All three items must be set before registering this letter. Missing: ' . implode(', ', $missing) . '.');
        }

        DB::beginTransaction();
        try {
            $letter->update([
                'status'              => Letter::STATUS_REGISTERED,
                'is_reference_locked' => true,
                'registered_by'       => $user->id,
                'registered_at'       => now(),
            ]);

            $targetUser = $letter->addressedTo;

            // Log routing entry
            LetterRecipient::create([
                'letter_id'    => $letter->id,
                'from_user_id' => $user->id,
                'to_user_id'   => $letter->addressed_to_user_id,
                'to_role_name' => null,
                'action'       => 'registered',
                'notes'        => "Letter registered as Ref #{$letter->letter_number} [{$letter->category}] and assigned to " . ($targetUser?->name ?? 'User'),
                'status'       => Letter::STATUS_REGISTERED,
            ]);

            // Action Audit Log
            LetterActionLog::record(
                $letter->id,
                $user->id,
                'registered',
                "Letter officially Registered as Ref #{$letter->letter_number} [{$letter->category}] and assigned to " . ($targetUser?->name ?? 'User') . " by {$user->name}.",
                [
                    'letter_number'        => $letter->letter_number,
                    'category'             => $letter->category,
                    'addressed_to_user_id' => $letter->addressed_to_user_id,
                ]
            );

            // In-App Notification to letter creator
            if ($letter->created_by && $letter->created_by !== $user->id) {
                LetterNotification::create([
                    'user_id'   => $letter->created_by,
                    'letter_id' => $letter->id,
                    'message'   => "Your letter '{$letter->subject}' has been registered by Secretary {$user->name} as Ref #{$letter->letter_number} [{$letter->category}].",
                ]);
            }

            // In-App Notification to assigned person
            if ($targetUser && $targetUser->id !== $user->id) {
                LetterNotification::create([
                    'user_id'   => $targetUser->id,
                    'letter_id' => $letter->id,
                    'message'   => "New letter registered and assigned to you: Ref #{$letter->letter_number} [{$letter->category}] - {$letter->subject}.",
                ]);
            }

            // SMS Notification to the selected person through ProcurementSmsService
            if ($targetUser) {
                $this->sendRegistrationSmsToTargetUser($targetUser, $letter, $user->name);
            }

            DB::commit();

            return back()->with('success', "Letter {$letter->letter_number} successfully Registered and dispatched to " . ($targetUser?->name ?? 'the selected person') . ".");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to register letter: " . $e->getMessage());
            return back()->with('error', 'Registration failed: ' . $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // INTERNAL HELPERS: DISPATCH, SMS, AUDIT
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Dispatch letter to Secretary role inbox with fallback to global_admin and SMS alerts.
     */
    private function dispatchToSecretary(Letter $letter, User $sender): void
    {
        // 1. Check if active secretary user exists
        $activeSecretaries = User::whereHas('roles', fn($q) => $q->whereIn('name', ['secretary', 'Secretary']))
            ->where('is_active', true)
            ->get();

        $fallbackToAdmin = $activeSecretaries->isEmpty();

        if ($fallbackToAdmin) {
            Log::warning("Letter dispatch audit: No active secretary user exists in the system for letter #{$letter->id} (Subject: {$letter->subject}). Falling back to global_admin.");
            LetterActionLog::record(
                $letter->id,
                $sender->id,
                'fallback_to_admin',
                "No active secretary user found in system. Fallback triggered: letter routed to Global Administrator."
            );
        }

        // 2. Add routing record
        LetterRecipient::create([
            'letter_id'    => $letter->id,
            'from_user_id' => $sender->id,
            'to_user_id'   => null,
            'to_role_name' => $fallbackToAdmin ? 'global_admin' : 'secretary',
            'action'       => 'initial_sent',
            'notes'        => $fallbackToAdmin
                ? "Sent to Secretary Inbox (Fallback routed to Global Admin: No active secretary user found) by {$sender->name}"
                : "Sent to Secretary Inbox by {$sender->name}",
            'status'       => Letter::STATUS_SENT,
        ]);

        // 3. In-App Notifications
        if ($fallbackToAdmin) {
            $admins = User::whereHas('roles', fn($q) => $q->whereIn('name', ['global_admin', 'admin', 'Global Admin', 'Admin']))
                ->where('is_active', true)
                ->get();

            foreach ($admins as $adm) {
                LetterNotification::create([
                    'user_id'   => $adm->id,
                    'letter_id' => $letter->id,
                    'message'   => "[Secretary Unassigned] New letter submitted by {$sender->name}: {$letter->subject}",
                ]);
            }
        } else {
            foreach ($activeSecretaries as $sec) {
                LetterNotification::create([
                    'user_id'   => $sec->id,
                    'letter_id' => $letter->id,
                    'message'   => "New letter received from {$sender->name}: {$letter->subject}",
                ]);
            }
        }

        // 4. Send SMS via ProcurementSmsService
        try {
            $procurementSms = app(ProcurementSmsService::class);
            $directLink = url('/letters/' . $letter->id);
            $subjectSnippet = \Illuminate\Support\Str::limit($letter->subject, 40);

            $message = "Wechacha ERP: New correspondence letter submitted by {$sender->name}. Subject: \"{$subjectSnippet}\". Register in Secretary Inbox: {$directLink}";

            $procurementSms->notifyRole($letter->id, 'secretary', $message);
        } catch (\Throwable $e) {
            Log::error("Failed to send letter dispatch SMS: " . $e->getMessage());
        }
    }

    /**
     * Send SMS to selected person when secretary registers the letter.
     * Must contain reference number, category, and direct link.
     */
    private function sendRegistrationSmsToTargetUser(User $targetUser, Letter $letter, string $secretaryName): void
    {
        try {
            $procurementSms = app(ProcurementSmsService::class);
            $directLink = url('/letters/' . $letter->id);
            $subjectSnippet = \Illuminate\Support\Str::limit($letter->subject, 35);

            $message = "Wechacha ERP: Letter #{$letter->letter_number} [{$letter->category}] has been registered and addressed to you by {$secretaryName}. Subject: \"{$subjectSnippet}\". View: {$directLink}";

            $procurementSms->sendToUser($letter->id, $targetUser, 'assigned_recipient', $message);
        } catch (\Throwable $e) {
            Log::error("Failed to send registration SMS to user {$targetUser->id}: " . $e->getMessage());
        }
    }
}
