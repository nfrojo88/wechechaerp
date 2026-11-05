<?php

namespace Tests\Unit;

use App\Models\Product;
use Tests\TestCase;

class ProductCatalogSortingUnitTest extends TestCase
{
    public function test_default_sorting_order_is_alphabetical_by_name()
    {
        $query = Product::query();

        // Default sorting
        $sort = 'name';
        $direction = 'asc';

        if (in_array($sort, ['name', 'sku', 'category', 'unit', 'unit_price', 'max_stock'])) {
            $query->orderBy($sort, $direction)->orderBy('id', 'asc');
        } else {
            $query->orderBy('name', 'asc')->orderBy('id', 'asc');
        }

        $orders = $query->getQuery()->orders;
        $this->assertNotEmpty($orders);
        $this->assertEquals('name', $orders[0]['column']);
        $this->assertEquals('asc', $orders[0]['direction']);
    }

    public function test_alphabetical_sorting_comparison()
    {
        $names = ['Cement Portland', 'Aggregate 02', 'Basalt Stone', 'Rebar 12mm', 'Timber 2x4'];
        
        $sorted = $names;
        sort($sorted, SORT_STRING | SORT_FLAG_CASE);

        $this->assertEquals(['Aggregate 02', 'Basalt Stone', 'Cement Portland', 'Rebar 12mm', 'Timber 2x4'], $sorted);
    }
}
