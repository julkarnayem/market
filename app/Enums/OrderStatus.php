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
     * The buyer may review once the asset has actually reached them, and for as
     * long as the order stands. That is Delivered (the seller has handed it over)
     * and Completed (the buyer confirmed, or the protection window auto-confirmed).
     *
     * Deliberately not tied to `delivery_status`: an order sits at Delivered with
     * delivery_status='delivered' for the whole protection window, which is exactly
     * when a buyer wants to write the review. Refunded, partially refunded and
     * cancelled orders are excluded — the sale was unwound — and so is Disputed,
     * which is an open claim rather than a finished purchase.
     */
    public function canBeReviewed(): bool {
        return in_array($this, [self::Delivered, self::Completed], true);
    }
    /**
     * A dispute is a claim about money that has already been paid, so the order
     * must be paid and not yet settled: Paid, Awaiting Delivery or Delivered.
     * Completed, refunded and cancelled orders are past the point of dispute,
     * and an unpaid one has nothing held to argue over.
     */
    public function canOpenDispute(): bool {
        return in_array($this, [self::Paid, self::DeliveryPending, self::Delivered], true);
    }
    /**
     * Whether this order still holds a unit of the listing's stock.
     *
     * Stock is taken at payment (OrderService::confirmPayment), so an order
     * awaiting payment holds nothing yet, and a refunded or cancelled one has
     * given it back. Everything in between is a live sale — including Disputed,
     * where the claim is unresolved and the buyer still has the asset, and
     * PartiallyRefunded, where they kept it and only some money went back.
     */
    public function countsAsSale(): bool {
        return in_array($this, [
            self::Paid, self::DeliveryPending, self::Delivered, self::Completed,
            self::Disputed, self::PartiallyRefunded, self::SellerPaid,
        ], true);
    }
    /**
     * The countsAsSale() statuses as raw values, for whereIn().
     *
     * @return list<string>
     */
    public static function saleValues(): array {
        return array_values(array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => $s->countsAsSale()),
        ));
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
