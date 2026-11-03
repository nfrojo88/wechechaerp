<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMergeColumnsToRequisitionTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('purchase_requests')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_requests', 'merged_into_pr_id')) {
                    $table->foreignId('merged_into_pr_id')->nullable()->after('status')->constrained('purchase_requests')->nullOnDelete();
                }
                if (!Schema::hasColumn('purchase_requests', 'merge_notes')) {
                    $table->text('merge_notes')->nullable()->after('merged_into_pr_id');
                }
            });
        }

        if (Schema::hasTable('material_requests')) {
            Schema::table('material_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('material_requests', 'merged_into_mr_id')) {
                    $table->foreignId('merged_into_mr_id')->nullable()->after('status')->constrained('material_requests')->nullOnDelete();
                }
                if (!Schema::hasColumn('material_requests', 'merge_notes')) {
                    $table->text('merge_notes')->nullable()->after('merged_into_mr_id');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('purchase_requests')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_requests', 'merge_notes')) {
                    $table->dropColumn('merge_notes');
                }
            });
        }

        if (Schema::hasTable('material_requests')) {
            Schema::table('material_requests', function (Blueprint $table) {
                if (Schema::hasColumn('material_requests', 'merged_into_mr_id')) {
                    $table->dropForeign(['merged_into_mr_id']);
                    $table->dropColumn('merged_into_mr_id');
                }
                if (Schema::hasColumn('material_requests', 'merge_notes')) {
                    $table->dropColumn('merge_notes');
                }
            });
        }
    }
}
