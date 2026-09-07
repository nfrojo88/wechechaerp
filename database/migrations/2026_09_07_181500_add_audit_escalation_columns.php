<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expense_requests')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('expense_requests', 'audit_escalated_3day_at')) {
                    $table->timestamp('audit_escalated_3day_at')->nullable()->after('audit_receipt_requested_at');
                }
                if (!Schema::hasColumn('expense_requests', 'audit_escalated_5day_at')) {
                    $table->timestamp('audit_escalated_5day_at')->nullable()->after('audit_escalated_3day_at');
                }
            });
        }

        if (Schema::hasTable('procurement_receipts')) {
            Schema::table('procurement_receipts', function (Blueprint $table) {
                if (!Schema::hasColumn('procurement_receipts', 'audit_escalated_3day_at')) {
                    $table->timestamp('audit_escalated_3day_at')->nullable();
                }
                if (!Schema::hasColumn('procurement_receipts', 'audit_escalated_5day_at')) {
                    $table->timestamp('audit_escalated_5day_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expense_requests')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                if (Schema::hasColumn('expense_requests', 'audit_escalated_3day_at')) {
                    $table->dropColumn('audit_escalated_3day_at');
                }
                if (Schema::hasColumn('expense_requests', 'audit_escalated_5day_at')) {
                    $table->dropColumn('audit_escalated_5day_at');
                }
            });
        }

        if (Schema::hasTable('procurement_receipts')) {
            Schema::table('procurement_receipts', function (Blueprint $table) {
                if (Schema::hasColumn('procurement_receipts', 'audit_escalated_3day_at')) {
                    $table->dropColumn('audit_escalated_3day_at');
                }
                if (Schema::hasColumn('procurement_receipts', 'audit_escalated_5day_at')) {
                    $table->dropColumn('audit_escalated_5day_at');
                }
            });
        }
    }
};
