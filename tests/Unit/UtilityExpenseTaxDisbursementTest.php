<?php

namespace Tests\Unit;

use App\Http\Controllers\Finance\UtilityExpenseController;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Models\UtilityExpense;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UtilityExpenseTaxDisbursementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        // Schema setup
        Schema::dropIfExists('utility_expenses');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->default('secret');
            $table->timestamps();
        });

        Schema::create('chart_of_accounts', function ($table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('type')->default('expense');
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bank_accounts', function ($table) {
            $table->id();
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->string('bank_name');
            $table->string('account_number');
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        UtilityExpenseController::ensureTableExists();
    }

    public function test_utility_expense_tax_accessors_and_calculations()
    {
        // 1. Default (No VAT, No Withholding)
        $exp1 = new UtilityExpense([
            'amount' => 1000.00,
        ]);
        $this->assertEquals(0.0, $exp1->calculated_withholding_amount);
        $this->assertEquals(1000.00, $exp1->effective_payable_amount);
        $this->assertFalse($exp1->has_vat);

        // 2. Exclusive VAT (15%) + 3% Service Withholding
        // Base = 1000, VAT = 150, WHT = 3% of 1000 = 30
        // Net payable = (1000 + 150) - 30 = 1120
        $exp2 = new UtilityExpense([
            'amount'          => 1000.00,
            'gross_amount'    => 1000.00,
            'vat_type'        => 'exclusive',
            'vat_rate'        => 15.00,
            'vat_amount'      => 150.00,
            'has_withholding' => true,
            'withholding_rate'=> 3.00,
        ]);
        $this->assertTrue($exp2->has_vat);
        $this->assertEquals(30.00, $exp2->calculated_withholding_amount);
        $this->assertEquals(1120.00, $exp2->effective_payable_amount);

        // 3. Inclusive / VAT B + 3% Service Withholding
        // Gross Invoice = 1150 (inclusive of 15% VAT).
        // Taxable Base = 1150 / 1.15 = 1000.
        // WHT = 3% of 1000 = 30.
        // Net payable = 1150 - 30 = 1120.
        $exp3 = new UtilityExpense([
            'amount'          => 1150.00,
            'gross_amount'    => 1150.00,
            'vat_type'        => 'vat_b',
            'vat_rate'        => 15.00,
            'has_withholding' => true,
            'withholding_rate'=> 3.00,
        ]);
        $this->assertTrue($exp3->has_vat);
        $this->assertEquals(30.00, $exp3->calculated_withholding_amount);
        $this->assertEquals(1120.00, $exp3->effective_payable_amount);

        // 4. Withholding Receipt URL accessor
        $exp4 = new UtilityExpense([
            'withholding_receipt' => 'uploads/withholding_receipts/sample_wht.pdf',
        ]);
        $this->assertNotNull($exp4->withholding_receipt_url);
        $this->assertStringContainsString('sample_wht.pdf', $exp4->withholding_receipt_url);
    }

    public function test_utility_expense_pay_disbursement_with_vat_and_withholding()
    {
        $user = new class extends User {
            protected $table = 'users';
            public function getRoleNames(): \Illuminate\Support\Collection
            {
                return collect(['finance_head']);
            }
            public function hasAnyRole(...$roles): bool
            {
                return true;
            }
        };
        $user->name = 'Finance Officer';
        $user->email = 'finance@wechecha.com';
        $user->password = 'secret';
        $user->save();

        Auth::login($user);

        $coa = ChartOfAccount::create([
            'code'            => '5201',
            'name'            => 'Utilities & Power Account',
            'current_balance' => 50000.00,
        ]);

        $bank = BankAccount::create([
            'coa_id'          => $coa->id,
            'bank_name'       => 'Commercial Bank of Ethiopia',
            'account_number'  => '100012345678',
            'current_balance' => 50000.00,
        ]);

        $expense = UtilityExpense::create([
            'expense_number'  => 'UTIL-2026-0001',
            'utility_type'    => 'electricity',
            'title'           => 'Substation Electric Bill - Head Office',
            'amount'          => 2000.00,
            'coa_id'          => $coa->id,
            'bank_account_id' => $bank->id,
            'created_by'      => $user->id,
            'assigned_to'     => $user->id,
            'status'          => UtilityExpense::STATUS_PENDING,
        ]);

        // Disburse with 15% Exclusive VAT + 3% Withholding Tax
        // Base = 2000.00, VAT = 300.00, WHT = 60.00
        // Net Disbursed = (2000 + 300) - 60 = 2240.00
        $request = Request::create(route('utility-expenses.pay', $expense->id), 'POST', [
            'gross_amount'               => '2000.00',
            'vat_type'                   => 'exclusive',
            'vat_rate'                   => '15.00',
            'vat_amount'                 => '300.00',
            'has_withholding'            => '1',
            'withholding_rate'           => '3.00',
            'withholding_amount'         => '60.00',
            'withholding_receipt_number' => 'WHT-SLIP-771122',
            'net_amount'                 => '2240.00',
            'paid_amount'                => '2240.00',
            'payment_method'             => 'bank_transfer',
            'payment_reference'          => 'TXN-EEU-99881',
            'payment_date'               => '2026-11-06',
            'payment_notes'              => 'Paid electricity bill with 15% VAT and 3% WHT voucher',
        ]);

        $controller = new UtilityExpenseController();
        $response = $controller->pay($request, $expense);

        $this->assertEquals(302, $response->getStatusCode());

        $freshExpense = $expense->fresh();
        $this->assertEquals(UtilityExpense::STATUS_PAID, $freshExpense->status);
        $this->assertEquals(2000.00, (float)$freshExpense->gross_amount);
        $this->assertEquals('exclusive', $freshExpense->vat_type);
        $this->assertEquals(15.00, (float)$freshExpense->vat_rate);
        $this->assertEquals(300.00, (float)$freshExpense->vat_amount);
        $this->assertTrue((bool)$freshExpense->has_withholding);
        $this->assertEquals(3.00, (float)$freshExpense->withholding_rate);
        $this->assertEquals(60.00, (float)$freshExpense->withholding_amount);
        $this->assertEquals('WHT-SLIP-771122', $freshExpense->withholding_receipt_number);
        $this->assertEquals(2240.00, (float)$freshExpense->paid_amount);
        $this->assertEquals(2240.00, (float)$freshExpense->net_amount);

        // Account balances should be decremented by the actual disbursed net amount (2,240.00)
        // 50,000 - 2,240 = 47,760.00
        $this->assertEquals(47760.00, (float)$coa->fresh()->current_balance);
        $this->assertEquals(47760.00, (float)$bank->fresh()->current_balance);
    }

    public function test_utility_expense_pay_with_vat_b_and_auto_wht_calculation()
    {
        $user = new class extends User {
            protected $table = 'users';
            public function getRoleNames(): \Illuminate\Support\Collection
            {
                return collect(['finance_head']);
            }
            public function hasAnyRole(...$roles): bool
            {
                return true;
            }
        };
        $user->name = 'Finance Manager';
        $user->email = 'fm@wechecha.com';
        $user->password = 'secret';
        $user->save();

        Auth::login($user);

        $coa = ChartOfAccount::create([
            'code'            => '5202',
            'name'            => 'Internet & WiFi',
            'current_balance' => 20000.00,
        ]);

        $expense = UtilityExpense::create([
            'expense_number'  => 'UTIL-2026-0002',
            'utility_type'    => 'wifi',
            'title'           => 'Monthly Fiber Internet Bill',
            'amount'          => 1150.00,
            'coa_id'          => $coa->id,
            'created_by'      => $user->id,
            'assigned_to'     => $user->id,
            'status'          => UtilityExpense::STATUS_PENDING,
        ]);

        // Submit disbursement with VAT B (15% included) and Withholding enabled but amount 0
        // Backend auto-calculates:
        // Base = 1150 / 1.15 = 1000
        // VAT = 1150 - 1000 = 150
        // WHT 3% on 1000 base = 30
        // Net payable = 1150 - 30 = 1120
        $request = Request::create(route('utility-expenses.pay', $expense->id), 'POST', [
            'gross_amount'               => '1150.00',
            'vat_type'                   => 'vat_b',
            'vat_rate'                   => '15.00',
            'has_withholding'            => '1',
            'withholding_rate'           => '3.00',
            'withholding_receipt_number' => 'WHT-VOUCHER-5544',
            'paid_amount'                => '1120.00',
            'payment_method'             => 'telebirr',
            'payment_reference'          => 'TEL-99881122',
            'payment_date'               => '2026-11-06',
        ]);

        $controller = new UtilityExpenseController();
        $controller->pay($request, $expense);

        $fresh = $expense->fresh();
        $this->assertEquals(UtilityExpense::STATUS_PAID, $fresh->status);
        $this->assertEquals('vat_b', $fresh->vat_type);
        $this->assertEquals(150.00, (float)$fresh->vat_amount);
        $this->assertTrue((bool)$fresh->has_withholding);
        $this->assertEquals(30.00, (float)$fresh->withholding_amount);
        $this->assertEquals('WHT-VOUCHER-5544', $fresh->withholding_receipt_number);
        $this->assertEquals(1120.00, (float)$fresh->paid_amount);

        // COA balance deduction: 20000 - 1120 = 18880.00
        $this->assertEquals(18880.00, (float)$coa->fresh()->current_balance);
    }
}
