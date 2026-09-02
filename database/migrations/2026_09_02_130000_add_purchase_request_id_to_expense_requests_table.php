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
                if (!Schema::hasColumn('expense_requests', 'purchase_request_id')) {
                    $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete()->after('maintenance_request_id');
                }
                if (!Schema::hasColumn('expense_requests', 'project_id')) {
                    $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete()->after('purchase_request_id');
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
                if (Schema::hasColumn('expense_requests', 'project_id')) {
                    $table->dropForeign(['project_id']);
                    $table->dropColumn('project_id');
                }
                if (Schema::hasColumn('expense_requests', 'purchase_request_id')) {
                    $table->dropForeign(['purchase_request_id']);
                    $table->dropColumn('purchase_request_id');
                }
            });
        }
    }
};
