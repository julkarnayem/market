<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case NotSubmitted = 'not_submitted';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function canSell(): bool
    {
        return $this === self::Approved;
    }

    public function canResubmit(): bool
    {
        return $this === self::Rejected || $this === self::NotSubmitted;
    }
}
