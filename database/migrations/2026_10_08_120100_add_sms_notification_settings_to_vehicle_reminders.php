<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicle_reminders')) {
            Schema::table('vehicle_reminders', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicle_reminders', 'notify_general_service')) {
                    $table->boolean('notify_general_service')->default(true)->after('alert_days_before');
                }
                if (!Schema::hasColumn('vehicle_reminders', 'notify_gm')) {
                    $table->boolean('notify_gm')->default(true)->after('notify_general_service');
                }
                if (!Schema::hasColumn('vehicle_reminders', 'send_sms')) {
                    $table->boolean('send_sms')->default(true)->after('notify_gm');
                }
                if (!Schema::hasColumn('vehicle_reminders', 'custom_sms_phone')) {
                    $table->string('custom_sms_phone', 100)->nullable()->after('send_sms');
                }
            });
        }

        if (Schema::hasTable('vehicle_reminder_notifications')) {
            Schema::table('vehicle_reminder_notifications', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicle_reminder_notifications', 'recipient_phone')) {
                    $table->string('recipient_phone', 50)->nullable()->after('alert_type');
                }
                if (!Schema::hasColumn('vehicle_reminder_notifications', 'recipient_role')) {
                    $table->string('recipient_role', 50)->nullable()->after('recipient_phone');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vehicle_reminders')) {
            Schema::table('vehicle_reminders', function (Blueprint $table) {
                $columns = ['notify_general_service', 'notify_gm', 'send_sms', 'custom_sms_phone'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('vehicle_reminders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('vehicle_reminder_notifications')) {
            Schema::table('vehicle_reminder_notifications', function (Blueprint $table) {
                $columns = ['recipient_phone', 'recipient_role'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('vehicle_reminder_notifications', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
