<?php
namespace Tests\Unit;

use App\Services\FeeCalculator;
use App\Services\SettingsService;
use Tests\TestCase;

class FeeCalculatorTest extends TestCase
{
    private FeeCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = app(FeeCalculator::class);
    }

    public function test_single_unit_fee_snapshot(): void
    {
        $snap = $this->calc->forOrder(100000, 1); // ৳1000
        $this->assertSame(100000, $snap['subtotal']);
        $this->assertSame(1000, $snap['seller_fee_bp']); // 10% default
        $this->assertSame(10000, $snap['seller_fee_amount']); // ৳100
        $this->assertSame(90000, $snap['seller_earning']);  // ৳900
        $this->assertFalse($snap['buyer_fee_enabled']);
        $this->assertSame(0, $snap['buyer_fee_amount']);
        $this->assertSame(100000, $snap['buyer_total']); // no buyer fee
    }

    public function test_quantity_multiplier(): void
    {
        $snap = $this->calc->forOrder(100000, 3); // ৳1000 × 3
        $this->assertSame(300000, $snap['subtotal']);
        $this->assertSame(30000, $snap['seller_fee_amount']); // 10%
        $this->assertSame(270000, $snap['seller_earning']);
        $this->assertSame(300000, $snap['buyer_total']);
    }

    public function test_no_free_threshold_low_price(): void
    {
        // ৳400 — fee applies (no ৳500 free threshold)
        $snap = $this->calc->forOrder(40000, 1);
        $this->assertSame(4000, $snap['seller_fee_amount']); // ৳40
        $this->assertSame(36000, $snap['seller_earning']);   // ৳360
    }

    public function test_withdrawal_fee_and_minimum(): void
    {
        $settings = app(SettingsService::class);
        $this->assertSame(5000, $settings->minWithdrawal());   // ৳50
        $this->assertSame(500, $settings->withdrawalFee());    // ৳5
    }

    public function test_promotion_prices(): void
    {
        $prices = \App\Services\PromotionService::PRICES;
        $this->assertSame(5000, $prices[1]);   // 1 day = ৳50
        $this->assertSame(10000, $prices[2]);  // 2 days = ৳100
        $this->assertSame(25000, $prices[5]);  // 5 days = ৳250
    }
}
