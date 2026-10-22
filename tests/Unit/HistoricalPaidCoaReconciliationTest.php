<?php

namespace Tests\Unit;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\ExpenseRequest;
use App\Models\OfficeMaterialRequest;
use App\Models\PettyCashReplenishment;
use App\Models\User;
use App\Services\FinanceCoaReconciliationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HistoricalPaidCoaReconciliationTest extends TestCase
{
    protected ChartOfAccount $cbeCoa;
    protected BankAccount $cbeBank;
    protected ChartOfAccount $cashCoa;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('office_material_requests');
        Schema::dropIfExists('petty_cash_replenishment_items');
        Schema::dropIfExists('petty_cash_replenishments');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->default('password');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('chart_of_accounts', function ($table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->default('asset');
            $table->string('subtype')->nullable();
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bank_accounts', function ($table) {
            $table->id();
            $table->string('account_name');
            $table->string('account_number');
            $table->string('bank_name');
            $table->string('branch')->nullable();
            $table->string('account_type')->default('checking');
            $table->string('currency')->default('ETB');
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expense_requests', function ($table) {
            $table->id();
            $table->string('request_number', 50)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('category', 50)->default('Service');
            $table->string('other_reason')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->string('status', 50)->default('Paid');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->unsignedBigInteger('chart_of_account_id')->nullable();
            $table->boolean('coa_deducted')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('office_material_requests', function ($table) {
            $table->id();
            $table->string('request_no', 50)->unique();
            $table->unsignedBigInteger('requested_by');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 50)->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->boolean('coa_deducted')->default(false);
            $table->string('office_purpose')->nullable();
            $table->timestamps();
        });

        Schema::create('petty_cash_replenishments', function ($table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->unsignedBigInteger('chart_of_account_id')->nullable();
            $table->unsignedBigInteger('source_coa_id')->nullable();
            $table->decimal('requested_amount', 18, 2)->default(0);
            $table->decimal('fulfilled_amount', 18, 2)->default(0);
            $table->string('status')->default('fulfilled');
            $table->timestamp('fulfilled_at')->nullable();
            $table->boolean('coa_deducted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->cbeCoa = ChartOfAccount::create([
            'code'            => '1011',
            'name'            => 'CBE Main Checking Account',
            'type'            => 'asset',
            'opening_balance' => 100000.00,
            'current_balance' => 100000.00,
            'is_active'       => true,
        ]);

        $this->cbeBank = BankAccount::create([
            'account_name'    => 'CBE Main Checking Account',
            'account_number'  => '1000123456789',
            'bank_name'       => 'Commercial Bank of Ethiopia',
            'currency'        => 'ETB',
            'current_balance' => 80000.00, // Bank was already deducted previously
            'coa_id'          => $this->cbeCoa->id,
            'is_active'       => true,
        ]);

        $this->cashCoa = ChartOfAccount::create([
            'code'            => '1010',
            'name'            => 'Petty Cash / Cash on Hand',
            'type'            => 'asset',
            'opening_balance' => 20000.00,
            'current_balance' => 20000.00,
            'is_active'       => true,
        ]);
    }

    public function test_reconciles_and_deducts_historical_paid_expense_requests(): void
    {
        // Simulate historical requests that were paid before the fix (coa_deducted = false)
        ExpenseRequest::create([
            'request_number'      => 'EXP-PAST-001',
            'user_id'             => $this->user->id,
            'amount'              => 5000.00,
            'net_amount'          => 5000.00,
            'status'              => 'Paid',
            'paid_at'             => now()->subDays(5),
            'bank_account_id'     => $this->cbeBank->id,
            'chart_of_account_id' => $this->cbeCoa->id,
            'coa_deducted'        => false,
        ]);

        ExpenseRequest::create([
            'request_number'      => 'EXP-PAST-002',
            'user_id'             => $this->user->id,
            'amount'              => 3500.00,
            'net_amount'          => 3500.00,
            'status'              => 'Paid',
            'paid_at'             => now()->subDays(3),
            'bank_account_id'     => $this->cbeBank->id,
            'chart_of_account_id' => null, // Will resolve via bank_account_id
            'coa_deducted'        => false,
        ]);

        $stats = FinanceCoaReconciliationService::reconcileHistoricalPayments();

        $this->assertEquals(2, $stats['expense_requests_count']);
        $this->assertEquals(8500.00, $stats['expense_requests_amount']);

        // Check that COA balance was decremented from 100,000 down to 91,500
        $this->assertEquals(91500.00, (float)$this->cbeCoa->fresh()->current_balance);

        // Verify coa_deducted was marked true
        $this->assertTrue((bool)ExpenseRequest::where('request_number', 'EXP-PAST-001')->value('coa_deducted'));
        $this->assertTrue((bool)ExpenseRequest::where('request_number', 'EXP-PAST-002')->value('coa_deducted'));

        // Idempotency check: Running again must NOT double-deduct
        $stats2 = FinanceCoaReconciliationService::reconcileHistoricalPayments();
        $this->assertEquals(0, $stats2['expense_requests_count']);
        $this->assertEquals(91500.00, (float)$this->cbeCoa->fresh()->current_balance);
    }

    public function test_reconciles_and_deducts_historical_office_material_requests(): void
    {
        OfficeMaterialRequest::create([
            'request_no'      => 'OFF-PAST-001',
            'requested_by'    => $this->user->id,
            'amount'          => 1200.00,
            'status'          => 'paid',
            'paid_at'         => now()->subDays(2),
            'coa_id'          => $this->cashCoa->id,
            'coa_deducted'    => false,
        ]);

        $stats = FinanceCoaReconciliationService::reconcileHistoricalPayments();

        $this->assertEquals(1, $stats['office_requests_count']);
        $this->assertEquals(1200.00, $stats['office_requests_amount']);
        $this->assertEquals(18800.00, (float)$this->cashCoa->fresh()->current_balance);
        $this->assertTrue((bool)OfficeMaterialRequest::where('request_no', 'OFF-PAST-001')->value('coa_deducted'));
    }

    public function test_reconciles_and_deducts_historical_petty_cash_replenishments(): void
    {
        PettyCashReplenishment::create([
            'request_no'          => 'PCR-PAST-001',
            'chart_of_account_id' => $this->cashCoa->id,
            'source_coa_id'       => $this->cbeCoa->id,
            'requested_amount'    => 10000.00,
            'fulfilled_amount'    => 10000.00,
            'status'              => 'fulfilled',
            'fulfilled_at'        => now()->subDays(4),
            'coa_deducted'        => false,
        ]);

        $stats = FinanceCoaReconciliationService::reconcileHistoricalPayments();

        $this->assertEquals(1, $stats['replenishments_count']);
        $this->assertEquals(10000.00, $stats['replenishments_amount']);
        $this->assertEquals(90000.00, (float)$this->cbeCoa->fresh()->current_balance);
        $this->assertTrue((bool)PettyCashReplenishment::where('request_no', 'PCR-PAST-001')->value('coa_deducted'));
    }

    public function test_auto_links_bank_account_coa_if_unlinked_and_deducts(): void
    {
        // Bank account without coa_id, but with matching bank_name
        $unlinkedBank = BankAccount::create([
            'account_name'    => 'Commercial Bank of Ethiopia Ops',
            'account_number'  => '1000999999999',
            'bank_name'       => 'Commercial Bank of Ethiopia',
            'currency'        => 'ETB',
            'current_balance' => 50000.00,
            'coa_id'          => null, // Unlinked!
            'is_active'       => true,
        ]);

        ExpenseRequest::create([
            'request_number'      => 'EXP-PAST-UNLINKED',
            'user_id'             => $this->user->id,
            'amount'              => 4000.00,
            'net_amount'          => 4000.00,
            'status'              => 'Paid',
            'paid_at'             => now()->subDays(1),
            'bank_account_id'     => $unlinkedBank->id,
            'chart_of_account_id' => null,
            'coa_deducted'        => false,
        ]);

        $stats = FinanceCoaReconciliationService::reconcileHistoricalPayments();

        $this->assertEquals(1, $stats['expense_requests_count']);
        // Verify bank account was automatically linked to the matching COA
        $this->assertEquals($this->cbeCoa->id, $unlinkedBank->fresh()->coa_id);
        // Verify COA balance was decremented
        $this->assertEquals(96000.00, (float)$this->cbeCoa->fresh()->current_balance);
    }
}
