<?php

namespace App\Livewire\VenueStudio;

use App\Models\Venue;
use App\Models\VenueSpace;
use Livewire\Component;

/**
 * Real utilization off real bookings — a 90-day window (45 days either side
 * of today), not a guess. "Crowd-pressure analysis" and scheduling-style
 * suggestions ("move Session B into Hall A") stay out of Phase 1: there is
 * no rule-based way to derive them from what this venue actually knows about
 * itself yet. What ships here is every number a coordinator could otherwise
 * only get by opening every event booked at this venue and counting by hand.
 */
class CapacityTab extends Component
{
    public Venue $venue;

    private const WINDOW_DAYS = 90;

    public function render()
    {
        $windowStart = now()->subDays(self::WINDOW_DAYS / 2)->startOfDay();
        $windowEnd = now()->addDays(self::WINDOW_DAYS / 2)->startOfDay();

        $rows = $this->venue->spaces->map(function (VenueSpace $space) use ($windowStart, $windowEnd) {
            $bookedDays = $space->bookings
                ->filter(fn ($b) => $b->event?->starts_at)
                ->sum(function ($b) use ($windowStart, $windowEnd) {
                    $start = $b->event->starts_at->max($windowStart);
                    $end = ($b->event->ends_at ?? $b->event->starts_at)->min($windowEnd);

                    return $start->lte($end) ? $start->diffInDays($end) + 1 : 0;
                });

            $pct = min(100, (int) round($bookedDays / self::WINDOW_DAYS * 100));

            return [
                'space' => $space,
                'booked_days' => $bookedDays,
                'pct' => $pct,
                'flag' => match (true) {
                    $pct >= 85 => ['label' => 'High demand', 'tone' => 'risk'],
                    $bookedDays === 0 => ['label' => 'Underused', 'tone' => 'warn'],
                    default => null,
                },
            ];
        })->sortByDesc('pct')->values();

        return view('livewire.venue-studio.capacity-tab', [
            'rows' => $rows,
            'windowDays' => self::WINDOW_DAYS,
        ]);
    }
}
