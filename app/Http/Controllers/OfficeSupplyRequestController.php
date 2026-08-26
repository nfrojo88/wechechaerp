<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\PrWorkflowLog;
use App\Models\Project;
use App\Models\Store;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OfficeSupplyRequestController extends Controller
{
    /**
     * Check if the authenticated user has HR, Coordinator, GM, or Admin role.
     */
    protected function canApprove(): bool
    {
        $user = Auth::user();
        if (!$user) return false;

        $userRoles = $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray();
        $allowed = ['hr_manager', 'hr_officer', 'hr', 'coordinator', 'general_service', 'general_services', 'admin', 'global_admin', 'gm', 'general_manager'];

        foreach ($allowed as $role) {
            if (in_array($role, $userRoles)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the user's primary display role for workflow logs.
     */
    protected function getUserRoleSlug(): string
    {
        $user = Auth::user();
        if (!$user) return 'requester';

        $userRoles = $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray();
        if (in_array('global_admin', $userRoles) || in_array('admin', $userRoles)) return 'admin';
        if (in_array('gm', $userRoles) || in_array('general_manager', $userRoles)) return 'gm';
        if (in_array('coordinator', $userRoles)) return 'coordinator';
        if (in_array('hr_manager', $userRoles)) return 'hr_manager';
        if (in_array('hr_officer', $userRoles) || in_array('hr', $userRoles)) return 'hr_officer';
        if (in_array('secretary', $userRoles)) return 'secretary';
        return 'requester';
    }

    // ─── Index / List ────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $user = Auth::user();
        $canApprove = $this->canApprove();

        $query = PurchaseRequest::with(['project', 'requestedBy', 'hrCoordinatorApprovedBy', 'items.product'])
            ->where(function ($q) {
                $q->where('is_office_request', true)
                  ->orWhere('office_purpose', '!=', null)
                  ->orWhere('status', PurchaseRequest::STATUS_PENDING_HR_APPROVAL);
            })
            ->latest();

        // If user is secretary only (not admin/approver), default to showing their own requests unless specified
        $isSecretaryOnly = !$canApprove && $user->hasRole('secretary');
        if ($isSecretaryOnly) {
            $query->where('requested_by', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('pr_no', 'like', '%' . $request->search . '%')
                  ->orWhere('office_purpose', 'like', '%' . $request->search . '%')
                  ->orWhere('justification', 'like', '%' . $request->search . '%');
            });
        }

        $requests = $query->paginate(15)->withQueryString();

        // Summary Stats
        $baseStatsQuery = PurchaseRequest::where(function ($q) {
            $q->where('is_office_request', true)
              ->orWhere('office_purpose', '!=', null)
              ->orWhere('status', PurchaseRequest::STATUS_PENDING_HR_APPROVAL);
        });

        if ($isSecretaryOnly) {
            $baseStatsQuery->where('requested_by', $user->id);
        }

        $stats = [
            'total'     => (clone $baseStatsQuery)->count(),
            'pending'   => (clone $baseStatsQuery)->where('status', PurchaseRequest::STATUS_PENDING_HR_APPROVAL)->count(),
            'approved'  => (clone $baseStatsQuery)->whereIn('status', [PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_COMPLETED, PurchaseRequest::STATUS_INTAKE_COMPLETE])->count(),
            'rejected'  => (clone $baseStatsQuery)->where('status', PurchaseRequest::STATUS_REJECTED)->count(),
        ];

        return view('procurement.office-requests.index', compact('requests', 'stats', 'canApprove', 'isSecretaryOnly'));
    }

    // ─── Create ──────────────────────────────────────────────────────────────
    public function create()
    {
        $projects = Project::whereIn('status', ['active', 'planning', 'in_progress', 'on_hold'])->orderBy('name')->get();
        if ($projects->isEmpty()) {
            $projects = Project::orderBy('name')->get();
        }

        $stores = Store::where('is_active', true)->orderBy('name')->get();

        $products = Product::orderBy('name')->get()->map(function ($product) {
            $latestPriceRecord = null;
            try {
                $latestPriceRecord = \App\Models\MaterialPrice::where('product_id', $product->id)
                    ->orderBy('effective_date', 'desc')
                    ->first();
            } catch (\Throwable $e) {}

            $unitCost = $latestPriceRecord ? (float)$latestPriceRecord->price : (float)($product->unit_price ?? $product->selling_price ?? 0);
            $product->latest_marketing_price = $unitCost;
            return $product;
        });

        // Common standard office purpose categories
        $purposes = [
            'Stationery & Paper Supplies'      => 'Stationery & Paper Supplies (ደብተር፣ እስክሪብቶ፣ ወረቀት)',
            'Printing & Toners'                => 'Printing & Toners (ቶነር፣ ካርትሪጅ፣ ፕሪንተር እቃዎች)',
            'Pantry, Tea & Cleaning'           => 'Pantry, Tea & Cleaning (ሻይ፣ ስኳር፣ ሳሙና፣ የጽዳት እቃዎች)',
            'IT & Computer Accessories'        => 'IT & Computer Accessories (ፍላሽ፣ ኬብል፣ አይጥ፣ ኪቦርድ)',
            'Office Furniture & Fixtures'      => 'Office Furniture & Fixtures (ጠረጴዛ፣ ወንበር፣ መደርደሪያ)',
            'Administrative & General Service' => 'Administrative & General Service (አስተዳደራዊ እና ጠቅላላ አገልግሎት)',
            'Other Office Supplies'            => 'Other Office Supplies (ሌሎች የቢሮ እቃዎች)',
        ];

        return view('procurement.office-requests.create', compact('projects', 'stores', 'products', 'purposes'));
    }

    // ─── Store ───────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'office_purpose'     => 'required|string|max:255',
            'project_id'         => 'nullable|exists:projects,id',
            'store_id'           => 'nullable|exists:stores,id',
            'priority'           => 'required|in:normal,high,urgent',
            'required_date'      => 'nullable|date',
            'justification'      => 'nullable|string|max:1000',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
            'items.*.unit'       => 'nullable|string|max:30',
            'items.*.specifications' => 'nullable|string|max:500',
        ]);

        // Strict association: Office Requests belong exclusively to Head Office
        $headOfficeProject = Project::where('name', 'like', '%Head Office%')
            ->orWhere('name', 'like', '%Main Office%')
            ->first();

        if (!$headOfficeProject) {
            try {
                $headOfficeProject = Project::firstOrCreate(
                    ['name' => 'Head Office (ዋና ቢሮ)'],
                    [
                        'code'        => 'HO-ADM',
                        'status'      => 'active',
                        'client_name' => 'Internal Administration',
                        'start_date'  => now(),
                    ]
                );
            } catch (\Throwable $e) {
                $headOfficeProject = Project::first();
            }
        }
        $projectId = $headOfficeProject ? $headOfficeProject->id : 1;

        $pr = DB::transaction(function () use ($request, $projectId) {
            $no = 'PR-OFF-' . date('Ymd') . '-' . str_pad(PurchaseRequest::withTrashed()->count() + 1, 4, '0', STR_PAD_LEFT);

            $purchaseRequest = PurchaseRequest::create([
                'pr_no'               => $no,
                'project_id'          => $projectId,
                'store_id'            => $request->store_id,
                'requested_by'        => Auth::id(),
                'priority'            => $request->priority,
                'type'                => 'normal',
                'is_office_request'   => true,
                'office_purpose'      => $request->office_purpose,
                'required_date'       => $request->required_date,
                'justification'       => $request->justification,
                'status'              => PurchaseRequest::STATUS_PENDING_HR_APPROVAL,
                'current_owner_role'  => 'hr_manager',
            ]);

            foreach ($request->items as $item) {
                $prod = Product::find($item['product_id']);
                $unit = !empty($item['unit']) ? $item['unit'] : ($prod?->unit ?? 'pcs');

                $latestPriceRecord = null;
                try {
                    $latestPriceRecord = \App\Models\MaterialPrice::where('product_id', $item['product_id'])
                        ->orderBy('effective_date', 'desc')
                        ->first();
                } catch (\Throwable $e) {}

                $estCost = $latestPriceRecord ? (float)$latestPriceRecord->price : (float)($prod?->unit_price ?? $prod?->selling_price ?? 0);
                if (isset($item['estimated_unit_cost']) && $item['estimated_unit_cost'] !== '' && (float)$item['estimated_unit_cost'] > 0) {
                    $estCost = (float) $item['estimated_unit_cost'];
                }

                $purchaseRequest->items()->create([
                    'product_id'          => $item['product_id'],
                    'quantity'            => $item['quantity'],
                    'unit'                => $unit,
                    'specifications'      => $item['specifications'] ?? null,
                    'estimated_unit_cost' => $estCost,
                ]);
            }

            // Create Initial Workflow Log
            try {
                PrWorkflowLog::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'from_stage'          => PurchaseRequest::STATUS_DRAFT,
                    'to_stage'            => PurchaseRequest::STATUS_PENDING_HR_APPROVAL,
                    'action'              => 'office_request_submitted',
                    'actor_role'          => 'secretary',
                    'actor_id'            => Auth::id(),
                    'notes'               => "Office Supply Request ({$request->office_purpose}) submitted for HR / Coordinator review.",
                    'created_at'          => now(),
                ]);
            } catch (\Throwable $e) {}

            return $purchaseRequest;
        });

        return redirect()->route('office-requests.show', $pr->id)
            ->with('success', "Office Supply Request #{$pr->pr_no} has been submitted directly to HR & Coordinator for review.");
    }

    // ─── Show ────────────────────────────────────────────────────────────────
    public function show(PurchaseRequest $office_request)
    {
        $office_request->load([
            'project', 'store', 'requestedBy', 'hrCoordinatorApprovedBy', 'approvedBy',
            'items.product', 'workflowLogs.actor',
        ]);

        $canApprove = $this->canApprove();

        // Check stock availability in active stores for these requested items
        $stockAvailability = [];
        foreach ($office_request->items as $item) {
            if ($item->product_id) {
                try {
                    $stockAvailability[$item->product_id] = Inventory::with('store')
                        ->where('product_id', $item->product_id)
                        ->where('quantity_on_hand', '>', 0)
                        ->get();
                } catch (\Throwable $e) {
                    $stockAvailability[$item->product_id] = collect();
                }
            }
        }

        return view('procurement.office-requests.show', compact('office_request', 'canApprove', 'stockAvailability'));
    }

    // ─── Approve (HR / Coordinator) ──────────────────────────────────────────
    public function approve(Request $request, PurchaseRequest $office_request)
    {
        if (!$this->canApprove()) {
            abort(403, 'Unauthorized: Only HR Manager, Coordinator, GM, or Admin can approve this office supply request.');
        }

        $request->validate([
            'notes'       => 'nullable|string|max:1000',
            'next_action' => 'required|in:approved_direct,send_to_procurement,send_to_store',
        ]);

        $actorRole = $this->getUserRoleSlug();
        $fromStatus = $office_request->status;

        $newStatus = PurchaseRequest::STATUS_APPROVED;
        $ownerRole = 'secretary';
        $actionNote = "Approved by {$actorRole} (" . Auth::user()->name . ")";

        if ($request->next_action === 'send_to_procurement') {
            $newStatus = PurchaseRequest::STATUS_PENDING_PROC_TEAM;
            $ownerRole = 'procurement_team';
            $actionNote .= " and forwarded to Procurement Team for purchasing.";
        } elseif ($request->next_action === 'send_to_store') {
            $newStatus = PurchaseRequest::STATUS_PENDING_STORE_REVIEW;
            $ownerRole = 'store_manager';
            $actionNote .= " and routed to Store for material dispatch.";
        } else {
            $actionNote .= " for direct fulfillment.";
        }

        if ($request->filled('notes')) {
            $actionNote .= " Note: " . $request->notes;
        }

        DB::transaction(function () use ($office_request, $request, $newStatus, $ownerRole, $fromStatus, $actorRole, $actionNote) {
            $office_request->update([
                'status'                     => $newStatus,
                'hr_coordinator_approved_by' => Auth::id(),
                'hr_coordinator_approved_at' => now(),
                'hr_coordinator_notes'       => $request->notes,
                'approved_by'                => Auth::id(),
                'approved_at'                => now(),
                'current_owner_role'         => $ownerRole,
            ]);

            try {
                PrWorkflowLog::create([
                    'purchase_request_id' => $office_request->id,
                    'from_stage'          => $fromStatus,
                    'to_stage'            => $newStatus,
                    'action'              => 'hr_coordinator_approved',
                    'actor_role'          => $actorRole,
                    'actor_id'            => Auth::id(),
                    'notes'               => $actionNote,
                    'created_at'          => now(),
                ]);
            } catch (\Throwable $e) {}
        });

        return back()->with('success', "Office Supply Request #{$office_request->pr_no} approved successfully.");
    }

    // ─── Reject (HR / Coordinator) ──────────────────────────────────────────
    public function reject(Request $request, PurchaseRequest $office_request)
    {
        if (!$this->canApprove()) {
            abort(403, 'Unauthorized: Only HR Manager, Coordinator, GM, or Admin can reject this office supply request.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $actorRole = $this->getUserRoleSlug();
        $fromStatus = $office_request->status;

        DB::transaction(function () use ($office_request, $request, $fromStatus, $actorRole) {
            $office_request->update([
                'status'               => PurchaseRequest::STATUS_REJECTED,
                'rejection_reason'     => $request->rejection_reason,
                'hr_coordinator_notes' => 'Rejected: ' . $request->rejection_reason,
                'current_owner_role'   => null,
            ]);

            try {
                PrWorkflowLog::create([
                    'purchase_request_id' => $office_request->id,
                    'from_stage'          => $fromStatus,
                    'to_stage'            => PurchaseRequest::STATUS_REJECTED,
                    'action'              => 'hr_coordinator_rejected',
                    'actor_role'          => $actorRole,
                    'actor_id'            => Auth::id(),
                    'notes'               => "Rejected by {$actorRole} (" . Auth::user()->name . "): " . $request->rejection_reason,
                    'created_at'          => now(),
                ]);
            } catch (\Throwable $e) {}
        });

        return back()->with('success', "Office Supply Request #{$office_request->pr_no} has been rejected.");
    }
}
