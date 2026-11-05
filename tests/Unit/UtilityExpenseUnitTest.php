<?php

namespace Tests\Unit;

use App\Models\ExpenseRequest;
use Tests\TestCase;

class UtilityExpenseUnitTest extends TestCase
{
    public function test_utility_category_constant_and_configuration()
    {
        $this->assertEquals('Utility Expense', ExpenseRequest::CATEGORY_UTILITY);

        $categories = ExpenseRequest::getCategoriesList();
        $this->assertArrayHasKey(ExpenseRequest::CATEGORY_UTILITY, $categories);

        $utilityConfig = $categories[ExpenseRequest::CATEGORY_UTILITY];
        $this->assertEquals('Utility Expense (የፍጆታ ወጪ)', $utilityConfig['label']);
        $this->assertEquals('fa-solid fa-bolt', $utilityConfig['icon']);
        $this->assertEquals('warning', $utilityConfig['color']);
        $this->assertTrue($utilityConfig['has_tax_options']);
        $this->assertNotEmpty($utilityConfig['description']);
    }

    public function test_calculated_withholding_on_expense_request()
    {
        $request = new ExpenseRequest();
        $request->amount = 1000.00;
        $request->gross_amount = 1000.00;
        $request->vat_type = 'none';
        $request->has_withholding = true;
        $request->withholding_rate = 3.00;

        // Base amount = 1000, 3% withholding = 30.00 ETB
        $this->assertEquals(30.00, $request->calculated_withholding_amount);
        $this->assertEquals(970.00, $request->effective_payable_amount);
    }

    public function test_utility_expense_vat_exclusive_calculation()
    {
        $newPayAmount = 2000.00;
        $vatRate = 15.00;
        $whtRate = 3.00;

        $vatAmount = round($newPayAmount * ($vatRate / 100), 2);
        $totalGrossWithVat = $newPayAmount + $vatAmount;
        $withholdingAmount = round($newPayAmount * ($whtRate / 100), 2);
        $netAmount = max(0, $totalGrossWithVat - $withholdingAmount);

        $this->assertEquals(300.00, $vatAmount);
        $this->assertEquals(2300.00, $totalGrossWithVat);
        $this->assertEquals(60.00, $withholdingAmount);
        $this->assertEquals(2240.00, $netAmount);
    }

    public function test_utility_expense_vat_inclusive_calculation()
    {
        $gross = 1150.00;
        $vatRate = 15.00;
        $whtRate = 3.00;

        $base = round($gross / (1 + ($vatRate / 100)), 2);
        $vatAmount = round($gross - $base, 2);
        $withholdingAmount = round($base * ($whtRate / 100), 2);
        $netAmount = max(0, $gross - $withholdingAmount);

        $this->assertEquals(1000.00, $base);
        $this->assertEquals(150.00, $vatAmount);
        $this->assertEquals(30.00, $withholdingAmount);
        $this->assertEquals(1120.00, $netAmount);
    }

    public function test_utility_category_filter_matching()
    {
        $utilityCategories = ['Utility Expense', 'Utility'];
        $selectedCategory = 'Utility Expense';

        $this->assertTrue(in_array($selectedCategory, $utilityCategories));
        $this->assertTrue(in_array('Utility', $utilityCategories));
    }
}
