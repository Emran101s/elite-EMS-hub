<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

/**
 * One place that formats money, so a screen and a document can never again
 * quietly disagree about what currency a figure is in.
 */
class MoneyTest extends TestCase
{
    public function test_for_screen_uses_the_symbol_and_no_decimals(): void
    {
        $this->assertSame('$1,250', Money::forScreen(125000, 'USD'));
        $this->assertSame('JD 1,250', Money::forScreen(125000, 'JOD'));
        $this->assertSame('$0', Money::forScreen(null, 'USD'));
    }

    public function test_for_document_uses_the_currency_code_and_cents(): void
    {
        $this->assertSame('USD 1,250.00', Money::forDocument(125000, 'USD'));
        $this->assertSame('JOD 51,071.50', Money::forDocument(5107150, 'JOD'));
        $this->assertSame('JOD 0.00', Money::forDocument(null, 'JOD'));
    }

    public function test_abbreviated_switches_to_k_past_a_thousand_units(): void
    {
        $this->assertSame('$850', Money::abbreviated(85000, 'USD'));
        $this->assertSame('$1.2K', Money::abbreviated(120000, 'USD'));
        $this->assertSame('$1K', Money::abbreviated(100000, 'USD'));
        $this->assertSame('JD 2.5K', Money::abbreviated(250000, 'JOD'));
    }

    public function test_an_unknown_currency_code_falls_back_to_itself_as_the_symbol(): void
    {
        $this->assertSame('XYZ 10.00', Money::forDocument(1000, 'XYZ'));
        $this->assertSame('XYZ 10', Money::forScreen(1000, 'XYZ'));
    }
}
