<?php
namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment    = 'pending_payment';
    case Paid              = 'paid';
    case DeliveryPending   = 'delivery_pending';
    case Delivered         = 'delivered';
    case Completed         = 'completed';
    case Disputed          = 'disputed';
    case Refunded          = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case SellerPaid        = 'seller_payment_released';
    case Cancelled         = 'cancelled';

    public function buyerCanCancel(): bool { return $this === self::PendingPayment; }
    public function isActive(): bool {
        return in_array($this, [self::Paid, self::DeliveryPending, self::Delivered, self::Disputed], true);
    }
    public function canBeDelivered(): bool { return in_array($this, [self::Paid, self::DeliveryPending], true); }
    public function canBeCompleted(): bool { return $this === self::Delivered; }
    /**
     * A dispute is a claim about money that has already been paid, so the order
     * must be paid and not yet settled: Paid, Awaiting Delivery or Delivered.
     * Completed, refunded and cancelled orders are past the point of dispute,
     * and an unpaid one has nothing held to argue over.
     */
    public function canOpenDispute(): bool {
        return in_array($this, [self::Paid, self::DeliveryPending, self::Delivered], true);
    }
    public function label(): string {
        return match($this) {
            self::PendingPayment    => 'Awaiting Payment',
            self::Paid              => 'Paid',
            self::DeliveryPending   => 'Delivery Pending',
            self::Delivered         => 'Delivered',
            self::Completed         => 'Completed',
            self::Disputed          => 'Disputed',
            self::Refunded          => 'Refunded',
            self::PartiallyRefunded => 'Partially Refunded',
            self::SellerPaid        => 'Seller Paid',
            self::Cancelled         => 'Cancelled',
        };
    }
}
