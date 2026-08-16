<?php
namespace App\Enums;

enum DisputeStatus: string
{
    case Open            = 'open';
    case UnderReview     = 'under_review';
    case WaitingForBuyer = 'waiting_for_buyer';
    case WaitingForSeller= 'waiting_for_seller';
    case Resolved        = 'resolved';
    case Rejected        = 'rejected';
    case Closed          = 'closed';

    public function isOpen(): bool {
        return in_array($this, [self::Open, self::UnderReview, self::WaitingForBuyer, self::WaitingForSeller]);
    }
    public function label(): string {
        return match($this) {
            self::Open             => 'Open',
            self::UnderReview      => 'Under Review',
            self::WaitingForBuyer  => 'Waiting for Buyer',
            self::WaitingForSeller => 'Waiting for Seller',
            self::Resolved         => 'Resolved',
            self::Rejected         => 'Rejected',
            self::Closed           => 'Closed',
        };
    }
}
