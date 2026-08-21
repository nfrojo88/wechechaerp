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
            if (!Schema::hasColumn('employees', 'national_id_number')) {
                $table->string('national_id_number')->nullable()->after('full_name');
            }
            if (!Schema::hasColumn('employees', 'national_id_card')) {
                $table->string('national_id_card')->nullable()->after('national_id_number');
            }
            if (!Schema::hasColumn('employees', 'asset_handover_document')) {
                $table->string('asset_handover_document')->nullable()->after('device_user_id');
            }
            if (!Schema::hasColumn('employees', 'profile_picture')) {
                $table->string('profile_picture')->nullable()->after('asset_handover_document');
            }
            if (!Schema::hasColumn('employees', 'registration_letter')) {
                $table->string('registration_letter')->nullable()->after('profile_picture');
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
                'national_id_number',
                'national_id_card',
                'asset_handover_document',
                'profile_picture',
                'registration_letter',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
