<?php

namespace App\Services;

use App\Support\Money;

/**
 * Server-side fee/earning math in integer poisha + basis points.
 * NEVER trust client-side amounts. Returns the exact snapshot to persist on
 * the order so later admin fee changes never recalculate old orders.
 */
class FeeCalculator
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param int $unitPrice poisha
     * @return array{
     *   subtotal:int, seller_fee_bp:int, seller_fee_amount:int,
     *   buyer_fee_enabled:bool, buyer_fee_type:?string, buyer_fee_bp:?int,
     *   buyer_fee_amount:int, platform_commission:int, buyer_total:int, seller_earning:int
     * }
     */
    public function forOrder(int $unitPrice, int $quantity = 1): array
    {
        $subtotal = $unitPrice * $quantity;

        // Seller fee — applies to ALL prices, no free threshold.
        $sellerFeeBp = $this->settings->sellerFeeBp();
        $sellerFeeAmount = Money::percentOf($subtotal, $sellerFeeBp);
        $sellerEarning = $subtotal - $sellerFeeAmount;

        // Buyer fee — optional, OFF by default.
        $buyerFeeEnabled = $this->settings->buyerFeeEnabled();
        $buyerFeeType = $buyerFeeEnabled ? $this->settings->buyerFeeType() : null;
        $buyerFeeBp = null;
        $buyerFeeAmount = 0;

        if ($buyerFeeEnabled) {
            if ($buyerFeeType === 'percentage') {
                $buyerFeeBp = $this->settings->buyerFeeValue();      // stored as bp
                $buyerFeeAmount = Money::percentOf($subtotal, $buyerFeeBp);
            } else {
                $buyerFeeAmount = $this->settings->buyerFeeValue();  // stored as poisha
            }
        }

        $buyerTotal = $subtotal + $buyerFeeAmount;
        $platformCommission = $sellerFeeAmount + $buyerFeeAmount;

        return [
            'subtotal' => $subtotal,
            'seller_fee_bp' => $sellerFeeBp,
            'seller_fee_amount' => $sellerFeeAmount,
            'buyer_fee_enabled' => $buyerFeeEnabled,
            'buyer_fee_type' => $buyerFeeType,
            'buyer_fee_bp' => $buyerFeeBp,
            'buyer_fee_amount' => $buyerFeeAmount,
            'platform_commission' => $platformCommission,
            'buyer_total' => $buyerTotal,
            'seller_earning' => $sellerEarning,
        ];
    }
}
