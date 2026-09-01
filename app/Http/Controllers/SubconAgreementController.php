<?php

namespace App\Http\Controllers;

use App\Models\SubconAgreement;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\TakeoffSheet;
use App\Models\TakeoffItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SubconAgreementController extends Controller
{
    /**
     * Display listing of subcontractor agreements
     */
    public function index(Request $request)
    {
        $status = $request->input('status');
        $search = $request->input('search');
        $projectId = $request->input('project_id');

        $query = SubconAgreement::with(['project', 'supplier', 'createdBy', 'approvedBy']);

        if (!empty($status) && $status !== 'all') {
            $query->where('status', $status);
        }

        if (!empty($projectId)) {
            $query->where('project_id', $projectId);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('agreement_no', 'like', "%{$search}%")
                  ->orWhere('subcontractor_name', 'like', "%{$search}%")
                  ->orWhere('work_description', 'like', "%{$search}%")
                  ->orWhere('scope_of_work', 'like', "%{$search}%")
                  ->orWhereHas('project', fn($sq) => $sq->where('name', 'like', "%{$search}%")->orWhere('project_name', 'like', "%{$search}%"))
                  ->orWhereHas('supplier', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $agreements = $query->latest()->paginate(20)->withQueryString();
        $projects = Project::where('status', 'active')->orderBy('name')->get();

        // Status counters
        $statusCounts = [
            'all'       => SubconAgreement::count(),
            'draft'     => SubconAgreement::where('status', 'draft')->count(),
            'pending'   => SubconAgreement::where('status', 'pending')->count(),
            'approved'  => SubconAgreement::where('status', 'approved')->count(),
            'active'    => SubconAgreement::where('status', 'active')->count(),
            'completed' => SubconAgreement::where('status', 'completed')->count(),
        ];

        return view('procurement.subcon-agreements.index', compact('agreements', 'statusCounts', 'projects', 'status', 'search', 'projectId'));
    }

    /**
     * Show form to create new agreement & upload agreement document
     */
    public function create()
    {
        $projects = Project::where('status', 'active')->orderBy('name')->get();
        $suppliers = Supplier::where('status', 'active')->orderBy('name')->get();
        $takeoffs = TakeoffSheet::where('status', 'approved')->with('project')->latest()->get();

        return view('procurement.subcon-agreements.create', compact('projects', 'suppliers', 'takeoffs'));
    }

    /**
     * Get takeoff items for selected takeoff sheet (AJAX)
     */
    public function getTakeoffItems(Request $request)
    {
        $takeoffId = $request->input('takeoff_id');

        if (!$takeoffId) {
            return response()->json(['items' => []]);
        }

        $items = TakeoffItem::where('takeoff_sheet_id', $takeoffId)
            ->with(['section', 'product'])
            ->select('id', 'takeoff_sheet_id', 'takeoff_section_id', 'description', 'quantity', 'unit', 'estimated_rate')
            ->get();

        return response()->json(['items' => $items]);
    }

    /**
     * Store new subcontractor agreement & uploaded file
     */
    public function store(Request $request)
    {
        $request->validate([
            'project_id'          => 'required|exists:projects,id',
            'supplier_id'         => 'nullable|exists:suppliers,id',
            'subcontractor_name'  => 'nullable|string|max:255',
            'subcontractor_contact' => 'nullable|string|max:255',
            'takeoff_sheet_id'    => 'nullable|exists:takeoff_sheets,id',
            'work_description'    => 'required|string',
            'start_date'          => 'required|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
            'contract_value'      => 'nullable|numeric|min:0',
            'retention_percent'   => 'nullable|numeric|min:0|max:100',
            'terms_conditions'    => 'nullable|string',
            'agreement_file'      => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp|max:20480',
            'items'               => 'nullable|array',
            'items.*.task_description' => 'required_with:items|string|max:255',
            'items.*.quantity'         => 'required_with:items|numeric|min:0.001',
            'items.*.unit'             => 'required_with:items|string|max:20',
            'items.*.unit_rate'        => 'required_with:items|numeric|min:0',
            'takeoff_items'       => 'nullable|array',
            'takeoff_items.*'     => 'exists:takeoff_items,id',
        ]);

        if (empty($request->supplier_id) && empty($request->subcontractor_name)) {
            return back()->withInput()->withErrors(['supplier_id' => 'Please select an existing supplier/subcontractor or enter the subcontractor name.']);
        }

        // Handle uploaded agreement file
        $filePath = null;
        if ($request->hasFile('agreement_file')) {
            $file = $request->file('agreement_file');
            $fileName = 'subcon_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('subcon_agreements', $fileName, 'public');
        }

        // Subcontractor name resolution
        $subcontractorName = $request->subcontractor_name;
        if (empty($subcontractorName) && $request->supplier_id) {
            $supplierObj = Supplier::find($request->supplier_id);
            $subcontractorName = $supplierObj?->name;
        }

        $agreement = DB::transaction(function () use ($request, $filePath, $subcontractorName) {
            $no = 'SUB-' . date('Ymd') . '-' . str_pad(SubconAgreement::count() + 1, 4, '0', STR_PAD_LEFT);
            $retentionPct = (float)($request->retention_percent ?? 10.00);
            $contractVal  = (float)($request->contract_value ?? 0);

            $agr = SubconAgreement::create([
                'agreement_no'         => $no,
                'project_id'           => $request->project_id,
                'supplier_id'          => $request->supplier_id,
                'subcontractor_name'   => $subcontractorName,
                'subcontractor_contact'=> $request->subcontractor_contact,
                'takeoff_sheet_id'     => $request->takeoff_sheet_id,
                'scope_of_work'        => $request->work_description,
                'work_description'     => $request->work_description,
                'start_date'           => $request->start_date,
                'end_date'             => $request->end_date,
                'contract_value'       => $contractVal,
                'retention_percent'    => $retentionPct,
                'retention_amount'     => round($contractVal * ($retentionPct / 100), 2),
                'terms_conditions'     => $request->terms_conditions,
                'agreement_file'       => $filePath,
                'status'               => 'draft',
                'created_by'           => Auth::id(),
            ]);

            $manualTotal = 0;
            if ($request->has('items') && is_array($request->items)) {
                foreach ($request->items as $item) {
                    if (empty($item['task_description'])) continue;
                    $qty = (float)($item['quantity'] ?? 0);
                    $rate = (float)($item['unit_rate'] ?? 0);
                    $total = round($qty * $rate, 2);
                    $manualTotal += $total;

                    $agr->items()->create([
                        'task_description' => $item['task_description'],
                        'quantity'         => $qty,
                        'unit'             => $item['unit'] ?? 'pcs',
                        'unit_rate'        => $rate,
                        'total_amount'     => $total,
                    ]);
                }
            }

            // Attach selected takeoff items if provided
            $takeoffTotal = 0;
            if ($request->has('takeoff_items') && $request->takeoff_items) {
                $takeoffData = [];
                $takeoffItems = TakeoffItem::whereIn('id', $request->takeoff_items)->get();

                foreach ($takeoffItems as $item) {
                    $rate = (float)($request->input("takeoff_rate_{$item->id}") ?? $item->estimated_rate ?? 0);
                    $quantity = (float)($request->input("takeoff_qty_{$item->id}") ?? $item->quantity ?? 0);
                    $totalAmount = round($quantity * $rate, 2);
                    $takeoffTotal += $totalAmount;

                    $takeoffData[$item->id] = [
                        'selected_quantity' => $quantity,
                        'rate'              => $rate,
                        'total_amount'      => $totalAmount,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];
                }

                $agr->takeoffItems()->attach($takeoffData);
            }

            $calculatedTotal = $manualTotal + $takeoffTotal;
            $finalTotal = $calculatedTotal > 0 ? $calculatedTotal : $contractVal;

            $agr->update([
                'total_amount'     => $finalTotal,
                'contract_value'   => $finalTotal > 0 ? $finalTotal : $contractVal,
                'retention_amount' => round($finalTotal * ($retentionPct / 100), 2),
            ]);

            return $agr;
        });

        return redirect()->route('subcon-agreements.show', $agreement)
            ->with('success', 'Subcontractor Agreement created and document uploaded successfully.');
    }

    /**
     * Show details of a specific agreement
     */
    public function show(SubconAgreement $subconAgreement)
    {
        $subconAgreement->load([
            'project',
            'supplier',
            'createdBy',
            'approvedBy',
            'items',
            'takeoffItems.section',
            'takeoffSheet.items',
            'ipcs'
        ]);

        return view('procurement.subcon-agreements.show', compact('subconAgreement'));
    }

    /**
     * Upload or update agreement document
     */
    public function uploadFile(Request $request, SubconAgreement $subconAgreement)
    {
        $request->validate([
            'agreement_file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp|max:20480',
        ]);

        // Delete old file if existing
        if ($subconAgreement->agreement_file && Storage::disk('public')->exists($subconAgreement->agreement_file)) {
            Storage::disk('public')->delete($subconAgreement->agreement_file);
        }

        $file = $request->file('agreement_file');
        $fileName = 'subcon_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs('subcon_agreements', $fileName, 'public');

        $subconAgreement->update([
            'agreement_file' => $filePath,
        ]);

        return back()->with('success', 'Subcontractor agreement document uploaded successfully.');
    }

    /**
     * Download or stream agreement document
     */
    public function downloadFile(SubconAgreement $subconAgreement)
    {
        if (empty($subconAgreement->agreement_file)) {
            return back()->with('error', 'No agreement document uploaded for this agreement.');
        }

        if (Storage::disk('public')->exists($subconAgreement->agreement_file)) {
            return Storage::disk('public')->download($subconAgreement->agreement_file, basename($subconAgreement->agreement_file));
        }

        $fullPath = public_path('storage/' . $subconAgreement->agreement_file);
        if (file_exists($fullPath)) {
            return response()->download($fullPath);
        }

        return redirect($subconAgreement->agreement_file_url);
    }

    /**
     * Helper to verify HR or Admin authorization
     */
    private function checkHrOrAdminAuth()
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $rawRoles = $user->roles->pluck('name')->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray();
        $allowedRoles = ['hr_officer', 'hr_manager', 'hr', 'admin', 'global_admin', 'gm', 'general_manager', 'contract_admin', 'coordinator'];
        
        $hasRole = !empty(array_intersect($rawRoles, $allowedRoles)) || $user->hasAnyRole($allowedRoles);
        $hasPerm = $user->can('hr.manage') || $user->can('subcon.approve') || $user->can('subcon.edit');

        if (!$hasRole && !$hasPerm) {
            abort(403, 'Unauthorized. Only HR Managers or Admins can perform this action.');
        }
    }

    /**
     * Approve subcon agreement
     */
    public function approve(Request $request, SubconAgreement $subconAgreement)
    {
        $this->checkHrOrAdminAuth();

        $subconAgreement->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Subcontractor agreement approved successfully.');
    }

    /**
     * Reject subcon agreement
     */
    public function reject(Request $request, SubconAgreement $subconAgreement)
    {
        $this->checkHrOrAdminAuth();

        $subconAgreement->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->input('reason', 'Agreement rejected by reviewer.'),
            'approved_by'      => Auth::id(),
            'approved_at'      => now(),
        ]);

        return back()->with('success', 'Subcontractor agreement marked as rejected.');
    }

    /**
     * Activate approved agreement
     */
    public function activate(Request $request, SubconAgreement $subconAgreement)
    {
        $this->checkHrOrAdminAuth();

        if (!in_array($subconAgreement->status, ['approved', 'draft'])) {
            return back()->withErrors('Only approved or draft agreements can be activated.');
        }

        $subconAgreement->update([
            'status' => 'active',
            'approved_by' => $subconAgreement->approved_by ?? Auth::id(),
            'approved_at' => $subconAgreement->approved_at ?? now(),
        ]);

        return back()->with('success', 'Subcontractor agreement activated successfully.');
    }
}
