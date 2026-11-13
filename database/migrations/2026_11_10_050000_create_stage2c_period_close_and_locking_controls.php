<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Establishes Stage 2C Period Close Checklist, Period 13 Controls,
     * and Database Triggers for Hard Financial Period Locking.
     */
    public function up(): void
    {
        // 1. Extend financial_periods with Stage 2C columns if not already present
        Schema::table('financial_periods', function (Blueprint $table) {
            if (!Schema::hasColumn('financial_periods', 'fiscal_year')) {
                $table->string('fiscal_year', 20)->default('2026')->after('end_date');
            }
            if (!Schema::hasColumn('financial_periods', 'status')) {
                $table->string('status', 30)->default('open')->after('code'); // 'open', 'closing_in_progress', 'closed', 'locked'
            }
            if (!Schema::hasColumn('financial_periods', 'is_period_13')) {
                $table->boolean('is_period_13')->default(false)->after('is_locked');
            }
            if (!Schema::hasColumn('financial_periods', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('is_period_13');
            }
            if (!Schema::hasColumn('financial_periods', 'closed_by')) {
                $table->unsignedBigInteger('closed_by')->nullable()->after('closed_at');
            }
            if (!Schema::hasColumn('financial_periods', 'locked_at')) {
                $table->timestamp('locked_at')->nullable();
            }
            if (!Schema::hasColumn('financial_periods', 'locked_by')) {
                $table->unsignedBigInteger('locked_by')->nullable();
            }
            if (!Schema::hasColumn('financial_periods', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable();
            }
            if (!Schema::hasColumn('financial_periods', 'reopened_by')) {
                $table->unsignedBigInteger('reopened_by')->nullable();
            }
            if (!Schema::hasColumn('financial_periods', 'reopen_reason')) {
                $table->text('reopen_reason')->nullable();
            }
            if (!Schema::hasColumn('financial_periods', 'tb_balanced_at_close')) {
                $table->boolean('tb_balanced_at_close')->default(false);
            }
            if (!Schema::hasColumn('financial_periods', 'tb_total_debit')) {
                $table->decimal('tb_total_debit', 18, 2)->nullable();
            }
            if (!Schema::hasColumn('financial_periods', 'tb_total_credit')) {
                $table->decimal('tb_total_credit', 18, 2)->nullable();
            }
            if (!Schema::hasColumn('financial_periods', 'tb_difference')) {
                $table->decimal('tb_difference', 18, 2)->default(0);
            }
            if (!Schema::hasColumn('financial_periods', 'closing_notes')) {
                $table->text('closing_notes')->nullable();
            }
        });

        // 2. Create period_closing_checklists table
        if (!Schema::hasTable('period_closing_checklists')) {
            Schema::create('period_closing_checklists', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('financial_period_id');
                $table->string('item_key', 60); // e.g. 'bank_reconciliation', 'tb_integrity'
                $table->string('item_name', 150);
                $table->enum('status', ['pending', 'passed', 'failed', 'waived'])->default('pending');
                $table->timestamp('verified_at')->nullable();
                $table->unsignedBigInteger('verified_by')->nullable();
                $table->json('details')->nullable();
                $table->text('waiver_reason')->nullable();
                $table->timestamps();

                $table->foreign('financial_period_id')->references('id')->on('financial_periods')->cascadeOnDelete();
                $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
                $table->unique(['financial_period_id', 'item_key']);
            });
        }

        // 3. MySQL Hard Locking Database Triggers
        if (DB::getDriverName() === 'mysql') {
            // Trigger A: Block INSERT of journal entries into locked periods
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_insert_journal_on_locked_period;");
            DB::unprepared("
                CREATE TRIGGER trg_block_insert_journal_on_locked_period
                BEFORE INSERT ON journal_entries
                FOR EACH ROW
                BEGIN
                    DECLARE locked_count INT DEFAULT 0;
                    DECLARE locked_period_code VARCHAR(50);

                    IF @period_lock_override IS NULL OR @period_lock_override != 1 THEN
                        SELECT COUNT(*), MAX(code) INTO locked_count, locked_period_code
                        FROM financial_periods
                        WHERE is_locked = 1
                          AND NEW.entry_date BETWEEN start_date AND end_date;

                        IF locked_count > 0 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'DB Hard Lock: Cannot create journal entry in locked financial period.';
                        END IF;
                    END IF;
                END;
            ");

            // Trigger B: Block UPDATE of journal entries in or into locked periods
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_update_journal_on_locked_period;");
            DB::unprepared("
                CREATE TRIGGER trg_block_update_journal_on_locked_period
                BEFORE UPDATE ON journal_entries
                FOR EACH ROW
                BEGIN
                    DECLARE locked_count INT DEFAULT 0;

                    IF @period_lock_override IS NULL OR @period_lock_override != 1 THEN
                        -- Check if current entry or modified entry falls in a locked period
                        SELECT COUNT(*) INTO locked_count
                        FROM financial_periods
                        WHERE is_locked = 1
                          AND (OLD.entry_date BETWEEN start_date AND end_date
                               OR NEW.entry_date BETWEEN start_date AND end_date);

                        IF locked_count > 0 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'DB Hard Lock: Cannot update journal entry in locked financial period.';
                        END IF;
                    END IF;
                END;
            ");

            // Trigger C: Block DELETE of journal entries in locked periods
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_delete_journal_on_locked_period;");
            DB::unprepared("
                CREATE TRIGGER trg_block_delete_journal_on_locked_period
                BEFORE DELETE ON journal_entries
                FOR EACH ROW
                BEGIN
                    DECLARE locked_count INT DEFAULT 0;

                    IF @period_lock_override IS NULL OR @period_lock_override != 1 THEN
                        SELECT COUNT(*) INTO locked_count
                        FROM financial_periods
                        WHERE is_locked = 1
                          AND OLD.entry_date BETWEEN start_date AND end_date;

                        IF locked_count > 0 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'DB Hard Lock: Cannot delete journal entry in locked financial period.';
                        END IF;
                    END IF;
                END;
            ");

            // Trigger D: Block INSERT of lines belonging to a locked period journal
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_insert_lines_on_locked_period;");
            DB::unprepared("
                CREATE TRIGGER trg_block_insert_lines_on_locked_period
                BEFORE INSERT ON journal_entry_lines
                FOR EACH ROW
                BEGIN
                    DECLARE parent_date DATE;
                    DECLARE locked_count INT DEFAULT 0;

                    IF @period_lock_override IS NULL OR @period_lock_override != 1 THEN
                        SELECT entry_date INTO parent_date
                        FROM journal_entries
                        WHERE id = NEW.journal_entry_id;

                        IF parent_date IS NOT NULL THEN
                            SELECT COUNT(*) INTO locked_count
                            FROM financial_periods
                            WHERE is_locked = 1
                              AND parent_date BETWEEN start_date AND end_date;

                            IF locked_count > 0 THEN
                                SIGNAL SQLSTATE '45000'
                                SET MESSAGE_TEXT = 'DB Hard Lock: Cannot insert lines into locked financial period.';
                            END IF;
                        END IF;
                    END IF;
                END;
            ");

            // Trigger E: Block UPDATE of lines belonging to a locked period journal
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_update_lines_on_locked_period;");
            DB::unprepared("
                CREATE TRIGGER trg_block_update_lines_on_locked_period
                BEFORE UPDATE ON journal_entry_lines
                FOR EACH ROW
                BEGIN
                    DECLARE parent_date DATE;
                    DECLARE locked_count INT DEFAULT 0;

                    IF @period_lock_override IS NULL OR @period_lock_override != 1 THEN
                        SELECT entry_date INTO parent_date
                        FROM journal_entries
                        WHERE id = OLD.journal_entry_id;

                        IF parent_date IS NOT NULL THEN
                            SELECT COUNT(*) INTO locked_count
                            FROM financial_periods
                            WHERE is_locked = 1
                              AND parent_date BETWEEN start_date AND end_date;

                            IF locked_count > 0 THEN
                                SIGNAL SQLSTATE '45000'
                                SET MESSAGE_TEXT = 'DB Hard Lock: Cannot update lines in locked financial period.';
                            END IF;
                        END IF;
                    END IF;
                END;
            ");

            // Trigger F: Block DELETE of lines belonging to a locked period journal
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_delete_lines_on_locked_period;");
            DB::unprepared("
                CREATE TRIGGER trg_block_delete_lines_on_locked_period
                BEFORE DELETE ON journal_entry_lines
                FOR EACH ROW
                BEGIN
                    DECLARE parent_date DATE;
                    DECLARE locked_count INT DEFAULT 0;

                    IF @period_lock_override IS NULL OR @period_lock_override != 1 THEN
                        SELECT entry_date INTO parent_date
                        FROM journal_entries
                        WHERE id = OLD.journal_entry_id;

                        IF parent_date IS NOT NULL THEN
                            SELECT COUNT(*) INTO locked_count
                            FROM financial_periods
                            WHERE is_locked = 1
                              AND parent_date BETWEEN start_date AND end_date;

                            IF locked_count > 0 THEN
                                SIGNAL SQLSTATE '45000'
                                SET MESSAGE_TEXT = 'DB Hard Lock: Cannot delete lines from locked financial period.';
                            END IF;
                        END IF;
                    END IF;
                END;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_delete_lines_on_locked_period;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_update_lines_on_locked_period;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_insert_lines_on_locked_period;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_delete_journal_on_locked_period;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_update_journal_on_locked_period;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_block_insert_journal_on_locked_period;");
        }

        Schema::dropIfExists('period_closing_checklists');

        Schema::table('financial_periods', function (Blueprint $table) {
            $table->dropColumn(['fiscal_year', 'status', 'is_period_13', 'closed_at', 'closed_by']);
        });
    }
};
