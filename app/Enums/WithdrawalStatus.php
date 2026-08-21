<?php
namespace App\Enums;

/**
 * A withdrawal is single-step: it stays Pending until staff either pay it
 * (Completed) or turn it down (Rejected), or the user takes it back (Cancelled).
 * There is no separate Approved/Processing hold and no Failed state — a payout
 * that cannot be sent is simply Rejected, which returns the money.
 */
enum WithdrawalStatus: string
{
    case Pending   = 'pending';
    case Completed = 'completed';
    case Rejected  = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string {
        return match($this) {
            self::Pending   => 'Pending',
            self::Completed => 'Paid',
            self::Rejected  => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }
    public function isTerminal(): bool {
        return in_array($this, [self::Completed, self::Rejected, self::Cancelled]);
    }
    /** Either side may still act: staff may pay or reject it, the user may cancel it. */
    public function isActionable(): bool {
        return $this === self::Pending;
    }
}
