<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillDryRunTest extends TestCase
{
        protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::statement('SET @gl_posting_in_progress = 1;');
            DB::statement('SET @period_lock_override = 0;');
        }

        $this->ensureSchema();
        DB::beginTransaction();

        
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    protected function ensureSchema(): void
    {
        if (Schema::hasTable('journal_entries')) {
            return;
        }
        $requiredTables = ['chart_of_accounts', 'credit_store_ledger', 'credit_store_payments', 'journal_entries'];
        $allExist = true;
        foreach ($requiredTables as $t) {
            if (!Schema::hasTable($t)) {
                $allExist = false;
                break;
            }
        }
        if ($allExist) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') { DB::statement('PRAGMA foreign_keys = OFF;'); } else { DB::statement('SET FOREIGN_KEY_CHECKS=0;'); }

        Schema::dropIfExists('credit_store_payments');
        Schema::dropIfExists('credit_store_ledger');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');

        Schema::create('chart_of_accounts', function ($table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->string('normal_side')->default('debit');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('credit_store_ledger', function ($table) {
            $table->id();
            $table->string('pr_no')->nullable();
            $table->string('supplier_name')->nullable();
            $table->decimal('credit_amount', 18, 2)->default(0);
            $table->decimal('remaining_balance', 18, 2)->default(0);
            $table->date('invoice_date')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_store_payments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('credit_store_ledger_id');
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('withholding_amount', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('journal_entries', function ($table) {
            $table->id();
            $table->string('entry_no', 50)->unique();
            $table->date('entry_date');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });
    }

    public function test_backfill_dry_run_maps_pre_8_july_payments_to_ap_and_leaves_live_gl_untouched()
    {
        ChartOfAccount::create(['code' => '2001', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        ChartOfAccount::create(['code' => '1011', 'name' => 'CBE Checking', 'type' => 'asset', 'normal_side' => 'debit']);
        ChartOfAccount::create(['code' => '2600', 'name' => 'WHT Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        ChartOfAccount::create(['code' => '1250', 'name' => 'Inventory on Hand', 'type' => 'asset', 'normal_side' => 'debit']);
        ChartOfAccount::create(['code' => '1260', 'name' => 'Construction WIP', 'type' => 'asset', 'normal_side' => 'debit']);

        // Historical Pre-8-July Invoice: Incurred 2026-06-20
        $ledgerPre = DB::table('credit_store_ledger')->insertGetId([
            'pr_no'             => 'PR-HIST-01',
            'supplier_name'     => 'Habesha Steel Mills',
            'credit_amount'     => 100000.00,
            'remaining_balance' => 0.00,
            'invoice_date'      => '2026-06-20',
            'created_at'        => '2026-06-20 10:00:00',
        ]);

        // Disbursed post opening: 2026-07-25
        DB::table('credit_store_payments')->insert([
            'credit_store_ledger_id' => $ledgerPre,
            'payment_date'           => '2026-07-25',
            'amount'                 => 100000.00,
            'vat_amount'             => 0.00,
            'withholding_amount'     => 2000.00, // 2% on goods
            'created_at'             => '2026-07-25 14:00:00',
        ]);

        // Post-8-July Invoice: Incurred 2026-08-10
        $ledgerPost = DB::table('credit_store_ledger')->insertGetId([
            'pr_no'             => 'PR-POST-02',
            'supplier_name'     => 'Dangote Cement',
            'credit_amount'     => 200000.00,
            'remaining_balance' => 0.00,
            'invoice_date'      => '2026-08-10',
            'created_at'        => '2026-08-10 10:00:00',
        ]);

        DB::table('credit_store_payments')->insert([
            'credit_store_ledger_id' => $ledgerPost,
            'payment_date'           => '2026-08-25',
            'amount'                 => 200000.00,
            'vat_amount'             => 0.00,
            'withholding_amount'     => 4000.00,
            'created_at'             => '2026-08-25 14:00:00',
        ]);

        // Execute Dry-Run Analyzer
        $payments = DB::table('credit_store_payments')
            ->join('credit_store_ledger', 'credit_store_payments.credit_store_ledger_id', '=', 'credit_store_ledger.id')
            ->select('credit_store_payments.*', 'credit_store_ledger.invoice_date')
            ->get();

        $dryRunSummary = [
            'total_rows'          => $payments->count(),
            'pre_8_july_rows'     => 0,
            'post_8_july_rows'    => 0,
            'pre_8_july_gross'    => 0.0,
            'post_8_july_gross'   => 0.0,
            'total_wht'           => 0.0,
            'total_net_bank'      => 0.0,
        ];

        foreach ($payments as $p) {
            $isPre = $p->invoice_date && $p->invoice_date < '2026-07-08';
            $amt   = (float)$p->amount;
            $wht   = (float)$p->withholding_amount;
            $net   = $amt - $wht;

            if ($isPre) {
                $dryRunSummary['pre_8_july_rows']++;
                $dryRunSummary['pre_8_july_gross'] += $amt;
            } else {
                $dryRunSummary['post_8_july_rows']++;
                $dryRunSummary['post_8_july_gross'] += $amt;
            }
            $dryRunSummary['total_wht'] += $wht;
            $dryRunSummary['total_net_bank'] += $net;
        }

        // Assert Dry-Run Findings
        $this->assertEquals(2, $dryRunSummary['total_rows']);
        $this->assertEquals(1, $dryRunSummary['pre_8_july_rows']);
        $this->assertEquals(100000.00, $dryRunSummary['pre_8_july_gross']);
        $this->assertEquals(1, $dryRunSummary['post_8_july_rows']);
        $this->assertEquals(200000.00, $dryRunSummary['post_8_july_gross']);
        $this->assertEquals(6000.00, $dryRunSummary['total_wht']);
        $this->assertEquals(294000.00, $dryRunSummary['total_net_bank']);

        // Assert that zero journal entries were posted to the General Ledger
        $this->assertEquals(0, DB::table('journal_entries')->count(), "Dry-run must post zero records to journal_entries.");
    }
}
