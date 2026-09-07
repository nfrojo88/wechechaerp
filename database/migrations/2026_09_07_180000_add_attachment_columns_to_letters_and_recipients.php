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
        if (Schema::hasTable('letters')) {
            Schema::table('letters', function (Blueprint $table) {
                if (!Schema::hasColumn('letters', 'resolution_attachment_path')) {
                    $table->string('resolution_attachment_path', 500)->nullable()->after('closing_notes');
                }
            });
        }

        if (Schema::hasTable('letter_recipients')) {
            Schema::table('letter_recipients', function (Blueprint $table) {
                if (!Schema::hasColumn('letter_recipients', 'attachment_path')) {
                    $table->string('attachment_path', 500)->nullable()->after('notes');
                }
                if (!Schema::hasColumn('letter_recipients', 'attachment_name')) {
                    $table->string('attachment_name', 255)->nullable()->after('attachment_path');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('letters')) {
            Schema::table('letters', function (Blueprint $table) {
                if (Schema::hasColumn('letters', 'resolution_attachment_path')) {
                    $table->dropColumn('resolution_attachment_path');
                }
            });
        }

        if (Schema::hasTable('letter_recipients')) {
            Schema::table('letter_recipients', function (Blueprint $table) {
                $columns = ['attachment_path', 'attachment_name'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('letter_recipients', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
