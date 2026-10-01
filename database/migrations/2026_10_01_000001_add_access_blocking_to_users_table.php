<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add access block fields to users table if not already present
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'access_blocked_at')) {
                $table->timestamp('access_blocked_at')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('users', 'access_block_reason')) {
                $table->text('access_block_reason')->nullable()->after('access_blocked_at');
            }
            if (!Schema::hasColumn('users', 'access_unblocked_by')) {
                $table->unsignedBigInteger('access_unblocked_by')->nullable()->after('access_block_reason');
            }
            if (!Schema::hasColumn('users', 'access_unblocked_at')) {
                $table->timestamp('access_unblocked_at')->nullable()->after('access_unblocked_by');
            }
            if (!Schema::hasColumn('users', 'access_unblock_reason')) {
                $table->text('access_unblock_reason')->nullable()->after('access_unblocked_at');
            }
        });

        // 2. Create user_access_audits table for strict audit logging
        if (!Schema::hasTable('user_access_audits')) {
            Schema::create('user_access_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('employee_id')->nullable()->index();
                $table->string('action', 50); // 'blocked', 'unblocked', 'warning_sent'
                $table->unsignedBigInteger('performed_by')->nullable()->index();
                $table->text('reason')->nullable();
                $table->integer('missed_streak_days')->default(0);
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_access_audits');

        Schema::table('users', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('users', 'access_unblock_reason')) $cols[] = 'access_unblock_reason';
            if (Schema::hasColumn('users', 'access_unblocked_at')) $cols[] = 'access_unblocked_at';
            if (Schema::hasColumn('users', 'access_unblocked_by')) $cols[] = 'access_unblocked_by';
            if (Schema::hasColumn('users', 'access_block_reason')) $cols[] = 'access_block_reason';
            if (Schema::hasColumn('users', 'access_blocked_at')) $cols[] = 'access_blocked_at';
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
