<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * Finds the same resource promised to two events at once. The data has always
 * been here — venue bookings, team assignments, supplier engagements — this
 * service is the first thing that reads it *across* events.
 *
 * A venue or a person cannot be in two places: risk. A supplier can serve a
 * few engagements at once: only flagged past capacity.
 */
class ResourceConflicts
{
    /** Concurrent engagements a supplier can serve — matches the utilization card. */
    private const SUPPLIER_CAPACITY = 3;

    /** @return Collection<int, array{type:string, severity:string, label:string, detail:string, events:array}> */
    public function detect(): Collection
    {
        // Two events sharing a start date is exactly the case a venue clash
        // exists to catch, so orderBy('starts_at') alone leaves the pair
        // tied. SQLite happens to return tied rows in insertion order;
        // Postgres does not promise that, so which event showed first in a
        // clash card could flip between requests. The id tie-break makes it
        // the same order every time, on every driver.
        $events = Event::active()
            ->whereNotNull('starts_at')
            ->with(['venue', 'teamMembers', 'suppliers'])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        return collect()
            ->concat($this->venueClashes($events))
            ->concat($this->teamClashes($events))
            ->concat($this->supplierOverbooking($events))
            ->values();
    }

    /** Every pair of overlapping events sharing a venue. */
    private function venueClashes(Collection $events): Collection
    {
        return $this->clashingPairs(
            $events->filter(fn ($e) => $e->venue_id),
            fn ($e) => [$e->venue_id],
        )->map(fn ($clash) => [
            'type' => 'venue',
            'severity' => 'risk',
            'label' => $clash['events'][0]->venue->name,
            'detail' => $this->pairLine($clash['events']),
            'events' => $this->eventRefs($clash['events']),
        ]);
    }

    /** The same person on the team of two overlapping events. */
    private function teamClashes(Collection $events): Collection
    {
        $people = $events->flatMap(fn ($e) => $e->teamMembers->map(fn ($u) => ['user' => $u, 'event' => $e]))
            ->groupBy(fn ($row) => $row['user']->id);

        $out = collect();
        foreach ($people as $rows) {
            $pairs = $this->overlappingPairs(collect($rows)->pluck('event'));
            foreach ($pairs as $pair) {
                $out->push([
                    'type' => 'team',
                    'severity' => 'warn',
                    'label' => $rows[0]['user']->name,
                    'detail' => $this->pairLine($pair),
                    'events' => $this->eventRefs($pair),
                ]);
            }
        }

        return $out;
    }

    /** A supplier engaged past capacity on the same days. */
    private function supplierOverbooking(Collection $events): Collection
    {
        $suppliers = $events->flatMap(fn ($e) => $e->suppliers->map(fn ($s) => ['supplier' => $s, 'event' => $e]))
            ->groupBy(fn ($row) => $row['supplier']->id);

        $out = collect();
        foreach ($suppliers as $rows) {
            $concurrent = $this->maxConcurrent(collect($rows)->pluck('event'));
            if ($concurrent['count'] > self::SUPPLIER_CAPACITY) {
                $out->push([
                    'type' => 'supplier',
                    'severity' => 'warn',
                    'label' => $rows[0]['supplier']->name,
                    'detail' => $concurrent['count'].' overlapping engagements (capacity '.self::SUPPLIER_CAPACITY.')',
                    'events' => $this->eventRefs($concurrent['events']),
                ]);
            }
        }

        return $out;
    }

    /* ── mechanics ── */

    private function overlaps(Event $a, Event $b): bool
    {
        $aEnd = $a->ends_at ?? $a->starts_at;
        $bEnd = $b->ends_at ?? $b->starts_at;

        return $a->starts_at->lte($bEnd) && $aEnd->gte($b->starts_at);
    }

    /** All overlapping pairs within a set of events. */
    private function overlappingPairs(Collection $events): array
    {
        $events = $events->values();
        $pairs = [];
        foreach ($events as $i => $a) {
            foreach ($events->slice($i + 1) as $b) {
                if ($this->overlaps($a, $b)) {
                    $pairs[] = [$a, $b];
                }
            }
        }

        return $pairs;
    }

    /** Group events by a key (e.g. venue) and emit overlapping pairs per group. */
    private function clashingPairs(Collection $events, callable $keys): Collection
    {
        $out = collect();
        foreach ($events->groupBy($keys) as $group) {
            foreach ($this->overlappingPairs($group) as $pair) {
                $out->push(['events' => $pair]);
            }
        }

        return $out;
    }

    /** The busiest single day for a set of events: how many run at once. */
    private function maxConcurrent(Collection $events): array
    {
        $best = ['count' => 0, 'events' => []];
        foreach ($events as $e) {
            $day = $e->starts_at;
            $running = $events->filter(fn ($o) => $o->starts_at->lte($e->ends_at ?? $day)
                && ($o->ends_at ?? $o->starts_at)->gte($day))->values();
            if ($running->count() > $best['count']) {
                $best = ['count' => $running->count(), 'events' => $running->all()];
            }
        }

        return $best;
    }

    private function pairLine(array $pair): string
    {
        return collect($pair)
            ->map(fn ($e) => $e->name.' ('.$e->starts_at->format('j M').'–'.($e->ends_at ?? $e->starts_at)->format('j M').')')
            ->implode(' ↔ ');
    }

    private function eventRefs(array $events): array
    {
        return collect($events)->map(fn ($e) => ['id' => $e->id, 'name' => $e->name])->all();
    }
}
