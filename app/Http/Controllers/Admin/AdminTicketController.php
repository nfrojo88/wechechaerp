<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\ItMaterialRequestItem;
use App\Services\ItIncidentAlertService;
use Illuminate\Http\Request;

class AdminTicketController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            $adminRoles = ['global_admin', 'admin', 'gm', 'General Manager', 'general_manager', 'it_admin', 'it_manager', 'store_manager', 'Store Manager', 'secretary'];
            if (!$user || !$user->roles || !$user->roles->whereIn('name', $adminRoles)->count()) {
                abort(403, 'Access denied. Authorized roles only.');
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        SupportTicket::ensureSchema();

        $query = SupportTicket::with(['user', 'assignedTo', 'receivedBy'])->withCount('replies');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('submission_type')) {
            $query->where('submission_type', $request->submission_type);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('subject', 'like', $term)
                  ->orWhere('ticket_no', 'like', $term)
                  ->orWhere('submitter_name', 'like', $term)
                  ->orWhere('department', 'like', $term)
                  ->orWhere('affected_system', 'like', $term)
                  ->orWhere('suggestion_title', 'like', $term);
            });
        }

        /** @var \Illuminate\Pagination\LengthAwarePaginator $tickets */
        $tickets = $query->latest()->paginate(20);
        $tickets->appends($request->query());
        $admins = User::whereHas('roles', fn($q) => $q->whereIn('name', ['global_admin', 'admin', 'gm', 'it_admin', 'it_manager']))->get(['id', 'name']);

        $stats = [
            'total'       => SupportTicket::count(),
            'open'        => SupportTicket::whereIn('status', ['new', 'open'])->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'waiting'     => SupportTicket::where('status', 'waiting_for_user')->count(),
            'resolved'    => SupportTicket::where('status', 'resolved')->count(),
            'closed'      => SupportTicket::where('status', 'closed')->count(),
            'critical'    => SupportTicket::whereIn('priority', ['critical', 'urgent'])->whereNotIn('status', ['resolved', 'closed'])->count(),
        ];

        return view('admin.tickets.index', compact('tickets', 'admins', 'stats'));
    }

    public function show(SupportTicket $ticket)
    {
        SupportTicket::ensureSchema();

        $ticket->load([
            'user.employee',
            'replies.user',
            'assignedTo',
            'receivedBy',
            'materialRequestItems',
            'mrGmDecidedBy',
            'mrStoreManagedBy',
            'mrSecretaryReceivedBy',
        ]);
        $admins = User::whereHas('roles', fn($q) => $q->whereIn('name', ['global_admin', 'admin', 'gm', 'it_admin', 'it_manager']))->get(['id', 'name']);

        return view('admin.tickets.show', compact('ticket', 'admins'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'message' => 'required|string|max:3000',
        ]);

        TicketReply::create([
            'ticket_id'      => $ticket->id,
            'user_id'        => auth()->id(),
            'message'        => $request->message,
            'is_admin_reply' => true,
        ]);

        if (in_array($ticket->status, ['new', 'open'])) {
            $ticket->update(['status' => 'in_progress']);
        }

        ActivityLog::log('replied', "Admin reply added to ticket {$ticket->ticket_no}", 'Support Tickets', $ticket);

        return back()->with('success', 'Admin reply sent successfully.');
    }

    /**
     * Update Section 6: For IT Department / Admin Use Only
     */
    public function updateItSection(Request $request, SupportTicket $ticket)
    {
        SupportTicket::ensureSchema();

        $request->validate([
            'status'                   => 'required|string|in:new,open,in_progress,waiting_for_user,resolved,rejected,planned,closed',
            'priority'                 => 'required|string|in:critical,urgent,high,medium,low',
            'assigned_to'              => 'nullable|exists:users,id',
            'received_by_id'           => 'nullable|exists:users,id',
            'date_received'            => 'nullable|date',
            'target_resolution_date'   => 'nullable|date',
            'root_cause'               => 'nullable|string|max:3000',
            'actions_taken'            => 'nullable|string|max:3000',
            'resolution_decision'      => 'nullable|string|max:3000',
            'date_closed'              => 'nullable|date',
            'user_confirmed_resolved'  => 'nullable|in:yes,no,pending',
        ]);

        $status = $request->status === 'new' ? 'open' : $request->status;
        $priority = match(strtolower($request->priority)) {
            'critical' => 'urgent',
            'high'     => 'high',
            'low'      => 'low',
            default    => 'medium',
        };

        $data = [
            'status'                  => $status,
            'priority'                => $priority,
            'impact_urgency'          => $request->priority,
            'assigned_to'             => $request->assigned_to,
            'received_by_id'          => $request->received_by_id ?: auth()->id(),
            'date_received'           => $request->date_received ?: ($ticket->date_received ?? now()->toDateString()),
            'target_resolution_date'  => $request->target_resolution_date,
            'root_cause'              => $request->root_cause,
            'actions_taken'           => $request->actions_taken,
            'resolution_decision'     => $request->resolution_decision,
            'date_closed'             => $request->date_closed,
            'user_confirmed_resolved' => $request->user_confirmed_resolved ?? 'pending',
        ];

        if (in_array($status, ['resolved', 'closed']) && empty($ticket->resolved_at)) {
            $data['resolved_at'] = now();
            if (empty($data['date_closed'])) {
                $data['date_closed'] = now()->toDateString();
            }
        } elseif ($request->status !== 'resolved' && $request->status !== 'closed') {
            $data['resolved_at'] = null;
        }

        $ticket->update($data);

        ActivityLog::log('updated', "IT Department details updated for ticket {$ticket->ticket_no} (Status: {$request->status})", 'Support Tickets', $ticket);

        return back()->with('success', 'IT Department section updated successfully.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'status' => 'required|in:new,open,in_progress,waiting_for_user,resolved,closed,rejected,planned',
        ]);

        $data = ['status' => $request->status];
        if (in_array($request->status, ['resolved', 'closed'])) {
            $data['resolved_at'] = now();
            if (empty($ticket->date_closed)) {
                $data['date_closed'] = now()->toDateString();
            }
        }
        $ticket->update($data);

        ActivityLog::log('updated', "Ticket {$ticket->ticket_no} status changed to {$request->status}", 'Support Tickets', $ticket);

        return back()->with('success', 'Ticket status updated to "' . ucfirst(str_replace('_', ' ', $request->status)) . '".');
    }

    public function assign(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $ticket->update([
            'assigned_to'    => $request->assigned_to,
            'received_by_id' => $ticket->received_by_id ?: auth()->id(),
        ]);

        ActivityLog::log('assigned', "Ticket {$ticket->ticket_no} assigned to user ID {$request->assigned_to}", 'Support Tickets', $ticket);

        return back()->with('success', 'Ticket assigned successfully.');
    }

    /**
     * Resend/Trigger SMS alert to GM & Global Admin.
     */
    public function resendSms(SupportTicket $ticket, ItIncidentAlertService $alertService)
    {
        $result = $alertService->sendProblemReportAlert($ticket, true);

        if (!empty($result['success'])) {
            return back()->with('success', "SMS alert re-dispatched to {$result['recipients_sent']} recipient(s) (GM & Global Admin).");
        } else {
            return back()->with('error', "SMS dispatch failed: " . ($result['error'] ?? 'Unknown gateway issue'));
        }
    }

    // ── Material Request Workflow ─────────────────────────────────────────────

    /**
     * GM: Approve a Material Request and forward to Store Manager.
     */
    public function gmApproveMaterialRequest(Request $request, SupportTicket $ticket, ItIncidentAlertService $alertService)
    {
        if (!$ticket->has_material_request) {
            return back()->with('error', 'This ticket has no material request.');
        }

        $request->validate([
            'mr_gm_notes' => 'nullable|string|max:1000',
        ]);

        $ticket->load('materialRequestItems');

        $ticket->update([
            'mr_gm_status'       => 'approved_to_store',
            'mr_gm_decided_at'   => now(),
            'mr_gm_decided_by'   => auth()->id(),
            'mr_gm_notes'        => $request->mr_gm_notes,
            'mr_store_status'    => 'pending_store',
        ]);

        // Notify Store Manager via SMS
        try {
            $alertService->sendStoreManagerAlert($ticket);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Store Manager SMS failed: ' . $e->getMessage());
        }

        ActivityLog::log('approved', "GM approved Material Request for Ticket {$ticket->ticket_no} → forwarded to Store Manager.", 'IT Material Requests', $ticket);

        return back()->with('success', 'Material Request approved and forwarded to Store Manager via SMS.');
    }

    /**
     * GM: Reject a Material Request.
     */
    public function gmRejectMaterialRequest(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'mr_gm_notes' => 'required|string|max:1000',
        ]);

        $ticket->update([
            'mr_gm_status'     => 'rejected_by_gm',
            'mr_gm_decided_at' => now(),
            'mr_gm_decided_by' => auth()->id(),
            'mr_gm_notes'      => $request->mr_gm_notes,
        ]);

        ActivityLog::log('rejected', "GM rejected Material Request for Ticket {$ticket->ticket_no}. Reason: {$request->mr_gm_notes}", 'IT Material Requests', $ticket);

        return back()->with('success', 'Material Request rejected.');
    }

    /**
     * Store Manager: Record dispatch status for each item and overall status.
     */
    public function storeManagerDispatch(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'mr_store_status' => 'required|in:dispatched,partially_dispatched,unavailable',
            'mr_store_notes'  => 'nullable|string|max:2000',
            'items'           => 'nullable|array',
            'items.*.id'                   => 'required|exists:it_material_request_items,id',
            'items.*.store_dispatch_qty'   => 'nullable|string|max:50',
            'items.*.store_dispatch_status'=> 'nullable|in:available,partial,unavailable',
            'items.*.store_notes'          => 'nullable|string|max:500',
        ]);

        // Update individual items
        if (!empty($request->items)) {
            foreach ($request->items as $itemData) {
                ItMaterialRequestItem::where('id', $itemData['id'])
                    ->where('support_ticket_id', $ticket->id)
                    ->update([
                        'store_dispatch_qty'    => $itemData['store_dispatch_qty'] ?? null,
                        'store_dispatch_status' => $itemData['store_dispatch_status'] ?? null,
                        'store_notes'           => $itemData['store_notes'] ?? null,
                    ]);
            }
        }

        $ticket->update([
            'mr_store_status'        => $request->mr_store_status,
            'mr_store_dispatched_at' => now(),
            'mr_store_managed_by'    => auth()->id(),
            'mr_store_notes'         => $request->mr_store_notes,
        ]);

        ActivityLog::log('dispatched', "Store Manager updated dispatch for Material Request Ticket {$ticket->ticket_no}: {$request->mr_store_status}", 'IT Material Requests', $ticket);

        return back()->with('success', 'Dispatch status updated successfully.');
    }

    /**
     * Head Office Secretary: Confirm receipt of dispatched materials.
     */
    public function secretaryConfirmReceipt(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'mr_secretary_notes' => 'nullable|string|max:1000',
        ]);

        $ticket->update([
            'mr_secretary_received'    => true,
            'mr_secretary_received_at' => now(),
            'mr_secretary_received_by' => auth()->id(),
            'mr_secretary_notes'       => $request->mr_secretary_notes,
        ]);

        ActivityLog::log('received', "Head Office Secretary confirmed receipt for Material Request Ticket {$ticket->ticket_no}.", 'IT Material Requests', $ticket);

        return back()->with('success', 'Receipt confirmed at Head Office.');
    }
}
