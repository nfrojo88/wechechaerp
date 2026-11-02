<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('expenses') && !Schema::hasColumn('expenses', 'budget_consumed_at')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->timestamp('budget_consumed_at')->nullable()->after('budget_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'budget_consumed_at')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn('budget_consumed_at');
            });
        }
    }
};
