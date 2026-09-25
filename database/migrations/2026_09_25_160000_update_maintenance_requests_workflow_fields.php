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
                if (!Schema::hasColumn('maintenance_requests', 'gs_status')) {
                    $table->string('gs_status', 50)->default('pending_gs')->index()->after('status');
                }
                if (!Schema::hasColumn('maintenance_requests', 'maintenance_person_name')) {
                    $table->string('maintenance_person_name', 255)->nullable()->after('gs_status');
                }
                if (!Schema::hasColumn('maintenance_requests', 'maintenance_person_account')) {
                    $table->string('maintenance_person_account', 255)->nullable()->after('maintenance_person_name');
                }
                if (!Schema::hasColumn('maintenance_requests', 'maintenance_person_phone')) {
                    $table->string('maintenance_person_phone', 100)->nullable()->after('maintenance_person_account');
                }
                if (!Schema::hasColumn('maintenance_requests', 'petty_cash_owner_id')) {
                    $table->unsignedBigInteger('petty_cash_owner_id')->nullable()->index()->after('maintenance_person_phone');
                }
                if (!Schema::hasColumn('maintenance_requests', 'money_amount')) {
                    $table->decimal('money_amount', 15, 2)->nullable()->after('petty_cash_owner_id');
                }
                if (!Schema::hasColumn('maintenance_requests', 'estimated_time')) {
                    $table->string('estimated_time', 150)->nullable()->after('money_amount');
                }
                if (!Schema::hasColumn('maintenance_requests', 'material_description')) {
                    $table->text('material_description')->nullable()->after('estimated_time');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gm_initial_status')) {
                    $table->string('gm_initial_status', 50)->nullable()->after('material_description');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gm_initial_action_at')) {
                    $table->timestamp('gm_initial_action_at')->nullable()->after('gm_initial_status');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gm_initial_notes')) {
                    $table->text('gm_initial_notes')->nullable()->after('gm_initial_action_at');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gm_return_reason')) {
                    $table->text('gm_return_reason')->nullable()->after('gm_initial_notes');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gm_final_approved_at')) {
                    $table->timestamp('gm_final_approved_at')->nullable()->after('gm_return_reason');
                }
                if (!Schema::hasColumn('maintenance_requests', 'is_returned_flow')) {
                    $table->boolean('is_returned_flow')->default(false)->after('gm_final_approved_at');
                }
            });
        }

        if (Schema::hasTable('expense_requests')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('expense_requests', 'petty_cash_owner_id')) {
                    $table->unsignedBigInteger('petty_cash_owner_id')->nullable()->index()->after('maintenance_request_id');
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
                    'gs_status',
                    'maintenance_person_name',
                    'maintenance_person_account',
                    'maintenance_person_phone',
                    'petty_cash_owner_id',
                    'money_amount',
                    'estimated_time',
                    'material_description',
                    'gm_initial_status',
                    'gm_initial_action_at',
                    'gm_initial_notes',
                    'gm_return_reason',
                    'gm_final_approved_at',
                    'is_returned_flow',
                ];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('maintenance_requests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('expense_requests')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                if (Schema::hasColumn('expense_requests', 'petty_cash_owner_id')) {
                    $table->dropColumn('petty_cash_owner_id');
                }
            });
        }
    }
};
