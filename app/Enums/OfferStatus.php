<?php

namespace App\Enums;

enum OfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Paid = 'paid';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Rejected => 'Declined',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
            self::Paid => 'Paid',
            self::Completed => 'Completed',
        };
    }

    public function locksListingPrice(): bool
    {
        return $this === self::Pending || $this === self::Accepted;
    }

    /** Awaiting a response from the other party. */
    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    /** Accepted and awaiting the buyer's payment. */
    public function awaitsPayment(): bool
    {
        return $this === self::Accepted;
    }
}
