<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Store;
use App\Models\Project;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add petty_cash_account_id to projects table
        if (Schema::hasTable('projects') && !Schema::hasColumn('projects', 'petty_cash_account_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->foreignId('petty_cash_account_id')->nullable()->after('default_store_id')->constrained('chart_of_accounts')->nullOnDelete();
            });
        }

        // 2. Backfill existing stores without a petty cash account
        if (Schema::hasTable('stores') && Schema::hasTable('chart_of_accounts')) {
            try {
                $stores = Store::whereNull('petty_cash_account_id')->get();
                foreach ($stores as $store) {
                    $store->autoCreateSitePettyCash();
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Migration backfill stores site petty cash: " . $e->getMessage());
            }
        }

        // 3. Backfill existing projects without a linked petty cash account
        if (Schema::hasTable('projects') && Schema::hasTable('chart_of_accounts')) {
            try {
                $projects = Project::whereNull('petty_cash_account_id')->get();
                foreach ($projects as $project) {
                    $project->autoCreateSitePettyCash();
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Migration backfill projects site petty cash: " . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'petty_cash_account_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropForeign(['petty_cash_account_id']);
                $table->dropColumn('petty_cash_account_id');
            });
        }
    }
};
