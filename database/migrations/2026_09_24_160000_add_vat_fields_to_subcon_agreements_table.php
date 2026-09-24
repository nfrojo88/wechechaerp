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
                if (!Schema::hasColumn('subcon_agreements', 'base_amount')) {
                    $table->decimal('base_amount', 18, 2)->default(0)->after('contract_value');
                }
                if (!Schema::hasColumn('subcon_agreements', 'vat_type')) {
                    $table->string('vat_type', 30)->default('exclusive')->after('base_amount'); // exclusive, inclusive, none
                }
                if (!Schema::hasColumn('subcon_agreements', 'vat_rate')) {
                    $table->decimal('vat_rate', 5, 2)->default(15.00)->after('vat_type');
                }
                if (!Schema::hasColumn('subcon_agreements', 'vat_amount')) {
                    $table->decimal('vat_amount', 18, 2)->default(0)->after('vat_rate');
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
                $cols = ['vat_amount', 'vat_rate', 'vat_type', 'base_amount'];
                foreach ($cols as $c) {
                    if (Schema::hasColumn('subcon_agreements', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
