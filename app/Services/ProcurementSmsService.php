<?php

namespace App\Services;

use App\Models\ProcurementSmsLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Procurement SMS Service
 * 
 * Sends SMS notifications at each procurement lifecycle stage transition.
 * Uses Africa's Talking gateway (configurable via .env).
 * Falls back gracefully if credentials are not set.
 */
class ProcurementSmsService
{
    private string $apiKey;
    private string $username;
    private string $shortcode;
    private bool   $enabled;

    public function __construct()
    {
        $this->apiKey    = config('services.africastalking.api_key', '');
        $this->username  = config('services.africastalking.username', 'sandbox');
        $this->shortcode = config('services.africastalking.shortcode', '');
        $this->enabled   = !empty($this->apiKey) && config('services.africastalking.enabled', false);
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

        $status = 'failed';
        $error  = null;

        if ($this->enabled) {
            try {
                $response = $this->dispatchViaAfricasTalking($phone, $message);
                $status   = $response ? 'sent' : 'failed';
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                Log::error("ProcurementSMS error: {$error}");
            }
        } else {
            // When not configured, log the message for debugging
            Log::info("ProcurementSMS [SIMULATED] → {$phone} | {$recipientRole} | {$message}");
            $status = 'sent'; // simulate success in dev
        }

        // Always log to DB for traceability
        try {
            DB::table('procurement_sms_logs')->insert([
                'purchase_request_id' => $purchaseRequestId,
                'recipient_phone'     => $phone,
                'recipient_role'      => $recipientRole,
                'message'             => $message,
                'status'              => $status,
                'error_message'       => $error,
                'sent_at'             => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("ProcurementSMS DB log failed: " . $e->getMessage());
        }
    }

    /**
     * Send notification SMS for a role.
     * If someone is assigned to that role, sends to that person's phone.
     * If no one is assigned to that role (or no phone found), automatically routes
     * the notification SMS to the phone of the person assigned to the global_admin role.
     */
    public function notifyRole(int $purchaseRequestId, string $roleName, string $message): void
    {
        try {
            // 1. Find phones for users assigned to this role
            $targetPhones = $this->getPhoneNumbersForRole($roleName);

            if (!empty($targetPhones)) {
                // Someone is assigned to that role -> send to their phone
                foreach ($targetPhones as $phone) {
                    $this->send($purchaseRequestId, $phone, $roleName, $message);
                }
                return;
            }

            // 2. If NO ONE is assigned to that role (or no phone), route to global_admin
            if (!in_array($roleName, ['global_admin', 'admin'])) {
                Log::info("ProcurementSMS: No assigned user with phone for role [{$roleName}] on PR #{$purchaseRequestId}. Escalating SMS to Global Admin.");

                $adminPhones = $this->getPhoneNumbersForRole('global_admin');

                // Check env / config fallback for admin phone if none found in DB
                if (empty($adminPhones)) {
                    $envPhone = config('services.sms.admin_phone') ?: env('ADMIN_PHONE');
                    if ($envPhone) {
                        $adminPhones[] = $this->normalizePhone($envPhone);
                    }
                }

                $escalatedMessage = "[Role '{$roleName}' unassigned] " . $message;
                foreach ($adminPhones as $adminPhone) {
                    $this->send($purchaseRequestId, $adminPhone, 'global_admin', $escalatedMessage);
                }
            }
        } catch (\Throwable $e) {
            Log::error("ProcurementSMS notifyRole failed: " . $e->getMessage());
        }
    }

    /**
     * Get phone numbers for all users assigned to the specified role.
     */
    public function getPhoneNumbersForRole(string $roleName): array
    {
        $aliases = [
            'purchase_manager' => ['purchase_manager', 'Purchase Manager', 'Procurement Manager', 'procurement_manager'],
            'purchase'         => ['purchase', 'Purchase', 'procurement', 'Procurement', 'procurement_officer', 'procurement_team'],
            'market_research'  => ['market_research', 'Market Research', 'marketing', 'Marketing', 'marketing_officer'],
            'gm'               => ['gm', 'GM', 'general_manager', 'General Manager'],
            'store_manager'    => ['store_manager', 'Store Manager', 'store', 'Store'],
            'finance_head'     => ['finance_head', 'Finance Head', 'finance_manager', 'Finance Manager', 'cfo', 'CFO'],
            'finance'          => ['finance', 'Finance', 'accountant', 'Accountant', 'finance_staff'],
            'general_service'  => ['general_service', 'General Service', 'dispatcher', 'fleet_manager'],
            'coordinator'      => ['coordinator', 'Coordinator', 'project_coordinator', 'site_coordinator'],
            'planning'         => ['planning', 'Planning', 'planning_manager', 'Planning Manager'],
            'global_admin'     => ['global_admin', 'admin', 'Global Admin', 'Admin'],
        ];

        $rolesToCheck = $aliases[$roleName] ?? [$roleName];

        try {
            $users = \App\Models\User::whereHas('roles', fn($q) => $q->whereIn('name', $rolesToCheck))
                ->with('employee')
                ->get();

            $phones = [];
            foreach ($users as $user) {
                $phone = $this->resolveUserPhone($user);
                if ($phone) {
                    $phones[] = $phone;
                }
            }

            return array_values(array_unique(array_filter($phones)));
        } catch (\Throwable $e) {
            Log::error("ProcurementSMS getPhoneNumbersForRole error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Resolve phone for a given User from Employee records.
     */
    public function resolveUserPhone(\App\Models\User $user): ?string
    {
        // 1. Direct employee relation
        $phone = $user->employee?->phone;

        // 2. Query Employee table by user_id
        if (empty($phone)) {
            $phone = \App\Models\Employee::where('user_id', $user->id)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->value('phone');
        }

        // 3. Match Employee table by email
        if (empty($phone) && !empty($user->email)) {
            $phone = \App\Models\Employee::where('email', $user->email)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->value('phone');
        }

        return !empty($phone) ? $this->normalizePhone($phone) : null;
    }

    private function dispatchViaAfricasTalking(string $phone, string $message): bool
    {
        $url  = 'https://api.africastalking.com/version1/messaging';
        $data = [
            'username' => $this->username,
            'to'       => $phone,
            'message'  => $message,
        ];
        if ($this->shortcode) {
            $data['from'] = $this->shortcode;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'apiKey: ' . $this->apiKey,
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

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        // Ethiopian numbers: starts with 09 → +2519
        if (str_starts_with($phone, '09') && strlen($phone) === 10) {
            $phone = '+251' . substr($phone, 1);
        }
        // Already international
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        return $phone;
    }
}
