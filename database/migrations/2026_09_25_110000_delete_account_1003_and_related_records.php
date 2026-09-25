<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\ChartOfAccount;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Permanently purges Chart of Account 1003 ("Awash Bank") and all associated transfers,
     * journal entries, transactions, and logs, reversing counter-party balances.
     */
    public function up(): void
    {
        ChartOfAccount::deleteAccountAndRelated('1003', true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Deletion of account and associated history is permanent.
    }
};
