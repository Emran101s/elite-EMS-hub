<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CurrencyService
{
    /** Pegged fallbacks (JOD is pegged to USD since 1995). */
    private const PEG = [
        'USD:JOD' => 0.709,
        'JOD:USD' => 1.410,
    ];

    /**
     * Exchange rate from one currency to another, cached 12h from a free
     * live source, falling back to the peg (or 1.0) when offline.
     */
    public function rate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) {
            return 1.0;
        }

        // Never hit the network during tests.
        if (app()->environment('testing')) {
            return self::PEG["$from:$to"] ?? 1.0;
        }

        return (float) Cache::remember("fx:$from:$to", now()->addHours(12), function () use ($from, $to) {
            try {
                $res = Http::timeout(4)->get("https://open.er-api.com/v6/latest/{$from}");
                $rate = data_get($res->json(), "rates.{$to}");
                if (is_numeric($rate) && $rate > 0) {
                    return round((float) $rate, 4);
                }
            } catch (\Throwable) {
                // fall through to peg
            }

            return self::PEG["$from:$to"] ?? 1.0;
        });
    }

    /** True when the cached rate is a live fetch (not the raw peg constant). */
    public function isLive(string $from, string $to): bool
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to || app()->environment('testing')) {
            return false;
        }

        return abs($this->rate($from, $to) - (self::PEG["$from:$to"] ?? -1)) > 0.0001;
    }

    /** Drop the cached rate so the next read re-fetches. */
    public function refresh(string $from, string $to): void
    {
        Cache::forget('fx:'.strtoupper($from).':'.strtoupper($to));
    }
}
