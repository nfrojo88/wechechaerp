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
        if (Schema::hasTable('subcon_agreements')) {
            Schema::table('subcon_agreements', function (Blueprint $table) {
                if (!Schema::hasColumn('subcon_agreements', 'unit_price_per_m2')) {
                    $table->decimal('unit_price_per_m2', 15, 2)->nullable()->after('contract_value');
                }
                if (!Schema::hasColumn('subcon_agreements', 'estimated_total_m2')) {
                    $table->decimal('estimated_total_m2', 15, 2)->nullable()->after('unit_price_per_m2');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('subcon_agreements')) {
            Schema::table('subcon_agreements', function (Blueprint $table) {
                if (Schema::hasColumn('subcon_agreements', 'estimated_total_m2')) {
                    $table->dropColumn('estimated_total_m2');
                }
                if (Schema::hasColumn('subcon_agreements', 'unit_price_per_m2')) {
                    $table->dropColumn('unit_price_per_m2');
                }
            });
        }
    }
};
