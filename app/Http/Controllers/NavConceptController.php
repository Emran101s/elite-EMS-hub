<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Event;
use App\Models\Task;
use App\Support\Workflow;
use Illuminate\View\View;

/**
 * A navigation concept, not a shipped screen.
 *
 * Two tiers: a narrow dark rail carrying the areas, and a light panel carrying
 * what is inside the area you are in. The dark part is 56px of icon rail, not
 * the 292px panel that came out earlier — the rule that a screen gets one dark
 * accent still holds, and this is it.
 *
 * Real counts throughout, because a nav design that has not met real data is a
 * drawing. Its own route; nothing in the platform imports it.
 */
class NavConceptController extends Controller
{
    public function __invoke(): View
    {
        $events = Event::whereNull('archived_at')
            ->withCount(['tasks as open_tasks' => fn ($q) => $q->whereNotIn('status', ['done', 'cancelled'])])
            ->with('client')
            ->orderByDesc('starts_at')
            ->get();

        // The tree: stages that actually have events in them, events beneath.
        // An empty stage is a folder nobody wants, so it is not drawn.
        $tree = collect(Workflow::SETS['event_stage']['states'])
            ->map(fn ($_, string $stage) => [
                'key' => $stage,
                'label' => Workflow::label('event_stage', $stage),
                'color' => Workflow::color('event_stage', $stage),
                'events' => $events->where('stage', $stage)->values(),
            ])
            ->filter(fn (array $group) => $group['events']->isNotEmpty())
            ->values();

        return view('concept.nav', [
            'user' => auth()->user(),
            'tree' => $tree,
            'eventCount' => $events->count(),
            'openTasks' => Task::whereNotIn('status', ['done', 'cancelled'])->count(),
            'pipeline' => collect(Deal::OPEN)->map(fn (string $stage) => [
                'key' => $stage,
                'label' => Workflow::label('deal_stage', $stage),
                'color' => Workflow::color('deal_stage', $stage),
                'count' => Deal::where('stage', $stage)->count(),
            ]),
            'recent' => Event::whereNull('archived_at')->latest('updated_at')->take(3)->get(),
            'archived' => Event::whereNotNull('archived_at')->count(),
        ]);
    }
}
