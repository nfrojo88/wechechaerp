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
        if (Schema::hasTable('inventory_movements')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_movements', 'balance_before')) {
                    $table->decimal('balance_before', 15, 3)->nullable()->after('quantity');
                }
                if (!Schema::hasColumn('inventory_movements', 'balance_after')) {
                    $table->decimal('balance_after', 15, 3)->nullable()->after('balance_before');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('inventory_movements')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                if (Schema::hasColumn('inventory_movements', 'balance_after')) {
                    $table->dropColumn('balance_after');
                }
                if (Schema::hasColumn('inventory_movements', 'balance_before')) {
                    $table->dropColumn('balance_before');
                }
            });
        }
    }
};
