<?php

namespace App\Support;

/**
 * ORBIT is dark-first — dark is the operations default; light is reserved for
 * planning, document and print surfaces (per the design's token comments).
 * This decides the `data-theme` printed on <html>. A per-session override
 * (set from a header control, cleared on logout) always wins.
 */
class ThemePolicy
{
    /** Modules that read as light "paper" surfaces rather than dark operations. */
    protected static array $light = ['brief', 'contract', 'files', 'documents', 'reports'];

    public static function for(?string $module = null): string
    {
        if (session()->has('theme.override')) {
            return session('theme.override') === 'light' ? 'light' : 'dark';
        }

        return $module !== null && in_array($module, static::$light, true) ? 'light' : 'dark';
    }

    /** Is this module a light "paper" surface? */
    public static function isLight(?string $module): bool
    {
        return static::for($module) === 'light';
    }
}
