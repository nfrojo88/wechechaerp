<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LockExpiredProbations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employees:lock-expired-probations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lock employee accounts where the 45-day test period has ended without renewal or guarantee letter & TIN submission';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking employees for expired 45-day test periods...');

        $lockedCount = 0;
        $activeEmployees = Employee::where('status', 'active')
            ->where('probation_completed', false)
            ->whereNotNull('date_of_joining')
            ->get();

        foreach ($activeEmployees as $emp) {
            if ($emp->is_test_period_expired) {
                $emp->update([
                    'status'      => 'terminated',
                    'lock_reason' => '45-Day Test Period Expired: Missing Guarantee Letter',
                ]);
                $this->warn("Locked Employee #{$emp->employee_code} ({$emp->full_name}) - 45-day test period expired.");
                $lockedCount++;
            }
        }

        $this->info("Completed. Total accounts locked: {$lockedCount}");
        Log::info("employees:lock-expired-probations executed. {$lockedCount} accounts locked.");

        return 0;
    }
}
