<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Services\CommandCenterService;
use App\Services\EventHealthService;
use Illuminate\View\View;

/**
 * The Flow — a design prototype, not a shipped screen.
 *
 * Real records on a proposed visual language: no dark panel, navigation as
 * pills on top, events as soft cards in lanes with their route drawn between
 * them. Its own route, so nothing in the platform depends on it.
 */
class FlowBoardController extends Controller
{
    public function __invoke(CommandCenterService $pulse, EventHealthService $healthService): View
    {
        $channels = $pulse->islands()->load('teamMembers')->map(function (Event $event) use ($healthService) {
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
                // The people on it — the reference boards lead with faces.
                'team' => $event->teamMembers->take(4)->map(fn (User $u) => [
                    'initials' => str($u->name)->explode(' ')->take(2)->map(fn ($p) => str($p)->substr(0, 1))->implode(''),
                    'name' => $u->name,
                ])->values()->all(),
                'teamMore' => max(0, $event->teamMembers->count() - 4),
                // Which of the three lanes this event sits in.
                'lane' => match ($event->stage) {
                    'draft', 'proposal' => 0,
                    'confirmed', 'planning' => 1,
                    default => 2,
                },
            ];
        })->values();

        return view('concept.flow', [
            'channels' => $channels,
            'stats' => $pulse->stats(),
            'spend' => $pulse->portfolioSpend(),
            'alerts' => $pulse->alerts()->take(5),
        ]);
    }
}
