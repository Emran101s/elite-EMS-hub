<?php

namespace App\Support;

/**
 * ORBIT status semantics — the single place a status becomes a colour, so no
 * Blade template ever hardcodes one. Maps to the ORBIT signal tokens in
 * resources/css/orbit-tokens.css.
 *
 * Each tone has two values there and they are NOT interchangeable:
 *   --vital      reads (text, icons, labels)
 *   --vital-lit  fills (rings, meters, active states)
 * Same for gold, ion, plasma, flare and critical.
 */
enum Tone: string
{
    case Gold = 'gold';         // the current position / VIP / the one key number
    case Vital = 'vital';       // done, healthy, paid, confirmed, ready
    case Ion = 'ion';           // in progress, informational
    case Plasma = 'plasma';     // planned, upcoming
    case Flare = 'flare';       // pending, needs approval, watch
    case Critical = 'critical'; // overdue, at risk, over budget
    case Neutral = 'neutral';   // draft, archived, not started

    public static function forHealth(int $percent): self
    {
        return match (true) {
            $percent >= 85 => self::Vital,
            $percent >= 70 => self::Ion,
            $percent >= 60 => self::Flare,
            default => self::Critical,
        };
    }

    public static function forTask(string $status, bool $overdue = false): self
    {
        if ($overdue) {
            return self::Critical;
        }

        return match ($status) {
            'done', 'completed', 'approved' => self::Vital,
            'in_progress' => self::Ion,
            'planned', 'upcoming' => self::Plasma,
            'need_approval', 'pending' => self::Flare,
            'cancelled', 'draft' => self::Neutral,
            default => self::Ion,
        };
    }

    /** Positive variance means under budget. */
    public static function forVariance(float $variance): self
    {
        return match (true) {
            $variance > 0 => self::Vital,
            $variance == 0 => self::Neutral,
            default => self::Critical,
        };
    }

    /** Common status strings across modules → a tone. Extend as modules migrate. */
    public static function forStatus(?string $status): self
    {
        return match ($status) {
            'done', 'completed', 'approved', 'paid', 'confirmed', 'signed', 'ready', 'delivered' => self::Vital,
            'in_progress', 'doing', 'active', 'sent', 'contracted', 'in_production' => self::Ion,
            'planned', 'upcoming', 'scheduled', 'todo', 'registered', 'invited' => self::Plasma,
            'pending', 'review', 'needs_approval', 'need_approval', 'quoted', 'partially_signed', 'expected' => self::Flare,
            'overdue', 'blocked', 'issue', 'rejected', 'void', 'no_show' => self::Critical,
            'draft', 'cancelled', 'archived' => self::Neutral,
            default => self::Ion,
        };
    }

    /** The READ colour — text, icons, labels. */
    public function var(): string
    {
        return $this === self::Neutral ? 'var(--ink-3)' : "var(--{$this->value})";
    }

    /** The FILL colour — rings, meters, bars, active states. Never text on a light surface. */
    public function lit(): string
    {
        return $this === self::Neutral ? 'var(--rim-hi)' : "var(--{$this->value}-lit)";
    }

    /** The tint — a surface wash sitting behind the read colour. */
    public function tint(): string
    {
        return $this === self::Neutral ? 'var(--deck)' : "var(--{$this->value}-tint)";
    }
}
