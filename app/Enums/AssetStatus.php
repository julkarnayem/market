<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case PendingEditApproval = 'pending_edit_approval';
    case Rejected = 'rejected';
    case Paused = 'paused';
    case SoldOut = 'sold_out';
    case Suspended = 'suspended';
    case Archived = 'archived';

    /** Visible on the public marketplace. */
    public function isPubliclyVisible(): bool
    {
        return in_array($this, [self::Published, self::PendingEditApproval], true);
    }

    /** Can currently receive orders/offers. */
    public function isPurchasable(): bool
    {
        return $this === self::Published;
    }
}
