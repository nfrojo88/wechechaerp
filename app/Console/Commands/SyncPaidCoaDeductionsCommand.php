<?php

namespace App\Console\Commands;

use App\Services\FinanceCoaReconciliationService;
use Illuminate\Console\Command;

class SyncPaidCoaDeductionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:sync-coa-deductions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile and deduct all approved and paid requests up to now from Chart of Account balances';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("Scanning and reconciling historical paid requests against Chart of Accounts...");

        $stats = FinanceCoaReconciliationService::reconcileHistoricalPayments();

        $this->table(
            ['Category', 'Count', 'Total Amount (ETB)'],
            [
                ['Expense Requests', $stats['expense_requests_count'], number_format($stats['expense_requests_amount'], 2)],
                ['Office Material Requests', $stats['office_requests_count'], number_format($stats['office_requests_amount'], 2)],
                ['Petty Cash Replenishments', $stats['replenishments_count'], number_format($stats['replenishments_amount'], 2)],
            ]
        );

        $totalAmt = $stats['expense_requests_amount'] + $stats['office_requests_amount'] + $stats['replenishments_amount'];
        $totalCount = $stats['expense_requests_count'] + $stats['office_requests_count'] + $stats['replenishments_count'];

        $this->info("Successfully deducted {$totalCount} requests totaling ETB " . number_format($totalAmt, 2) . " from Chart of Accounts.");

        return Command::SUCCESS;
    }
}
