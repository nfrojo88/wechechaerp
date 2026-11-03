<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SlipSequence;

class RecalculateSlipSequencesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slips:recalculate {--dry-run : Only show proposed recalculations without updating the database} {--store= : Optional store ID} {--type= : Optional slip type (receive/send)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate current_slip_no, used_count, and status from real registered rows for all slip sequence books.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = (bool) $this->option('dry-run');

        $this->info("===============================================================");
        $this->info("   SLIP SEQUENCE RECALCULATION" . ($isDryRun ? " (DRY-RUN)" : ""));
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
        $changedCount = 0;

        foreach ($sequences as $seq) {
            $res = $seq->recalculate(save: !$isDryRun);

            if ($res['changed']) {
                $changedCount++;
            }

            $currentText = ($res['old_current'] !== $res['new_current'])
                ? "<comment>#{$res['old_current']} ➔ #{$res['new_current']}</comment>"
                : "#{$res['new_current']}";

            $usedText = ($res['old_used'] !== $res['new_used'])
                ? "<comment>{$res['old_used']} ➔ {$res['new_used']}</comment>"
                : "{$res['new_used']}";

            $statusText = ($res['old_status'] !== $res['new_status'])
                ? "<comment>{$res['old_status']} ➔ {$res['new_status']}</comment>"
                : "{$res['new_status']}";

            $actionText = $res['changed']
                ? ($isDryRun ? '<comment>[WOULD UPDATE]</comment>' : '<info>[UPDATED]</info>')
                : '<fg=gray>[NO CHANGE]</>';

            $rows[] = [
                $seq->id,
                $seq->store?->name ?? "Store #{$seq->store_id}",
                strtoupper($seq->slip_type),
                $res['book_range'],
                $currentText,
                $usedText,
                $statusText,
                $actionText,
            ];
        }

        $this->table(
            ['ID', 'Store', 'Type', 'Range', 'Current Slip', 'Used Count', 'Status', 'Action'],
            $rows
        );

        $this->newLine();

        if ($isDryRun) {
            $this->warn("DRY RUN COMPLETE: {$changedCount} of {$sequences->count()} sequence book(s) would be updated.");
            $this->line("Run without --dry-run to apply recalculation changes to the database.");
        } else {
            $this->info("RECALCULATION COMPLETE: Successfully updated {$changedCount} of {$sequences->count()} sequence book(s). All real slip records were preserved untouched.");
        }

        return 0;
    }
}
