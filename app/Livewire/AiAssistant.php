<?php

namespace App\Livewire;

use App\Models\Event;
use App\Services\EventHealthService;
use App\Services\PortfolioAdvisor;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The briefing. Rule-based, and the page says so.
 *
 * It reuses the same advisor each event's Overview already runs, applied to
 * the whole book. The rules are a producer's Monday-morning read: what is on
 * fire, what is blocked, what is late, what is missing — and every line names
 * the record it came from and links straight to it.
 */
#[Layout('components.layouts.app', ['title' => 'Command Briefing', 'hideTitleRow' => true])]
class AiAssistant extends Component
{
    /** all · critical · warning — how much noise you want. */
    public string $filter = 'all';

    /** The event whose own briefing is open, if any. */
    public ?int $focusId = null;

    public function setFilter(string $filter): void
    {
        if (in_array($filter, ['all', 'critical', 'warning'], true)) {
            $this->filter = $filter;
        }
    }

    public function focus(?int $eventId): void
    {
        $this->focusId = $this->focusId === $eventId ? null : $eventId;
    }

    public function render()
    {
        $advisor = app(PortfolioAdvisor::class);

        $events = Event::whereNull('archived_at')
            ->with(array_merge(PortfolioAdvisor::RELATIONS, EventHealthService::RELATIONS))
            ->orderBy('starts_at')
            ->get();

        $all = $advisor->attention($events);

        $shown = (match ($this->filter) {
            'critical' => $all->where('severity', 'critical'),
            'warning' => $all->whereIn('severity', ['critical', 'warning']),
            default => $all,
        })->values();

        $focus = $this->focusId ? $events->firstWhere('id', $this->focusId) : null;

        return view('livewire.ai-assistant', [
            'events' => $events,
            'headline' => $advisor->headline($events, $all),
            'items' => $shown,
            'counts' => [
                'all' => $all->count(),
                'critical' => $all->where('severity', 'critical')->count(),
                'warning' => $all->where('severity', 'warning')->count(),
                'info' => $all->where('severity', 'info')->count(),
            ],
            'byEvent' => $all->groupBy('where')->map->count()->sortDesc(),
            'focus' => $focus,
            'focusSummary' => $focus ? app(EventHealthService::class)->aiSummary($focus) : null,
        ]);
    }
}
