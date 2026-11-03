<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SlipSequence;
use App\Services\SlipSequenceService;

class AuditSlipSequencesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slips:audit {--store= : Optional store ID} {--type= : Optional slip type (receive/send)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit and cross-check slip sequences across all stores, reporting gaps, duplicates, and mismatches without modifying data.';

    /**
     * Execute the console command.
     */
    public function handle(SlipSequenceService $slipService)
    {
        $this->info("===============================================================");
        $this->info("   SLIP SEQUENCE RECONCILIATION & CONTINUITY AUDIT");
        $this->info("===============================================================");

        $query = SlipSequence::with('store')->orderBy('store_id')->orderBy('book_start_no');

        if ($storeId = $this->option('store')) {
            $query->where('store_id', $storeId);
        }

        if ($type = $this->option('type')) {
            $query->where('slip_type', $type);
        }

        $sequences = $query->get();

        if ($sequences->isEmpty()) {
            $this->warn("No slip sequences found matching criteria.");
            return 0;
        }

        $rows = [];
        $totalProblems = 0;

        foreach ($sequences as $seq) {
            $res = $slipService->reconcile($seq);

            $statusText = $res['status'] === 'ok' ? '<info>OK</info>' : '<error>PROBLEMS</error>';
            if ($res['status'] !== 'ok') {
                $totalProblems++;
            }

            $rows[] = [
                $seq->id,
                $res['store_name'],
                strtoupper($res['slip_type']),
                $res['book_range'],
                "#" . $res['current_slip_no'],
                "{$res['actual_used_count']} / {$res['expected_used_count']}",
                $res['void_count'],
                $res['gaps_count'] > 0 ? "<comment>{$res['gaps_count']}</comment>" : "0",
                $res['duplicates_count'] > 0 ? "<error>{$res['duplicates_count']}</error>" : "0",
                $statusText,
            ];
        }

        $this->table(
            ['ID', 'Store', 'Type', 'Range', 'Next', 'Used/Expected', 'Void', 'Gaps', 'Duplicates', 'Health'],
            $rows
        );

        $this->newLine();

        if ($totalProblems > 0) {
            $this->warn("Audit finished: Found {$totalProblems} sequence book(s) with gaps, duplicates, or count mismatches.");
            $this->line("Run web cross-check in Store Manager > Slip Sequences to inspect leaf-level details.");
        } else {
            $this->info("Audit finished: All {$sequences->count()} sequence books are 100% continuous and fully reconciled!");
        }

        return 0;
    }
}
