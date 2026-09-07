<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AuditInquiryEscalationService;

class EscalateAuditInquiriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:escalate-overdue-inquiries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check pending auditor receipt inquiries older than 3 days (notify Auditor & Finance Head) and 5 days (escalate to GM & General Admin) via SMS';

    /**
     * Execute the console command.
     */
    public function handle(AuditInquiryEscalationService $escalationService): int
    {
        $this->info('Starting audit receipt inquiry escalation check...');

        $res = $escalationService->checkAndEscalate();

        $this->info("Checked {$res['checked']} pending inquiry(ies).");
        $this->info("Escalated (3-day): {$res['escalated_3day']}");
        $this->info("Escalated (5-day): {$res['escalated_5day']}");
        $this->info("SMS Sent: {$res['sms_sent']} | SMS Failed: {$res['sms_failed']}");

        foreach ($res['details'] as $det) {
            $this->line(" - [{$det['ref']}] {$det['tier']} (Age: {$det['age']} days) -> Sent: {$det['sent']}, Failed: {$det['failed']}");
        }

        return Command::SUCCESS;
    }
}
