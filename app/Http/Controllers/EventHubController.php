<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventHealthService;
use Illuminate\View\View;

class EventHubController extends Controller
{
    public const TABS = [
        'overview', 'agenda', 'tasks', 'budget', 'suppliers', 'venue', 'sponsors',
        'attendees', 'files', 'risks', 'approvals', 'reports', 'ai', 'settings',
    ];

    public function show(Event $event, EventHealthService $healthService): View
    {
        $tab = in_array(request('tab'), self::TABS, true) ? request('tab') : 'overview';

        $event->load([
            'avatar', 'client', 'venue', 'projectManager', 'project',
            'rooms', 'agendaDays.sessions.room', 'agendaSessions',
            'tasks.assignee', 'budgetItems.supplier', 'suppliers',
            'sponsors', 'risks.owner', 'approvals.requester', 'approvals.decider',
            'teamMembers',
        ]);

        return view('events.hub', [
            'event' => $event,
            'tab' => $tab,
            'health' => $healthService->breakdown($event),
            'ai' => $healthService->aiSummary($event),
        ]);
    }
}
