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
        if (Schema::hasTable('purchase_request_items')) {
            Schema::table('purchase_request_items', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_request_items', 'purchased_quantity')) {
                    $table->decimal('purchased_quantity', 15, 3)->default(0)->after('quantity');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_request_items')) {
            Schema::table('purchase_request_items', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_request_items', 'purchased_quantity')) {
                    $table->dropColumn('purchased_quantity');
                }
            });
        }
    }
};