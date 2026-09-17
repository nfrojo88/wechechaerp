<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialRequest;
use App\Models\PurchaseRequest;
use App\Models\Project;
use App\Models\Store;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScratchMaterialRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user) {
                abort(403, 'Access denied.');
            }

            $hasAccess = false;
            $rawRoles = method_exists($user, 'roles')
                ? $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray()
                : [];

            if (in_array('global_admin', $rawRoles) || in_array('admin', $rawRoles)) {
                $hasAccess = true;
            }

            if (!$hasAccess && method_exists($user, 'hasAnyRole')) {
                $hasAccess = $user->hasAnyRole(['global_admin', 'admin', 'Global Admin', 'Admin']);
            }

            if (!$hasAccess) {
                abort(403, 'Access denied. Global Admin or Admin only.');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $activeTab   = $request->query('tab', 'all');
        $search      = trim($request->query('search', ''));
        $projectId   = $request->query('project_id');
        $status      = $request->query('status');
        $dateFrom    = $request->query('date_from');
        $dateTo      = $request->query('date_to');

        // Base Scratch Material Requests Query (Not generated from Maintenance or Coordinator forecast)
        $mrQuery = MaterialRequest::with(['project', 'store', 'creator', 'items.product', 'purchaseRequests'])
            ->whereNull('maintenance_request_id')
            ->where(function ($q) {
                $q->whereNull('source')
                  ->orWhere('source', '')
                  ->orWhere(function ($sub) {
                      $sub->where('source', 'not like', 'Maintenance%')
                          ->where('source', 'not like', 'Coordinator%');
                  });
            });

        // Base Scratch Purchase Requests Query (Direct purchase requests without prior material request)
        $prQuery = PurchaseRequest::with(['project', 'store', 'requestedBy', 'items.product', 'supplier', 'workflowLogs.actor'])
            ->whereNull('material_request_id');

        // Apply filters to Material Requests
        if (!empty($search)) {
            $mrQuery->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('source', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('creator', fn($u) => $u->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('items.product', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }
        if (!empty($projectId)) {
            $mrQuery->where('project_id', $projectId);
        }
        if (!empty($status)) {
            $mrQuery->where('status', $status);
        }
        if (!empty($dateFrom)) {
            $mrQuery->whereDate('required_date', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $mrQuery->whereDate('required_date', '<=', $dateTo);
        }

        // Apply filters to Purchase Requests
        if (!empty($search)) {
            $prQuery->where(function ($q) use ($search) {
                $q->where('pr_no', 'like', "%{$search}%")
                  ->orWhere('justification', 'like', "%{$search}%")
                  ->orWhereHas('requestedBy', fn($u) => $u->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('items.product', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }
        if (!empty($projectId)) {
            $prQuery->where('project_id', $projectId);
        }
        if (!empty($status)) {
            $prQuery->where('status', $status);
        }
        if (!empty($dateFrom)) {
            $prQuery->whereDate('required_date', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $prQuery->whereDate('required_date', '<=', $dateTo);
        }

        // Calculate KPI counters (across scratch queries)
        $kpi = [
            'total_scratch_mr'    => (clone $mrQuery)->count(),
            'pending_scratch_mr'  => (clone $mrQuery)->whereIn('status', ['draft', 'pending_planning', 'submitted', 'sent_to_store_manager'])->count(),
            'approved_scratch_mr' => (clone $mrQuery)->whereIn('status', ['planning_approved', 'approved', 'sent_to_pr', 'transfer_created', 'fulfilled'])->count(),
            'total_scratch_pr'    => (clone $prQuery)->count(),
            'pending_scratch_pr'  => (clone $prQuery)->whereNotIn('status', ['completed', 'intake_complete', 'rejected', 'cancelled'])->count(),
        ];

        // Paginate results based on active tab
        $materialRequests = ($activeTab === 'purchase')
            ? collect()
            : (clone $mrQuery)->latest()->paginate(15, ['*'], 'mr_page')->withQueryString();

        $purchaseRequests = ($activeTab === 'material')
            ? collect()
            : (clone $prQuery)->latest()->paginate(15, ['*'], 'pr_page')->withQueryString();

        // Eagerly resolve linked transfers for paginated PRs to show zero-item transfer breakdown
        if ($purchaseRequests instanceof \Illuminate\Pagination\LengthAwarePaginator && $purchaseRequests->isNotEmpty()) {
            $prNos = $purchaseRequests->pluck('pr_no')->filter()->toArray();

            $transfers = \App\Models\Transfer::with(['fromStore', 'toStore', 'items.product', 'driver'])
                ->where(function ($q) use ($prNos) {
                    foreach ($prNos as $no) {
                        $q->orWhere('reason', 'like', "%{$no}%");
                    }
                })
                ->get();

            foreach ($purchaseRequests as $pr) {
                $pr->matched_transfers = $transfers->filter(function ($t) use ($pr) {
                    return str_contains($t->reason ?? '', $pr->pr_no);
                });
            }
        }

        $projects = Project::orderBy('name')->get();
        $stores   = Store::where('is_active', true)->orderBy('name')->get();

        return view('admin.procurement.scratch-requests', compact(
            'materialRequests',
            'purchaseRequests',
            'projects',
            'stores',
            'kpi',
            'activeTab'
        ));
    }

    public function exportCsv(Request $request)
    {
        $search    = trim($request->query('search', ''));
        $projectId = $request->query('project_id');
        $status    = $request->query('status');
        $dateFrom  = $request->query('date_from');
        $dateTo    = $request->query('date_to');

        $query = MaterialRequest::with(['project', 'store', 'creator', 'items.product'])
            ->whereNull('maintenance_request_id')
            ->where(function ($q) {
                $q->whereNull('source')
                  ->orWhere('source', '')
                  ->orWhere(function ($sub) {
                      $sub->where('source', 'not like', 'Maintenance%')
                          ->where('source', 'not like', 'Coordinator%');
                  });
            });

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('source', 'like', "%{$search}%")
                  ->orWhereHas('creator', fn($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }
        if (!empty($projectId)) {
            $query->where('project_id', $projectId);
        }
        if (!empty($status)) {
            $query->where('status', $status);
        }
        if (!empty($dateFrom)) {
            $query->whereDate('required_date', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->whereDate('required_date', '<=', $dateTo);
        }

        $records = $query->latest()->get();

        $filename = 'scratch_material_requests_' . date('Ymd_His') . '.csv';

        return new StreamedResponse(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Reference Number',
                'Source / Tag',
                'Project',
                'Destination Store',
                'Required Date',
                'Status',
                'Items Count',
                'Requested By',
                'Created At',
            ]);

            foreach ($records as $row) {
                fputcsv($handle, [
                    $row->reference_number,
                    $row->source ?? 'Manual Creation',
                    $row->project?->name ?? 'Central / HQ',
                    $row->store?->name ?? 'General Store',
                    optional($row->required_date)->format('Y-m-d') ?? '-',
                    $row->status,
                    $row->items->count(),
                    $row->creator?->name ?? 'Staff',
                    optional($row->created_at)->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
