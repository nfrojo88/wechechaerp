<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Permanently purges Expense Request "EXP-FUEL-2026-0273" and its associated
     * VehicleFuelRequest, Receipt, ReceiptItems, and ExpenseHubVatDecision records.
     */
    public function up(): void
    {
        try {
            $targetRef = 'EXP-FUEL-2026-0273';

            // 1. Locate ExpenseRequest(s) matching EXP-FUEL-2026-0273
            $expenseRequestIds = [];
            if (Schema::hasTable('expense_requests')) {
                $expenseRequests = DB::table('expense_requests')
                    ->where(function ($q) use ($targetRef) {
                        $q->where('request_number', $targetRef)
                          ->orWhereRaw('TRIM(request_number) = ?', [$targetRef])
                          ->orWhere('request_number', 'like', "%{$targetRef}%");
                    })
                    ->get();

                foreach ($expenseRequests as $exp) {
                    $expenseRequestIds[] = $exp->id;
                }
            }

            // 2. Locate linked VehicleFuelRequest(s)
            $fuelRequestIds = [];
            if (Schema::hasTable('vehicle_fuel_requests')) {
                $fuelQuery = DB::table('vehicle_fuel_requests');
                if (!empty($expenseRequestIds)) {
                    $fuelQuery->where(function ($q) use ($expenseRequestIds, $targetRef) {
                        $q->whereIn('expense_request_id', $expenseRequestIds)
                          ->orWhere('reason', 'like', "%{$targetRef}%")
                          ->orWhere('request_no', 'like', "%{$targetRef}%");
                    });
                } else {
                    $fuelQuery->where(function ($q) use ($targetRef) {
                        $q->where('reason', 'like', "%{$targetRef}%")
                          ->orWhere('request_no', 'like', "%{$targetRef}%");
                    });
                }
                $fuelRequests = $fuelQuery->get();
                foreach ($fuelRequests as $fr) {
                    $fuelRequestIds[] = $fr->id;
                }
            }

            // 3. Purge linked Receipts and ReceiptItems
            if (Schema::hasTable('receipts')) {
                $receiptQuery = DB::table('receipts');
                $receiptQuery->where(function ($q) use ($expenseRequestIds, $fuelRequestIds, $targetRef) {
                    $hasClause = false;
                    if (!empty($expenseRequestIds)) {
                        $q->where(function ($sub) use ($expenseRequestIds) {
                            $sub->whereIn('purchasable_id', $expenseRequestIds)
                                ->where(function ($c) {
                                    $c->where('purchasable_type', 'like', '%ExpenseRequest%')
                                      ->orWhere('purchasable_type', 'expense_request');
                                });
                        });
                        $hasClause = true;
                    }
                    if (!empty($fuelRequestIds)) {
                        $method = $hasClause ? 'orWhere' : 'where';
                        $q->$method(function ($sub) use ($fuelRequestIds) {
                            $sub->whereIn('purchasable_id', $fuelRequestIds)
                                ->where(function ($c) {
                                    $c->where('purchasable_type', 'like', '%VehicleFuelRequest%')
                                      ->orWhere('purchasable_type', 'vehicle_fuel_request');
                                });
                        });
                        $hasClause = true;
                    }
                    $method = $hasClause ? 'orWhere' : 'where';
                    $q->$method('description', 'like', "%{$targetRef}%")
                      ->orWhere('receipt_number', 'like', "%{$targetRef}%");
                });

                $receipts = $receiptQuery->get();
                $receiptIds = $receipts->pluck('id')->toArray();

                if (!empty($receiptIds) && Schema::hasTable('receipt_items')) {
                    DB::table('receipt_items')->whereIn('receipt_id', $receiptIds)->delete();
                }
                if (!empty($receiptIds)) {
                    DB::table('receipts')->whereIn('id', $receiptIds)->delete();
                }
            }

            // 4. Purge linked ExpenseHubVatDecisions and Audit Logs
            if (Schema::hasTable('expense_hub_vat_decisions')) {
                $decisionQuery = DB::table('expense_hub_vat_decisions');
                $decisionQuery->where(function ($q) use ($expenseRequestIds, $fuelRequestIds, $targetRef) {
                    $hasClause = false;
                    if (!empty($expenseRequestIds)) {
                        $q->where(function ($sub) use ($expenseRequestIds) {
                            $sub->where('source_type', 'expense_request')
                                ->whereIn('source_id', $expenseRequestIds);
                        });
                        $hasClause = true;
                    }
                    if (!empty($fuelRequestIds)) {
                        $method = $hasClause ? 'orWhere' : 'where';
                        $q->$method(function ($sub) use ($fuelRequestIds) {
                            $sub->where('source_type', 'fuel_request')
                                ->whereIn('source_id', $fuelRequestIds);
                        });
                        $hasClause = true;
                    }
                    $method = $hasClause ? 'orWhere' : 'where';
                    $q->$method('source_reference', 'like', "%{$targetRef}%")
                      ->orWhere('reference_no', 'like', "%{$targetRef}%");
                });

                $decisions = $decisionQuery->get();
                $decisionIds = $decisions->pluck('id')->toArray();

                if (!empty($decisionIds) && Schema::hasTable('expense_hub_vat_audit_logs')) {
                    DB::table('expense_hub_vat_audit_logs')->whereIn('decision_id', $decisionIds)->delete();
                }
                if (!empty($decisionIds)) {
                    DB::table('expense_hub_vat_decisions')->whereIn('id', $decisionIds)->delete();
                }
            }

            // 5. Clean up any foreign references in letters or credit store payments
            if (!empty($expenseRequestIds) && Schema::hasTable('letters') && Schema::hasColumn('letters', 'expense_request_id')) {
                DB::table('letters')->whereIn('expense_request_id', $expenseRequestIds)->update(['expense_request_id' => null]);
            }
            if (!empty($expenseRequestIds) && Schema::hasTable('credit_store_payments') && Schema::hasColumn('credit_store_payments', 'expense_request_id')) {
                DB::table('credit_store_payments')->whereIn('expense_request_id', $expenseRequestIds)->update(['expense_request_id' => null]);
            }

            // 6. Delete VehicleFuelRequest(s)
            if (!empty($fuelRequestIds) && Schema::hasTable('vehicle_fuel_requests')) {
                DB::table('vehicle_fuel_requests')->whereIn('id', $fuelRequestIds)->delete();
            }

            // 7. Delete ExpenseRequest(s)
            if (!empty($expenseRequestIds) && Schema::hasTable('expense_requests')) {
                DB::table('expense_requests')->whereIn('id', $expenseRequestIds)->delete();
            }

            // 8. Delete activity logs if any
            if (Schema::hasTable('activity_logs')) {
                DB::table('activity_logs')->where(function ($q) use ($expenseRequestIds, $targetRef) {
                    if (!empty($expenseRequestIds)) {
                        $q->where(function ($sub) use ($expenseRequestIds) {
                            $sub->where('model_type', 'like', '%ExpenseRequest%')
                                ->whereIn('model_id', $expenseRequestIds);
                        });
                        $q->orWhere('description', 'like', "%{$targetRef}%");
                    } else {
                        $q->where('description', 'like', "%{$targetRef}%");
                    }
                })->delete();
            }

            Log::info("Migration delete_fuel_request_exp_fuel_2026_0273 executed successfully. Purged EXP-FUEL-2026-0273.");
        } catch (\Throwable $e) {
            Log::error("Migration delete_fuel_request_exp_fuel_2026_0273 encountered error: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Deletion cannot be reversed
    }
};
