<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('transfers', 'driver_employee_id')) {
                $table->foreignId('driver_employee_id')->nullable()->after('rejection_reason')->constrained('employees')->nullOnDelete();
            }
            if (!Schema::hasColumn('transfers', 'vehicle_plate_no')) {
                $table->string('vehicle_plate_no', 100)->nullable()->after('driver_employee_id');
            }
            if (!Schema::hasColumn('transfers', 'dispatch_notes')) {
                $table->text('dispatch_notes')->nullable()->after('vehicle_plate_no');
            }
            if (!Schema::hasColumn('transfers', 'dispatched_by')) {
                $table->foreignId('dispatched_by')->nullable()->after('dispatch_notes')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('transfers', 'dispatched_at')) {
                $table->timestamp('dispatched_at')->nullable()->after('dispatched_by');
            }
            if (!Schema::hasColumn('transfers', 'outgoing_slip_file')) {
                $table->string('outgoing_slip_file', 500)->nullable()->after('dispatched_at');
            }
            if (!Schema::hasColumn('transfers', 'outgoing_slip_no')) {
                $table->string('outgoing_slip_no', 100)->nullable()->after('outgoing_slip_file');
            }
            if (!Schema::hasColumn('transfers', 'receiving_slip_file')) {
                $table->string('receiving_slip_file', 500)->nullable()->after('outgoing_slip_no');
            }
            if (!Schema::hasColumn('transfers', 'receiving_slip_no')) {
                $table->string('receiving_slip_no', 100)->nullable()->after('receiving_slip_file');
            }
            if (!Schema::hasColumn('transfers', 'receiving_notes')) {
                $table->text('receiving_notes')->nullable()->after('receiving_slip_no');
            }
        });
    }

    public function down()
    {
        Schema::table('transfers', function (Blueprint $table) {
            if (Schema::hasColumn('transfers', 'driver_employee_id')) {
                $table->dropForeign(['driver_employee_id']);
                $table->dropColumn('driver_employee_id');
            }
            if (Schema::hasColumn('transfers', 'dispatched_by')) {
                $table->dropForeign(['dispatched_by']);
                $table->dropColumn('dispatched_by');
            }
            $table->dropColumn([
                'vehicle_plate_no',
                'dispatch_notes',
                'dispatched_at',
                'outgoing_slip_file',
                'outgoing_slip_no',
                'receiving_slip_file',
                'receiving_slip_no',
                'receiving_notes',
            ]);
        });
    }
};
