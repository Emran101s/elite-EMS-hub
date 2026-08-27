<?php

namespace App\Support;

use App\Models\Event;

/**
 * One place that knows how to write a sum of money.
 *
 * There were twenty-one of these — a closure named $money or $fmt, re-derived
 * per screen, per PDF, per card. Two of them disagreed on which currency a
 * figure was in (the JOD/USD mismatch on the contract, and a client record
 * that printed every deal as "JD" regardless of what currency it was actually
 * raised in) because the same fact was written down twenty-one times instead
 * of once. This is the once.
 *
 * Three shapes, because the app genuinely uses three:
 *  - forScreen     "$1,250" / "JD 1,250"   — the ordinary reading, everywhere
 *                  a figure just needs to be seen. Symbol, no decimals.
 *  - forDocument   "USD 1,250.00"          — a contract, invoice or proposal
 *                  quoting a figure someone will sign against. Currency
 *                  code (not a symbol, which can be ambiguous across
 *                  borders) and cents, because a legal document rounds
 *                  nothing.
 *  - abbreviated   "$1.2K"                 — a portfolio KPI tile, where six
 *                  figures of precision would just crowd the number that
 *                  matters: the shape of it.
 */
class Money
{
    /**
     * "$1,250" or "JD 1,250" — the everyday reading.
     *
     * $cents accepts float as well as int: a handful of columns (transport
     * cost, event-priced items, invoice lines) carry a tenth of a cent now
     * so a typed-in price can keep its third decimal — an int-only
     * signature would silently truncate that fraction right back off on
     * the way to the screen.
     */
    public static function forScreen(int|float|null $cents, string $currency): string
    {
        $symbol = Event::CURRENCIES[$currency][0] ?? $currency;
        $sep = strlen($symbol) > 1 ? ' ' : '';

        return $symbol.$sep.number_format(($cents ?? 0) / 100);
    }

    /**
     * "USD 1,250.00" — the currency code, and the cents, for a document
     * someone signs. $decimals defaults to 2 everywhere except the few
     * screens (Invoice Editor) that opt into a third decimal explicitly.
     */
    public static function forDocument(int|float|null $cents, string $currency, int $decimals = 2): string
    {
        return $currency.' '.number_format(($cents ?? 0) / 100, $decimals);
    }

    /**
     * "$1.2K" past a thousand currency units, "$850" below it — a portfolio
     * tile reads faster as a shape than as six digits.
     */
    public static function abbreviated(int|float|null $cents, string $currency): string
    {
        $symbol = Event::CURRENCIES[$currency][0] ?? $currency;
        $sep = strlen($symbol) > 1 ? ' ' : '';
        $cents ??= 0;

        $body = abs($cents) >= 100_000
            ? rtrim(rtrim(number_format($cents / 100_000, 1), '0'), '.').'K'
            : number_format($cents / 100);

        return $symbol.$sep.$body;
    }
}
