<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Livewire\Hub\AgendaTab;
use App\Models\Event;
use App\Services\AgendaTimeline;
use Illuminate\Http\Response;

/**
 * Renders the agenda "timeline" — the room-lane × time Gantt shown on the Agenda
 * tab — to PDF with headless Chrome, using the exact same geometry (AgendaTimeline)
 * the screen uses, so the drawing prints as it is. One timeline per day.
 */
class AgendaTimelinePdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event, AgendaTimeline $timeline): Response
    {
        $event->load([
            'agendaDays' => fn ($q) => $q->orderBy('date'),
            'agendaDays.sessions' => fn ($q) => $q->orderBy('starts_at'),
            'agendaDays.sessions.room',
            'agendaDays.sessions.speakers',
        ]);

        // ?day={id} prints that single day; otherwise every day, each its own timeline.
        $agendaDays = $event->agendaDays;
        $single = $agendaDays->firstWhere('id', request()->integer('day'));
        if ($single) {
            $agendaDays = collect([$single]);
        }

        $days = $agendaDays->map(fn ($d) => [
            'label' => $d->label,
            'date' => $d->date?->format('l, j F Y') ?? '',
            'index' => $agendaDays->search(fn ($x) => $x->id === $d->id) + 1,
            'timeline' => $timeline->forSessions($d->sessions),
        ])->values()->all();

        // Type + status legends, limited to what actually appears.
        $shownTypes = $event->agendaDays->flatMap->sessions->pluck('type')->unique()->all();
        $legend = collect(AgendaTab::PALETTE)->only($shownTypes)->values()->unique(0)->values()->all();

        $html = view('events.agenda-timeline-pdf', [
            'event' => $event,
            'days' => $days,
            'single' => $single,
            'legend' => $legend,
            'css' => $this->compiledCss(),
        ])->render();

        $pdf = $this->chrome($html)->landscape()->format('A4')->pdf();

        $name = str($event->name)->slug().'-timeline'
            .($single ? '-'.str($single->date?->format('M-j') ?? $single->label)->slug() : '').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }
}
