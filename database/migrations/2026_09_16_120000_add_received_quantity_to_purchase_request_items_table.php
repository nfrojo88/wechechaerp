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
                if (!Schema::hasColumn('purchase_request_items', 'received_quantity')) {
                    $table->decimal('received_quantity', 15, 3)->default(0)->after('purchased_quantity');
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
                if (Schema::hasColumn('purchase_request_items', 'received_quantity')) {
                    $table->dropColumn('received_quantity');
                }
            });
        }
    }
};