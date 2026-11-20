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
        if (Schema::hasTable('maintenance_requests')) {
            Schema::table('maintenance_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('maintenance_requests', 'replacement_condition')) {
                    $table->string('replacement_condition', 50)->nullable()->after('condition_rating');
                }
                if (!Schema::hasColumn('maintenance_requests', 'replacement_action')) {
                    $table->string('replacement_action', 50)->nullable()->after('replacement_condition');
                }
                if (!Schema::hasColumn('maintenance_requests', 'sent_to_store_manager_at')) {
                    $table->timestamp('sent_to_store_manager_at')->nullable()->after('replacement_action');
                }
                if (!Schema::hasColumn('maintenance_requests', 'resolved_at')) {
                    $table->timestamp('resolved_at')->nullable()->after('sent_to_store_manager_at');
                }
                if (!Schema::hasColumn('maintenance_requests', 'reported_by_user_id')) {
                    $table->unsignedBigInteger('reported_by_user_id')->nullable()->after('resolved_at');
                }
                if (!Schema::hasColumn('maintenance_requests', 'assigned_to_user_id')) {
                    $table->unsignedBigInteger('assigned_to_user_id')->nullable()->after('reported_by_user_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('maintenance_requests')) {
            Schema::table('maintenance_requests', function (Blueprint $table) {
                $columns = [
                    'replacement_condition',
                    'replacement_action',
                    'sent_to_store_manager_at',
                    'resolved_at',
                    'reported_by_user_id',
                    'assigned_to_user_id',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('maintenance_requests', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
