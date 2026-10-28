<?php

use App\Http\Controllers\VehicleFuelRequestController;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Permanently purges Expense Request "EXP-FUEL-2026-0273" and ALL its linked records:
     * - VehicleFuelRequest
     * - Receipt and ReceiptItems
     * - ExpenseHubVatDecision and Audit Logs
     * - Expenses record
     * - VehicleKmLog
     * - Letters and CreditStorePayment foreign key references
     * - ActivityLogs and NotificationLogs
     * - Uploaded disk attachments
     */
    public function up(): void
    {
        try {
            $summary = VehicleFuelRequestController::purgeTargetFuelRequest('EXP-FUEL-2026-0273');
            Log::info("Migration purge_target_expense_exp_fuel_2026_0273 executed. Purged summary: " . json_encode($summary));
        } catch (\Throwable $e) {
            Log::error("Migration purge_target_expense_exp_fuel_2026_0273 error: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Permanent deletion cannot be reversed
    }
};
