<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Expense;
use App\Models\ExpenseRequest;
use App\Models\PurchaseOrder;
use App\Models\EmergencyFund;
use App\Models\Project;
use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ApprovalHubController extends Controller
{
    public function index(Request $request)
    {
        $items = new Collection();
        $user = Auth::user();

        // 1. Fetch Expense Requests (Employee "Ask Money" Requests)
        $expenseRequests = ExpenseRequest::with([
            'user',
            'employee',
            'hrReviewer',
            'gmApprover',
            'financeHead',
            'financeStaff',
            'paidBy',
            'chartOfAccount',
            'coa',
            'bankAccount'
        ])->latest()->get()->map(function ($req) {
            $statusRaw = $req->status;

            // Map status into uniform labels & keys
            [$statusLabel, $statusKey, $badgeColor] = match($statusRaw) {
                ExpenseRequest::STATUS_PENDING_HR           => ['Pending HR Review', 'pending_hr', 'warning'],
                ExpenseRequest::STATUS_PENDING_GM           => ['Pending GM Review', 'pending_gm', 'info'],
                ExpenseRequest::STATUS_APPROVED_ASSIGNED,
                ExpenseRequest::STATUS_ASSIGNED             => ['Assigned to Finance', 'finance_queue', 'primary'],
                ExpenseRequest::STATUS_PAID                 => ['Paid', 'paid', 'success'],
                ExpenseRequest::STATUS_REJECTED             => ['Rejected', 'rejected', 'danger'],
                default                                     => [$statusRaw ?? 'Pending', 'pending', 'secondary'],
            };

            $applicantName = $req->employee ? $req->employee->full_name : ($req->user->name ?? 'Employee');
            $deptOrProject = $req->employee->department ?? ($req->user->department ?? 'General / Operations');
            $categoryName = $req->category . ($req->other_reason ? ' (' . $req->other_reason . ')' : '');

            return (object) [
                'id_raw'          => $req->id,
                'id_formatted'    => $req->request_number ?? ('REQ-' . $req->id),
                'type'            => 'expense_request',
                'date'            => $req->paid_at ?? $req->created_at,
                'project'         => $deptOrProject,
                'category'        => $categoryName,
                'description'     => $req->description,
                'applicant_name'  => $applicantName,
                'base_amount'     => (float) $req->amount,
                'vat_amount'      => 0,
                'net_amount'      => (float) $req->amount,
                'status'          => $statusLabel,
                'status_raw'      => $statusRaw,
                'status_key'      => $statusKey,
                'color'           => $badgeColor,
                'attachment'      => $req->attachment,
                'attachment_url'  => $req->attachment_url,
                'rejection_reason'=> $req->rejection_reason,
                'paid_at'         => $req->paid_at,
                'paid_by_name'    => $req->paidBy->name ?? null,
                'payment_reference'=> $req->payment_reference,
                'coa_name'        => $req->effective_coa->name ?? null,
                'bank_name'       => $req->bankAccount->bank_name ?? null,
                'route_show'      => url('/expense-requests?search=' . urlencode($req->request_number ?? '')),
                'route_approve'   => route('expense-requests.hr-review', $req->id),
                'route_reject'    => route('expense-requests.hr-review', $req->id),
                'raw_model'       => $req,
            ];
        });
        $items = $items->concat($expenseRequests);

        // 2. Fetch Direct Expenses
        $expenses = Expense::with(['project'])->latest()->get()->map(function ($exp) {
            $statusRaw = strtolower($exp->status ?? 'pending');
            $statusKey = match($statusRaw) {
                'approved' => 'paid',
                'rejected' => 'rejected',
                'paid'     => 'paid',
                default    => 'pending_gm',
            };
            $badgeColor = match($statusKey) {
                'paid'     => 'success',
                'rejected' => 'danger',
                default    => 'warning',
            };

            return (object) [
                'id_raw'          => $exp->id,
                'id_formatted'    => 'EXP-' . str_pad($exp->id, 5, '0', STR_PAD_LEFT),
                'type'            => 'expense',
                'date'            => $exp->expense_date ?? $exp->created_at,
                'project'         => $exp->project ? $exp->project->name : 'General',
                'category'        => ucfirst($exp->category ?? 'Expense'),
                'description'     => $exp->description,
                'applicant_name'  => 'Direct Entry',
                'base_amount'     => (float) $exp->amount,
                'vat_amount'      => 0,
                'net_amount'      => (float) $exp->amount,
                'status'          => ucfirst($exp->status ?? 'Pending'),
                'status_raw'      => $exp->status ?? 'pending',
                'status_key'      => $statusKey,
                'color'           => $badgeColor,
                'attachment'      => $exp->receipt_path ?? null,
                'attachment_url'  => $exp->receipt_path ? \App\Services\FileUploadService::url($exp->receipt_path) : null,
                'rejection_reason'=> null,
                'paid_at'         => $exp->created_at,
                'paid_by_name'    => null,
                'payment_reference'=> null,
                'coa_name'        => null,
                'bank_name'       => null,
                'route_show'      => route('expenses.show', $exp->id),
                'route_approve'   => route('expenses.approve', $exp->id),
                'route_reject'    => route('expenses.reject', $exp->id),
                'raw_model'       => $exp,
            ];
        });
        $items = $items->concat($expenses);

        // 3. Fetch Purchase Orders
        $pos = PurchaseOrder::with(['project'])->latest()->get()->map(function ($po) {
            $statusRaw = strtolower($po->status ?? 'draft');
            $statusKey = match($statusRaw) {
                'received', 'completed' => 'paid',
                'cancelled'             => 'rejected',
                'issued', 'approved'    => 'finance_queue',
                default                 => 'pending_gm',
            };
            $badgeColor = match($statusKey) {
                'paid'          => 'success',
                'rejected'      => 'danger',
                'finance_queue' => 'primary',
                default         => 'warning',
            };

            return (object) [
                'id_raw'          => $po->id,
                'id_formatted'    => $po->reference_number ?? ('PUR-' . str_pad($po->id, 5, '0', STR_PAD_LEFT)),
                'type'            => 'purchase_order',
                'date'            => $po->issued_date ?? $po->created_at,
                'project'         => $po->project ? $po->project->name : 'Procurement',
                'category'        => 'Purchase',
                'description'     => 'Supplier: ' . ($po->supplier_name ?? 'N/A') . ($po->notes ? ' - ' . $po->notes : ''),
                'applicant_name'  => $po->supplier_name ?? 'Purchasing',
                'base_amount'     => (float) $po->total_amount,
                'vat_amount'      => 0,
                'net_amount'      => (float) $po->total_amount,
                'status'          => ucfirst($po->status ?? 'Draft'),
                'status_raw'      => $po->status ?? 'draft',
                'status_key'      => $statusKey,
                'color'           => $badgeColor,
                'attachment'      => null,
                'attachment_url'  => null,
                'rejection_reason'=> null,
                'paid_at'         => null,
                'paid_by_name'    => null,
                'payment_reference'=> null,
                'coa_name'        => null,
                'bank_name'       => null,
                'route_show'      => route('purchase-orders.show', $po->id),
                'route_approve'   => route('purchase-orders.issue', $po->id),
                'route_reject'    => '#',
                'raw_model'       => $po,
            ];
        });
        $items = $items->concat($pos);

        // 4. Fetch Emergency Funds
        if (class_exists(EmergencyFund::class)) {
            $efs = EmergencyFund::with(['project'])->latest()->get()->map(function ($ef) {
                $statusRaw = strtolower($ef->status ?? 'pending');
                $statusKey = match($statusRaw) {
                    'approved', 'disbursed' => 'paid',
                    'rejected'              => 'rejected',
                    default                 => 'pending_gm',
                };
                $badgeColor = match($statusKey) {
                    'paid'     => 'success',
                    'rejected' => 'danger',
                    default    => 'danger',
                };

                return (object) [
                    'id_raw'          => $ef->id,
                    'id_formatted'    => 'EMR-' . str_pad($ef->id, 5, '0', STR_PAD_LEFT),
                    'type'            => 'emergency_fund',
                    'date'            => $ef->created_at,
                    'project'         => $ef->project ? $ef->project->name : 'Operations',
                    'category'        => 'Emergency Fund',
                    'description'     => $ef->justification ?? 'Emergency Fund Request',
                    'applicant_name'  => 'Site / Operations',
                    'base_amount'     => (float) $ef->requested_amount,
                    'vat_amount'      => 0,
                    'net_amount'      => (float) $ef->requested_amount,
                    'status'          => ucfirst($ef->status ?? 'Pending'),
                    'status_raw'      => $ef->status ?? 'pending',
                    'status_key'      => $statusKey,
                    'color'           => $badgeColor,
                    'attachment'      => null,
                    'attachment_url'  => null,
                    'rejection_reason'=> null,
                    'paid_at'         => null,
                    'paid_by_name'    => null,
                    'payment_reference'=> null,
                    'coa_name'        => null,
                    'bank_name'       => null,
                    'route_show'      => route('emergency-funds.show', $ef->id),
                    'route_approve'   => route('emergency-funds.approve', $ef->id),
                    'route_reject'    => route('emergency-funds.reject', $ef->id),
                    'raw_model'       => $ef,
                ];
            });
            $items = $items->concat($efs);
        }

        $user = Auth::user();
        $rolesStr = strtolower(implode(' ', $user ? $user->getRoleNames()->toArray() : []));
        $isAdmin = $user && ($user->hasAnyRole(['admin', 'global_admin']) || str_contains($rolesStr, 'admin'));
        $isHR = $user && ($user->hasAnyRole(['HR Manager', 'hr_manager', 'HR Officer', 'hr_officer', 'hr']) || str_contains($rolesStr, 'hr') || $user->can('hr.view'));
        $isGM = $user && ($user->hasAnyRole(['General Manager', 'general_manager', 'gm']) || str_contains($rolesStr, 'gm'));
        $isFinance = $user && ($user->hasAnyRole(['Finance head', 'finance_head', 'finance_manager', 'Finance staff', 'finance_staff', 'finance', 'cashier', 'accountant']) || str_contains($rolesStr, 'finance') || str_contains($rolesStr, 'cashier') || str_contains($rolesStr, 'accountant'));

        // Role-based visibility scoping
        if ($isFinance && !$isAdmin && !$isHR && !$isGM) {
            // Finance only sees requests approved for finance queue, paid history, and rejected
            $items = $items->filter(function ($item) {
                return in_array($item->status_key, ['finance_queue', 'paid', 'rejected']);
            });
        } elseif ($isHR && !$isAdmin && !$isGM) {
            // HR only sees pending HR reviews and approved/rejected history
            $items = $items->filter(function ($item) {
                return in_array($item->status_key, ['pending_hr', 'paid', 'rejected', 'finance_queue']);
            });
        } elseif ($isGM && !$isAdmin) {
            // GM only sees pending GM reviews and approved/rejected history
            $items = $items->filter(function ($item) {
                return in_array($item->status_key, ['pending_gm', 'finance_queue', 'paid', 'rejected']);
            });
        }

        // Calculate Tab Counts
        $tabCounts = [
            'all'           => $items->count(),
            'pending_hr'    => $items->where('status_key', 'pending_hr')->count(),
            'pending_gm'    => $items->where('status_key', 'pending_gm')->count(),
            'finance_queue' => $items->where('status_key', 'finance_queue')->count(),
            'paid'          => $items->where('status_key', 'paid')->count(),
            'rejected'      => $items->where('status_key', 'rejected')->count(),
        ];

        // Determine default tab based on role
        if ($request->has('tab')) {
            $activeTab = $request->input('tab');
        } else {
            if ($isFinance && !$isAdmin && !$isHR && !$isGM) {
                $activeTab = 'finance_queue';
            } elseif ($isHR && !$isAdmin && !$isGM) {
                $activeTab = 'pending_hr';
            } elseif ($isGM && !$isAdmin) {
                $activeTab = 'pending_gm';
            } else {
                $activeTab = 'all';
            }
        }

        // Apply Tab Filter
        if ($activeTab !== 'all' && array_key_exists($activeTab, $tabCounts)) {
            $items = $items->where('status_key', $activeTab);
        }

        // Apply Status Filter if explicitly selected in dropdown
        if ($request->filled('status') && $request->status !== 'all') {
            $status = strtolower($request->status);
            $items = $items->filter(function ($item) use ($status) {
                return strtolower($item->status_key) === $status || strtolower($item->status) === $status;
            });
        }

        // Apply Project / Department Filter
        if ($request->filled('project') && $request->project !== 'all') {
            $projectName = strtolower($request->project);
            $items = $items->filter(function ($item) use ($projectName) {
                return str_contains(strtolower($item->project), $projectName);
            });
        }

        // Apply Category Filter
        if ($request->filled('category') && $request->category !== 'all') {
            $catFilter = strtolower($request->category);
            $items = $items->filter(function ($item) use ($catFilter) {
                return str_contains(strtolower($item->category), $catFilter);
            });
        }

        // Apply Date Range Filter
        if ($request->filled('date_from')) {
            $from = Carbon::parse($request->date_from)->startOfDay();
            $items = $items->filter(function ($item) use ($from) {
                return Carbon::parse($item->date)->gte($from);
            });
        }
        if ($request->filled('date_to')) {
            $to = Carbon::parse($request->date_to)->endOfDay();
            $items = $items->filter(function ($item) use ($to) {
                return Carbon::parse($item->date)->lte($to);
            });
        }

        // Apply Keyword Search
        if ($request->filled('search')) {
            $keyword = strtolower($request->search);
            $items = $items->filter(function ($item) use ($keyword) {
                return str_contains(strtolower($item->id_formatted), $keyword)
                    || str_contains(strtolower($item->description), $keyword)
                    || str_contains(strtolower($item->applicant_name), $keyword)
                    || str_contains(strtolower($item->project), $keyword)
                    || str_contains(strtolower($item->category), $keyword);
            });
        }

        // Sort items by latest date
        $items = $items->sortByDesc(function ($item) {
            return Carbon::parse($item->date)->timestamp;
        });

        // Collect distinct categories & projects for filter dropdowns
        $categories = [
            'Transport', 'Office Material', 'Loading & Unloading',
            'Contract Work', 'Maintenance', 'Purchase', 'Other'
        ];
        $projects = Project::orderBy('name')->get();

        // Finance Accounts & Staff for action modals
        $chartOfAccounts = ChartOfAccount::with('manager')->where('is_active', true)->orderBy('name')->get();
        $bankAccounts = BankAccount::orderBy('bank_name')->get();
        $financeStaff = User::whereHas('roles', function($q) {
            $q->whereIn('name', ['Finance staff', 'finance_staff', 'Finance head', 'finance_head', 'cashier', 'accountant', 'admin', 'global_admin']);
        })->orWhereHas('employee', function($q) {
            $q->where('department', 'like', '%Finance%');
        })->orderBy('name')->get();

        // Pagination
        $perPage = 20;
        $page = (int) $request->input('page', 1);
        $paginatedItems = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('finance.approvals.index', compact(
            'paginatedItems',
            'projects',
            'categories',
            'tabCounts',
            'activeTab',
            'chartOfAccounts',
            'bankAccounts',
            'financeStaff',
            'isAdmin',
            'isHR',
            'isGM',
            'isFinance'
        ));
    }
}
