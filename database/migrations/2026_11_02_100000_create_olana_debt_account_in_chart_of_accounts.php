<?php

use App\Models\ChartOfAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('chart_of_accounts')) {
            return;
        }

        // Check if an account named OLANA already exists
        $existing = ChartOfAccount::where('name', 'OLANA')->first();

        if (!$existing) {
            // Pick a non-conflicting code in the liabilities / debt sequence
            $targetCode = '2004';
            if (ChartOfAccount::where('code', $targetCode)->exists()) {
                $targetCode = '2803';
            }
            if (ChartOfAccount::where('code', $targetCode)->exists()) {
                $maxCode = ChartOfAccount::whereBetween('code', ['2000', '2999'])->max('code');
                $targetCode = (string) (((int) $maxCode) + 1);
            }

            ChartOfAccount::create([
                'code'            => $targetCode,
                'name'            => 'OLANA',
                'type'            => 'liability',
                'subtype'         => 'debt',
                'description'     => 'Debt / Debtor Account — OLANA (Negative balance tracking)',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'is_active'       => true,
                'is_system'       => false,
                'sort_order'      => (int) $targetCode,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('chart_of_accounts')) {
            ChartOfAccount::where('name', 'OLANA')->delete();
        }
    }
};
