<?php

namespace Tests\Unit;

use App\Services\Restoration\RestorationCostCalculator;
use PHPUnit\Framework\TestCase;

class RestorationCostCalculatorTest extends TestCase
{
    public function test_it_calculates_low_base_and_high_scenarios_from_explicit_prices(): void
    {
        $result = (new RestorationCostCalculator)->calculate([
            'currency' => 'IDR',
            'items' => [
                [
                    'id' => 'seedlings',
                    'label' => 'Bibit native',
                    'quantity' => 1000,
                    'unit' => 'bibit',
                    'prices' => ['low' => 5000, 'base' => 7500, 'high' => 10000],
                ],
            ],
        ]);

        $this->assertSame('complete', $result['status']);
        $this->assertSame(5000000, $result['scenarios']['low']['total']);
        $this->assertSame(7500000, $result['scenarios']['base']['total']);
        $this->assertSame(10000000, $result['scenarios']['high']['total']);
        $this->assertSame([], $result['missing_prices']);
    }

    public function test_it_keeps_totals_null_when_prices_are_not_available(): void
    {
        $result = (new RestorationCostCalculator)->calculate([
            'items' => [
                ['id' => 'mulch', 'label' => 'Mulsa', 'quantity' => 4, 'unit' => 'ha', 'prices' => []],
            ],
        ]);

        $this->assertSame('incomplete', $result['status']);
        $this->assertNull($result['scenarios']['base']['total']);
        $this->assertContains('mulch.base', $result['missing_prices']);
    }
}
