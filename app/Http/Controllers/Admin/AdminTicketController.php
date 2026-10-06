<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\ItIncidentAlertService;
use Illuminate\Http\Request;

class AdminTicketController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            $adminRoles = ['global_admin', 'admin', 'gm', 'General Manager', 'it_admin', 'it_manager'];
            if (!$user || !$user->roles || !$user->roles->whereIn('name', $adminRoles)->count()) {
                abort(403, 'Access denied. Admin or GM access only.');
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

        $tickets = $query->latest()->paginate(20)->withQueryString();
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

        $ticket->load(['user.employee', 'replies.user', 'assignedTo', 'receivedBy']);
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

        $data = [
            'status'                  => $request->status,
            'priority'                => $request->priority,
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

        if ($request->status === 'resolved' && empty($ticket->resolved_at)) {
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
}
