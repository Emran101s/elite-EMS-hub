<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventCommandHeader;
use App\Services\EventHealthService;
use Illuminate\View\View;

class EventHubController extends Controller
{
    /**
     * The tabs a hub URL may ask for.
     *
     * Read off Event::HUB_TABS rather than listed again here: a second list is
     * a list that goes stale, and when it does the tab simply stops opening —
     * the nav still offers it, the URL still looks right, and the page quietly
     * falls back to Overview.
     *
     * @return list<string>
     */
    public static function tabs(): array
    {
        return array_keys(Event::HUB_TABS);
    }

    public function show(Event $event, EventHealthService $healthService, EventCommandHeader $headerService): View
    {
        $tab = in_array(request('tab'), self::tabs(), true) ? request('tab') : 'overview';

        // A disabled module's tab falls back to Overview.
        if (! $event->moduleEnabled($tab)) {
            $tab = 'overview';
        }

        $event->load([
            'client', 'venue', 'projectManager',
            'rooms', 'cateringItems', 'agendaDays.sessions.room', 'agendaSessions.day',
            'tasks.assignee', 'budgetItems', 'suppliers', 'roomBlocks',
            'attendees', 'transport.manifest', 'transferGuests',
            'sponsors', 'risks.owner', 'approvals.requester',
            'teamMembers', 'speakers', 'brief', 'contract',
        ]);

        // approvals.decider: nothing outside the Approvals tab reads it —
        // Health/Header/Alerts only ever read requester. Loading it on
        // every tab paid for a relation nineteen tabs never touch.
        if ($tab === 'approvals') {
            $event->loadMissing('approvals.decider');
        }

        return view('events.hub', [
            'event' => $event,
            'tab' => $tab,
            'health' => $healthService->breakdown($event),
            // Everything the header says, counted off this event's own records.
            'header' => $headerService->for($event),
            'ai' => $healthService->aiSummary($event),
            'alerts' => $this->liveAlerts($event),
            'workload' => $this->teamWorkload($event),
        ]);
    }

    /**
     * Live alert feed — every line traces to a record.
     */
    private function liveAlerts(Event $event)
    {
        $alerts = collect();

        foreach ($event->approvals->where('status', 'pending') as $approval) {
            $alerts->push(['tone' => 'warn', 'title' => $approval->title.' awaiting approval',
                'sub' => ucfirst($approval->type).' approval', 'when' => $approval->created_at]);
        }

        foreach ($event->tasks->filter(fn ($t) => $t->isOpen() && $t->due_on?->isPast()) as $task) {
            $alerts->push(['tone' => 'risk', 'title' => $task->title.' overdue',
                'sub' => $task->assignee?->name ?? 'Unassigned', 'when' => $task->due_on]);
        }

        foreach ($event->risks->filter->isOpen()->sortByDesc->severity()->take(3) as $risk) {
            $alerts->push(['tone' => $risk->severity() >= 15 ? 'risk' : 'info', 'title' => $risk->title,
                'sub' => str($risk->category)->replace('_', ' ')->title().' risk · '.$risk->severity().'/25', 'when' => $risk->updated_at]);
        }

        // Money. An event forecast to blow its cap by half showed nothing here
        // — the number lived on one tab, and the feed that exists to say what
        // needs you never mentioned it.
        $cost = $event->costForecast();

        if ($cost['over'] > 0) {
            $alerts->push([
                'tone' => 'risk',
                'title' => 'Forecast is '.$event->money($cost['over']).' over budget',
                'sub' => $event->money($cost['forecast']).' against '.$event->money($cost['cap']).' · '.$cost['pct'].'%',
                'when' => $event->updated_at,
            ]);
        }

        // Commitments the budget cannot count. Rooms held at a rate nobody has
        // agreed are real; a budget that omits them silently is not.
        $pending = app(\App\Services\BudgetSync::class)->pending($event);

        if ($pending !== []) {
            $alerts->push([
                'tone' => 'warn',
                'title' => count($pending).' '.str('commitment')->plural(count($pending)).' not in the budget',
                'sub' => collect($pending)->take(2)->map(fn (array $p) => $p['module'].' · '.$p['what'])->implode(' · '),
                'when' => $event->updated_at,
            ]);
        }

        foreach ($event->suppliers->filter(fn ($s) => $s->pivot->status === 'issue') as $supplier) {
            $alerts->push(['tone' => 'risk', 'title' => 'Supplier issue: '.$supplier->name,
                'sub' => str($supplier->category)->replace('_', ' & ')->title(), 'when' => $supplier->updated_at]);
        }

        return $alerts->sortBy(fn ($a) => $a['tone'] === 'risk' ? 0 : 1)->take(5)->values();
    }

    /**
     * Open tasks on this event per team member vs a 5-task comfort capacity.
     */
    private function teamWorkload(Event $event)
    {
        return $event->teamMembers->map(function ($member) use ($event) {
            $open = $event->tasks->where('assignee_id', $member->id)->where('status', '!=', 'done')->count();

            return [
                'user' => $member,
                'open' => $open,
                'pct' => min(100, (int) round($open / 5 * 100)),
            ];
        })->sortByDesc('pct')->values();
    }
}
