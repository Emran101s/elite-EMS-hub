<?php

namespace App\Support;

/**
 * ORBIT status semantics — the single place a status becomes a colour, so no
 * Blade template ever hardcodes one. Maps to the ORBIT signal tokens
 * (resources/css/orbit-tokens.css): --vital / --ion / --plasma / --flare /
 * --critical for status, and --solar for prestige (which is NEVER a status).
 */
enum Tone: string
{
    case Solar = 'solar';       // prestige only: VIP, premium tier, brand — never a status
    case Vital = 'vital';       // done · healthy · paid · confirmed · on track
    case Ion = 'ion';           // in progress · informational
    case Plasma = 'plasma';     // planned · upcoming · scheduled
    case Flare = 'flare';       // pending · needs approval · watch
    case Critical = 'critical'; // overdue · at risk · over budget · blocked

    /** Health % → tone band (mirrors EventHealthService bands). */
    public static function forHealth(int $pct): self
    {
        return match (true) {
            $pct >= 81 => self::Vital,
            $pct >= 61 => self::Ion,
            $pct >= 41 => self::Flare,
            default => self::Critical,
        };
    }

    /** Common status strings across modules → a tone. Extend as modules migrate. */
    public static function forStatus(?string $status): self
    {
        return match ($status) {
            'done', 'completed', 'approved', 'paid', 'confirmed', 'signed', 'ready', 'delivered' => self::Vital,
            'in_progress', 'doing', 'active', 'sent', 'contracted', 'in_production' => self::Ion,
            'planned', 'upcoming', 'scheduled', 'draft', 'todo', 'registered', 'invited' => self::Plasma,
            'pending', 'review', 'needs_approval', 'quoted', 'partially_signed', 'expected' => self::Flare,
            'overdue', 'blocked', 'issue', 'cancelled', 'rejected', 'void', 'no_show' => self::Critical,
            default => self::Ion,
        };
    }

    /** The ORBIT CSS var this tone reads (e.g. "var(--vital)"). */
    public function var(): string
    {
        return 'var(--'.$this->value.')';
    }

    /** The soft background var (e.g. "var(--vital-bg)"). */
    public function bg(): string
    {
        return 'var(--'.$this->value.'-bg)';
    }
}
