<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventHealthService;
use Illuminate\View\View;

class EventHubController extends Controller
{
    public const TABS = [
        'overview', 'brief', 'contract', 'planning', 'agenda', 'speakers', 'tasks', 'budget', 'suppliers', 'venue',
        'transportation', 'accommodation', 'exhibition', 'sponsors',
        'attendees', 'files', 'risks', 'approvals', 'reports', 'ai', 'settings',
    ];

    public function show(Event $event, EventHealthService $healthService): View
    {
        $tab = in_array(request('tab'), self::TABS, true) ? request('tab') : 'overview';

        // A disabled module's tab falls back to Overview.
        if (! $event->moduleEnabled($tab)) {
            $tab = 'overview';
        }

        $event->load([
            'avatar', 'client', 'venue', 'projectManager', 'project',
            'rooms', 'agendaDays.sessions.room', 'agendaSessions',
            'tasks.assignee', 'budgetItems.supplier', 'suppliers',
            'sponsors', 'risks.owner', 'approvals.requester', 'approvals.decider',
            'teamMembers', 'speakers', 'brief', 'contract',
        ]);

        return view('events.hub', [
            'event' => $event,
            'tab' => $tab,
            'health' => $healthService->breakdown($event),
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
