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
                if (!Schema::hasColumn('purchase_request_items', 'unaccepted_quantity')) {
                    $table->decimal('unaccepted_quantity', 15, 3)->default(0)->after('received_quantity');
                }
                if (!Schema::hasColumn('purchase_request_items', 'is_fulfillment_closed')) {
                    $table->boolean('is_fulfillment_closed')->default(false)->after('unaccepted_quantity');
                }
                if (!Schema::hasColumn('purchase_request_items', 'fulfillment_closed_at')) {
                    $table->timestamp('fulfillment_closed_at')->nullable()->after('is_fulfillment_closed');
                }
                if (!Schema::hasColumn('purchase_request_items', 'fulfillment_closed_by')) {
                    $table->unsignedBigInteger('fulfillment_closed_by')->nullable()->after('fulfillment_closed_at');
                }
                if (!Schema::hasColumn('purchase_request_items', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('fulfillment_closed_by');
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
                $columns = ['unaccepted_quantity', 'is_fulfillment_closed', 'fulfillment_closed_at', 'fulfillment_closed_by', 'rejection_reason'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('purchase_request_items', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
