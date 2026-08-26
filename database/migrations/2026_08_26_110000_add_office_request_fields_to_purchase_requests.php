<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requests', 'is_office_request')) {
                $table->boolean('is_office_request')->default(false)->after('type')->index();
            }
            if (!Schema::hasColumn('purchase_requests', 'office_purpose')) {
                $table->string('office_purpose', 255)->nullable()->after('is_office_request');
            }
            if (!Schema::hasColumn('purchase_requests', 'hr_coordinator_notes')) {
                $table->text('hr_coordinator_notes')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('purchase_requests', 'hr_coordinator_approved_by')) {
                $table->foreignId('hr_coordinator_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_at');
            }
            if (!Schema::hasColumn('purchase_requests', 'hr_coordinator_approved_at')) {
                $table->timestamp('hr_coordinator_approved_at')->nullable()->after('hr_coordinator_approved_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_requests', 'hr_coordinator_approved_by')) {
                $table->dropForeign(['hr_coordinator_approved_by']);
                $table->dropColumn('hr_coordinator_approved_by');
            }
            if (Schema::hasColumn('purchase_requests', 'hr_coordinator_approved_at')) {
                $table->dropColumn('hr_coordinator_approved_at');
            }
            if (Schema::hasColumn('purchase_requests', 'hr_coordinator_notes')) {
                $table->dropColumn('hr_coordinator_notes');
            }
            if (Schema::hasColumn('purchase_requests', 'office_purpose')) {
                $table->dropColumn('office_purpose');
            }
            if (Schema::hasColumn('purchase_requests', 'is_office_request')) {
                $table->dropColumn('is_office_request');
            }
        });
    }
};
