<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('letters')) {
            // 1. Make letter_number nullable for drafts and sent letters before secretary registration
            try {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('ALTER TABLE letters MODIFY letter_number VARCHAR(60) NULL');
                }
            } catch (\Throwable $e) {
                // If native change is supported or driver differs
            }

            Schema::table('letters', function (Blueprint $table) {
                // Category (Leave Letter, Advance Loan Letter, Payment, Government, Bank & Insurance)
                if (!Schema::hasColumn('letters', 'category')) {
                    $table->string('category', 100)->nullable()->after('subject');
                }

                // Handled / Addressed Person selected by Secretary
                if (!Schema::hasColumn('letters', 'addressed_to_user_id')) {
                    $table->foreignId('addressed_to_user_id')->nullable()->constrained('users')->nullOnDelete()->after('category');
                }

                // Reference number lock flag (locked once assigned by secretary)
                if (!Schema::hasColumn('letters', 'is_reference_locked')) {
                    $table->boolean('is_reference_locked')->default(false)->after('letter_number');
                }

                // Sent timestamp (when employee sends draft to secretary)
                if (!Schema::hasColumn('letters', 'sent_at')) {
                    $table->timestamp('sent_at')->nullable()->after('status');
                }

                // Registration tracking (when secretary registers letter)
                if (!Schema::hasColumn('letters', 'registered_by')) {
                    $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete()->after('sent_at');
                }
                if (!Schema::hasColumn('letters', 'registered_at')) {
                    $table->timestamp('registered_at')->nullable()->after('registered_by');
                }
            });

            // Adjust status column to support 'draft', 'sent', 'registered'
            try {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE letters MODIFY status VARCHAR(50) NOT NULL DEFAULT 'draft'");
                }
            } catch (\Throwable $e) {
                // Fallback handled gracefully
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('letters')) {
            Schema::table('letters', function (Blueprint $table) {
                $columns = [
                    'category',
                    'addressed_to_user_id',
                    'is_reference_locked',
                    'sent_at',
                    'registered_by',
                    'registered_at',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('letters', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
