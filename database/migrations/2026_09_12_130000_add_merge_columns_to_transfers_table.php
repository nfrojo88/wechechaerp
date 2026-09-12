<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMergeColumnsToTransfersTable extends Migration
{
    public function up()
    {
        Schema::table('transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('transfers', 'merged_into_transfer_id')) {
                $table->foreignId('merged_into_transfer_id')->nullable()->after('material_request_id')->constrained('transfers')->nullOnDelete();
            }
            if (!Schema::hasColumn('transfers', 'merge_notes')) {
                $table->text('merge_notes')->nullable()->after('merged_into_transfer_id');
            }
        });
    }

    public function down()
    {
        Schema::table('transfers', function (Blueprint $table) {
            if (Schema::hasColumn('transfers', 'merged_into_transfer_id')) {
                $table->dropForeign(['merged_into_transfer_id']);
                $table->dropColumn('merged_into_transfer_id');
            }
            if (Schema::hasColumn('transfers', 'merge_notes')) {
                $table->dropColumn('merge_notes');
            }
        });
    }
}
