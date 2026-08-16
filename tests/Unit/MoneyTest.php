<?php
namespace Tests\Unit;

use App\Support\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_to_poisha_whole_bdt(): void
    {
        $this->assertSame(100, Money::toPoisha(1));
        $this->assertSame(5000, Money::toPoisha(50));
        $this->assertSame(100000, Money::toPoisha(1000));
    }

    public function test_to_poisha_fractional_bdt(): void
    {
        $this->assertSame(150, Money::toPoisha(1.5));
        $this->assertSame(50, Money::toPoisha(0.50));
    }

    public function test_format(): void
    {
        $this->assertSame('৳50.00', Money::format(5000));
        $this->assertSame('৳1,000.00', Money::format(100000));
    }

    public function test_percent_of_10_percent(): void
    {
        // 10% of ৳1000 = ৳100 = 10000 poisha. Seller fee: 1000 bp.
        $this->assertSame(10000, Money::percentOf(100000, 1000));
    }

    public function test_percent_of_zero(): void
    {
        $this->assertSame(0, Money::percentOf(100000, 0));
    }

    public function test_no_free_threshold(): void
    {
        // ৳400 at 10% = ৳40 — no free threshold applies
        $this->assertSame(4000, Money::percentOf(40000, 1000));
    }

    public function test_seller_earning_calculation(): void
    {
        // ৳1000 listing, 10% fee → seller gets ৳900
        $price  = 100000; // 1000 BDT in poisha
        $feeBp  = 1000;   // 10%
        $fee    = Money::percentOf($price, $feeBp);
        $earning= $price - $fee;
        $this->assertSame(10000, $fee);    // ৳100
        $this->assertSame(90000, $earning); // ৳900
    }
}
