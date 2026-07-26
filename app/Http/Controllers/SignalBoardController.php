<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\CommandCenterService;
use App\Services\EventHealthService;
use Illuminate\View\View;

/**
 * The Signal Board — a design prototype, not a shipped screen.
 *
 * Real records, new visual language: navy as a line rather than a slab, events
 * as aligned channel strips rather than cards, and a board you can drive.
 * Lives on its own route so nothing in the platform depends on it.
 */
class SignalBoardController extends Controller
{
    public function __invoke(CommandCenterService $pulse, EventHealthService $healthService): View
    {
        $channels = $pulse->islands()->map(function (Event $event) use ($healthService) {
            $health = $healthService->breakdown($event);
            $open = $event->tasks->filter->isOpen();

            return [
                'id' => $event->id,
                'name' => $event->name,
                'stage' => str($event->stage)->replace('_', ' ')->title(),
                'where' => trim(($event->city ?: '').($event->country ? ', '.$event->country : ''), ', ') ?: 'Location TBC',
                'starts' => $event->starts_at,
                'days' => $event->starts_at ? (int) now()->startOfDay()->diffInDays($event->starts_at->startOfDay(), false) : null,
                'score' => $health['score'],
                'group' => $health['group'],
                'status' => str($health['status'])->replace('_', ' ')->title(),
                'href' => route('events.hub', $event),
                'x' => $event->pos_x,
                'y' => $event->pos_y,
                // The four channels every event carries. null = nothing routed yet.
                'meters' => [
                    ['Budget', $health['components']['budget']],
                    ['Tasks', $health['components']['tasks']],
                    ['Suppliers', $health['components']['suppliers']],
                    ['Risk', $health['components']['risk']],
                ],
                'pax' => (int) ($event->expected_participants ?: 0),
                'open' => $open->count(),
                'overdue' => $open->filter(fn ($t) => $t->due_on?->isPast())->count(),
                'risks' => $event->open_risks,
                'budget' => (int) $event->budget_cents,
                'currency' => $event->currencySymbol(),
            ];
        })->values();

        return view('concept.signal-board', [
            'channels' => $channels,
            'stats' => $pulse->stats(),
            'spend' => $pulse->portfolioSpend(),
            'alerts' => $pulse->alerts()->take(5),
        ]);
    }
}
