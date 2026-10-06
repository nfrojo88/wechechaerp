<?php

namespace App\Services;

use App\Contracts\SmsProviderInterface;
use App\Models\NotificationLog;
use App\Models\ProcurementSmsSetting;
use App\Models\PurchaseRequest;
use App\Models\MaterialRequest;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProcurementHandoffNotificationService
 *
 * Coordinates instant SMS notifications triggered on procurement lifecycle handoffs.
 * Ensures transactions are respected (via DB::afterCommit), resolves role assignees,
 * formats short SMS messages under 160 chars, retries failed sends, and logs all attempts.
 */
class ProcurementHandoffNotificationService
{
    public function __construct(
        protected SmsProviderInterface $smsProvider
    ) {}

    /**
     * Trigger an instant SMS notification for a given handoff action.
     * Guaranteed to fire immediately after DB commit (or right away if no active transaction).
     *
     * @param string $handoffKey e.g. 'mr_submitted', 'planning_forwarded', etc.
     * @param Model  $requestModel MaterialRequest, PurchaseRequest, or Transfer
     * @param array  $context Additional context (e.g. override targets, assignees, notes)
     */
    public function triggerHandoff(string $handoffKey, Model $requestModel, array $context = []): void
    {
        $runner = function () use ($handoffKey, $requestModel, $context) {
            try {
                $this->executeHandoffNotification($handoffKey, $requestModel, $context);
            } catch (\Throwable $e) {
                Log::error("Procurement SMS handoff execution error [{$handoffKey}]: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        };

        // If inside an active DB transaction, wait for commit so rollbacks never send SMS
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($runner);
        } else {
            $runner();
        }
    }

    /**
     * Execute the notification: resolve settings, resolve recipients, format message, send & log.
     */
    public function executeHandoffNotification(string $handoffKey, Model $requestModel, array $context = []): array
    {
        $config = config("procurement_handoffs.handoffs.{$handoffKey}");
        if (!$config) {
            Log::warning("Procurement SMS handoff key [{$handoffKey}] not found in config.");
            return [];
        }

        // Check if handoff is enabled in DB settings (or fallback to true)
        $dbSetting = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('procurement_sms_settings')) {
                $dbSetting = ProcurementSmsSetting::where('handoff_key', $handoffKey)->first();
            }
        } catch (\Throwable $e) {
            // DB table might not exist yet, fallback to config
        }

        if ($dbSetting && !$dbSetting->is_enabled) {
            Log::info("Procurement SMS handoff [{$handoffKey}] is disabled in admin settings.");
            return [];
        }

        // Determine request type and ID
        $requestType = $this->determineRequestType($requestModel);
        $requestId   = $requestModel->getKey();

        // Sender information
        $senderUser = Auth::user() ?? (isset($context['sender_user']) ? $context['sender_user'] : null);
        if (!$senderUser && method_exists($requestModel, 'creator')) {
            $senderUser = $requestModel->creator;
        }

        $senderName = $senderUser ? ($senderUser->name ?? 'Staff') : 'System';
        $senderRole = $context['sender_role'] ?? ($config['sender_role'] ?? 'user');

        // Resolve recipients
        $targetRoles = isset($context['target_roles']) ? (array)$context['target_roles'] : ($config['target_roles'] ?? []);
        $recipients  = $this->resolveRecipients($targetRoles, $requestModel, $context);

        if (empty($recipients)) {
            // Escalate to fallback role / admin if no recipients found
            $fallbackRole = config('procurement_handoffs.fallback_role', 'global_admin');
            Log::warning("Procurement SMS [{$handoffKey}]: No active recipients found for roles [" . implode(',', $targetRoles) . "] on {$requestType} #{$requestId}. Escalating to {$fallbackRole}.");
            $recipients = $this->resolveFallbackRecipients($fallbackRole, $context);
        }

        if (empty($recipients)) {
            Log::warning("Procurement SMS [{$handoffKey}]: Fallback recipient resolution also returned no phones.");
            return [];
        }

        // Render message template
        $template = ($dbSetting && !empty($dbSetting->template))
            ? $dbSetting->template
            : ($config['default_template'] ?? '{priority}{req_no} from {sender_name}: {action}. Open: {link}');

        $message = $this->renderMessage($template, $requestModel, [
            'sender_name' => $senderName,
            'sender_role' => $this->humanizeRole($senderRole),
            'action'      => $context['action_label'] ?? ($config['action_label'] ?? 'review and action'),
            'priority'    => $this->isEmergencyRequest($requestModel, $context) ? 'URGENT: ' : '',
        ]);

        $results = [];

        foreach ($recipients as $recipient) {
            $phone = $this->smsProvider instanceof \App\Services\Sms\AfroMessageProvider 
                ? $this->smsProvider->normalizePhone($recipient['phone'])
                : $this->normalizePhone($recipient['phone']);

            if (empty($phone)) {
                continue;
            }

            // Quick retry up to 3 times on gateway failure
            $status = 'failed';
            $error  = null;
            $retries = 0;

            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $sendResult = $this->smsProvider->send($phone, $message);
                if (!empty($sendResult['success'])) {
                    $status = 'sent';
                    $error  = null;
                    break;
                }

                $retries = $attempt;
                $error   = $sendResult['error'] ?? 'SMS dispatch error';

                if ($attempt < 3) {
                    usleep(150000); // 150ms quick pause before retry
                }
            }

            // Save to notification_log
            try {
                $log = NotificationLog::create([
                    'request_type'           => $requestType,
                    'request_id'             => $requestId,
                    'action'                 => $handoffKey,
                    'sender_user_id'         => $senderUser?->id,
                    'recipient_user_id'      => $recipient['user_id'] ?? null,
                    'recipient_employee_id'  => $recipient['employee_id'] ?? null,
                    'role'                   => $recipient['role'] ?? 'unknown',
                    'phone'                  => $phone,
                    'message'                => $message,
                    'status'                 => $status,
                    'error'                  => $error,
                    'retries'                => $retries,
                ]);

                $results[] = $log;
            } catch (\Throwable $e) {
                Log::error("Failed to insert notification_log: " . $e->getMessage());
            }

            // Also maintain legacy procurement_sms_logs for PRs if table exists
            if ($requestType === 'purchase_request') {
                try {
                    DB::table('procurement_sms_logs')->insert([
                        'purchase_request_id' => $requestId,
                        'recipient_phone'     => substr($phone, 0, 30),
                        'recipient_role'      => substr($recipient['role'] ?? 'user', 0, 60),
                        'message'             => $message,
                        'status'              => $status,
                        'error_message'       => $error,
                        'sent_at'             => now(),
                    ]);
                } catch (\Throwable $e) {}
            }
        }

        return $results;
    }

    /**
     * Resolve target recipients for roles, taking project/store location & specific assignees into account.
     */
    public function resolveRecipients(array $targetRoles, Model $requestModel, array $context = []): array
    {
        $recipients = [];
        $projectId = $requestModel->project_id ?? ($context['project_id'] ?? null);
        $storeId   = $requestModel->destination_store_id ?? ($requestModel->store_id ?? ($context['store_id'] ?? null));

        // 1. Direct assigned employee (e.g. driver)
        if (!empty($context['assigned_employee_id'])) {
            $emp = Employee::find($context['assigned_employee_id']);
            if ($emp && !empty($emp->phone)) {
                $recipients[] = [
                    'user_id'     => $emp->user_id,
                    'employee_id' => $emp->id,
                    'role'        => 'driver',
                    'phone'       => $emp->phone,
                    'name'        => $emp->full_name,
                ];
            }
        }

        // 2. Direct assigned user (e.g. finance staff, purchaser)
        if (!empty($context['assigned_user_id'])) {
            $user = User::with('employee')->find($context['assigned_user_id']);
            if ($user) {
                $phone = $this->resolveUserPhone($user);
                if ($phone) {
                    $recipients[] = [
                        'user_id'     => $user->id,
                        'employee_id' => $user->employee?->id,
                        'role'        => $targetRoles[0] ?? 'assigned_user',
                        'phone'       => $phone,
                        'name'        => $user->name,
                    ];
                }
            }
        }

        // If specific assignees were explicitly resolved, return them
        if (!empty($recipients)) {
            return $this->uniqueRecipients($recipients);
        }

        // 3. Role-based resolution
        foreach ($targetRoles as $roleName) {
            $roleRecipients = $this->getRecipientsForRole($roleName, $projectId, $storeId, $requestModel);
            $recipients = array_merge($recipients, $roleRecipients);
        }

        return $this->uniqueRecipients($recipients);
    }

    /**
     * Get active recipients for a specific role.
     */
    protected function getRecipientsForRole(string $roleName, ?int $projectId, ?int $storeId, Model $requestModel): array
    {
        $aliases = [
            'site_engineer'   => ['site_engineer', 'Site Engineer', 'engineer'],
            'planning'        => ['planning', 'Planning', 'planning_manager', 'Planning Manager'],
            'coordinator'     => ['coordinator', 'Coordinator', 'project_coordinator', 'site_coordinator'],
            'store_manager'   => ['store_manager', 'Store Manager', 'store', 'Store'],
            'store_keeper'    => ['store_keeper', 'Store Keeper', 'storekeeper', 'Storekeeper', 'store_clerk'],
            'purchase_manager'=> ['purchase_manager', 'Purchase Manager', 'Procurement Manager', 'procurement_manager'],
            'purchase'        => ['purchase', 'Purchase', 'procurement', 'Procurement', 'procurement_officer', 'procurement_team'],
            'market_research' => ['market_research', 'Market Research', 'marketing', 'Marketing', 'marketing_officer'],
            'gm'              => ['gm', 'GM', 'general_manager', 'General Manager'],
            'finance_head'    => ['finance_head', 'Finance Head', 'finance_manager', 'Finance Manager', 'cfo', 'CFO'],
            'finance'         => ['finance', 'Finance', 'accountant', 'Accountant', 'finance_staff', 'cashier', 'Cashier'],
            'general_service' => ['general_service', 'General Service', 'dispatcher', 'fleet_manager'],
            'driver'          => ['driver', 'Driver'],
            'global_admin'    => ['global_admin', 'admin', 'Global Admin', 'Admin'],
        ];

        $rolesToCheck = $aliases[$roleName] ?? [$roleName];
        $recipients   = [];

        // Special case: Requester (Site Engineer who created the request)
        if ($roleName === 'site_engineer' && (isset($requestModel->created_by) || isset($requestModel->requested_by))) {
            $creatorId = $requestModel->created_by ?? $requestModel->requested_by;
            $creator = User::with('employee')->find($creatorId);
            if ($creator) {
                $phone = $this->resolveUserPhone($creator);
                if ($phone) {
                    return [[
                        'user_id'     => $creator->id,
                        'employee_id' => $creator->employee?->id,
                        'role'        => 'site_engineer',
                        'phone'       => $phone,
                        'name'        => $creator->name,
                    ]];
                }
            }
        }

        // Special case: Driver (check DriverBooking on PR or Employee drivers)
        if ($roleName === 'driver') {
            if ($requestModel instanceof PurchaseRequest && $requestModel->driverBooking?->driver) {
                $d = $requestModel->driverBooking->driver;
                if (!empty($d->phone)) {
                    return [[
                        'user_id'     => $d->user_id,
                        'employee_id' => $d->id,
                        'role'        => 'driver',
                        'phone'       => $d->phone,
                        'name'        => $d->full_name,
                    ]];
                }
            }
        }

        // A. Project-scoped resolution for site roles (Coordinator / Site Engineer)
        if ($projectId && in_array($roleName, ['coordinator', 'site_engineer'])) {
            try {
                $project = Project::with(['users' => fn($q) => $q->whereHas('roles', fn($r) => $r->whereIn('name', $rolesToCheck))])->find($projectId);
                if ($project && $project->users->isNotEmpty()) {
                    foreach ($project->users as $u) {
                        $p = $this->resolveUserPhone($u);
                        if ($p) {
                            $recipients[] = [
                                'user_id'     => $u->id,
                                'employee_id' => $u->employee?->id,
                                'role'        => $roleName,
                                'phone'       => $p,
                                'name'        => $u->name,
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        // B. Store-scoped resolution for store roles (Store Manager / Store Keeper)
        if ($storeId && in_array($roleName, ['store_manager', 'store_keeper'])) {
            try {
                $store = Store::with(['manager', 'users'])->find($storeId);
                if ($store) {
                    if ($roleName === 'store_manager' && $store->manager) {
                        $p = $this->resolveUserPhone($store->manager);
                        if ($p) {
                            $recipients[] = [
                                'user_id'     => $store->manager->id,
                                'employee_id' => $store->manager->employee?->id,
                                'role'        => 'store_manager',
                                'phone'       => $p,
                                'name'        => $store->manager->name,
                            ];
                        }
                    }
                    if ($store->users && $store->users->isNotEmpty()) {
                        foreach ($store->users as $su) {
                            if ($su->roles()->whereIn('name', $rolesToCheck)->exists()) {
                                $p = $this->resolveUserPhone($su);
                                if ($p) {
                                    $recipients[] = [
                                        'user_id'     => $su->id,
                                        'employee_id' => $su->employee?->id,
                                        'role'        => $roleName,
                                        'phone'       => $p,
                                        'name'        => $su->name,
                                    ];
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        // C. General role match (all active users assigned this role across system)
        if (empty($recipients)) {
            try {
                $users = User::whereHas('roles', fn($q) => $q->whereIn('name', $rolesToCheck))
                    ->with('employee')
                    ->get();

                foreach ($users as $user) {
                    $phone = $this->resolveUserPhone($user);
                    if ($phone) {
                        $recipients[] = [
                            'user_id'     => $user->id,
                            'employee_id' => $user->employee?->id,
                            'role'        => $roleName,
                            'phone'       => $phone,
                            'name'        => $user->name,
                        ];
                    }
                }
            } catch (\Throwable $e) {}
        }

        return $recipients;
    }

    /**
     * Resolve fallback recipients when target role has no users or valid phone numbers.
     */
    protected function resolveFallbackRecipients(string $fallbackRole, array $context = []): array
    {
        $recipients = [];

        try {
            $adminUsers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['global_admin', 'admin']))
                ->with('employee')
                ->get();

            foreach ($adminUsers as $admin) {
                $phone = $this->resolveUserPhone($admin);
                if ($phone) {
                    $recipients[] = [
                        'user_id'     => $admin->id,
                        'employee_id' => $admin->employee?->id,
                        'role'        => 'global_admin',
                        'phone'       => $phone,
                        'name'        => $admin->name,
                    ];
                }
            }
        } catch (\Throwable $e) {}

        // Fallback to configured ADMIN_PHONE if no DB user phone found
        if (empty($recipients)) {
            $envPhone = config('procurement_handoffs.fallback_phone')
                ?: config('services.sms.admin_phone')
                ?: env('ADMIN_PHONE');

            if (!empty($envPhone)) {
                $recipients[] = [
                    'user_id'     => null,
                    'employee_id' => null,
                    'role'        => 'global_admin',
                    'phone'       => $envPhone,
                    'name'        => 'Global Admin',
                ];
            }
        }

        return $recipients;
    }

    /**
     * Render message content with placeholders and enforce < 160 chars limit.
     */
    public function renderMessage(string $template, Model $requestModel, array $data): string
    {
        $reqNo = $requestModel->reference_number 
            ?? $requestModel->pr_no 
            ?? $requestModel->transfer_no 
            ?? ('REQ-' . $requestModel->getKey());

        $projectName = 'General';
        if (isset($requestModel->project) && $requestModel->project) {
            $projectName = $requestModel->project->name;
        } elseif (isset($requestModel->project_id) && $requestModel->project_id) {
            $p = Project::find($requestModel->project_id);
            if ($p) $projectName = $p->name;
        }

        $link = $this->resolveRequestLink($requestModel);

        $replacements = [
            '{req_no}'      => $reqNo,
            '{sender_name}' => $data['sender_name'] ?? 'Staff',
            '{sender_role}' => $data['sender_role'] ?? 'Role',
            '{action}'      => $data['action'] ?? 'action needed',
            '{site}'        => $projectName,
            '{priority}'    => $data['priority'] ?? '',
            '{link}'        => $link,
        ];

        $rendered = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Clean extra whitespace
        $rendered = trim(preg_replace('/\s+/', ' ', $rendered));

        // Enforce maximum 160 characters (GSM 7-bit standard single SMS segment)
        if (mb_strlen($rendered) > 160) {
            // Trim message while keeping the link intact
            $linkPart = ' Open: ' . $link;
            $maxTextLen = 160 - mb_strlen($linkPart) - 3; // ellipsis
            if ($maxTextLen > 20) {
                $baseText = str_replace($linkPart, '', $rendered);
                $rendered = mb_substr($baseText, 0, $maxTextLen) . '...' . $linkPart;
            } else {
                $rendered = mb_substr($rendered, 0, 157) . '...';
            }
        }

        return $rendered;
    }

    /**
     * Resolve URL link to the request.
     */
    protected function resolveRequestLink(Model $requestModel): string
    {
        $id = $requestModel->getKey();
        if ($requestModel instanceof MaterialRequest) {
            return url("/procurement/requests/{$id}");
        } elseif ($requestModel instanceof Transfer) {
            return url("/transfers/{$id}");
        } else {
            return url("/purchase-requests/{$id}");
        }
    }

    /**
     * Check if request is an EMERGENCY request.
     */
    protected function isEmergencyRequest(Model $requestModel, array $context): bool
    {
        if (!empty($context['is_emergency'])) {
            return true;
        }
        if (isset($requestModel->priority) && strtolower($requestModel->priority) === 'emergency') {
            return true;
        }
        if (isset($requestModel->type) && strtolower($requestModel->type) === 'emergency') {
            return true;
        }
        if (isset($requestModel->source) && stripos($requestModel->source, 'emergency') !== false) {
            return true;
        }
        return false;
    }

    /**
     * Resolve User phone number from User or linked Employee.
     */
    public function resolveUserPhone(User $user): ?string
    {
        $phone = $user->phone ?? null;

        if (empty($phone) && $user->employee) {
            $phone = $user->employee->phone;
        }

        if (empty($phone)) {
            $phone = Employee::where('user_id', $user->id)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->value('phone');
        }

        if (empty($phone) && !empty($user->email)) {
            $phone = Employee::where('email', $user->email)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->value('phone');
        }

        if (empty($phone) && !empty($user->name)) {
            $phone = Employee::where(function($q) use ($user) {
                $q->where('full_name', $user->name)
                  ->orWhere('first_name', $user->name);
            })->whereNotNull('phone')
              ->where('phone', '!=', '')
              ->value('phone');
        }

        return !empty($phone) ? $this->normalizePhone($phone) : null;
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        $phone = ltrim($phone, '+');

        // 09XXXXXXXX or 07XXXXXXXX (10 digits) => 2519... / 2517...
        if (str_starts_with($phone, '0') && (str_starts_with($phone, '09') || str_starts_with($phone, '07'))) {
            $phone = '251' . substr($phone, 1);
        }

        // 9XXXXXXXX or 7XXXXXXXX (9 digits without leading 0) => 2519... / 2517...
        if (strlen($phone) === 9 && (str_starts_with($phone, '9') || str_starts_with($phone, '7'))) {
            $phone = '251' . $phone;
        }

        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    protected function determineRequestType(Model $model): string
    {
        if ($model instanceof MaterialRequest) {
            return 'material_request';
        }
        if ($model instanceof Transfer) {
            return 'transfer';
        }
        return 'purchase_request';
    }

    protected function humanizeRole(string $role): string
    {
        return ucwords(str_replace('_', ' ', $role));
    }

    protected function uniqueRecipients(array $recipients): array
    {
        $seen = [];
        $unique = [];
        foreach ($recipients as $r) {
            $key = $r['phone'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $r;
            }
        }
        return $unique;
    }
}
