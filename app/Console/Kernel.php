<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Sync ZKTeco device punch logs → attendance table every 5 minutes
        $schedule->command('zkteco:sync')->everyFiveMinutes()->withoutOverlapping();

        // Detect 5 consecutive absent days and auto-suspend access (daily at 18:30 after sync)
        $schedule->command('attendance:check-absence-blocks')->dailyAt('18:30')->withoutOverlapping();

        // Check & escalate overdue audit receipt inquiries (>3 days to Auditor/Finance Head, >5 days to GM/Admin)
        $schedule->command('audit:escalate-overdue-inquiries')->hourly()->withoutOverlapping();

        // Check vehicle reminders daily (Bolo, insurance, service intervals)
        $schedule->command('vehicles:check-reminders')->dailyAt('08:00')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
