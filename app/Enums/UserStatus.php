<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Restricted = 'restricted';
    case PendingVerification = 'pending_verification';

    public function canTransact(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Restricted => 'Restricted',
            self::PendingVerification => 'Pending Verification',
        };
    }
}
