<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maintenance_requests')) {
            Schema::table('maintenance_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('maintenance_requests', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('admin_notes');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gm_approved_at')) {
                    $table->timestamp('gm_approved_at')->nullable()->after('admin_notes');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gm_approver_id')) {
                    $table->unsignedBigInteger('gm_approver_id')->nullable()->after('gm_approved_at');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gm_decision_locked')) {
                    $table->boolean('gm_decision_locked')->default(false)->after('gm_approver_id');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gm_decision_summary')) {
                    $table->text('gm_decision_summary')->nullable()->after('gm_decision_locked');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('maintenance_requests')) {
            Schema::table('maintenance_requests', function (Blueprint $table) {
                $columns = ['gm_decision_summary', 'gm_decision_locked', 'gm_approver_id', 'gm_approved_at'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('maintenance_requests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
