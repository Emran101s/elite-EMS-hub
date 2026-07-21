<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Shapes a day's agenda sessions into a "program board" — time blocks with the
 * Main Hall promoted to a Main Stage and parallel tracks beside it. Shared by the
 * Agenda tab (screen) and the program PDF so both render identically.
 */
class AgendaProgram
{
    /** track name → [visual kind, mark glyph]. */
    private const TRACK_META = [
        'Main Stage' => ['main', '★'],
        'Plenary' => ['plen', '◆'],
        'Ceremony' => ['cer', '◆'],
        'Networking' => ['net', ''],
        'Hospitality' => ['net', ''],
        'Track' => ['track', ''],
    ];

    /**
     * Tracks that run in the background all day — folded into a "throughout the
     * day" strip rather than occupying a slot. This is a property of the track,
     * never of duration: a six-hour workshop is a session, arrivals are not.
     */
    private const AMBIENT = ['Exhibition', 'Media', 'Registration', 'Partnerships', 'Setup', 'Logistics'];

    /**
     * Back-of-house tracks a delegate never attends. Hidden from the public
     * programme; the internal one keeps them (ops needs build and press times).
     */
    public const CREW_ONLY = ['Setup', 'Media', 'Registration', 'Partnerships'];

    public const AUDIENCES = ['internal' => 'Internal', 'public' => 'Public'];

    /** Card ordering within a block (Main Stage first, ambient last). */
    private const RANK = ['main' => 1, 'plen' => 2, 'cer' => 2, 'track' => 5, 'net' => 6];

    /**
     * Sessions starting within this many minutes of each other are the same
     * slot. Real programmes stagger parallel starts (13:30 and 14:00), so exact
     * equality would split them into separate rows and hide the parallelism.
     */
    private const SLOT_TOLERANCE = 30;

    /** True when a day has nothing left to show for this audience. */
    public static function isEmpty(array $program): bool
    {
        return empty($program['blocks']) && empty($program['ambient']);
    }

    /**
     * @param  string  $audience  'internal' (everything) or 'public' (delegate-facing only)
     * @return array{blocks: array, ambient: array}
     */
    public function forDay(Collection $sessions, string $audience = 'internal'): array
    {
        if ($audience === 'public') {
            $sessions = $sessions->reject(fn ($s) => in_array($s->track, self::CREW_ONLY, true));
        }

        // Ambient is a property of the track, never of duration — a genuine
        // six-hour workshop belongs on the programme, not in a footnote pill.
        [$ambient, $timed] = $sessions->partition(fn ($s) => in_array($s->track, self::AMBIENT, true));

        return [
            // A programme carrying unapproved sessions must say so, loudly.
            'unconfirmed' => $sessions->reject(fn ($s) => $s->isSettled())->count(),
            'total' => $sessions->count(),
            'blocks' => $this->blocks($timed),
            'ambient' => $ambient
                ->sortBy(fn ($s) => substr((string) $s->starts_at, 0, 5))
                ->map(fn ($s) => [
                    'title' => $s->title,
                    'room' => $this->shortRoom($s->room?->name),
                    'time' => $this->range($s),
                ])->values()->all(),
        ];
    }

    /**
     * Group sessions into slots of concurrent work: consecutive sessions whose
     * starts fall within SLOT_TOLERANCE of the slot's start sit side by side.
     * Each card carries its own time whenever it differs from the slot, so a
     * block header can never misreport a session that runs long or short.
     */
    private function blocks(Collection $timed): array
    {
        $min = fn ($t) => (int) substr((string) $t, 0, 2) * 60 + (int) substr((string) $t, 3, 2);
        $fmt = fn (int $m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);

        $slots = [];
        foreach ($timed->sortBy(fn ($s) => $min($s->starts_at))->values() as $s) {
            $start = $min($s->starts_at);
            $end = $min($s->ends_at);
            $last = count($slots) - 1;

            // Same slot only if it genuinely overlaps AND starts close by — back-to-back
            // sessions (10:00–10:30 then 10:30–12:00) are sequential, not parallel.
            $overlaps = $last >= 0 && $start < $slots[$last]['end'];

            if ($overlaps && $start - $slots[$last]['start'] <= self::SLOT_TOLERANCE) {
                $slots[$last]['sessions'][] = $s;
                $slots[$last]['end'] = max($slots[$last]['end'], $end);

                continue;
            }
            $slots[] = ['start' => $start, 'end' => $end, 'sessions' => [$s]];
        }

        return array_map(function (array $slot) use ($min, $fmt) {
            $cards = collect($slot['sessions'])
                ->map(function ($s) use ($min, $slot) {
                    $card = $this->card($s);
                    // Only label a card's time when it differs from its slot.
                    $card['ownTime'] = ($min($s->starts_at) !== $slot['start'] || $min($s->ends_at) !== $slot['end'])
                        ? $this->range($s)
                        : null;

                    return $card;
                })
                ->sortBy(fn ($c) => self::RANK[$c['kind']] ?? 9)
                ->values();

            return [
                'time' => $fmt($slot['start']),
                'end' => $fmt($slot['end']),
                'full' => $cards->count() === 1,
                'cards' => $cards->all(),
            ];
        }, $slots);
    }

    private function card($s): array
    {
        [$kind, $mark] = $this->meta($s);
        $room = $this->shortRoom($s->room?->name);

        return [
            'session' => $s,
            'kind' => $kind,
            'mark' => $mark,
            'room' => $room,
            'kicker' => $kind === 'track' ? ($room ?: 'Track') : (self::TRACK_META_LABELS[$kind] ?? 'Session'),
            'time' => $this->range($s),
            'ownTime' => null,
            'settled' => $s->isSettled(),
            'status' => $s->statusLabel(),
        ];
    }

    /** kind → display label for the card kicker. */
    private const TRACK_META_LABELS = [
        'main' => 'Main Stage', 'plen' => 'Plenary', 'cer' => 'Ceremony', 'net' => 'Networking',
    ];

    /** Resolve [kind, mark] from the session's track, falling back to its type. */
    private function meta($s): array
    {
        if ($s->track && isset(self::TRACK_META[$s->track])) {
            return self::TRACK_META[$s->track];
        }

        return match (true) {
            in_array($s->type, ['break', 'lunch', 'networking'], true) => ['net', ''],
            in_array($s->type, ['opening', 'keynote'], true) => ['plen', '◆'],
            $s->type === 'closing' => ['cer', '◆'],
            default => ['track', ''],
        };
    }

    private function range($s): string
    {
        return substr((string) $s->starts_at, 0, 5).'–'.substr((string) $s->ends_at, 0, 5);
    }

    private function shortRoom(?string $name): string
    {
        if (! $name) {
            return '';
        }

        return trim(str_replace(
            [' — Room', 'Main Summit Hall', 'Networking Corridor', 'Exhibition Halls'],
            ['', 'Main Hall', 'Corridor', 'Exhibition'],
            $name
        ));
    }
}
