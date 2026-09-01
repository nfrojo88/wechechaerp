<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\ExpenseRequest;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            $expenseRequests = DB::table('expense_requests')
                ->where('has_withholding', true)
                ->orWhere('withholding_amount', '>', 0)
                ->get();

            foreach ($expenseRequests as $req) {
                $gross = (float)($req->gross_amount > 0 ? $req->gross_amount : $req->amount);
                $vatType = $req->vat_type ?? 'none';
                $vatRate = (float)($req->vat_rate > 0 ? $req->vat_rate : 15.00);

                $taxableBase = in_array($vatType, ['inclusive', 'vat_b'])
                    ? round($gross / (1 + ($vatRate / 100)), 2)
                    : $gross;

                $vatAmount = (float)$req->vat_amount;
                if ($vatAmount <= 0) {
                    if (in_array($vatType, ['inclusive', 'vat_b'])) {
                        $vatAmount = round($gross - $taxableBase, 2);
                    } elseif ($vatType === 'exclusive') {
                        $vatAmount = round($gross * ($vatRate / 100), 2);
                    }
                }

                // Strict 3% Withholding calculation
                $withholdingRate = 3.00;
                $withholdingAmount = round($taxableBase * ($withholdingRate / 100), 2);

                $netAmount = $vatType === 'exclusive'
                    ? round(($gross + $vatAmount) - $withholdingAmount, 2)
                    : round($gross - $withholdingAmount, 2);

                DB::table('expense_requests')
                    ->where('id', $req->id)
                    ->update([
                        'has_withholding'    => true,
                        'withholding_rate'   => $withholdingRate,
                        'withholding_amount' => $withholdingAmount,
                        'vat_amount'         => $vatAmount,
                        'net_amount'         => $netAmount,
                    ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Withholding tax recalculation migration: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive
    }
};
