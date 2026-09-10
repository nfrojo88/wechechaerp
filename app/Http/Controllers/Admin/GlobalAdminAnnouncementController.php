<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementSmsLog;
use App\Models\Employee;
use App\Models\Project;
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
            'title'        => 'required|string|max:255',
            'message'      => 'required|string|max:1200',
            'target_type'  => 'required|string|in:all,department,project,selected',
            'departments'  => 'nullable|array',
            'departments.*'=> 'string',
            'project_ids'  => 'nullable|array',
            'project_ids.*'=> 'exists:projects,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*'=> 'exists:employees,id',
            'send_sms'     => 'nullable|boolean',
            'is_published' => 'nullable|boolean',
            'expires_at'   => 'nullable|date',
        ]);

        $targetType = $validated['target_type'];
        $sendSms = $request->boolean('send_sms');
        $isPublished = $request->boolean('is_published');
        $messageText = trim($validated['message']);

        // Resolve recipients
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

        $recipients = $query->get();
        $totalRecipients = $recipients->count();

        if ($totalRecipients === 0) {
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
                $phone = trim($recipient->phone ?? '');

                if (empty($phone)) {
                    AnnouncementSmsLog::create([
                        'announcement_id' => $announcement->id,
                        'employee_id'     => $recipient->id,
                        'recipient_name'  => $recipient->full_name,
                        'phone_number'    => 'N/A',
                        'status'          => 'skipped',
                        'error_message'   => 'Employee record has no phone number on file',
                    ]);
                    $failedCount++;
                    continue;
                }

                try {
                    $res = $smsService->sendMessage($phone, $messageText);
                    $success = !empty($res['success']);

                    AnnouncementSmsLog::create([
                        'announcement_id'  => $announcement->id,
                        'employee_id'      => $recipient->id,
                        'recipient_name'   => $recipient->full_name,
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
                    Log::error("Bulk SMS error for {$recipient->full_name} ({$phone}): " . $ex->getMessage());

                    AnnouncementSmsLog::create([
                        'announcement_id' => $announcement->id,
                        'employee_id'     => $recipient->id,
                        'recipient_name'  => $recipient->full_name,
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
