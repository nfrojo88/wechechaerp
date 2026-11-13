<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Services\Finance\WithholdingTaxRateHelper;
use InvalidArgumentException;

class WithholdingTaxRateHelperTest extends TestCase
{
    /** @test */
    public function it_returns_standard_rate_for_goods_and_services_with_valid_tin()
    {
        $goodsRate = WithholdingTaxRateHelper::resolveRate('goods', true, 15000.00);
        $this->assertEquals(2.00, $goodsRate['rate_percent']);
        $this->assertEquals(0.02, $goodsRate['rate_decimal']);
        $this->assertTrue($goodsRate['is_applicable']);
        $this->assertEquals('to_be_verified', $goodsRate['status']);
        $this->assertEquals('to_be_verified', $goodsRate['threshold_status']);

        $svcRate = WithholdingTaxRateHelper::resolveRate('services', true, 5000.00);
        $this->assertEquals(3.00, $svcRate['rate_percent']);
        $this->assertEquals(0.03, $svcRate['rate_decimal']);
        $this->assertTrue($svcRate['is_applicable']);
    }

    /** @test */
    public function it_returns_thirty_percent_penalty_rate_when_tin_is_invalid()
    {
        $rateNoTin = WithholdingTaxRateHelper::resolveRate('services', false, 5000.00);
        $this->assertEquals(30.00, $rateNoTin['rate_percent']);
        $this->assertEquals(0.30, $rateNoTin['rate_decimal']);
        $this->assertTrue($rateNoTin['is_applicable']);
    }

    /** @test */
    public function it_enforces_thresholds_returning_zero_below_threshold()
    {
        // Goods threshold is ETB 10,000
        $belowGoods = WithholdingTaxRateHelper::resolveRate('goods', true, 8500.00);
        $this->assertEquals(0.00, $belowGoods['rate_percent']);
        $this->assertFalse($belowGoods['is_applicable']);
        $this->assertFalse($belowGoods['exceeds_threshold']);

        $aboveGoods = WithholdingTaxRateHelper::resolveRate('goods', true, 12000.00);
        $this->assertEquals(2.00, $aboveGoods['rate_percent']);
        $this->assertTrue($aboveGoods['is_applicable']);

        // Services threshold is ETB 3,000
        $belowSvc = WithholdingTaxRateHelper::resolveRate('services', true, 2500.00);
        $this->assertEquals(0.00, $belowSvc['rate_percent']);
        $this->assertFalse($belowSvc['is_applicable']);

        $aboveSvc = WithholdingTaxRateHelper::resolveRate('services', true, 3500.00);
        $this->assertEquals(3.00, $aboveSvc['rate_percent']);
        $this->assertTrue($aboveSvc['is_applicable']);
    }

    /** @test */
    public function it_resolves_rate_from_item_with_stored_classification()
    {
        // 1. Explicit rate takes precedence
        $item1 = ['withholding_rate' => 2.00, 'purchase_type' => 'services'];
        $this->assertEquals(2.00, WithholdingTaxRateHelper::resolveRateFromItem($item1));

        // 2. Null rate derives from stored classification: goods
        $itemGoods = ['withholding_rate' => null, 'purchase_type' => 'goods'];
        $this->assertEquals(2.00, WithholdingTaxRateHelper::resolveRateFromItem($itemGoods));

        // 3. Null rate derives from stored classification: services
        $itemSvc = ['withholding_rate' => null, 'purchase_type' => 'services'];
        $this->assertEquals(3.00, WithholdingTaxRateHelper::resolveRateFromItem($itemSvc));
    }

    /** @test */
    public function it_throws_exception_when_item_rate_is_null_and_unclassified()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Null withholding rate and unclassified item");

        $unclassifiedItem = ['withholding_rate' => null, 'purchase_type' => null];
        WithholdingTaxRateHelper::resolveRateFromItem($unclassifiedItem);
    }

    /** @test */
    public function it_rejects_unclassified_object_instances_from_controllers()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Null withholding rate and unclassified item");

        $mockItem = (object) [
            'withholding_rate' => null,
            'purchase_type'    => null,
            'item_type'        => null,
            'category'         => 'miscellaneous_unknown',
        ];

        WithholdingTaxRateHelper::resolveRateFromItem($mockItem);
    }

    /** @test */
    public function it_resolves_income_and_client_wht_correctly_when_classified()
    {
        // When client withholding is on equipment rental service
        $rentalItem = (object) [
            'withholding_rate' => null,
            'item_type'        => 'services',
        ];
        $this->assertEquals(3.00, WithholdingTaxRateHelper::resolveRateFromItem($rentalItem));

        // When client withholding is on sale of scrap goods
        $scrapItem = (object) [
            'withholding_rate' => null,
            'item_type'        => 'goods',
        ];
        $this->assertEquals(2.00, WithholdingTaxRateHelper::resolveRateFromItem($scrapItem));
    }
}
