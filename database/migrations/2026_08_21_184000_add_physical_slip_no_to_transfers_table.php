<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPhysicalSlipNoToTransfersTable extends Migration
{
    public function up()
    {
        Schema::table('transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('transfers', 'physical_slip_no')) {
                $table->string('physical_slip_no', 100)->nullable()->after('transfer_no');
            }
        });
    }

    public function down()
    {
        Schema::table('transfers', function (Blueprint $table) {
            if (Schema::hasColumn('transfers', 'physical_slip_no')) {
                $table->dropColumn('physical_slip_no');
            }
        });
    }
}
