<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;

echo "=== BENCHMARK: N+1 QUERIES VS GROUPED BATCH QUERY ===\n";

$accounts = ChartOfAccount::take(50)->get();
if ($accounts->isEmpty()) {
    echo "No accounts found to benchmark.\n";
    exit(0);
}

$accountCount = $accounts->count();
echo "Benchmarking with {$accountCount} accounts...\n";

// 1. Measure N+1 iterative queries
DB::flushQueryLog();
DB::enableQueryLog();
$startN1 = microtime(true);
$balancesN1 = [];
foreach ($accounts as $acc) {
    $balancesN1[$acc->id] = (float) $acc->ledger_balance;
}
$timeN1 = (microtime(true) - $startN1) * 1000.0; // milliseconds
$queryCountN1 = count(DB::getQueryLog());

// 2. Measure Single Grouped Batch query
DB::flushQueryLog();
DB::enableQueryLog();
$startBatch = microtime(true);
$balancesBatch = ChartOfAccount::getBatchLedgerBalances($accounts);
$timeBatch = (microtime(true) - $startBatch) * 1000.0; // milliseconds
$queryCountBatch = count(DB::getQueryLog());

// Verify correctness
$match = true;
foreach ($balancesN1 as $id => $val) {
    if (abs($val - ($balancesBatch[$id] ?? 0.0)) > 0.009) {
        $match = false;
        echo "Mismatch on account ID {$id}: N1={$val} vs Batch=" . ($balancesBatch[$id] ?? 'null') . "\n";
    }
}

$speedup = $timeBatch > 0 ? round($timeN1 / $timeBatch, 1) : 0;

echo "--------------------------------------------------------\n";
echo "RESULTS:\n";
echo "N+1 Approach:     {$queryCountN1} queries executed in " . number_format($timeN1, 2) . " ms\n";
echo "Grouped Approach: {$queryCountBatch} query executed in " . number_format($timeBatch, 2) . " ms\n";
echo "Speedup Factor:   {$speedup}x faster\n";
echo "Data Integrity:   " . ($match ? "VERIFIED (100% Identical Balances)" : "FAILED (Discrepancies found)") . "\n";
echo "--------------------------------------------------------\n";
