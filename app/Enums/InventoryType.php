<?php

namespace App\Enums;

/**
 * How much stock a listing represents. Drives which purchase actions the
 * listing offers and whether a paid order consumes inventory.
 *
 * Bidding is deliberately restricted to Single: a bid is an offer for *the*
 * item, which only means something when there is exactly one of it.
 */
enum InventoryType: string
{
    case Single = 'single';
    case Multiple = 'multiple';
    case Unlimited = 'unlimited';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single / unique item',
            self::Multiple => 'Multiple quantity',
            self::Unlimited => 'Unlimited stock',
        };
    }

    /** Buy Now is available for every inventory type. */
    public function allowsBuyNow(): bool
    {
        return true;
    }

    /** Only a unique item can be bid on. Enforced server-side, not just in the UI. */
    public function allowsBidding(): bool
    {
        return $this === self::Single;
    }

    /** Unlimited listings never draw down stock and never sell out. */
    public function consumesInventory(): bool
    {
        return $this !== self::Unlimited;
    }
}
