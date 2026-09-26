<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementSmsLog;
use App\Models\Employee;
use App\Models\Project;
use App\Models\SubconAgreement;
use App\Services\SmsEthiopiaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GlobalAdminAnnouncementController extends Controller
{
    /**
     * Enforce strict Global Admin access on all actions.
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user || !$user->hasRole('global_admin')) {
                abort(403, 'Unauthorized access. This feature is strictly reserved for the Global Admin role.');
            }
            return $next($request);
        });
    }

    /**
     * Auto-heal database tables if they do not exist yet.
     */
    private function ensureTablesExist(): void
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('announcements')) {
                \Illuminate\Support\Facades\Schema::create('announcements', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->string('title');
                    $table->text('message');
                    $table->string('target_type', 30)->default('all');
                    $table->json('target_criteria')->nullable();
                    $table->boolean('send_sms')->default(true);
                    $table->boolean('is_published')->default(true);
                    $table->timestamp('expires_at')->nullable();
                    $table->unsignedInteger('total_recipients')->default(0);
                    $table->unsignedInteger('sms_sent_count')->default(0);
                    $table->unsignedInteger('sms_failed_count')->default(0);
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->timestamps();
                    $table->softDeletes();
                });
            }

            if (!\Illuminate\Support\Facades\Schema::hasTable('announcement_sms_logs')) {
                \Illuminate\Support\Facades\Schema::create('announcement_sms_logs', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('announcement_id');
                    $table->unsignedBigInteger('employee_id')->nullable();
                    $table->string('recipient_name')->nullable();
                    $table->string('phone_number', 40);
                    $table->string('status', 20)->default('pending');
                    $table->text('error_message')->nullable();
                    $table->text('response_payload')->nullable();
                    $table->timestamps();
                });
            }
        } catch (\Throwable $e) {
            Log::error('Announcement table auto-heal error: ' . $e->getMessage());
        }
    }

    /**
     * Display listing of announcements and compose form.
     */
    public function index(Request $request)
    {
        $this->ensureTablesExist();

        $announcements = Announcement::with('author')
            ->latest()
            ->paginate(15);

        // Fetch distinct departments from active employees
        $departments = Employee::where('status', 'active')
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        // Fetch active projects
        $projects = Project::orderBy('name')->get();

        // Fetch active employees with phones for custom selection
        $employees = Employee::where('status', 'active')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code', 'phone', 'department', 'role_title', 'project_id']);

        // Fetch subcon agreements (with phone or supplier phone)
        $subconAgreements = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('subcon_agreements')) {
            $subconAgreements = SubconAgreement::with(['project', 'supplier'])->latest()->get();
        }

        // Count subcon with phone numbers
        $totalSubconWithPhone = $subconAgreements->filter(function ($sub) {
            $phone = trim($sub->subcontractor_contact ?: ($sub->supplier?->phone ?? ''));
            return !empty($phone);
        })->count();

        // Stats
        $totalBroadcasts = Announcement::count();
        $totalSmsSent = Announcement::sum('sms_sent_count');
        $totalSmsFailed = Announcement::sum('sms_failed_count');
        $activeBannersCount = Announcement::activeBanner()->count();
        $totalEmployeesWithPhone = $employees->count();

        return view('admin.announcements.index', compact(
            'announcements',
            'departments',
            'projects',
            'employees',
            'subconAgreements',
            'totalSubconWithPhone',
            'totalBroadcasts',
            'totalSmsSent',
            'totalSmsFailed',
            'activeBannersCount',
            'totalEmployeesWithPhone'
        ));
    }

    /**
     * Dispatch a single test SMS before mass broadcasting.
     */
    public function sendTestSms(Request $request)
    {
        $request->validate([
            'phone'   => 'required|string|max:30',
            'message' => 'required|string|max:1000',
        ]);

        try {
            $smsService = app(SmsEthiopiaService::class);
            $result = $smsService->sendNotification($request->input('phone'), $request->input('message'));

            if (!empty($result['success'])) {
                return response()->json([
                    'success' => true,
                    'message' => "Test SMS dispatched successfully to {$request->input('phone')}!",
                    'data'    => $result['data'] ?? null
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to send test SMS via AfroMessage gateway.',
                'error'   => $result['error'] ?? null
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Test SMS dispatch exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Exception sending SMS: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new announcement and dispatch bulk SMS if requested.
     */
    public function store(Request $request)
    {
        $this->ensureTablesExist();

        $validated = $request->validate([
            'title'              => 'required|string|max:255',
            'message'            => 'required|string|max:1200',
            'target_type'        => 'required|string|in:all,department,project,selected,subcon,client',
            'departments'        => 'nullable|array',
            'departments.*'      => 'string',
            'project_ids'        => 'nullable|array',
            'project_ids.*'      => 'exists:projects,id',
            'employee_ids'       => 'nullable|array',
            'employee_ids.*'     => 'exists:employees,id',
            'subcon_ids'         => 'nullable|array',
            'subcon_ids.*'       => 'exists:subcon_agreements,id',
            'client_names'       => 'nullable|array',
            'client_names.*'     => 'nullable|string|max:255',
            'client_phones'      => 'nullable|array',
            'client_phones.*'    => 'nullable|string|max:50',
            'client_bulk_phones' => 'nullable|string',
            'send_sms'           => 'nullable|boolean',
            'is_published'       => 'nullable|boolean',
            'expires_at'         => 'nullable|date',
        ]);

        $targetType = $validated['target_type'];
        $sendSms = $request->boolean('send_sms');
        $isPublished = $request->boolean('is_published');
        $messageText = trim($validated['message']);

        // Build recipient list
        $recipients = [];

        if ($targetType === 'subcon') {
            $subconIds = $request->input('subcon_ids', []);
            $query = SubconAgreement::with(['project', 'supplier']);
            if (!empty($subconIds)) {
                $query->whereIn('id', $subconIds);
            }
            $agreements = $query->get();

            foreach ($agreements as $ag) {
                $phone = trim($ag->subcontractor_contact ?: ($ag->supplier?->phone ?? ''));
                $name = $ag->subcontractor_display_name;
                if (!empty($ag->agreement_no)) {
                    $name .= ' (' . $ag->agreement_no . ')';
                }
                $recipients[] = [
                    'employee_id' => null,
                    'name'        => $name . ' [Subcon]',
                    'phone'       => $phone,
                    'type'        => 'subcon',
                ];
            }
        } elseif ($targetType === 'client') {
            $clientNames = $request->input('client_names', []);
            $clientPhones = $request->input('client_phones', []);
            $bulkText = $request->input('client_bulk_phones', '');

            // Process dynamic client rows
            if (is_array($clientPhones)) {
                foreach ($clientPhones as $i => $phone) {
                    $phone = trim($phone ?? '');
                    if (!empty($phone)) {
                        $name = trim($clientNames[$i] ?? '') ?: 'Client';
                        $recipients[] = [
                            'employee_id' => null,
                            'name'        => $name . ' [Client]',
                            'phone'       => $phone,
                            'type'        => 'client',
                        ];
                    }
                }
            }

            // Process bulk phone numbers
            if (!empty($bulkText)) {
                $rawPhones = preg_split('/[\r\n,;]+/', $bulkText);
                foreach ($rawPhones as $rawPhone) {
                    $cleaned = trim($rawPhone);
                    if (!empty($cleaned)) {
                        $recipients[] = [
                            'employee_id' => null,
                            'name'        => 'Client (' . $cleaned . ')',
                            'phone'       => $cleaned,
                            'type'        => 'client',
                        ];
                    }
                }
            }
        } else {
            // Employee target audience
            $query = Employee::where('status', 'active');

            if ($targetType === 'department') {
                $depts = $request->input('departments', []);
                if (empty($depts)) {
                    return back()->with('error', 'Please select at least one department for department-targeted broadcast.')->withInput();
                }
                $query->whereIn('department', $depts);
            } elseif ($targetType === 'project') {
                $projIds = $request->input('project_ids', []);
                if (empty($projIds)) {
                    return back()->with('error', 'Please select at least one project for project-targeted broadcast.')->withInput();
                }
                $query->whereIn('project_id', $projIds);
            } elseif ($targetType === 'selected') {
                $empIds = $request->input('employee_ids', []);
                if (empty($empIds)) {
                    return back()->with('error', 'Please select at least one employee from the list.')->withInput();
                }
                $query->whereIn('id', $empIds);
            }

            $employees = $query->get();
            foreach ($employees as $emp) {
                $recipients[] = [
                    'employee_id' => $emp->id,
                    'name'        => $emp->full_name,
                    'phone'       => trim($emp->phone ?? ''),
                    'type'        => 'employee',
                ];
            }
        }

        $totalRecipients = count($recipients);

        if ($totalRecipients === 0) {
            if ($targetType === 'subcon') {
                return back()->with('error', 'No subcontractors selected or found with valid phone numbers in agreements.')->withInput();
            } elseif ($targetType === 'client') {
                return back()->with('error', 'Please add at least one client with a phone number.')->withInput();
            }
            return back()->with('error', 'No active employees matched the selected target audience.')->withInput();
        }

        // Store Announcement record
        $announcement = Announcement::create([
            'title'            => $validated['title'],
            'message'          => $messageText,
            'target_type'      => $targetType,
            'target_criteria'  => [
                'departments'  => $request->input('departments', []),
                'project_ids'  => $request->input('project_ids', []),
                'employee_ids' => $request->input('employee_ids', []),
                'subcon_ids'   => $request->input('subcon_ids', []),
                'client_count' => $targetType === 'client' ? $totalRecipients : 0,
            ],
            'send_sms'         => $sendSms,
            'is_published'     => $isPublished,
            'expires_at'       => $validated['expires_at'] ?? null,
            'total_recipients' => $totalRecipients,
            'sms_sent_count'   => 0,
            'sms_failed_count' => 0,
            'created_by'       => auth()->id(),
        ]);

        $sentCount = 0;
        $failedCount = 0;

        // Dispatch bulk SMS if enabled
        if ($sendSms) {
            $smsService = app(SmsEthiopiaService::class);

            foreach ($recipients as $recipient) {
                $phone = $recipient['phone'];
                $recipientName = $recipient['name'];
                $empId = $recipient['employee_id'];

                if (empty($phone)) {
                    AnnouncementSmsLog::create([
                        'announcement_id' => $announcement->id,
                        'employee_id'     => $empId,
                        'recipient_name'  => $recipientName,
                        'phone_number'    => 'N/A',
                        'status'          => 'skipped',
                        'error_message'   => 'Recipient has no phone number on file',
                    ]);
                    $failedCount++;
                    continue;
                }

                try {
                    $res = $smsService->sendMessage($phone, $messageText);
                    $success = !empty($res['success']);

                    AnnouncementSmsLog::create([
                        'announcement_id'  => $announcement->id,
                        'employee_id'      => $empId,
                        'recipient_name'   => $recipientName,
                        'phone_number'     => $phone,
                        'status'           => $success ? 'sent' : 'failed',
                        'error_message'    => $success ? null : ($res['message'] ?? 'Gateway delivery failure'),
                        'response_payload' => isset($res['data']) ? json_encode($res['data']) : (isset($res['error']) ? json_encode($res['error']) : null),
                    ]);

                    if ($success) {
                        $sentCount++;
                    } else {
                        $failedCount++;
                    }

                } catch (\Throwable $ex) {
                    Log::error("Bulk SMS error for {$recipientName} ({$phone}): " . $ex->getMessage());

                    AnnouncementSmsLog::create([
                        'announcement_id' => $announcement->id,
                        'employee_id'     => $empId,
                        'recipient_name'  => $recipientName,
                        'phone_number'    => $phone,
                        'status'          => 'failed',
                        'error_message'   => $ex->getMessage(),
                    ]);

                    $failedCount++;
                }
            }

            // Update stats
            $announcement->update([
                'sms_sent_count'   => $sentCount,
                'sms_failed_count' => $failedCount,
            ]);
        }

        $notice = "Announcement '{$announcement->title}' successfully processed!";
        if ($sendSms) {
            $notice .= " SMS Broadcast summary: {$sentCount} sent successfully, {$failedCount} failed/skipped out of {$totalRecipients} recipients.";
        }
        if ($isPublished) {
            $notice .= " In-app banner is now live for employees.";
        }

        return redirect()->route('admin.announcements.index')->with('success', $notice);
    }

    /**
     * Show announcement details and SMS delivery logs.
     */
    public function show(Announcement $announcement)
    {
        $announcement->load(['author', 'smsLogs.employee']);
        $logs = $announcement->smsLogs()->paginate(50);

        return view('admin.announcements.show', compact('announcement', 'logs'));
    }

    /**
     * Toggle in-app banner publication status.
     */
    public function togglePublish(Announcement $announcement)
    {
        $announcement->update([
            'is_published' => !$announcement->is_published
        ]);

        $statusText = $announcement->is_published ? 'activated' : 'deactivated';
        return back()->with('success', "In-app banner for '{$announcement->title}' has been {$statusText}.");
    }

    /**
     * Delete an announcement.
     */
    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return redirect()->route('admin.announcements.index')->with('success', 'Announcement removed successfully.');
    }
}
