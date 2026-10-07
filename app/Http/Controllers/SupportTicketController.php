<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\ActivityLog;
use App\Models\ItMaterialRequestItem;
use App\Services\ItIncidentAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        SupportTicket::ensureSchema();

        $query = SupportTicket::where('user_id', auth()->id())
            ->with(['assignedTo'])
            ->withCount('replies');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('submission_type')) {
            $query->where('submission_type', $request->submission_type);
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('subject', 'like', $term)
                  ->orWhere('ticket_no', 'like', $term)
                  ->orWhere('affected_system', 'like', $term)
                  ->orWhere('suggestion_title', 'like', $term);
            });
        }

        $tickets = $query->latest()->paginate(15)->withQueryString();

        return view('tickets.index', compact('tickets'));
    }

    public function create()
    {
        SupportTicket::ensureSchema();

        $user = auth()->user();
        $employee = $user->employee ?? null;

        // Auto-fill submitter fields from authenticated employee profile
        $submitterName = $employee?->full_name ?? $user->name;
        $employeeCode  = $employee?->employee_code ?? '';
        $department    = $employee?->department ?? '';
        $contactPhone  = $employee?->phone ?? $user->phone ?? '';
        $contactEmail  = $employee?->email ?? $user->email ?? '';
        $submittedDate = now()->format('Y-m-d');
        $products = \App\Models\Product::orderBy('name')->get(['id', 'name', 'unit', 'category']);

        return view('tickets.create', compact(
            'submitterName', 'employeeCode', 'department',
            'contactPhone', 'contactEmail', 'submittedDate',
            'products'
        ));
    }

    public function store(Request $request, ItIncidentAlertService $alertService)
    {
        SupportTicket::ensureSchema();

        $submissionType = $request->input('submission_type', 'problem');

        // Dynamic validation rules based on submission type
        $rules = [
            'submission_type'         => 'required|in:problem,suggestion,both',
            'submitter_name'          => 'required|string|max:255',
            'employee_code'           => 'nullable|string|max:100',
            'department'              => 'nullable|string|max:150',
            'contact_email'           => 'nullable|string|max:150',
            'contact_phone'           => 'nullable|string|max:100',
            'submitted_date'          => 'nullable|date',
            'attachment'              => 'nullable|file|max:10240', // 10MB
            'attachments_notes'       => 'nullable|string|max:1000',
        ];

        if (in_array($submissionType, ['problem', 'both'])) {
            $rules['subject']             = 'required|string|max:255';
            $rules['category']            = 'required|string|max:100';
            $rules['description']         = 'required|string|max:5000';
            $rules['impact_urgency']      = 'required|in:critical,high,medium,low';
            $rules['affected_system']     = 'nullable|string|max:255';
            $rules['location']            = 'nullable|string|max:255';
            $rules['incident_started_at'] = 'nullable|string|max:255';
            $rules['frequency']           = 'nullable|in:Once,Sometimes,Always';
            $rules['steps_to_reproduce']  = 'nullable|string|max:3000';
            $rules['error_message']       = 'nullable|string|max:3000';
            $rules['already_tried']       = 'nullable|string|max:2000';
        }

        if (in_array($submissionType, ['suggestion', 'both'])) {
            if ($submissionType === 'suggestion') {
                $rules['suggestion_title'] = 'required|string|max:255';
            } else {
                $rules['suggestion_title'] = 'nullable|string|max:255';
            }
            $rules['suggestion_area']       = 'nullable|string|max:100';
            $rules['current_situation']     = 'nullable|string|max:3000';
            $rules['suggested_change']      = 'nullable|string|max:3000';
            $rules['expected_benefits']      = 'nullable|array';
            $rules['expected_benefits_other']= 'nullable|string|max:255';
        }

        $validated = $request->validate($rules);

        // Map priority safely for legacy ENUM('low', 'medium', 'high', 'urgent')
        $urgency = strtolower($request->impact_urgency ?? 'medium');
        $priority = match($urgency) {
            'critical' => 'urgent',
            'high'     => 'high',
            'low'      => 'low',
            default    => 'medium',
        };

        // Handle attachment file upload
        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentName = $file->getClientOriginalName();
            $attachmentPath = $file->store('it_reports', 'public');
        }

        $subject = $request->subject ?: ($request->suggestion_title ?: 'IT Submission');

        $ticket = SupportTicket::create([
            'user_id'                 => auth()->id(),
            'submitter_name'          => $request->submitter_name,
            'employee_code'           => $request->employee_code,
            'department'              => $request->department,
            'contact_email'           => $request->contact_email,
            'contact_phone'           => $request->contact_phone,
            'submitted_date'          => $request->submitted_date ?? now()->toDateString(),
            'submission_type'         => $submissionType,

            // Problem
            'category'                => $request->category ?? 'Other',
            'subject'                 => $subject,
            'description'             => $request->description ?? ($request->suggested_change ?? 'No description provided'),
            'priority'                => $priority,
            'impact_urgency'          => $request->impact_urgency ?? $urgency,
            'affected_system'         => $request->affected_system,
            'location'                => $request->location,
            'incident_started_at'     => $request->incident_started_at,
            'frequency'               => $request->frequency,
            'steps_to_reproduce'      => $request->steps_to_reproduce,
            'error_message'           => $request->error_message,
            'already_tried'           => $request->already_tried,

            // Suggestion
            'suggestion_title'        => $request->suggestion_title,
            'suggestion_area'         => $request->suggestion_area,
            'current_situation'       => $request->current_situation,
            'suggested_change'        => $request->suggested_change,
            'expected_benefits'       => $request->expected_benefits,
            'expected_benefits_other' => $request->expected_benefits_other,

            // Attachments
            'attachment_path'         => $attachmentPath,
            'attachment_name'         => $attachmentName,
            'attachments_notes'       => $request->attachments_notes,

            // Material Request header
            'has_material_request'    => $request->boolean('has_material_request'),
            'mr_justification'        => $request->mr_justification,
            'mr_urgency'              => $request->mr_urgency,
            'mr_project_location'     => $request->mr_project_location,
            'mr_gm_status'            => $request->boolean('has_material_request') ? 'pending_gm' : null,

            'status'                  => 'open',
            'date_received'           => now()->toDateString(),
            'user_confirmed_resolved' => 'pending',
        ]);

        // Save material request line items
        if ($request->boolean('has_material_request') && $request->filled('mr_items')) {
            foreach ($request->mr_items as $item) {
                $itemName = trim($item['item_name'] ?? '');
                if ($itemName === '__CUSTOM__' && !empty($item['custom_item_name'])) {
                    $itemName = trim($item['custom_item_name']);
                }
                if (!empty($itemName)) {
                    ItMaterialRequestItem::create([
                        'support_ticket_id' => $ticket->id,
                        'item_name'         => $itemName,
                        'quantity'          => $item['quantity'] ?? 1,
                        'unit'              => $item['unit'] ?? null,
                        'purpose'           => $item['purpose'] ?? null,
                        'urgency_level'     => $item['urgency_level'] ?? 'medium',
                    ]);
                }
            }
        }

        ActivityLog::log(
            'created',
            "IT {$submissionType} report {$ticket->ticket_no} submitted by {$ticket->submitter_name}: {$ticket->subject}",
            'IT Department Reports',
            $ticket
        );

        // Immediate SMS escalation to GM & Global Admin for problem reports or critical/high urgency
        $smsResult = ['success' => false, 'recipients_sent' => 0];
        if (in_array($submissionType, ['problem', 'both']) || in_array($priority, ['critical', 'high', 'urgent'])) {
            $smsResult = $alertService->sendProblemReportAlert($ticket);
        }

        // If material request included, send additional SMS to GM
        if ($request->boolean('has_material_request')) {
            try {
                $alertService->sendMaterialRequestAlert($ticket);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('MR GM SMS failed: ' . $e->getMessage());
            }
        }

        $msg = "IT report #{$ticket->ticket_no} submitted successfully.";
        if ($request->boolean('has_material_request')) {
            $msg .= " Your Material Request is now pending GM approval.";
        }
        if (!empty($smsResult['recipients_sent'])) {
            $msg .= " An immediate SMS alert has been sent to the General Manager (GM) and Global Admin ({$smsResult['recipients_sent']} recipient(s)).";
        }

        return redirect()->route('tickets.show', $ticket)->with('success', $msg);
    }

    public function show(SupportTicket $ticket)
    {
        SupportTicket::ensureSchema();

        $user = auth()->user();
        $isAdmin = $user->roles && $user->roles->whereIn('name', ['global_admin', 'admin', 'gm'])->count();

        // Allow ticket owner or administrators to view
        if ($ticket->user_id !== $user->id && !$isAdmin) {
            abort(403, 'Unauthorized access to this ticket.');
        }

        $ticket->load([
            'replies.user',
            'assignedTo',
            'receivedBy',
            'user.employee',
            'materialRequestItems',
            'mrGmDecidedBy',
            'mrStoreManagedBy',
            'mrSecretaryReceivedBy',
        ]);

        return view('tickets.show', compact('ticket'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $user = auth()->user();
        $isAdmin = $user->roles && $user->roles->whereIn('name', ['global_admin', 'admin', 'gm'])->count();

        if ($ticket->user_id !== $user->id && !$isAdmin) {
            abort(403);
        }

        $request->validate([
            'message' => 'required|string|max:3000',
        ]);

        TicketReply::create([
            'ticket_id'      => $ticket->id,
            'user_id'        => $user->id,
            'message'        => $request->message,
            'is_admin_reply' => false,
        ]);

        // Reopen if it was resolved or closed and user replied
        if (in_array($ticket->status, ['resolved', 'closed', 'rejected'])) {
            $ticket->update(['status' => 'open']);
        }

        return back()->with('success', 'Your reply has been posted.');
    }

    public function print(SupportTicket $ticket)
    {
        SupportTicket::ensureSchema();

        $user = auth()->user();
        $isAdmin = $user->roles && $user->roles->whereIn('name', ['global_admin', 'admin', 'gm'])->count();

        if ($ticket->user_id !== $user->id && !$isAdmin) {
            abort(403);
        }

        $ticket->load([
            'assignedTo',
            'receivedBy',
            'user.employee',
            'materialRequestItems',
            'mrGmDecidedBy',
            'mrStoreManagedBy',
            'mrSecretaryReceivedBy',
        ]);

        return view('tickets.print', compact('ticket'));
    }
}
