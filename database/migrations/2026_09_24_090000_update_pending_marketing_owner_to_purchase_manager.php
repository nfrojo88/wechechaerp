<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Moves any PRs currently owned by 'market_research' to 'purchase_manager'
     * so that Direct Buy price review and decisions are handled by the Purchasing Manager.
     */
    public function up(): void
    {
        DB::table('purchase_requests')
            ->where('current_owner_role', 'market_research')
            ->update(['current_owner_role' => 'purchase_manager']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('purchase_requests')
            ->where('status', 'pending_marketing_review')
            ->where('current_owner_role', 'purchase_manager')
            ->update(['current_owner_role' => 'market_research']);
    }
};
