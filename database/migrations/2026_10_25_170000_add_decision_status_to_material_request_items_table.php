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
        if (Schema::hasTable('material_request_items')) {
            Schema::table('material_request_items', function (Blueprint $table) {
                if (!Schema::hasColumn('material_request_items', 'status')) {
                    $table->string('status', 30)->default('pending')->after('quantity_fulfilled')->index();
                }
                if (!Schema::hasColumn('material_request_items', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('notes');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('material_request_items')) {
            Schema::table('material_request_items', function (Blueprint $table) {
                if (Schema::hasColumn('material_request_items', 'rejection_reason')) {
                    $table->dropColumn('rejection_reason');
                }
                if (Schema::hasColumn('material_request_items', 'status')) {
                    $table->dropColumn('status');
                }
            });
        }
    }
};
