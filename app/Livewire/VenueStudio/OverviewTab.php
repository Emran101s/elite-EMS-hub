<?php

namespace App\Livewire\VenueStudio;

use App\Models\Venue;
use App\Models\VenueSpace;
use Livewire\Component;

/**
 * Venue Studio's Digital Twin Workspace — two real view modes: Occupancy
 * (an event running in the space today) and Readiness (is the space's own
 * data complete). Floorplan/operational/risk/AV/visitor-flow modes are
 * deferred — each needs a data model (AV inventory, risk points) that does
 * not exist anywhere yet.
 */
class OverviewTab extends Component
{
    public Venue $venue;

    public string $mode = 'occupancy';

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['occupancy', 'readiness'], true) ? $mode : 'occupancy';
    }

    /**
     * Priority order is deliberate: missing capacity blocks Capacity
     * Intelligence outright, missing dimensions only blocks the floor-area
     * calc, and a fully-documented space that's simply never been booked is
     * a utilization fact, not a data-quality one.
     */
    private function readinessState(VenueSpace $space): string
    {
        return match (true) {
            empty($space->capacity_by_setup) => 'missing_capacity',
            $space->width_m === null || $space->length_m === null => 'missing_dimensions',
            $space->bookings->isEmpty() => 'never_booked',
            default => 'fully_documented',
        };
    }

    public function render()
    {
        $spaces = $this->venue->spaces;

        return view('livewire.venue-studio.overview-tab', [
            'zones' => $spaces->groupBy(fn ($s) => $s->floor_zone ?: 'Unassigned'),
            'mode' => $this->mode,
            'readinessBySpace' => $spaces->mapWithKeys(fn ($s) => [$s->id => $this->readinessState($s)]),
        ]);
    }
}
