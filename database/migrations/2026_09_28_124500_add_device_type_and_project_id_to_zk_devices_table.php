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
        if (Schema::hasTable('zk_devices')) {
            Schema::table('zk_devices', function (Blueprint $table) {
                if (!Schema::hasColumn('zk_devices', 'device_type')) {
                    $table->string('device_type', 30)->default('head_office')->after('name'); // 'head_office' or 'site'
                }
                if (!Schema::hasColumn('zk_devices', 'project_id')) {
                    $table->foreignId('project_id')->nullable()->after('device_type')->constrained('projects')->nullOnDelete();
                }
                if (!Schema::hasColumn('zk_devices', 'ip_address')) {
                    $table->string('ip_address', 50)->nullable()->after('location');
                }
                if (!Schema::hasColumn('zk_devices', 'port')) {
                    $table->integer('port')->nullable()->default(80)->after('ip_address');
                }
                if (!Schema::hasColumn('zk_devices', 'model_name')) {
                    $table->string('model_name', 100)->nullable()->after('name');
                }
                if (!Schema::hasColumn('zk_devices', 'notes')) {
                    $table->text('notes')->nullable()->after('is_active');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('zk_devices')) {
            Schema::table('zk_devices', function (Blueprint $table) {
                $cols = ['notes', 'model_name', 'port', 'ip_address', 'project_id', 'device_type'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('zk_devices', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
