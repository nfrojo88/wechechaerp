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
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'guarantor_name')) {
                $table->string('guarantor_name')->nullable()->after('guarantee_letter');
            }
            if (!Schema::hasColumn('employees', 'guarantor_id_number')) {
                $table->string('guarantor_id_number')->nullable()->after('guarantor_name');
            }
            if (!Schema::hasColumn('employees', 'guarantor_id_card')) {
                $table->string('guarantor_id_card')->nullable()->after('guarantor_id_number');
            }
            if (!Schema::hasColumn('employees', 'guarantor_phone')) {
                $table->string('guarantor_phone')->nullable()->after('guarantor_id_card');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $columns = [
                'guarantor_name',
                'guarantor_id_number',
                'guarantor_id_card',
                'guarantor_phone',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
