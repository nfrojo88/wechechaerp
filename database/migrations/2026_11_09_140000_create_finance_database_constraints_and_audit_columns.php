<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add audit and seed tracking columns
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('journal_entry_lines', 'original_account_id')) {
                $table->unsignedBigInteger('original_account_id')->nullable()->after('account_id');
            }
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('chart_of_accounts', 'is_seed')) {
                $table->boolean('is_seed')->default(false)->after('is_system')->index();
            }
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('journal_entries', 'is_seed')) {
                $table->boolean('is_seed')->default(false)->after('is_reversal')->index();
            }
        });

        // 2. Create Database-Level Trigger Constraints
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // Trigger 1: Prevent updating entry status to 'posted' if lines are unbalanced or < 2
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_journal_entries_prevent_unbalanced_post
                BEFORE UPDATE OF status ON journal_entries
                FOR EACH ROW
                WHEN NEW.status = 'posted' AND (
                    (SELECT COUNT(*) FROM journal_entry_lines WHERE journal_entry_id = NEW.id) < 2
                    OR
                    ABS(
                        (SELECT COALESCE(SUM(CASE WHEN side = 'debit' THEN amount ELSE 0 END), 0) FROM journal_entry_lines WHERE journal_entry_id = NEW.id)
                        -
                        (SELECT COALESCE(SUM(CASE WHEN side = 'credit' THEN amount ELSE 0 END), 0) FROM journal_entry_lines WHERE journal_entry_id = NEW.id)
                    ) > 0.009
                )
                BEGIN
                    SELECT RAISE(ABORT, 'DB Constraint Violation: Cannot post unbalanced journal entry. Total debits must equal total credits.');
                END;
            ");

            // Trigger 2: Prevent direct insertion of 'posted' journal entry without balanced lines
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_journal_entries_prevent_direct_unbalanced_insert
                AFTER INSERT ON journal_entries
                FOR EACH ROW
                WHEN NEW.status = 'posted' AND (
                    (SELECT COUNT(*) FROM journal_entry_lines WHERE journal_entry_id = NEW.id) < 2
                    OR
                    ABS(
                        (SELECT COALESCE(SUM(CASE WHEN side = 'debit' THEN amount ELSE 0 END), 0) FROM journal_entry_lines WHERE journal_entry_id = NEW.id)
                        -
                        (SELECT COALESCE(SUM(CASE WHEN side = 'credit' THEN amount ELSE 0 END), 0) FROM journal_entry_lines WHERE journal_entry_id = NEW.id)
                    ) > 0.009
                )
                BEGIN
                    SELECT RAISE(ABORT, 'DB Constraint Violation: Cannot insert a posted journal entry without balanced lines.');
                END;
            ");

            // Trigger 3: Immutability: Prevent modifying lines of a posted journal entry
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_prevent_posted_lines_update
                BEFORE UPDATE ON journal_entry_lines
                FOR EACH ROW
                WHEN (SELECT status FROM journal_entries WHERE id = OLD.journal_entry_id) = 'posted'
                BEGIN
                    SELECT RAISE(ABORT, 'DB Constraint Violation: Lines of a posted journal entry are immutable and cannot be updated.');
                END;
            ");

            // Trigger 4: Immutability: Prevent deleting lines of a posted journal entry
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_prevent_posted_lines_delete
                BEFORE DELETE ON journal_entry_lines
                FOR EACH ROW
                WHEN (SELECT status FROM journal_entries WHERE id = OLD.journal_entry_id) = 'posted'
                BEGIN
                    SELECT RAISE(ABORT, 'DB Constraint Violation: Lines of a posted journal entry are immutable and cannot be deleted.');
                END;
            ");

            // Trigger 5: Immutability: Prevent inserting lines into an already posted journal entry
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_prevent_posted_lines_insert
                BEFORE INSERT ON journal_entry_lines
                FOR EACH ROW
                WHEN (SELECT status FROM journal_entries WHERE id = NEW.journal_entry_id) = 'posted'
                BEGIN
                    SELECT RAISE(ABORT, 'DB Constraint Violation: Lines cannot be added to an already posted journal entry.');
                END;
            ");

            // Trigger 6: Header BEFORE INSERT validation: cannot insert header as 'posted' directly
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_journal_entries_prevent_direct_posted_insert
                BEFORE INSERT ON journal_entries
                FOR EACH ROW
                WHEN NEW.status = 'posted'
                BEGIN
                    SELECT RAISE(ABORT, 'DB Constraint Violation: Cannot insert journal entry directly as posted.');
                END;
            ");

            // Trigger 7: Header Immutability: Block update of posted journal headers
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_prevent_posted_journal_entries_update
                BEFORE UPDATE ON journal_entries
                FOR EACH ROW
                WHEN OLD.status = 'posted' AND (NEW.entry_no != OLD.entry_no OR NEW.entry_date != OLD.entry_date OR NEW.status = 'draft')
                BEGIN
                    SELECT RAISE(ABORT, 'DB Constraint Violation: Posted journal entries cannot be modified or reverted.');
                END;
            ");

            // Trigger 8: Header Immutability: Block delete of posted journal headers
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_prevent_posted_journal_entries_delete
                BEFORE DELETE ON journal_entries
                FOR EACH ROW
                WHEN OLD.status = 'posted'
                BEGIN
                    SELECT RAISE(ABORT, 'DB Constraint Violation: Posted journal entries are immutable and cannot be deleted.');
                END;
            ");
        } elseif ($driver === 'mysql') {
            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_journal_entries_prevent_unbalanced_post
                BEFORE UPDATE ON journal_entries
                FOR EACH ROW
                BEGIN
                    DECLARE total_debit DECIMAL(18,2);
                    DECLARE total_credit DECIMAL(18,2);
                    DECLARE line_count INT;
                    IF NEW.status = 'posted' THEN
                        SELECT COALESCE(SUM(CASE WHEN side = 'debit' THEN amount ELSE 0 END), 0),
                               COALESCE(SUM(CASE WHEN side = 'credit' THEN amount ELSE 0 END), 0),
                               COUNT(*)
                        INTO total_debit, total_credit, line_count
                        FROM journal_entry_lines
                        WHERE journal_entry_id = NEW.id;

                        IF line_count < 2 OR ABS(total_debit - total_credit) > 0.009 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'DB Constraint Violation: Cannot post unbalanced journal entry. Total debits must equal total credits.';
                        END IF;
                    END IF;
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_journal_entries_prevent_direct_posted_insert
                BEFORE INSERT ON journal_entries
                FOR EACH ROW
                BEGIN
                    IF NEW.status = 'posted' THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'DB Constraint Violation: Cannot insert journal entry directly as posted.';
                    END IF;
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_prevent_posted_journal_entries_update
                BEFORE UPDATE ON journal_entries
                FOR EACH ROW
                BEGIN
                    IF OLD.status = 'posted' THEN
                        IF NEW.entry_no != OLD.entry_no OR NEW.entry_date != OLD.entry_date OR NEW.status = 'draft' THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'DB Constraint Violation: Posted journal entries cannot be modified or reverted.';
                        END IF;
                    END IF;
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_prevent_posted_journal_entries_delete
                BEFORE DELETE ON journal_entries
                FOR EACH ROW
                BEGIN
                    IF OLD.status = 'posted' THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'DB Constraint Violation: Posted journal entries are immutable and cannot be deleted.';
                    END IF;
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_prevent_posted_lines_update
                BEFORE UPDATE ON journal_entry_lines
                FOR EACH ROW
                BEGIN
                    DECLARE parent_status VARCHAR(20);
                    SELECT status INTO parent_status FROM journal_entries WHERE id = OLD.journal_entry_id;
                    IF parent_status = 'posted' THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'DB Constraint Violation: Lines of a posted journal entry are immutable and cannot be updated.';
                    END IF;
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_prevent_posted_lines_delete
                BEFORE DELETE ON journal_entry_lines
                FOR EACH ROW
                BEGIN
                    DECLARE parent_status VARCHAR(20);
                    SELECT status INTO parent_status FROM journal_entries WHERE id = OLD.journal_entry_id;
                    IF parent_status = 'posted' THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'DB Constraint Violation: Lines of a posted journal entry are immutable and cannot be deleted.';
                    END IF;
                END;
            ");

            DB::unprepared("
                CREATE TRIGGER IF NOT EXISTS trg_prevent_posted_lines_insert
                BEFORE INSERT ON journal_entry_lines
                FOR EACH ROW
                BEGIN
                    DECLARE parent_status VARCHAR(20);
                    SELECT status INTO parent_status FROM journal_entries WHERE id = NEW.journal_entry_id;
                    IF parent_status = 'posted' THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'DB Constraint Violation: Lines cannot be added to an already posted journal entry.';
                    END IF;
                END;
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_entries_prevent_unbalanced_post;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_entries_prevent_direct_unbalanced_insert;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_entries_prevent_direct_posted_insert;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_prevent_posted_journal_entries_update;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_prevent_posted_journal_entries_delete;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_prevent_posted_lines_update;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_prevent_posted_lines_delete;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_prevent_posted_lines_insert;");
        } elseif ($driver === 'mysql') {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_entries_prevent_unbalanced_post;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_journal_entries_prevent_direct_posted_insert;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_prevent_posted_journal_entries_update;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_prevent_posted_journal_entries_delete;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_prevent_posted_lines_update;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_prevent_posted_lines_delete;");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_prevent_posted_lines_insert;");
        }

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            if (Schema::hasColumn('journal_entry_lines', 'original_account_id')) {
                $table->dropColumn('original_account_id');
            }
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('chart_of_accounts', 'is_seed')) {
                $table->dropColumn('is_seed');
            }
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            if (Schema::hasColumn('journal_entries', 'is_seed')) {
                $table->dropColumn('is_seed');
            }
        });
    }
};
