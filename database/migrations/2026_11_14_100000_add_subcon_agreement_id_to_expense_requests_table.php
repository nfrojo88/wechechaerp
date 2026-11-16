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
        if (Schema::hasTable('expense_requests')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('expense_requests', 'subcon_agreement_id')) {
                    $table->unsignedBigInteger('subcon_agreement_id')->nullable()->after('project_id');
                    $table->index('subcon_agreement_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('expense_requests')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                if (Schema::hasColumn('expense_requests', 'subcon_agreement_id')) {
                    $table->dropIndex(['subcon_agreement_id']);
                    $table->dropColumn('subcon_agreement_id');
                }
            });
        }
    }
};
