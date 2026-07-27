<?php

namespace App\View\Components;

use App\Models\Event;
use App\Services\EventHealthService;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The Event Radar, rehomed.
 *
 * It used to be a pinned panel in the Command Spine sidebar. With the sidebar
 * gone it lives behind an icon button in the top nav — same events, same
 * computed health, no permanent column.
 */
class EventRadar extends Component
{
    public function __construct(private EventHealthService $health) {}

    public function render(): View
    {
        // EventHealthService::RELATIONS, not a subset: scoring an event walks
        // all of them, and this component renders on every page.
        $events = Event::with(EventHealthService::RELATIONS)
            ->active()
            ->orderBy('starts_at')
            ->get()
            ->map(function (Event $event) {
                $breakdown = $this->health->breakdown($event);
                $event->radar_score = $breakdown['score'];
                $event->radar_group = $breakdown['group'];
                $event->radar_status = str($breakdown['status'])->replace('_', ' ')->title();

                return $event;
            });

        return view('components.event-radar', [
            'events' => $events,
            'attention' => $events->filter(fn (Event $e) => in_array($e->radar_group, ['risk', 'warn'], true))->count(),
        ]);
    }
}
