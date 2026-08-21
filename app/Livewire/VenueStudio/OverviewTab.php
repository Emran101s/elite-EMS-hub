<?php

namespace App\Livewire\VenueStudio;

use App\Models\Venue;
use Livewire\Component;

/**
 * Venue Studio's Digital Twin Workspace — Phase 1 scope is one real view: an
 * occupancy grid of the venue's own spaces, grouped by floor zone, coloured
 * by whether each space has an event running in it today. Floorplan/
 * operational/risk/AV/visitor-flow modes are deferred — each needs a data
 * model (AV inventory, risk points) that does not exist anywhere yet.
 */
class OverviewTab extends Component
{
    public Venue $venue;

    public function render()
    {
        $spaces = $this->venue->spaces;

        return view('livewire.venue-studio.overview-tab', [
            'zones' => $spaces->groupBy(fn ($s) => $s->floor_zone ?: 'Unassigned'),
        ]);
    }
}
