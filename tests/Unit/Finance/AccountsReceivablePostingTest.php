<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Finance\AccountsReceivableGlPostingService;
use App\Services\Finance\JournalEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountsReceivablePostingTest extends TestCase
{
    protected AccountsReceivableGlPostingService $arService;
    protected JournalEngine $journalEngine;
    protected User $user;

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

        $this->user = User::create(['name' => 'AR Officer', 'email' => 'ar@wechecha.com']);
        $this->arService = new AccountsReceivableGlPostingService();
        $this->journalEngine = new JournalEngine();
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
        $requiredTables = [
            'users', 'chart_of_accounts', 'journal_entries',
            'journal_entry_lines', 'financial_periods', 'financial_audit_trails'
        ];

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

        Schema::dropIfExists('financial_audit_trails');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('finance_officer');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('chart_of_accounts', function ($table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->string('subtype')->nullable();
            $table->string('normal_side')->default('debit');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('journal_entries', function ($table) {
            $table->id();
            $table->string('entry_no', 50)->unique();
            $table->date('entry_date');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedBigInteger('reopened_by')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->boolean('is_reversal')->default(false);
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->unsignedBigInteger('reversed_by_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_entry_lines', function ($table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->string('side', 10);
            $table->decimal('amount', 18, 2);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('party_type', 50)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_periods', function ($table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('fiscal_year', 20)->default('2026');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
        });

        Schema::create('financial_audit_trails', function ($table) {
            $table->id();
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->string('action', 50);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_role', 50)->nullable();
            $table->text('reason')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function test_rental_invoice_posts_gross_ar_base_income_and_output_vat()
    {
        $arAcc  = ChartOfAccount::create(['code' => '1200', 'name' => 'Trade Receivables', 'type' => 'asset', 'normal_side' => 'debit']);
        $revAcc = ChartOfAccount::create(['code' => '4200', 'name' => 'Machine Rent Income', 'type' => 'revenue', 'normal_side' => 'credit']);
        $vatAcc = ChartOfAccount::create(['code' => '2300', 'name' => 'VAT Payable', 'type' => 'liability', 'normal_side' => 'credit']);

        // Rental Invoice: Base ETB 100,000 + 15% VAT ETB 15,000 = Gross ETB 115,000
        $je = $this->arService->postRentalInvoice('INV-2026-001', 100000.00, 15.00, 42, 10, $this->user->id, '2026-10-10');

        $this->assertEquals('posted', $je->status);
        $lines = $je->lines;
        $this->assertCount(3, $lines);

        $debitLine = $lines->firstWhere('side', 'debit');
        $this->assertEquals($arAcc->id, $debitLine->account_id);
        $this->assertEquals(115000.00, $debitLine->amount);
        $this->assertEquals('client', $debitLine->party_type);
        $this->assertEquals(42, $debitLine->party_id);

        $incomeLine = $lines->where('side', 'credit')->firstWhere('account_id', $revAcc->id);
        $this->assertEquals(100000.00, $incomeLine->amount);

        $vatLine = $lines->where('side', 'credit')->firstWhere('account_id', $vatAcc->id);
        $this->assertEquals(150000.00 / 10, $vatLine->amount); // 15,000
    }

    public function test_customer_receipt_deducts_wht_on_pre_vat_base_and_clears_ar_in_full()
    {
        $arAcc   = ChartOfAccount::create(['code' => '1200', 'name' => 'Trade Receivables', 'type' => 'asset', 'normal_side' => 'debit']);
        $bankAcc = ChartOfAccount::create(['code' => '1011', 'name' => 'CBE Checking', 'type' => 'asset', 'normal_side' => 'debit']);
        $whtAcc  = ChartOfAccount::create(['code' => '1300', 'name' => 'Withholding Tax Receivable', 'type' => 'asset', 'normal_side' => 'debit']);

        // Invoice was Gross ETB 115,000 (Base ETB 100,000 + VAT ETB 15,000)
        // Client withholds 2% on BASE amount (2% of 100,000 = ETB 2,000)
        // Client remits net wire: 115,000 - 2,000 = ETB 113,000
        $je = $this->arService->postCustomerReceipt(
            'INV-2026-001',
            115000.00,
            100000.00,
            $bankAcc->id,
            42,
            2.00,
            'WHT-CERT-99881',
            $this->user->id,
            '2026-10-25'
        );

        $this->assertEquals('posted', $je->status);
        $lines = $je->lines;
        $this->assertCount(3, $lines);

        // Bank debited: 113,000
        $bankLine = $lines->where('side', 'debit')->firstWhere('account_id', $bankAcc->id);
        $this->assertEquals(113000.00, $bankLine->amount);

        // WHT Receivable debited: 2,000
        $whtLine = $lines->where('side', 'debit')->firstWhere('account_id', $whtAcc->id);
        $this->assertEquals(2000.00, $whtLine->amount);

        // AR credited: 115,000 (Clearing full invoice)
        $arLine = $lines->firstWhere('side', 'credit');
        $this->assertEquals($arAcc->id, $arLine->account_id);
        $this->assertEquals(115000.00, $arLine->amount);
    }
}
