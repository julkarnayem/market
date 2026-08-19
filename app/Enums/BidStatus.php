<?php

namespace App\Enums;

enum BidStatus: string
{
    case Active = 'active';
    case Outbid = 'outbid';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Outbid => 'Outbid',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    /** Still in the running — can be outbid, accepted, rejected or cancelled. */
    public function isLive(): bool
    {
        return $this === self::Active;
    }

    /** Terminal states no action can move away from. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Rejected, self::Cancelled, self::Expired], true);
    }
}
