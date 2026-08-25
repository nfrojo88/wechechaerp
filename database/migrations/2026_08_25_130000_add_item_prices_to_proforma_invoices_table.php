<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddItemPricesToProformaInvoicesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('proforma_invoices') && !Schema::hasColumn('proforma_invoices', 'item_prices')) {
            Schema::table('proforma_invoices', function (Blueprint $table) {
                $table->json('item_prices')->nullable()->after('grand_total');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('proforma_invoices') && Schema::hasColumn('proforma_invoices', 'item_prices')) {
            Schema::table('proforma_invoices', function (Blueprint $table) {
                $table->dropColumn('item_prices');
            });
        }
    }
}
