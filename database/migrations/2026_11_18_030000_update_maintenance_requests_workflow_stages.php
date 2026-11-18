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
                if (!Schema::hasColumn('maintenance_requests', 'diagnosis')) {
                    $table->text('diagnosis')->nullable()->after('description');
                }
                if (!Schema::hasColumn('maintenance_requests', 'diagnosis_attachments')) {
                    $table->json('diagnosis_attachments')->nullable()->after('diagnosis');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gs_verified_at')) {
                    $table->timestamp('gs_verified_at')->nullable()->after('diagnosis_attachments');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gs_verified_by')) {
                    $table->unsignedBigInteger('gs_verified_by')->nullable()->after('gs_verified_at');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gs_rejected_at')) {
                    $table->timestamp('gs_rejected_at')->nullable()->after('gs_verified_by');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gs_rejected_by')) {
                    $table->unsignedBigInteger('gs_rejected_by')->nullable()->after('gs_rejected_at');
                }
                if (!Schema::hasColumn('maintenance_requests', 'materials_data')) {
                    $table->json('materials_data')->nullable()->after('material_description');
                }
                if (!Schema::hasColumn('maintenance_requests', 'material_cost')) {
                    $table->decimal('material_cost', 15, 2)->default(0)->nullable()->after('materials_data');
                }
                if (!Schema::hasColumn('maintenance_requests', 'labor_cost')) {
                    $table->decimal('labor_cost', 15, 2)->default(0)->nullable()->after('material_cost');
                }
                if (!Schema::hasColumn('maintenance_requests', 'total_cost')) {
                    $table->decimal('total_cost', 15, 2)->default(0)->nullable()->after('labor_cost');
                }
                if (!Schema::hasColumn('maintenance_requests', 'technician_name')) {
                    $table->string('technician_name', 255)->nullable()->after('total_cost');
                }
                if (!Schema::hasColumn('maintenance_requests', 'technician_phone')) {
                    $table->string('technician_phone', 100)->nullable()->after('technician_name');
                }
                if (!Schema::hasColumn('maintenance_requests', 'technician_bank_name')) {
                    $table->string('technician_bank_name', 255)->nullable()->after('technician_phone');
                }
                if (!Schema::hasColumn('maintenance_requests', 'technician_account_name')) {
                    $table->string('technician_account_name', 255)->nullable()->after('technician_bank_name');
                }
                if (!Schema::hasColumn('maintenance_requests', 'technician_account_number')) {
                    $table->string('technician_account_number', 255)->nullable()->after('technician_account_name');
                }
                if (!Schema::hasColumn('maintenance_requests', 'condition_rating')) {
                    $table->string('condition_rating', 50)->nullable()->after('technician_account_number');
                }
                if (!Schema::hasColumn('maintenance_requests', 'quotation_attachments')) {
                    $table->json('quotation_attachments')->nullable()->after('condition_rating');
                }
                if (!Schema::hasColumn('maintenance_requests', 'remarks')) {
                    $table->text('remarks')->nullable()->after('quotation_attachments');
                }
                if (!Schema::hasColumn('maintenance_requests', 'gs_details_locked')) {
                    $table->boolean('gs_details_locked')->default(false)->after('remarks');
                }
                if (!Schema::hasColumn('maintenance_requests', 'hold_reason')) {
                    $table->text('hold_reason')->nullable()->after('gs_details_locked');
                }
                if (!Schema::hasColumn('maintenance_requests', 'hold_review_date')) {
                    $table->date('hold_review_date')->nullable()->after('hold_reason');
                }
                if (!Schema::hasColumn('maintenance_requests', 'previous_asset_status')) {
                    $table->string('previous_asset_status', 50)->nullable()->after('hold_review_date');
                }
                if (!Schema::hasColumn('maintenance_requests', 'odometer')) {
                    $table->string('odometer', 100)->nullable()->after('previous_asset_status');
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
                $cols = [
                    'diagnosis',
                    'diagnosis_attachments',
                    'gs_verified_at',
                    'gs_verified_by',
                    'gs_rejected_at',
                    'gs_rejected_by',
                    'materials_data',
                    'material_cost',
                    'labor_cost',
                    'total_cost',
                    'technician_name',
                    'technician_phone',
                    'technician_bank_name',
                    'technician_account_name',
                    'technician_account_number',
                    'condition_rating',
                    'quotation_attachments',
                    'remarks',
                    'gs_details_locked',
                    'hold_reason',
                    'hold_review_date',
                    'previous_asset_status',
                    'odometer',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('maintenance_requests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
