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
        if (Schema::hasTable('material_requests')) {
            Schema::table('material_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('material_requests', 'maintenance_request_id')) {
                    $table->unsignedBigInteger('maintenance_request_id')->nullable()->after('destination_store_id');
                    $table->index('maintenance_request_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('material_requests')) {
            Schema::table('material_requests', function (Blueprint $table) {
                if (Schema::hasColumn('material_requests', 'maintenance_request_id')) {
                    $table->dropColumn('maintenance_request_id');
                }
            });
        }
    }
};
