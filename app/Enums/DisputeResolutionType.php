<?php
namespace App\Enums;

/**
 * The outcomes a dispute can end on — whether proposed by the buyer, offered by
 * the seller, or decided by an admin.
 *
 * Replacement is the one outcome that moves no money: the seller re-delivers and
 * the order completes normally, so the held earning is released to them.
 */
enum DisputeResolutionType: string
{
    case FullRefund    = 'full_refund';
    case PartialRefund = 'partial_refund';
    case Replacement   = 'replacement';
    case ReleaseSeller = 'release_seller';

    public function label(): string
    {
        return match ($this) {
            self::FullRefund    => 'Full refund',
            self::PartialRefund => 'Partial refund',
            self::Replacement   => 'Replacement / fix',
            self::ReleaseSeller => 'Release payment to seller',
        };
    }

    /** Only a partial refund needs the proposer to name an amount. */
    public function requiresAmount(): bool
    {
        return $this === self::PartialRefund;
    }

    /**
     * What buyer and seller may put on the table themselves. Releasing the
     * payment is an admin decision — a buyer offering it would be indistinct
     * from simply withdrawing, and a seller may not award it to themselves.
     *
     * @return list<array{value:string,label:string}>
     */
    public static function negotiableOptions(): array
    {
        return array_map(
            fn (self $t) => ['value' => $t->value, 'label' => $t->label()],
            [self::FullRefund, self::PartialRefund, self::Replacement],
        );
    }

    /** Validation rule body for a negotiated proposal. */
    public static function negotiableRule(): string
    {
        return 'in:' . implode(',', array_column(self::negotiableOptions(), 'value'));
    }
}
