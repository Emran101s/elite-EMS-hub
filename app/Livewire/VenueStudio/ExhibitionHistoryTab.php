<?php

namespace App\Livewire\VenueStudio;

use App\Models\EventExhibitionHall;
use App\Models\Venue;
use Livewire\Component;

/**
 * Read-only, deliberately — booths are a sales concept tied to one event's
 * own exhibitors and revenue target (App\Livewire\ExhibitionFloorPlan), not
 * portable to a bare Venue with no exhibitors of its own. This tab does not
 * invent venue-level booth-sales machinery; it rolls up what already
 * happened, hall by hall, and links out to each event's own floor plan for
 * anything that needs changing.
 */
class ExhibitionHistoryTab extends Component
{
    public Venue $venue;

    public function render()
    {
        $halls = EventExhibitionHall::whereHas('event', fn ($q) => $q->where('venue_id', $this->venue->id))
            ->with(['event', 'booths.exhibitor'])
            ->get()
            ->sortByDesc(fn ($hall) => $hall->event?->starts_at)
            ->values();

        $rows = $halls->map(function (EventExhibitionHall $hall) {
            $sold = $hall->booths->filter(fn ($b) => $b->status() === 'sold');

            return [
                'hall' => $hall,
                'booths' => $hall->booths->count(),
                'sold' => $sold->count(),
                'revenue' => $sold->sum('price_cents'),
            ];
        });

        return view('livewire.venue-studio.exhibition-history-tab', ['rows' => $rows]);
    }
}
