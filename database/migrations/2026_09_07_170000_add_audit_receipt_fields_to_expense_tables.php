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
                if (!Schema::hasColumn('expense_requests', 'audit_receipt_status')) {
                    $table->string('audit_receipt_status', 50)->nullable()->index();
                }
                if (!Schema::hasColumn('expense_requests', 'audit_receipt_notes')) {
                    $table->text('audit_receipt_notes')->nullable();
                }
                if (!Schema::hasColumn('expense_requests', 'audit_receipt_requested_at')) {
                    $table->timestamp('audit_receipt_requested_at')->nullable();
                }
                if (!Schema::hasColumn('expense_requests', 'audit_receipt_requested_by')) {
                    $table->unsignedBigInteger('audit_receipt_requested_by')->nullable()->index();
                }
                if (!Schema::hasColumn('expense_requests', 'audit_receipt_verified_at')) {
                    $table->timestamp('audit_receipt_verified_at')->nullable();
                }
                if (!Schema::hasColumn('expense_requests', 'audit_receipt_verified_by')) {
                    $table->unsignedBigInteger('audit_receipt_verified_by')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('office_material_requests')) {
            Schema::table('office_material_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('office_material_requests', 'audit_receipt_status')) {
                    $table->string('audit_receipt_status', 50)->nullable()->index();
                }
                if (!Schema::hasColumn('office_material_requests', 'audit_receipt_notes')) {
                    $table->text('audit_receipt_notes')->nullable();
                }
                if (!Schema::hasColumn('office_material_requests', 'audit_receipt_requested_at')) {
                    $table->timestamp('audit_receipt_requested_at')->nullable();
                }
                if (!Schema::hasColumn('office_material_requests', 'audit_receipt_requested_by')) {
                    $table->unsignedBigInteger('audit_receipt_requested_by')->nullable()->index();
                }
                if (!Schema::hasColumn('office_material_requests', 'audit_receipt_verified_at')) {
                    $table->timestamp('audit_receipt_verified_at')->nullable();
                }
                if (!Schema::hasColumn('office_material_requests', 'audit_receipt_verified_by')) {
                    $table->unsignedBigInteger('audit_receipt_verified_by')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (!Schema::hasColumn('expenses', 'receipt_path')) {
                    $table->string('receipt_path')->nullable();
                }
                if (!Schema::hasColumn('expenses', 'audit_receipt_status')) {
                    $table->string('audit_receipt_status', 50)->nullable()->index();
                }
                if (!Schema::hasColumn('expenses', 'audit_receipt_notes')) {
                    $table->text('audit_receipt_notes')->nullable();
                }
                if (!Schema::hasColumn('expenses', 'audit_receipt_requested_at')) {
                    $table->timestamp('audit_receipt_requested_at')->nullable();
                }
                if (!Schema::hasColumn('expenses', 'audit_receipt_requested_by')) {
                    $table->unsignedBigInteger('audit_receipt_requested_by')->nullable()->index();
                }
                if (!Schema::hasColumn('expenses', 'audit_receipt_verified_at')) {
                    $table->timestamp('audit_receipt_verified_at')->nullable();
                }
                if (!Schema::hasColumn('expenses', 'audit_receipt_verified_by')) {
                    $table->unsignedBigInteger('audit_receipt_verified_by')->nullable()->index();
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
                $table->dropColumn([
                    'audit_receipt_status',
                    'audit_receipt_notes',
                    'audit_receipt_requested_at',
                    'audit_receipt_requested_by',
                    'audit_receipt_verified_at',
                    'audit_receipt_verified_by',
                ]);
            });
        }

        if (Schema::hasTable('office_material_requests')) {
            Schema::table('office_material_requests', function (Blueprint $table) {
                $table->dropColumn([
                    'audit_receipt_status',
                    'audit_receipt_notes',
                    'audit_receipt_requested_at',
                    'audit_receipt_requested_by',
                    'audit_receipt_verified_at',
                    'audit_receipt_verified_by',
                ]);
            });
        }

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn([
                    'receipt_path',
                    'audit_receipt_status',
                    'audit_receipt_notes',
                    'audit_receipt_requested_at',
                    'audit_receipt_requested_by',
                    'audit_receipt_verified_at',
                    'audit_receipt_verified_by',
                ]);
            });
        }
    }
};
