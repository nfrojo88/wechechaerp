<?php

namespace App\Console\Commands;

use App\Http\Controllers\VehicleFuelRequestController;
use Illuminate\Console\Command;

class PurgeTargetExpenseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expense:purge-target {target=EXP-FUEL-2026-0273 : The expense request number to purge}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently purge target expense request and all linked fuel, receipt, VAT decision, and log records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $target = trim((string)$this->argument('target'));

        if (empty($target)) {
            $this->error('Target expense reference cannot be empty.');
            return Command::FAILURE;
        }

        $this->info("Initiating permanent purge for target: [{$target}]...");

        $summary = VehicleFuelRequestController::purgeTargetFuelRequest($target);

        $tableRows = [];
        $totalItems = 0;
        foreach ($summary as $entity => $count) {
            $tableRows[] = [ucwords(str_replace('_', ' ', $entity)), $count];
            $totalItems += $count;
        }

        $this->table(['Entity / Record Type', 'Purged Count'], $tableRows);

        $this->info("Completed purge for [{$target}]. Total linked items affected: {$totalItems}.");

        return Command::SUCCESS;
    }
}
