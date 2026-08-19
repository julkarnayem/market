<?php
namespace App\Enums;

/**
 * The dispute lifecycle.
 *
 * Deliberately separate from OrderStatus and Payment status: an order can be
 * `disputed` while the dispute itself is still `open`, and a dispute reaching
 * `resolved_partial` is what *causes* the order to become `partially_refunded`.
 *
 * The four terminal states record who the money went to:
 *   resolved_buyer   — admin decided a full refund
 *   resolved_seller  — admin released the payment to the seller
 *   resolved_partial — admin split it
 *   refunded         — buyer and seller settled it themselves, money moved
 *   cancelled        — closed with no financial outcome
 */
enum DisputeStatus: string
{
    case Open            = 'open';
    case SellerResponded = 'seller_responded';
    case Negotiating     = 'negotiating';
    case Escalated       = 'escalated';
    case ResolvedBuyer   = 'resolved_buyer';
    case ResolvedSeller  = 'resolved_seller';
    case ResolvedPartial = 'resolved_partial';
    case Refunded        = 'refunded';
    case Cancelled       = 'cancelled';

    /**
     * Still live. While this is true the seller's earning stays held, the thread
     * accepts messages, and no second resolution may execute.
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::Open, self::SellerResponded, self::Negotiating, self::Escalated,
        ], true);
    }

    /** A decision may only be applied to an active dispute. */
    public function isResolvable(): bool
    {
        return $this->isActive();
    }

    public function isEscalated(): bool
    {
        return $this === self::Escalated;
    }

    public function label(): string
    {
        return match ($this) {
            self::Open            => 'Open',
            self::SellerResponded => 'Seller Responded',
            self::Negotiating     => 'Negotiating',
            self::Escalated       => 'Escalated to Admin',
            self::ResolvedBuyer   => 'Resolved — Buyer',
            self::ResolvedSeller  => 'Resolved — Seller',
            self::ResolvedPartial => 'Resolved — Partial',
            self::Refunded        => 'Refunded',
            self::Cancelled       => 'Cancelled',
        };
    }

    /**
     * Admin queue tabs. The four resolved states collapse into one "Resolved"
     * filter, which Admin\DisputeController expands.
     *
     * @return list<array{value:string,label:string}>
     */
    public static function adminTabs(): array
    {
        return [
            ['value' => 'open',             'label' => 'Open'],
            ['value' => 'seller_responded', 'label' => 'Seller Responded'],
            ['value' => 'negotiating',      'label' => 'Negotiating'],
            ['value' => 'escalated',        'label' => 'Escalated'],
            ['value' => 'resolved',         'label' => 'Resolved'],
            ['value' => 'refunded',         'label' => 'Refunded'],
            ['value' => 'all',              'label' => 'All'],
        ];
    }

    /**
     * The statuses the "Resolved" tab stands for.
     *
     * @return list<string>
     */
    public static function resolvedValues(): array
    {
        return [
            self::ResolvedBuyer->value,
            self::ResolvedSeller->value,
            self::ResolvedPartial->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_values(array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => $s->isActive()),
        ));
    }
}
