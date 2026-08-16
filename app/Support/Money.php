<?php

namespace App\Support;

/**
 * All monetary amounts are stored and computed as integer minor units (poisha).
 * 1 BDT = 100 poisha. Never use floats for money.
 *
 * Fee rates are integer basis points (bp): 10% = 1000 bp, 100% = 10000 bp.
 */
final class Money
{
    public const SUBUNITS = 100;         // poisha per BDT
    public const SYMBOL = '৳';

    /** Convert BDT (int or numeric string) to poisha. */
    public static function toPoisha(int|float|string $bdt): int
    {
        // Parse safely to avoid float drift: split on decimal point.
        $s = number_format((float) $bdt, 2, '.', '');
        [$whole, $frac] = explode('.', $s);
        $sign = str_starts_with($whole, '-') ? -1 : 1;
        $whole = (int) ltrim($whole, '-');
        $frac = (int) $frac;
        return $sign * ($whole * self::SUBUNITS + $frac);
    }

    /** Poisha -> BDT float (display/report only, never for calculation). */
    public static function toBdt(int $poisha): float
    {
        return $poisha / self::SUBUNITS;
    }

    /** Format poisha as "৳1,234.56". */
    public static function format(int $poisha, bool $symbol = true): string
    {
        $bdt = number_format($poisha / self::SUBUNITS, 2, '.', ',');
        return $symbol ? self::SYMBOL.$bdt : $bdt;
    }

    /**
     * Percentage of an amount using integer basis points, rounded half-up.
     * e.g. percentOf(100000, 1000) = 10% of ৳1000 = ৳100 = 10000 poisha.
     */
    public static function percentOf(int $poisha, int $basisPoints): int
    {
        return intdiv($poisha * $basisPoints + 5000, 10000);
    }
}
