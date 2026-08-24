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
            // Second Guarantor & Guarantee Information
            if (!Schema::hasColumn('employees', 'guarantee_letter_2')) {
                $table->string('guarantee_letter_2')->nullable()->after('guarantee_letter');
            }
            if (!Schema::hasColumn('employees', 'guarantor_2_name')) {
                $table->string('guarantor_2_name')->nullable()->after('guarantor_phone');
            }
            if (!Schema::hasColumn('employees', 'guarantor_2_id_number')) {
                $table->string('guarantor_2_id_number')->nullable()->after('guarantor_2_name');
            }
            if (!Schema::hasColumn('employees', 'guarantor_2_id_card')) {
                $table->string('guarantor_2_id_card')->nullable()->after('guarantor_2_id_number');
            }
            if (!Schema::hasColumn('employees', 'guarantor_2_phone')) {
                $table->string('guarantor_2_phone')->nullable()->after('guarantor_2_id_card');
            }

            // Multiple Registration Letters / Contract Pictures
            if (!Schema::hasColumn('employees', 'registration_letters')) {
                $table->text('registration_letters')->nullable()->after('registration_letter');
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
                'guarantee_letter_2',
                'guarantor_2_name',
                'guarantor_2_id_number',
                'guarantor_2_id_card',
                'guarantor_2_phone',
                'registration_letters',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
