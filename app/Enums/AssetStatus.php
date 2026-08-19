<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case PendingEditApproval = 'pending_edit_approval';
    case BidAccepted = 'bid_accepted';
    case Rejected = 'rejected';
    case Paused = 'paused';
    case SoldOut = 'sold_out';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending review',
            self::Published => 'Published',
            self::PendingEditApproval => 'Pending edit approval',
            self::BidAccepted => 'Bid Accepted',
            self::Rejected => 'Rejected',
            self::Paused => 'Paused',
            self::SoldOut => 'Sold out',
            self::Suspended => 'Suspended',
            self::Archived => 'Archived',
        };
    }

    /**
     * Visible on the public marketplace. BidAccepted stays visible: the seller
     * accepted a bid and is waiting on payment, so the page must still render
     * to show that state — it is explicitly *not* Sold.
     */
    public function isPubliclyVisible(): bool
    {
        return in_array($this, [self::Published, self::PendingEditApproval, self::BidAccepted], true);
    }

    /**
     * Can currently receive new orders/bids/offers. BidAccepted is excluded —
     * Buy Now and New Bid are both closed while payment is pending, and only
     * the winning bidder may check out (that path checks the bid, not this).
     */
    public function isPurchasable(): bool
    {
        return $this === self::Published;
    }
}
