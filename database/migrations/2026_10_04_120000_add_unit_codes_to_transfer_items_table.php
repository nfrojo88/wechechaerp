<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transfer_items') && !Schema::hasColumn('transfer_items', 'unit_codes')) {
            Schema::table('transfer_items', function (Blueprint $table) {
                $table->text('unit_codes')->nullable()->after('unit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transfer_items') && Schema::hasColumn('transfer_items', 'unit_codes')) {
            Schema::table('transfer_items', function (Blueprint $table) {
                $table->dropColumn('unit_codes');
            });
        }
    }
};
