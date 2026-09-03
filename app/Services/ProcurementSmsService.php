<?php

namespace App\Services;

use App\Models\ProcurementSmsLog;
use Illuminate\Support\Facades\Log;

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
            \DB::table('procurement_sms_logs')->insert([
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
     * Send to all users of a given role who have a phone number.
     * If no users are assigned to that role, falls back to global_admin.
     */
    public function notifyRole(int $purchaseRequestId, string $roleName, string $message): void
    {
        try {
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

            $hasAssignedUsers = \App\Models\User::whereHas('roles', fn($q) => $q->whereIn('name', $rolesToCheck))->exists();

            // If no user is assigned to this role at all, and it's not global_admin, notify global_admin
            if (!$hasAssignedUsers && !in_array($roleName, ['global_admin', 'admin'])) {
                Log::info("ProcurementSMS: No users assigned to role [{$roleName}] for PR #{$purchaseRequestId}. Falling back to global_admin.");
                $this->notifyRole($purchaseRequestId, 'global_admin', "[Role '{$roleName}' unassigned] " . $message);
                return;
            }

            $users = \App\Models\User::whereHas('roles', fn($q) => $q->whereIn('name', $rolesToCheck))
                ->whereHas('employee', fn($q) => $q->whereNotNull('phone')->where('phone', '!=', ''))
                ->with('employee:id,user_id,phone')
                ->get();

            // If users exist for the role but none have a phone number, attempt global_admin fallback
            if ($users->isEmpty() && !in_array($roleName, ['global_admin', 'admin'])) {
                Log::info("ProcurementSMS: Users in role [{$roleName}] have no phone numbers for PR #{$purchaseRequestId}. Routing notification to global_admin.");
                $this->notifyRole($purchaseRequestId, 'global_admin', "[No phone for role '{$roleName}'] " . $message);
                return;
            }

            foreach ($users as $user) {
                $phone = $user->employee?->phone ?? null;
                if ($phone) {
                    $phone = $this->normalizePhone($phone);
                    $this->send($purchaseRequestId, $phone, $roleName, $message);
                }
            }
        } catch (\Throwable $e) {
            Log::error("ProcurementSMS notifyRole failed: " . $e->getMessage());
        }
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
