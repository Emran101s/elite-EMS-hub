<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * The one place Agenda Builder decides what counts as a problem — a real
 * scheduling clash (the same room or the same person double-booked), or a
 * session that isn't complete enough to trust yet (no room, no speaker,
 * oversold, or a speaker who hasn't confirmed). Every finding carries a
 * severity from the same real logic whether it's checked for one day or the
 * whole event, so the day banner, the Inspector, every Agenda view, and the
 * event-wide Clash Check all agree with each other by construction — they
 * read the same findings, not five separate implementations of "is this a
 * problem."
 *
 * A finding is a plain array:
 *   type        one of TYPE_LABELS' keys
 *   severity    critical | high | medium | low
 *   message     one sentence, already speaks for itself
 *   sessions    [sessionId => agendaDayId, …] — every session this touches
 *   roomName    the room involved, or null
 *   speakerName the person involved, or null
 */
class AgendaConflicts
{
    public const TYPE_LABELS = [
        'room_overlap' => 'Room overlap',
        'speaker_overlap' => 'Speaker overlap',
        'missing_speaker' => 'Missing speaker',
        'missing_room' => 'Missing room',
        'capacity_overflow' => 'Capacity overflow',
        'unconfirmed_speaker' => 'Unconfirmed speaker',
    ];

    public const SEVERITY_ORDER = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

    /**
     * Every finding for one day's sessions — the overlap check the board has
     * always run, plus the completeness checks (missing room, missing
     * speaker, capacity, unconfirmed) at their own severities.
     */
    public function forDay(Collection $sessions): Collection
    {
        return $this->overlaps($sessions)->merge($this->completeness($sessions))->values();
    }

    /**
     * Every finding across the whole event: each day's own findings, plus
     * the one thing no single day can see on its own — two day entries that
     * genuinely share the same calendar date, whose sessions can actually
     * overlap in real time even though the board draws them as separate
     * lanes.
     *
     * A speaker simply appearing on more than one day is deliberately not a
     * finding here — different days cannot overlap in time, and the
     * platform has no speaker-availability data to check against. Reporting
     * that as a "conflict" would be inventing one, not finding one.
     */
    public function forEvent(Event $event, Collection $days): Collection
    {
        $findings = collect();

        foreach ($days as $day) {
            $findings = $findings->merge($this->forDay($day->sessions));
        }

        return $findings->merge($this->crossDayOverlaps($days))->values();
    }

    /**
     * Collapse a findings collection into the [dayId => [sessionId =>
     * [message, …]]] shape the board's banner, pins and rings already read
     * — optionally limited to the severities serious enough to earn the red
     * ring, so extending detection event-wide never changes what a block
     * already looked like.
     *
     * @return array<int, array<int, array<int, string>>>
     */
    public function bySessionAndDay(Collection $findings, ?array $severities = null): array
    {
        $map = [];
        foreach ($findings as $f) {
            if ($severities && ! in_array($f['severity'], $severities, true)) {
                continue;
            }
            foreach ($f['sessions'] as $sessionId => $dayId) {
                $map[$dayId][$sessionId][] = $f['message'];
            }
        }

        return $map;
    }

    /** Every finding that touches one particular session, worst severity first. */
    public function forSession(Collection $findings, int $sessionId): Collection
    {
        return $findings
            ->filter(fn ($f) => array_key_exists($sessionId, $f['sessions']))
            ->sortBy(fn ($f) => self::SEVERITY_ORDER[$f['severity']] ?? 9)
            ->values();
    }

    /**
     * The one severity-visibility lookup every Agenda surface shares:
     * [sessionId => worst severity touching it]. A session with both a
     * critical room clash and a low missing-detail note reports as
     * critical — the same "worst thing wins" rule the Inspector and Clash
     * Check already sort by, now available per session for a block, a row,
     * or a card to pick its own visual weight from.
     *
     * @return array<int, string>
     */
    public function severityBySession(Collection $findings): array
    {
        $map = [];
        foreach ($findings as $f) {
            $rank = self::SEVERITY_ORDER[$f['severity']] ?? 9;
            foreach (array_keys($f['sessions']) as $sessionId) {
                $current = $map[$sessionId] ?? null;
                if ($current === null || $rank < (self::SEVERITY_ORDER[$current] ?? 9)) {
                    $map[$sessionId] = $f['severity'];
                }
            }
        }

        return $map;
    }

    /** Same-room / same-speaker overlaps within one set of sessions. */
    private function overlaps(Collection $sessions, bool $crossDay = false): Collection
    {
        $findings = collect();

        foreach ($sessions as $a) {
            foreach ($sessions as $b) {
                // Each unordered pair once — both sessions still end up
                // flagged, since bySessionAndDay() reads every session
                // listed on the finding, not just the first.
                if ($a->id >= $b->id) {
                    continue;
                }
                if (! ($a->starts_at < $b->ends_at && $a->ends_at > $b->starts_at)) {
                    continue;
                }

                $suffix = $crossDay ? ' — two day entries share this date' : '';

                if ($a->room_id && $a->room_id === $b->room_id) {
                    $findings->push([
                        'type' => 'room_overlap',
                        'severity' => 'critical',
                        'message' => 'Room "'.$a->room?->name.'" double-booked: "'.$a->title.'" and "'.$b->title.'"'.$suffix,
                        'sessions' => [$a->id => $a->agenda_day_id, $b->id => $b->agenda_day_id],
                        'roomName' => $a->room?->name,
                        'speakerName' => null,
                    ]);
                }

                // The same person billed on two overlapping sessions.
                $shared = $a->speakers->pluck('name', 'id')->intersectByKeys($b->speakers->pluck('name', 'id'));
                foreach ($shared as $name) {
                    $findings->push([
                        'type' => 'speaker_overlap',
                        'severity' => 'critical',
                        'message' => $name.' is double-booked: "'.$a->title.'" and "'.$b->title.'"'.$suffix,
                        'sessions' => [$a->id => $a->agenda_day_id, $b->id => $b->agenda_day_id],
                        'roomName' => null,
                        'speakerName' => $name,
                    ]);
                }
            }
        }

        return $findings;
    }

    /**
     * The one cross-day case that is a real time overlap rather than an
     * invented one: two AgendaDay rows that happen to carry the same real
     * date. Ordinary events never trigger this — every day has its own
     * date — but if two ever do share one (a duplicated day, a data-entry
     * slip), their sessions really are on the same clock regardless of
     * which lane the board drew them in.
     */
    private function crossDayOverlaps(Collection $days): Collection
    {
        $findings = collect();

        $byDate = $days->filter(fn ($d) => $d->date)->groupBy(fn ($d) => $d->date->toDateString());

        foreach ($byDate as $sameDateDays) {
            if ($sameDateDays->count() < 2) {
                continue;
            }
            $findings = $findings->merge($this->overlaps($sameDateDays->flatMap->sessions, crossDay: true));
        }

        return $findings;
    }

    /** Missing room, missing speaker, oversold capacity, an unconfirmed person billed. */
    private function completeness(Collection $sessions): Collection
    {
        $findings = collect();
        $settled = ['confirmed', 'final'];

        foreach ($sessions as $s) {
            if ($s->speakers->isEmpty()) {
                $findings->push([
                    'type' => 'missing_speaker',
                    'severity' => in_array($s->status, $settled, true) ? 'high' : 'medium',
                    'message' => '"'.$s->title.'" has no speaker billed',
                    'sessions' => [$s->id => $s->agenda_day_id],
                    'roomName' => null,
                    'speakerName' => null,
                ]);
            }

            if ($s->room_id === null) {
                $findings->push([
                    'type' => 'missing_room',
                    'severity' => in_array($s->status, $settled, true) ? 'high' : 'low',
                    'message' => '"'.$s->title.'" has no room assigned',
                    'sessions' => [$s->id => $s->agenda_day_id],
                    'roomName' => null,
                    'speakerName' => null,
                ]);
            }

            if ($s->room?->capacity && $s->capacity && $s->capacity > $s->room->capacity) {
                $findings->push([
                    'type' => 'capacity_overflow',
                    'severity' => 'high',
                    'message' => '"'.$s->title.'" seats '.$s->capacity.' but '.$s->room->name.' holds '.number_format($s->room->capacity),
                    'sessions' => [$s->id => $s->agenda_day_id],
                    'roomName' => $s->room->name,
                    'speakerName' => null,
                ]);
            }

            foreach ($s->speakers as $sp) {
                if ($sp->status !== 'confirmed') {
                    $findings->push([
                        'type' => 'unconfirmed_speaker',
                        'severity' => 'medium',
                        'message' => $sp->name.' ('.$sp->status.') is billed on "'.$s->title.'"',
                        'sessions' => [$s->id => $s->agenda_day_id],
                        'roomName' => null,
                        'speakerName' => $sp->name,
                    ]);
                }
            }
        }

        return $findings;
    }
}
