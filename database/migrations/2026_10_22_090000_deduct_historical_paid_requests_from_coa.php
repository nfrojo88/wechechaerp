<?php

use App\Services\FinanceCoaReconciliationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Reconciles all historical and pending paid expense requests, office material requests,
     * and fulfilled replenishments so that Chart of Accounts balances accurately reflect all paid items.
     */
    public function up(): void
    {
        try {
            FinanceCoaReconciliationService::ensureSchema();
            $stats = FinanceCoaReconciliationService::reconcileHistoricalPayments();
            Log::info("Migration 2026_10_22_090000_deduct_historical_paid_requests_from_coa completed successfully.", $stats);
        } catch (\Throwable $e) {
            Log::error("Migration 2026_10_22_090000_deduct_historical_paid_requests_from_coa encountered error: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Historical balance deductions cannot be generically reversed.
    }
};
