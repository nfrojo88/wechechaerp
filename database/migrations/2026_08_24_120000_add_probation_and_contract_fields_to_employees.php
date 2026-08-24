<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'contract_end_date')) {
                $table->date('contract_end_date')->nullable()->after('employment_type');
            }
            if (!Schema::hasColumn('employees', 'tin_number')) {
                $table->string('tin_number', 50)->nullable()->after('national_id_number');
            }
            if (!Schema::hasColumn('employees', 'probation_ends_at')) {
                $table->date('probation_ends_at')->nullable()->after('date_of_joining');
            }
            if (!Schema::hasColumn('employees', 'probation_completed')) {
                $table->boolean('probation_completed')->default(false)->after('probation_ends_at');
            }
            if (!Schema::hasColumn('employees', 'lock_reason')) {
                $table->string('lock_reason')->nullable()->after('status');
            }
        });
    }

    public function down()
    {
        Schema::table('employees', function (Blueprint $table) {
            $columns = ['contract_end_date', 'tin_number', 'probation_ends_at', 'probation_completed', 'lock_reason'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
