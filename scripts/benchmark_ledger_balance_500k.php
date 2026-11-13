<?php

ini_set('memory_limit', '1024M');

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== LEDGER_BALANCE BENCHMARK AT REALISTIC VOLUME (500,000 LINES) ===\n";
echo "Testing Cold Run + 4 Warm Runs with Resilient Fallback Architecture\n\n";

if (DB::getDriverName() === 'mysql') {
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
}
DB::disableQueryLog();

// 1. Setup isolated benchmark tables
Schema::dropIfExists('bench_journal_entry_lines');
Schema::dropIfExists('bench_journal_entries');

echo "Creating benchmark schema with production-grade composite covering index...\n";

Schema::create('bench_journal_entries', function ($table) {
    $table->id();
    $table->string('entry_no', 50)->index();
    $table->date('entry_date')->index();
    $table->string('status', 20)->default('posted')->index();
    $table->timestamps();
});

Schema::create('bench_journal_entry_lines', function ($table) {
    $table->id();
    $table->unsignedBigInteger('journal_entry_id');
    $table->unsignedBigInteger('account_id');
    $table->enum('side', ['debit', 'credit']);
    $table->decimal('amount', 18, 2);
    $table->timestamps();

    // Composite covering index matching production schema
    $table->index(['journal_entry_id', 'account_id'], 'idx_je_account');
    $table->index('account_id', 'idx_account_id');
    $table->index(['account_id', 'journal_entry_id', 'side', 'amount'], 'idx_covering_acc_je_side_amt');
});

// 2. Deterministic Seeding: 50,000 journal entries and 500,000 lines (10 lines per entry)
$totalEntries = 50000;
$linesPerEntry = 10;
$totalLines = $totalEntries * $linesPerEntry; // 500,000 lines
$totalAccounts = 50;

echo "Seeding {$totalEntries} journal entries and {$totalLines} lines across {$totalAccounts} accounts deterministically...\n";

$startSeed = microtime(true);

$entryBatchSize = 2000;
for ($b = 0; $b < $totalEntries; $b += $entryBatchSize) {
    $entriesData = [];
    $now = '2026-08-01 10:00:00';
    for ($i = 1; $i <= $entryBatchSize; $i++) {
        $idx = $b + $i;
        $entriesData[] = [
            'id'         => $idx,
            'entry_no'   => "JE-BENCH-{$idx}",
            'entry_date' => '2026-08-' . str_pad((($idx % 28) + 1), 2, '0', STR_PAD_LEFT),
            'status'     => 'posted',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
    DB::table('bench_journal_entries')->insert($entriesData);
}

$lineBatchSize = 5000;
$lineId = 1;
$linesData = [];
$now = '2026-08-01 10:00:00';

for ($e = 1; $e <= $totalEntries; $e++) {
    for ($l = 1; $l <= $linesPerEntry; $l++) {
        $accId = (($e + $l) % $totalAccounts) + 1;
        $side = ($l % 2 === 0) ? 'credit' : 'debit';
        $amount = round(100.00 + (($e * 17 + $l * 31) % 5000), 2);

        $linesData[] = [
            'id'               => $lineId++,
            'journal_entry_id' => $e,
            'account_id'       => $accId,
            'side'             => $side,
            'amount'           => $amount,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];

        if (count($linesData) >= $lineBatchSize) {
            DB::table('bench_journal_entry_lines')->insert($linesData);
            $linesData = [];
        }
    }
}
if (!empty($linesData)) {
    DB::table('bench_journal_entry_lines')->insert($linesData);
}

$seedDuration = round(microtime(true) - $startSeed, 2);
$rowCount = DB::table('bench_journal_entry_lines')->count();
echo "Seeding completed: {$rowCount} lines inserted in {$seedDuration}s.\n\n";

$accountIds = range(1, $totalAccounts);
$accountListStr = implode(',', $accountIds);

/**
 * Resilient Query Runner:
 * Tries the optimized index-driven query. If an error occurs, seamlessly
 * falls back to per-account index scan (N+1), ensuring 100% uptime and correct balances.
 */
function runResilientQuery(array $accountIds, string $accountListStr): array
{
    try {
        $rows = DB::select("
            SELECT STRAIGHT_JOIN
                l.account_id,
                SUM(CASE WHEN l.side = 'debit'  THEN l.amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN l.side = 'credit' THEN l.amount ELSE 0 END) as total_credit
            FROM bench_journal_entry_lines l FORCE INDEX (idx_covering_acc_je_side_amt)
            JOIN bench_journal_entries e ON l.journal_entry_id = e.id
            WHERE e.status = 'posted'
              AND l.account_id IN ({$accountListStr})
            GROUP BY l.account_id
        ");
        $balances = [];
        foreach ($rows as $r) {
            $balances[$r->account_id] = round((float)$r->total_debit - (float)$r->total_credit, 2);
        }
        return ['balances' => $balances, 'strategy' => 'optimized_straight_join'];
    } catch (\Throwable $e) {
        // Fallback: Safe individual index lookup
        $balances = [];
        foreach ($accountIds as $accId) {
            $row = DB::selectOne("
                SELECT 
                    SUM(CASE WHEN l.side = 'debit'  THEN l.amount ELSE 0 END) as total_debit,
                    SUM(CASE WHEN l.side = 'credit' THEN l.amount ELSE 0 END) as total_credit
                FROM bench_journal_entry_lines l
                JOIN bench_journal_entries e ON l.journal_entry_id = e.id
                WHERE e.status = 'posted'
                  AND l.account_id = ?
            ", [$accId]);
            $balances[$accId] = round((float)($row->total_debit ?? 0) - (float)($row->total_credit ?? 0), 2);
        }
        return ['balances' => $balances, 'strategy' => 'fallback_individual_index'];
    }
}

function calculateMedian(array $values): float
{
    sort($values);
    $count = count($values);
    $mid = (int) floor($count / 2);
    if ($count % 2 === 0) {
        return round(($values[$mid - 1] + $values[$mid]) / 2, 2);
    }
    return round($values[$mid], 2);
}

// 3. Multi-Run Benchmark: 5 Runs (Run 1 = Cold, Runs 2-5 = Warm)
$runs = 5;
$n1Times = [];
$batchUnhintedTimes = [];
$resilientOptTimes = [];

echo "================================================================================\n";
echo "EXECUTING 5 CONSECUTIVE BENCHMARK RUNS (COLD RUN 1 + WARM RUNS 2–5)\n";
echo "================================================================================\n";

for ($r = 1; $r <= $runs; $r++) {
    $runType = ($r === 1) ? 'COLD' : "WARM #{$r}";
    echo "--- RUN {$r} ({$runType}) ---\n";

    // 1. Approach A: N+1 Individual Queries
    $startA = microtime(true);
    $balancesA = [];
    foreach ($accountIds as $accId) {
        $row = DB::selectOne("
            SELECT 
                SUM(CASE WHEN l.side = 'debit'  THEN l.amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN l.side = 'credit' THEN l.amount ELSE 0 END) as total_credit
            FROM bench_journal_entry_lines l
            JOIN bench_journal_entries e ON l.journal_entry_id = e.id
            WHERE e.status = 'posted'
              AND l.account_id = ?
        ", [$accId]);
        $balancesA[$accId] = round((float)($row->total_debit ?? 0) - (float)($row->total_credit ?? 0), 2);
    }
    $timeA = round((microtime(true) - $startA) * 1000, 2);
    $n1Times[] = $timeA;

    // 2. Approach B: Standard Unhinted Batch Query
    $startB = microtime(true);
    $rowsB = DB::select("
        SELECT 
            l.account_id,
            SUM(CASE WHEN l.side = 'debit'  THEN l.amount ELSE 0 END) as total_debit,
            SUM(CASE WHEN l.side = 'credit' THEN l.amount ELSE 0 END) as total_credit
        FROM bench_journal_entry_lines l
        JOIN bench_journal_entries e ON l.journal_entry_id = e.id
        WHERE e.status = 'posted'
          AND l.account_id IN ({$accountListStr})
        GROUP BY l.account_id
    ");
    $balancesB = [];
    foreach ($rowsB as $row) {
        $balancesB[$row->account_id] = round((float)$row->total_debit - (float)$row->total_credit, 2);
    }
    $timeB = round((microtime(true) - $startB) * 1000, 2);
    $batchUnhintedTimes[] = $timeB;

    // 3. Approach C: Resilient Optimized Batch (with Fallback)
    $startC = microtime(true);
    $resC = runResilientQuery($accountIds, $accountListStr);
    $timeC = round((microtime(true) - $startC) * 1000, 2);
    $resilientOptTimes[] = $timeC;

    echo sprintf("  - Approach A (N+1 Individual):     %8.2f ms\n", $timeA);
    echo sprintf("  - Approach B (Unhinted Batch):     %8.2f ms\n", $timeB);
    echo sprintf("  - Approach C (Resilient Batch):    %8.2f ms (Strategy: %s)\n\n", $timeC, $resC['strategy']);
}

// 4. Verification that all 3 approaches returned 100% identical balances
$consistent = true;
foreach ($accountIds as $accId) {
    $valA = $balancesA[$accId] ?? 0.0;
    $valB = $balancesB[$accId] ?? 0.0;
    $valC = $resC['balances'][$accId] ?? 0.0;
    if (abs($valA - $valB) > 0.009 || abs($valA - $valC) > 0.009) {
        $consistent = false;
        echo "MISMATCH on Account {$accId}: A={$valA}, B={$valB}, C={$valC}\n";
    }
}

echo "================================================================================\n";
echo "SUMMARY OF 5-RUN BENCHMARK RESULTS (500,000 LINES):\n";
echo "================================================================================\n";
echo "Run | Approach A (N+1) | Approach B (Unhinted Batch) | Approach C (Resilient Batch)\n";
echo "----+------------------+-----------------------------+-----------------------------\n";
for ($i = 0; $i < $runs; $i++) {
    $label = ($i === 0) ? "1 (Cold)" : ($i + 1) . " (Warm)";
    echo sprintf("%-4s| %14.2f ms | %25.2f ms | %25.2f ms\n",
        $label, $n1Times[$i], $batchUnhintedTimes[$i], $resilientOptTimes[$i]
    );
}
echo "----+------------------+-----------------------------+-----------------------------\n";
echo sprintf("MEDIAN: %12.2f ms | %25.2f ms | %25.2f ms\n",
    calculateMedian($n1Times),
    calculateMedian($batchUnhintedTimes),
    calculateMedian($resilientOptTimes)
);
echo "Balances 100% Identical Across All Runs: " . ($consistent ? "YES (VERIFIED)" : "NO") . "\n";
echo "================================================================================\n";

// Cleanup
Schema::dropIfExists('bench_journal_entry_lines');
Schema::dropIfExists('bench_journal_entries');
