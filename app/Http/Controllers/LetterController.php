<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterRecipient;
use App\Models\LetterNotification;
use App\Models\User;
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
                ->where(function($q) use ($user) {
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
            'attachments.uploader',
            'recipients.fromUser',
            'recipients.toUser',
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

        return view('letters.show', compact('letter', 'users', 'roles'));
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
            'redirection_notes'=> 'required|string|max:1000',
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
            'closing_notes' => 'required|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            // Update letter
            $letter->update([
                'status'        => Letter::STATUS_CLOSED,
                'closed_by'     => $user->id,
                'closed_at'     => now(),
                'closing_notes' => $validated['closing_notes'],
            ]);

            // Log in routing table
            LetterRecipient::create([
                'letter_id'    => $letter->id,
                'from_user_id' => $user->id,
                'to_user_id'   => null,
                'to_role_name' => null,
                'action'       => 'closed',
                'notes'        => 'Closed with decision/resolution: ' . $validated['closing_notes'],
                'status'       => Letter::STATUS_CLOSED,
            ]);

            // Notify Creator
            if ($letter->created_by && $letter->created_by !== $user->id) {
                LetterNotification::create([
                    'user_id'   => $letter->created_by,
                    'letter_id' => $letter->id,
                    'message'   => "Letter {$letter->letter_number} decision was recorded and marked as Closed by {$user->name}.",
                ]);
            }

            DB::commit();

            return back()->with('success', 'Letter decision recorded and marked as Reviewed & Closed.');
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
}
