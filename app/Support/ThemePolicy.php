<?php

namespace App\Support;

/**
 * ORBIT is light-first. Light is home; dark is a mode you enter for show days
 * and live operations. This decides the `data-theme` printed on <html>.
 * A per-session override (set from a header control, cleared on logout) wins.
 *
 * To invert the ratio later, change DEFAULT_THEME and move modules between the
 * two lists. Nothing else in the codebase changes.
 */
class ThemePolicy
{
    /** Change the product-wide default here and nowhere else. */
    protected const DEFAULT_THEME = 'light';

    /** Live operations. Everything else is light. */
    protected const DARK_MODULES = [
        'pulse', 'run-of-show', 'check-in', 'transport-board', 'twin',
    ];

    public static function for(?string $module = null, ?object $event = null): string
    {
        if (session()->has('theme.override')) {
            return session('theme.override') === 'dark' ? 'dark' : 'light';
        }

        // Show days go dark automatically.
        $phase = $event?->phase;
        if (is_object($phase) && method_exists($phase, 'prefersDarkTheme') && $phase->prefersDarkTheme()) {
            return 'dark';
        }

        return $module !== null && in_array($module, self::DARK_MODULES, true)
            ? 'dark'
            : self::DEFAULT_THEME;
    }

    public static function isDark(?string $module = null, ?object $event = null): bool
    {
        return static::for($module, $event) === 'dark';
    }

    public static function override(string $theme): void
    {
        abort_unless(in_array($theme, ['light', 'dark'], true), 422);
        session(['theme.override' => $theme]);
    }

    public static function clearOverride(): void
    {
        session()->forget('theme.override');
    }
}
