<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * IT Incident & Problem Report Escalation Service
 * 
 * Sends immediate SMS alerts to GM (General Manager) and Global Admin
 * whenever an IT problem, system error, or incident report is submitted.
 */
class ItIncidentAlertService
{
    private SmsEthiopiaService $ethiopiaSms;
    private ProcurementSmsService $procurementSms;

    public function __construct(
        ?SmsEthiopiaService $ethiopiaSms = null,
        ?ProcurementSmsService $procurementSms = null
    ) {
        $this->ethiopiaSms    = $ethiopiaSms ?? app(SmsEthiopiaService::class);
        $this->procurementSms = $procurementSms ?? app(ProcurementSmsService::class);
    }

    /**
     * Send immediate SMS escalation alert for an IT Problem / Incident Report to GM & Global Admin.
     */
    public function sendProblemReportAlert(SupportTicket $ticket, bool $force = false): array
    {
        // Check deduplication unless forced
        $dedupKey = "it_ticket_alert_sent_{$ticket->id}";
        if (!$force && Cache::has($dedupKey)) {
            Log::info("ItIncidentAlertService: alert already sent for Ticket #{$ticket->ticket_no} within cooldown.");
            return ['success' => true, 'already_sent' => true, 'recipients' => 0];
        }

        $recipientPhones = $this->resolveEscalationPhones();
        if (empty($recipientPhones)) {
            Log::warning("ItIncidentAlertService: No GM or Global Admin phone numbers found for Ticket #{$ticket->ticket_no}");
            return ['success' => false, 'error' => 'No recipient phone numbers configured for GM or Global Admin'];
        }

        $urgency = strtoupper($ticket->priority ?? $ticket->impact_urgency ?? 'MEDIUM');
        $subject = mb_substr($ticket->subject ?? 'IT Issue', 0, 40);
        $submitter = $ticket->submitter_name ?? ($ticket->user?->name ?? 'Staff');
        $dept = $ticket->department ?? 'General';
        $system = $ticket->affected_system ? " [{$ticket->affected_system}]" : '';

        // Executive SMS message fitting standard SMS bounds
        $message = "[IT ALERT] #{$ticket->ticket_no} ({$urgency}){$system}: \"{$subject}\" by {$submitter} ({$dept}). Urgent attention requested. Log in to ERP to view.";

        $results = [];
        $sentCount = 0;
        $logEntries = [];

        foreach ($recipientPhones as $phone) {
            $normalizedPhone = $this->procurementSms->normalizePhone($phone);
            if (empty($normalizedPhone)) {
                continue;
            }

            try {
                $response = $this->ethiopiaSms->sendMessage($normalizedPhone, $message);
                $isSent = !empty($response['success']);
                if ($isSent) {
                    $sentCount++;
                    $logEntries[] = "[" . now()->format('Y-m-d H:i:s') . "] Sent SMS to {$normalizedPhone}";
                    Log::info("ItIncidentAlertService: SMS sent to {$normalizedPhone} for Ticket #{$ticket->ticket_no}");
                } else {
                    $errMsg = is_array($response['error'] ?? null) ? json_encode($response['error']) : ($response['message'] ?? 'Gateway dispatch failed');
                    $logEntries[] = "[" . now()->format('Y-m-d H:i:s') . "] Failed {$normalizedPhone}: {$errMsg}";
                    Log::warning("ItIncidentAlertService: SMS failed to {$normalizedPhone} for Ticket #{$ticket->ticket_no}: {$errMsg}");
                }
                $results[$normalizedPhone] = $isSent;
            } catch (\Throwable $e) {
                $logEntries[] = "[" . now()->format('Y-m-d H:i:s') . "] Exception {$normalizedPhone}: " . $e->getMessage();
                Log::error("ItIncidentAlertService exception for {$normalizedPhone}: " . $e->getMessage());
                $results[$normalizedPhone] = false;
            }
        }

        Cache::put($dedupKey, true, now()->addMinutes(10));

        // Update ticket record
        try {
            $existingLog = $ticket->sms_alert_log ? ($ticket->sms_alert_log . "\n") : '';
            $ticket->update([
                'sms_alert_sent' => $sentCount > 0 || $ticket->sms_alert_sent,
                'notified_gm'    => true,
                'notified_admin' => true,
                'sms_alert_log'  => $existingLog . implode("\n", $logEntries),
            ]);
        } catch (\Throwable $e) {
            Log::error("ItIncidentAlertService error updating ticket record: " . $e->getMessage());
        }

        return [
            'success'        => $sentCount > 0,
            'recipients_sent'=> $sentCount,
            'total_targets'  => count($recipientPhones),
            'log'            => implode("\n", $logEntries),
        ];
    }

    /**
     * Notify GM that an IT Material Request requires their approval.
     */
    public function sendMaterialRequestAlert(SupportTicket $ticket): array
    {
        $dedupKey = "it_mr_gm_alert_{$ticket->id}";
        if (Cache::has($dedupKey)) {
            return ['success' => true, 'already_sent' => true];
        }

        $gmPhones = $this->procurementSms->getPhoneNumbersForRole('gm');
        $adminPhones = $this->procurementSms->getGlobalAdminPhoneNumbers();
        $phones = array_values(array_unique(array_filter(array_merge($gmPhones, $adminPhones))));

        if (empty($phones)) {
            Log::warning("ItIncidentAlertService: No GM phone found for MR alert on Ticket #{$ticket->ticket_no}");
            return ['success' => false, 'error' => 'No GM phone configured'];
        }

        $submitter   = $ticket->submitter_name ?? 'IT Staff';
        $dept        = $ticket->department ?? '';
        $itemCount   = $ticket->materialRequestItems ? $ticket->materialRequestItems->count() : '?';
        $urgency     = strtoupper($ticket->mr_urgency ?? 'MEDIUM');
        $message = "[IT MATERIAL REQUEST] Ticket #{$ticket->ticket_no}: {$itemCount} item(s) requested by {$submitter} ({$dept}). Urgency: {$urgency}. Please review and approve in the ERP system.";

        $sentCount = 0;
        foreach ($phones as $phone) {
            $normalized = $this->procurementSms->normalizePhone($phone);
            if (!$normalized) continue;
            try {
                $resp = $this->ethiopiaSms->sendMessage($normalized, $message);
                if (!empty($resp['success'])) $sentCount++;
            } catch (\Throwable $e) {
                Log::error("MR GM SMS error for {$normalized}: " . $e->getMessage());
            }
        }

        Cache::put($dedupKey, true, now()->addMinutes(15));

        try {
            $ticket->update(['mr_sms_gm_sent' => true]);
        } catch (\Throwable $e) {}

        return ['success' => $sentCount > 0, 'recipients_sent' => $sentCount];
    }

    /**
     * Notify Store Manager that GM approved an IT Material Request.
     */
    public function sendStoreManagerAlert(SupportTicket $ticket): array
    {
        $storePhones = $this->procurementSms->getPhoneNumbersForRole('store_manager');
        $extra = array_filter([env('STORE_MANAGER_PHONE')]);
        $phones = array_values(array_unique(array_filter(array_merge($storePhones, $extra))));

        // fallback to GM+admin phones if no store manager configured
        if (empty($phones)) {
            $phones = $this->resolveEscalationPhones();
        }

        $submitter  = $ticket->submitter_name ?? 'IT Staff';
        $dept       = $ticket->department ?? '';
        $itemCount  = $ticket->materialRequestItems ? $ticket->materialRequestItems->count() : '?';
        $message = "[STORE ACTION REQUIRED] IT Material Request #{$ticket->ticket_no} APPROVED by GM. {$itemCount} item(s) from {$submitter} ({$dept}) need dispatch. Check ERP Procurement \u2192 IT Requests.";

        $sentCount = 0;
        foreach ($phones as $phone) {
            $normalized = $this->procurementSms->normalizePhone($phone);
            if (!$normalized) continue;
            try {
                $resp = $this->ethiopiaSms->sendMessage($normalized, $message);
                if (!empty($resp['success'])) $sentCount++;
            } catch (\Throwable $e) {
                Log::error("MR Store SMS error for {$normalized}: " . $e->getMessage());
            }
        }

        try {
            $ticket->update(['mr_sms_store_sent' => true]);
        } catch (\Throwable $e) {}

        return ['success' => $sentCount > 0, 'recipients_sent' => $sentCount];
    }

    /**
     * Send direct system error alert to GM & Global Admin when an unhandled 500 error occurs.
     */
    public function sendDirectSystemErrorAlert(string $errorTitle, string $errorMessage, ?string $location = null): void
    {
        // 15-minute cooldown to avoid spamming on recurring server error
        $cacheKey = "it_system_error_alert_" . md5($errorTitle . $location);
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, true, now()->addMinutes(15));

        $recipientPhones = $this->resolveEscalationPhones();
        if (empty($recipientPhones)) {
            return;
        }

        $loc = $location ? " at {$location}" : '';
        $msgSnippet = mb_substr($errorMessage, 0, 45);
        $message = "[ERP CRITICAL ERROR]{$loc}: {$errorTitle} - {$msgSnippet}. Immediate admin investigation required.";

        foreach ($recipientPhones as $phone) {
            $normalizedPhone = $this->procurementSms->normalizePhone($phone);
            if ($normalizedPhone) {
                try {
                    $this->ethiopiaSms->sendMessage($normalizedPhone, $message);
                } catch (\Throwable $e) {
                    Log::error("ItIncidentAlertService direct error SMS failed to {$normalizedPhone}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Resolve all distinct phone numbers of Global Admins, GM, and IT Managers.
     */
    public function resolveEscalationPhones(): array
    {
        $phones = [];

        try {
            // 1. Global Admin phone numbers
            $adminPhones = $this->procurementSms->getGlobalAdminPhoneNumbers();
            foreach ($adminPhones as $p) {
                if ($p) $phones[] = $p;
            }

            // 2. GM phone numbers
            $gmPhones = $this->procurementSms->getPhoneNumbersForRole('gm');
            foreach ($gmPhones as $p) {
                if ($p) $phones[] = $p;
            }

            // 3. Additional roles: it_admin, it_manager, general_manager
            $itPhones = $this->procurementSms->getPhoneNumbersForRole('it_admin');
            foreach ($itPhones as $p) {
                if ($p) $phones[] = $p;
            }

            $itMgrPhones = $this->procurementSms->getPhoneNumbersForRole('it_manager');
            foreach ($itMgrPhones as $p) {
                if ($p) $phones[] = $p;
            }

            // 4. Fallback .env and config
            $extra = [
                env('GM_PHONE'),
                env('GLOBAL_ADMIN_PHONE'),
                env('ADMIN_PHONE'),
                env('AFROMESSAGE_BACKUP_PHONE'),
                config('services.sms.admin_phone'),
            ];
            foreach ($extra as $ep) {
                if (!empty($ep)) {
                    $norm = $this->procurementSms->normalizePhone($ep);
                    if ($norm) $phones[] = $norm;
                }
            }
        } catch (\Throwable $e) {
            Log::error("ItIncidentAlertService resolveEscalationPhones error: " . $e->getMessage());
        }

        return array_values(array_unique(array_filter($phones)));
    }
}
