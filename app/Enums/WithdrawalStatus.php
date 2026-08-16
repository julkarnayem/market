<?php
namespace App\Enums;

enum WithdrawalStatus: string
{
    case Pending    = 'pending';
    case Approved   = 'approved';
    case Processing = 'processing';
    case Completed  = 'completed';
    case Rejected   = 'rejected';
    case Failed     = 'failed';

    public function label(): string {
        return match($this) {
            self::Pending    => 'Pending',
            self::Approved   => 'Approved',
            self::Processing => 'Processing',
            self::Completed  => 'Completed',
            self::Rejected   => 'Rejected',
            self::Failed     => 'Failed',
        };
    }
    public function isTerminal(): bool {
        return in_array($this, [self::Completed, self::Rejected, self::Failed]);
    }
}
