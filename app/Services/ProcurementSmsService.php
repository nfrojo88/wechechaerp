<?php

namespace App\Services;

use App\Models\ProcurementSmsLog;
use App\Models\User;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Store;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Procurement SMS Service
 * 
 * Sends real SMS notifications at each procurement lifecycle stage transition.
 * Uses AfroMessage gateway (via SmsEthiopiaService) for verified Ethiopian SMS delivery.
 * Falls back gracefully to Africa's Talking or simulated log if offline.
 */
class ProcurementSmsService
{
    private SmsEthiopiaService $ethiopiaSms;
    private string $atApiKey;
    private string $atUsername;
    private string $atShortcode;
    private bool   $atEnabled;

    public function __construct(?SmsEthiopiaService $ethiopiaSms = null)
    {
        $this->ethiopiaSms = $ethiopiaSms ?? app(SmsEthiopiaService::class);
        $this->atApiKey    = config('services.africastalking.api_key', '');
        $this->atUsername  = config('services.africastalking.username', 'sandbox');
        $this->atShortcode = config('services.africastalking.shortcode', '');
        $this->atEnabled   = !empty($this->atApiKey) && config('services.africastalking.enabled', false);
    }

    /**
     * Send an SMS to a phone number and log the attempt.
     *
     * @param int    $purchaseRequestId
     * @param string $phone             E.164 format e.g. +251911123456
     * @param string $recipientRole
     * @param string $message
     */
    public function send(int $purchaseRequestId, string $phone, string $recipientRole, string $message): void
    {
        if (empty($phone)) {
            Log::warning("ProcurementSMS: No phone for role [{$recipientRole}] on PR #{$purchaseRequestId}");
            return;
        }

        $formattedPhone = $this->normalizePhone($phone);

        // Deduplication: prevent duplicate SMS to same phone for same PR within 30s
        $cacheKey = "proc_sms_sent_{$purchaseRequestId}_{$formattedPhone}";
        if (Cache::has($cacheKey)) {
            Log::info("ProcurementSMS deduplicated (already sent within 30s) to {$formattedPhone} on PR #{$purchaseRequestId}");
            return;
        }
        Cache::put($cacheKey, true, now()->addSeconds(30));

        $status = 'failed';
        $error  = null;

        // 1. Primary gateway: AfroMessage (SmsEthiopiaService)
        try {
            $response = $this->ethiopiaSms->sendMessage($formattedPhone, $message);
            if (!empty($response['success'])) {
                $status = 'sent';
                Log::info("ProcurementSMS sent via AfroMessage to {$formattedPhone} [{$recipientRole}] on PR #{$purchaseRequestId}");
            } else {
                $error = is_array($response['error'] ?? null)
                    ? json_encode($response['error'])
                    : ($response['message'] ?? 'AfroMessage dispatch failed');
                Log::warning("ProcurementSMS AfroMessage failed for {$formattedPhone}: {$error}");
            }
        } catch (\Throwable $e) {
            $error = 'AfroMessage exception: ' . $e->getMessage();
            Log::error("ProcurementSMS AfroMessage error: {$error}");
        }

        // 2. Fallback gateway: Africa's Talking (if enabled and AfroMessage didn't succeed)
        if ($status !== 'sent' && $this->atEnabled) {
            try {
                $atResult = $this->dispatchViaAfricasTalking($formattedPhone, $message);
                if ($atResult) {
                    $status = 'sent';
                    $error = null;
                    Log::info("ProcurementSMS sent via Africa's Talking fallback to {$formattedPhone} [{$recipientRole}]");
                }
            } catch (\Throwable $e) {
                $error = ($error ? $error . ' | ' : '') . 'AT error: ' . $e->getMessage();
                Log::error("ProcurementSMS AT fallback error: " . $e->getMessage());
            }
        }

        // 3. Fallback simulation log if neither gateway succeeded
        if ($status !== 'sent' && empty($error)) {
            Log::info("ProcurementSMS [SIMULATED] → {$formattedPhone} | {$recipientRole} | {$message}");
            $status = 'sent';
        }

        // 4. Always log to DB for traceability
        try {
            DB::table('procurement_sms_logs')->insert([
                'purchase_request_id' => $purchaseRequestId,
                'recipient_phone'     => substr($formattedPhone, 0, 30),
                'recipient_role'      => substr($recipientRole, 0, 60),
                'message'             => $message,
                'status'              => $status,
                'error_message'       => $error,
                'sent_at'             => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("ProcurementSMS DB log insert failed: " . $e->getMessage());
        }

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('notification_log')) {
                \App\Models\NotificationLog::create([
                    'request_type'   => 'purchase_request',
                    'request_id'     => $purchaseRequestId,
                    'action'         => 'lifecycle_stage_transition',
                    'sender_user_id' => \Illuminate\Support\Facades\Auth::id(),
                    'role'           => $recipientRole,
                    'phone'          => $formattedPhone,
                    'message'        => $message,
                    'status'         => $status,
                    'error'          => $error,
                    'retries'        => 0,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("ProcurementSMS notification_log insert failed: " . $e->getMessage());
        }
    }

    /**
     * Send notification SMS for a role.
     * If someone is assigned to that role, sends to that person's phone.
     * If no one is assigned to that role (or no phone found), automatically routes
     * the notification SMS to the phone of the person assigned to the global_admin role.
     *
     * @param int         $purchaseRequestId
     * @param string      $roleName
     * @param string      $message
     * @param int|null    $projectId Optional project ID for project-specific coordinators/engineers
     * @param int|null    $storeId   Optional store ID for store-specific managers/keepers
     */
    public function notifyRole(int $purchaseRequestId, string $roleName, string $message, ?int $projectId = null, ?int $storeId = null): void
    {
        try {
            // 1. First check if an employee is assigned to this role
            $targetPhones = $this->getPhoneNumbersForRole($roleName, $projectId, $storeId);

            if (!empty($targetPhones)) {
                // Employee assigned to that role found -> send to their phone
                foreach ($targetPhones as $phone) {
                    $this->send($purchaseRequestId, $phone, $roleName, $message);
                }
                return;
            }

            // 2. If NO employee is assigned to that role (or no phone found), send to Global Admin
            Log::info("ProcurementSMS: No assigned employee with phone for role [{$roleName}] on PR #{$purchaseRequestId}. Escalating SMS to Global Admin.");

            $adminPhones = $this->getGlobalAdminPhoneNumbers();
            $roleTitle = ucfirst(str_replace('_', ' ', $roleName));
            $escalatedMessage = "[Role '{$roleTitle}' Unassigned — Global Admin Action Required] " . $message;

            foreach ($adminPhones as $adminPhone) {
                $this->send($purchaseRequestId, $adminPhone, 'global_admin', $escalatedMessage);
            }
        } catch (\Throwable $e) {
            Log::error("ProcurementSMS notifyRole failed: " . $e->getMessage());
        }
    }

    /**
     * Send SMS directly to a specific User.
     */
    public function sendToUser(int $purchaseRequestId, User $user, string $recipientRole, string $message): void
    {
        $phone = $this->resolveUserPhone($user);
        if ($phone) {
            $this->send($purchaseRequestId, $phone, $recipientRole, $message);
        } else {
            $this->notifyRole($purchaseRequestId, $recipientRole, $message);
        }
    }

    /**
     * Send SMS directly to a specific Employee.
     */
    public function sendToEmployee(int $purchaseRequestId, Employee $employee, string $recipientRole, string $message): void
    {
        $phone = $employee->phone;
        if (!empty($phone)) {
            $this->send($purchaseRequestId, $this->normalizePhone($phone), $recipientRole, $message);
        } else {
            $this->notifyRole($purchaseRequestId, $recipientRole, $message);
        }
    }

    /**
     * Get phone numbers for all users assigned to the specified role.
     * Optionally scoped by project or store for location-specific assignments.
     */
    public function getPhoneNumbersForRole(string $roleName, ?int $projectId = null, ?int $storeId = null): array
    {
        $aliases = [
            'purchase_manager' => ['purchase_manager', 'Purchase Manager', 'Procurement Manager', 'procurement_manager'],
            'purchase'         => ['purchase', 'Purchase', 'procurement', 'Procurement', 'procurement_officer', 'procurement_team'],
            'market_research'  => ['market_research', 'Market Research', 'marketing', 'Marketing', 'marketing_officer'],
            'gm'               => ['gm', 'GM', 'general_manager', 'General Manager'],
            'store_manager'    => ['store_manager', 'Store Manager', 'store', 'Store'],
            'store_keeper'     => ['store_keeper', 'Store Keeper', 'storekeeper', 'Storekeeper', 'store_clerk'],
            'finance_head'     => ['finance_head', 'Finance Head', 'finance_manager', 'Finance Manager', 'cfo', 'CFO'],
            'finance'          => ['finance', 'Finance', 'accountant', 'Accountant', 'finance_staff', 'cashier', 'Cashier'],
            'general_service'  => ['general_service', 'General Service', 'dispatcher', 'fleet_manager'],
            'coordinator'      => ['coordinator', 'Coordinator', 'project_coordinator', 'site_coordinator', 'site_engineer'],
            'planning'         => ['planning', 'Planning', 'planning_manager', 'Planning Manager'],
            'secretary'        => ['secretary', 'Secretary', 'head_office_secretary'],
            'global_admin'     => ['global_admin', 'admin', 'Global Admin', 'Admin'],
        ];

        $rolesToCheck = $aliases[$roleName] ?? [$roleName];
        $phones = [];

        try {
            // A. If Project is specified and checking coordinator / site engineer:
            if ($projectId && in_array($roleName, ['coordinator', 'site_engineer'])) {
                $project = Project::with(['users' => fn($q) => $q->whereHas('roles', fn($r) => $r->whereIn('name', $rolesToCheck))])->find($projectId);
                if ($project && $project->users->isNotEmpty()) {
                    foreach ($project->users as $u) {
                        $p = $this->resolveUserPhone($u);
                        if ($p) $phones[] = $p;
                    }
                }
            }

            // B. If Store is specified and checking store_manager / store_keeper:
            if ($storeId && in_array($roleName, ['store_manager', 'store_keeper'])) {
                $store = Store::with(['manager', 'users'])->find($storeId);
                if ($store) {
                    if ($roleName === 'store_manager' && $store->manager) {
                        $p = $this->resolveUserPhone($store->manager);
                        if ($p) $phones[] = $p;
                    }
                    if ($store->users && $store->users->isNotEmpty()) {
                        foreach ($store->users as $su) {
                            if ($su->roles()->whereIn('name', $rolesToCheck)->exists()) {
                                $p = $this->resolveUserPhone($su);
                                if ($p) $phones[] = $p;
                            }
                        }
                    }
                }
            }

            // C. General role match (all active users assigned this role across the system)
            if (empty($phones)) {
                $users = User::whereHas('roles', fn($q) => $q->whereIn('name', $rolesToCheck))
                    ->with('employee')
                    ->get();

                foreach ($users as $user) {
                    $phone = $this->resolveUserPhone($user);
                    if ($phone) {
                        $phones[] = $phone;
                    }
                }
            }

            // D. Check Employee table directly by role_title if Spatie roles produced no phones
            if (empty($phones)) {
                $employeeQuery = Employee::whereNotNull('phone')
                    ->where('phone', '!=', '')
                    ->where(function ($q) use ($rolesToCheck) {
                        foreach ($rolesToCheck as $r) {
                            $cleanRole = str_replace('_', ' ', $r);
                            $q->orWhere('role_title', 'LIKE', '%' . $cleanRole . '%')
                              ->orWhere('role_title', 'LIKE', '%' . $r . '%');
                        }
                    });

                if ($projectId) {
                    $employeeQuery->where('project_id', $projectId);
                }

                $empPhones = $employeeQuery->pluck('phone')->toArray();
                foreach ($empPhones as $ep) {
                    $norm = $this->normalizePhone($ep);
                    if ($norm) $phones[] = $norm;
                }
            }

            return array_values(array_unique(array_filter($phones)));
        } catch (\Throwable $e) {
            Log::error("ProcurementSMS getPhoneNumbersForRole error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get phone numbers of Global / General Administrators.
     * Checks users with global_admin/admin roles, employees with Admin role title,
     * super-user #1, and configured fallback phones.
     */
    public function getGlobalAdminPhoneNumbers(): array
    {
        $phones = [];

        try {
            // 1. Spatie users with global_admin / admin roles
            $adminRoles = ['global_admin', 'admin', 'Global Admin', 'Admin', 'general_admin', 'General Admin'];
            $users = User::whereHas('roles', fn($q) => $q->whereIn('name', $adminRoles))
                ->with('employee')
                ->get();

            foreach ($users as $user) {
                $p = $this->resolveUserPhone($user);
                if ($p) $phones[] = $p;
            }

            // 2. Employees with admin role title
            $adminEmps = Employee::whereNotNull('phone')
                ->where('phone', '!=', '')
                ->where(function ($q) {
                    $q->where('role_title', 'LIKE', '%Admin%')
                      ->orWhere('role_title', 'LIKE', '%Administrator%')
                      ->orWhere('role_title', 'LIKE', '%General Manager%');
                })
                ->get();

            foreach ($adminEmps as $emp) {
                if (!empty($emp->phone)) {
                    $norm = $this->normalizePhone($emp->phone);
                    if ($norm) $phones[] = $norm;
                }
            }

            // 3. User ID 1 fallback if still empty
            if (empty($phones)) {
                $super = User::with('employee')->find(1);
                if ($super) {
                    $p = $this->resolveUserPhone($super);
                    if ($p) $phones[] = $p;
                }
            }
        } catch (\Throwable $e) {
            Log::error("ProcurementSMS getGlobalAdminPhoneNumbers error: " . $e->getMessage());
        }

        // 4. Config & .env fallback phone numbers
        $envPhones = [
            config('procurement_handoffs.fallback_phone'),
            config('services.sms.admin_phone'),
            env('ADMIN_PHONE'),
            env('AFROMESSAGE_BACKUP_PHONE'),
            env('GLOBAL_ADMIN_PHONE'),
            env('ADMIN_MOBILE'),
        ];

        foreach ($envPhones as $ep) {
            if (!empty($ep)) {
                $phones[] = $this->normalizePhone($ep);
            }
        }

        return array_values(array_unique(array_filter($phones)));
    }

    /**
     * Resolve phone for a given User from User or Employee records.
     */
    public function resolveUserPhone(User $user): ?string
    {
        // 1. Direct user phone attribute (if defined on User model)
        $phone = $user->phone ?? null;

        // 2. Direct employee relation
        if (empty($phone)) {
            $phone = $user->employee?->phone;
        }

        // 3. Query Employee table by user_id
        if (empty($phone)) {
            $phone = Employee::where('user_id', $user->id)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->value('phone');
        }

        // 4. Match Employee table by email
        if (empty($phone) && !empty($user->email)) {
            $phone = Employee::where('email', $user->email)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->value('phone');
        }

        // 5. Match Employee table by name
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

    private function dispatchViaAfricasTalking(string $phone, string $message): bool
    {
        $url  = 'https://api.africastalking.com/version1/messaging';
        $data = [
            'username' => $this->atUsername,
            'to'       => $phone,
            'message'  => $message,
        ];
        if ($this->atShortcode) {
            $data['from'] = $this->atShortcode;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'apiKey: ' . $this->atApiKey,
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT        => 10,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201) {
            throw new \RuntimeException("Africa's Talking HTTP {$httpCode}: {$result}");
        }

        return true;
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        // Ethiopian numbers: starts with 09 or 07 → +2519 or +2517
        if ((str_starts_with($phone, '09') || str_starts_with($phone, '07')) && strlen($phone) === 10) {
            $phone = '+251' . substr($phone, 1);
        } elseif (str_starts_with($phone, '251') && strlen($phone) === 12) {
            $phone = '+' . $phone;
        } elseif (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        return $phone;
    }
}
