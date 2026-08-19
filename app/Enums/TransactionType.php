<?php
namespace App\Enums;

enum TransactionType: string
{
    case SellerEarningPending  = 'seller_earning_pending';   // pending hold after order
    case SellerEarningReleased = 'seller_earning_released';  // pending → available
    case SellerEarningReversed = 'seller_earning_reversed';  // pending → gone (refunded to buyer)
    case BuyerPurchase         = 'buyer_purchase';
    case BuyerFee              = 'buyer_fee';
    case SellerPlatformFee     = 'seller_platform_fee';
    case Refund                = 'refund';
    case PartialRefund         = 'partial_refund';
    case WithdrawalReserve     = 'withdrawal_reserve';       // debit on request
    case WithdrawalFee         = 'withdrawal_fee';
    case WithdrawalReturn      = 'withdrawal_return';        // credit on rejection
    case WithdrawalCompleted   = 'withdrawal_completed';
    case AdminAdjustment       = 'admin_adjustment';
    case PromotionPurchase      = 'promotion_purchase';
    // Legacy aliases kept for backward compat
    case Earning    = 'earning';
    case Purchase   = 'purchase';
    case Fee        = 'fee';
    case Withdrawal = 'withdrawal';
    case Hold       = 'hold';
    case Release    = 'release';
    case Adjustment = 'adjustment';

    public function isDebit(): bool {
        return in_array($this, [
            self::PromotionPurchase, self::WithdrawalReserve, self::SellerEarningReversed,
            self::Purchase, self::Fee, self::Hold, self::Withdrawal,
        ]);
    }
    public function isCredit(): bool {
        return in_array($this, [
            self::SellerEarningReleased, self::Refund, self::PartialRefund,
            self::WithdrawalReturn, self::AdminAdjustment, self::Release,
            self::Earning, self::Adjustment,
        ]);
    }
}
