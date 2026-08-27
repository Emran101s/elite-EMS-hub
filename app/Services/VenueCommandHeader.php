<?php

namespace App\Services;

use App\Models\Venue;
use App\Models\VenueDocument;
use Illuminate\Support\Collection;

/**
 * Everything Venue Studio's header, KPI strip, and Command Tower say about a
 * venue, in one place — mirrors EventCommandHeader's role for the Event Hub.
 *
 * A venue never had this before: VenuesManager's only computed figure was a
 * raw events_count. Every figure here is counted off the venue's own real
 * records (its spaces, its bookings, ResourceConflicts' existing overlap
 * detection) — nothing invented, nothing stored separately from the records
 * it describes.
 */
class VenueCommandHeader
{
    public function __construct(private ResourceConflicts $conflicts) {}

    /** The whole header, in the order it is drawn. */
    public function for(Venue $venue): array
    {
        return [
            'scale' => $this->scale($venue),
            'health' => $this->health($venue),
            'upcoming' => $this->upcoming($venue),
            'conflicts' => $this->conflicts->forVenue($venue),
            'readiness' => $this->readiness($venue),
            'contract' => $this->contractStatus($venue),
        ];
    }

    /**
     * The venue's own most recent contract-category document, if one has
     * been filed — real data once it exists, never a synthetic status. Reads
     * the already-eager-loaded $venue->documents collection, no extra query.
     */
    public function contractStatus(Venue $venue): ?array
    {
        $doc = $venue->documents->where('category', 'contract')->sortByDesc('created_at')->first();

        if (! $doc) {
            return null;
        }

        [$label, $class] = VenueDocument::contractStatusMeta()[$doc->status] ?? [ucfirst((string) $doc->status), 'bg-navy-50 text-navy-500'];

        return ['status' => $doc->status, 'label' => $label, 'class' => $class, 'document' => $doc];
    }

    /** The figures you would say out loud. */
    public function scale(Venue $venue): array
    {
        $spaces = $venue->spaces;
        $maxCapacity = $spaces->max('capacity') ?? $venue->capacity;

        return [
            ['label' => 'Spaces', 'value' => (string) $spaces->count(), 'icon' => 'building'],
            ['label' => 'Max capacity', 'value' => $maxCapacity ? number_format($maxCapacity) : '—', 'icon' => 'users'],
            ['label' => 'Events hosted', 'value' => (string) $venue->events()->count(), 'icon' => 'calendar'],
            ['label' => 'Type', 'value' => $venue->type ?: 'Unspecified', 'icon' => $venue->isHotel() ? 'home' : 'building'],
        ];
    }

    /**
     * Rule-based, and it says so — not a stored score, recomputed every time
     * from real numbers: a real double-booking is the sharpest signal a
     * venue can carry, so it dominates; missing capacity data is a real gap
     * (Capacity Intelligence cannot compute utilization for a space with no
     * numbers), so it costs points too.
     */
    public function health(Venue $venue): array
    {
        $spaces = $venue->spaces;
        $conflictCount = $this->conflicts->forVenue($venue)->count();

        $score = 100;
        $score -= min(60, $conflictCount * 30);

        if ($spaces->isNotEmpty()) {
            $undocumented = $spaces->reject->isFullyDocumented()->count();
            $score -= (int) round($undocumented / $spaces->count() * 25);
        } else {
            $score -= 25;
        }

        $score = max(0, $score);

        $status = match (true) {
            $conflictCount > 0 => 'risk',
            $score >= 85 => 'track',
            $score >= 60 => 'warn',
            default => 'risk',
        };

        return ['score' => $score, 'status' => $status];
    }

    /** Next events booked here, soonest first. */
    public function upcoming(Venue $venue): Collection
    {
        return $venue->events()->active()
            ->whereNotNull('starts_at')
            ->where('starts_at', '>=', now()->startOfDay())
            ->orderBy('starts_at')
            ->take(5)
            ->get();
    }

    /** Can this venue's record actually be relied on — the gates, and how many are met. */
    public function readiness(Venue $venue): array
    {
        $spaces = $venue->spaces;

        $gates = [
            ['label' => 'Spaces defined', 'met' => $spaces->isNotEmpty(),
                'note' => $spaces->isNotEmpty() ? $spaces->count().' on file' : 'No spaces added yet'],
        ];

        if ($spaces->isNotEmpty()) {
            $undocumented = $spaces->reject->isFullyDocumented()->count();
            $gates[] = ['label' => 'Capacity documented', 'met' => $undocumented === 0,
                'note' => $undocumented === 0 ? 'Every space has setup capacities and dimensions' : $undocumented.' space(s) missing capacity or dimension data'];
        }

        $hasContact = (bool) ($venue->contact_name || $venue->contact_phone || $venue->contact_email);
        $gates[] = ['label' => 'Contact on file', 'met' => $hasContact,
            'note' => $hasContact ? ($venue->contact_name ?: 'Contact set') : 'No contact recorded'];

        $met = collect($gates)->where('met', true)->count();

        return [
            'gates' => $gates,
            'met' => $met,
            'total' => count($gates),
            'pct' => count($gates) > 0 ? (int) round($met / count($gates) * 100) : 0,
        ];
    }
}
