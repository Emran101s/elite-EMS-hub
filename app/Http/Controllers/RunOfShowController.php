<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventAgendaSession;
use Illuminate\View\View;

class RunOfShowController extends Controller
{
    /** Session type → [legend group, hex]. */
    private const PALETTE = [
        'opening' => ['Opening', '#0B1F3A'],
        'keynote' => ['Keynote', '#8B5CF6'],
        'panel' => ['Session', '#3B82F6'],
        'workshop' => ['Workshop', '#22C55E'],
        'break' => ['Break', '#94A3B8'],
        'lunch' => ['Meal', '#D4AF37'],
        'networking' => ['Session', '#3B82F6'],
        'exhibition' => ['Session', '#3B82F6'],
        'gala_dinner' => ['Meal', '#D4AF37'],
        'closing' => ['Closing', '#0B1F3A'],
    ];

    public function __invoke(Event $event): View
    {
        $event->load([
            'venue',
            'agendaDays' => fn ($q) => $q->orderBy('sort'),
            'agendaDays.sessions' => fn ($q) => $q->orderBy('starts_at'),
            'agendaDays.sessions.room',
            'tasks',
        ]);

        $days = $event->agendaDays;
        $day = $days->firstWhere('id', request()->integer('day')) ?? $days->first();

        $timeline = $day ? $this->buildTimeline($day->sessions) : null;

        // Overview matrix: module × day counts.
        $overview = $days->map(fn ($d) => [
            'label' => $d->label,
            'date' => $d->date,
            'agenda' => $d->sessions->count(),
            'tasks' => $event->tasks->filter(fn ($t) => $t->due_on && $d->date && $t->due_on->isSameDay($d->date))->count(),
        ]);

        return view('events.run-of-show', [
            'event' => $event,
            'days' => $days,
            'day' => $day,
            'timeline' => $timeline,
            'overview' => $overview,
            'legend' => collect(self::PALETTE)->values()->unique(0)->values(),
        ]);
    }

    /**
     * Position each session as a block (left%/width%) inside a padded hour window,
     * grouped into room lanes. Returns null when there are no sessions.
     */
    private function buildTimeline($sessions): ?array
    {
        if ($sessions->isEmpty()) {
            return null;
        }

        $toMin = fn (string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);

        $startMin = (int) (floor($sessions->min(fn ($s) => $toMin($s->starts_at)) / 60) * 60);
        $endMin = (int) (ceil($sessions->max(fn ($s) => $toMin($s->ends_at)) / 60) * 60);
        $startMin = min($startMin, $endMin - 60);
        $span = max($endMin - $startMin, 60);

        $hours = [];
        for ($m = $startMin; $m <= $endMin; $m += 60) {
            $hours[] = ['label' => sprintf('%02d:00', intdiv($m, 60)), 'left' => round(($m - $startMin) / $span * 100, 3)];
        }

        $lanes = $sessions
            ->groupBy(fn ($s) => $s->room?->name ?? 'Unassigned')
            ->map(function ($group, $room) use ($toMin, $startMin, $span) {
                return [
                    'room' => $room,
                    'blocks' => $group->map(function ($s) use ($toMin, $startMin, $span) {
                        $sMin = $toMin($s->starts_at);
                        $eMin = $toMin($s->ends_at);
                        [$legend, $hex] = self::PALETTE[$s->type] ?? ['Session', '#3B82F6'];

                        return [
                            'session' => $s,
                            'left' => round(($sMin - $startMin) / $span * 100, 3),
                            'width' => round(max($eMin - $sMin, 15) / $span * 100, 3),
                            'hex' => $hex,
                            'legend' => $legend,
                        ];
                    })->values(),
                ];
            })->values();

        return ['hours' => $hours, 'lanes' => $lanes];
    }
}
