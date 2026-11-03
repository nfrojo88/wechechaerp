<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SlipSequenceService;

class SyncAndFillSlipGapsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slip-sequences:sync-and-fill-gaps {--store= : Optional Store ID} {--type= : Optional Slip Type (receive or send)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all currently added slips in Store Manager & Store Keeper, accept added slips, auto-fill missing gaps as void entries, and advance sequence counters.';

    /**
     * Execute the console command.
     */
    public function handle(SlipSequenceService $service): int
    {
        $storeId = $this->option('store') ? (int)$this->option('store') : null;
        $slipType = $this->option('type') ?: null;

        $this->info("Checking currently added slips across Store Manager & Store Keeper sections...");

        $result = $service->checkAndFillMissingSlips($storeId, $slipType);

        $this->line("Stores checked: " . $result['stores_checked']);
        $this->line("Missing gaps filled: " . $result['gaps_filled']);

        if (!empty($result['sequences_updated'])) {
            $this->table(
                ['Store', 'Type', 'Book Range', 'Old Current', 'New Current', 'Max Added', 'Gaps Filled'],
                $result['sequences_updated']
            );
            $this->info("Successfully synchronized sequences and filled all missing slip gaps.");
        } else {
            $this->info("All sequence books are already continuous with zero gaps.");
        }

        return Command::SUCCESS;
    }
}
