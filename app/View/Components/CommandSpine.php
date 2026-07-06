<?php

namespace App\View\Components;

use App\Models\Event;
use App\Models\EventApproval;
use App\Services\EventHealthService;
use Illuminate\View\Component;

class CommandSpine extends Component
{
    public function __construct(private EventHealthService $health)
    {
    }

    public function render()
    {
        // Live event radar — same computed health score as the hubs/islands.
        $radar = Event::with(['tasks', 'budgetItems', 'suppliers', 'rooms', 'agendaSessions', 'risks', 'approvals', 'avatar'])
            ->whereNull('archived_at')
            ->whereNot('status', 'completed')
            ->orderBy('starts_at')
            ->get()
            ->map(function (Event $event) {
                $breakdown = $this->health->breakdown($event);
                $event->radar_score = $breakdown['score'];
                $event->radar_group = $breakdown['group'];
                $event->radar_status = str($breakdown['status'])->replace('_', ' ')->title();
                $event->radar_vip = in_array($event->type, ['vip_reception', 'embassy_event', 'private_dinner'], true);

                return $event;
            });

        $openRisks = $radar->sum(fn (Event $event) => $event->risks->filter->isOpen()->count());

        return view('components.command-spine', [
            'radar' => $radar,
            'core' => [
                'events' => $radar->count(),
                'risks' => $openRisks,
                'approvals' => EventApproval::where('status', 'pending')->count(),
            ],
        ]);
    }
}
