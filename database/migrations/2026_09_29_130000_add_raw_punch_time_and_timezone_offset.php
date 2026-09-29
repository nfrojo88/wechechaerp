<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. Add raw_punch_time to device_attendance_logs
        if (Schema::hasTable('device_attendance_logs') && !Schema::hasColumn('device_attendance_logs', 'raw_punch_time')) {
            Schema::table('device_attendance_logs', function (Blueprint $table) {
                $table->dateTime('raw_punch_time')->nullable()->after('punch_time');
            });
        }

        // 2. Add timezone_offset_hours to zk_devices
        if (Schema::hasTable('zk_devices') && !Schema::hasColumn('zk_devices', 'timezone_offset_hours')) {
            Schema::table('zk_devices', function (Blueprint $table) {
                $table->integer('timezone_offset_hours')->default(-5)->after('location');
            });
        }

        // 3. Populate default biometric_timezone_offset_hours in system_settings
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'biometric_timezone_offset_hours'],
                [
                    'value' => '-5',
                    'type' => 'integer',
                    'group' => 'attendance',
                    'description' => 'Biometric attendance machine timezone offset in hours (-5 hours converts machine 05:07 PM to local 12:07 PM)',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down()
    {
        if (Schema::hasTable('device_attendance_logs') && Schema::hasColumn('device_attendance_logs', 'raw_punch_time')) {
            Schema::table('device_attendance_logs', function (Blueprint $table) {
                $table->dropColumn('raw_punch_time');
            });
        }

        if (Schema::hasTable('zk_devices') && Schema::hasColumn('zk_devices', 'timezone_offset_hours')) {
            Schema::table('zk_devices', function (Blueprint $table) {
                $table->dropColumn('timezone_offset_hours');
            });
        }
    }
};
