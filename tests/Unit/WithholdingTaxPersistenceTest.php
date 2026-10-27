<?php

namespace Tests\Unit;

use App\Models\ExpenseRequest;
use Tests\TestCase;

class WithholdingTaxPersistenceTest extends TestCase
{
    public function test_withholding_calculation_for_standard_rates()
    {
        // 1. Test 2% (Goods)
        $exp2 = new ExpenseRequest();
        $exp2->amount = 10000.00;
        $exp2->has_withholding = true;
        $exp2->withholding_rate = 2;
        $exp2->withholding_amount = 0; // Unset explicit amount, use rate

        $this->assertEquals(200.00, $exp2->calculated_withholding_amount);
        $this->assertEquals(9800.00, $exp2->effective_payable_amount);

        // 2. Test 3% (Services)
        $exp3 = new ExpenseRequest();
        $exp3->amount = 10000.00;
        $exp3->has_withholding = true;
        $exp3->withholding_rate = 3;
        $exp3->withholding_amount = null;

        $this->assertEquals(300.00, $exp3->calculated_withholding_amount);
        $this->assertEquals(9700.00, $exp3->effective_payable_amount);

        // 3. Test 30% (No TIN)
        $exp30 = new ExpenseRequest();
        $exp30->amount = 10000.00;
        $exp30->has_withholding = true;
        $exp30->withholding_rate = 30;

        $this->assertEquals(3000.00, $exp30->calculated_withholding_amount);
        $this->assertEquals(7000.00, $exp30->effective_payable_amount);

        // 4. Test explicit withholding_amount overrides rate calculation
        $expCustom = new ExpenseRequest();
        $expCustom->amount = 10000.00;
        $expCustom->has_withholding = true;
        $expCustom->withholding_rate = 3;
        $expCustom->withholding_amount = 450.00;

        $this->assertEquals(450.00, $expCustom->calculated_withholding_amount);
        $this->assertEquals(9550.00, $expCustom->effective_payable_amount);

        // 5. Test disabled withholding returns 0.00
        $expDisabled = new ExpenseRequest();
        $expDisabled->amount = 10000.00;
        $expDisabled->has_withholding = false;
        $expDisabled->withholding_amount = 300.00;

        $this->assertEquals(0.00, $expDisabled->calculated_withholding_amount);
        $this->assertEquals(10000.00, $expDisabled->effective_payable_amount);
    }
}
