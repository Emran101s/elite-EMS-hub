<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use Illuminate\Http\Response;

/**
 * The Master Schedule — the complete internal inventory of every session,
 * crew work included, one row each with room, line-up and sign-off status.
 *
 * One of three agenda documents, each with a single job:
 *   Programme      → what's on, for delegates (AgendaProgramPdfController)
 *   Master Schedule→ every session, for the team (this)
 *   Run of Show    → the cue sheet for show day (RunOfShowPdfController)
 */
class MasterSchedulePdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event): Response
    {
        $event->load([
            'venue',
            'agendaDays' => fn ($q) => $q->orderBy('date'),
            'agendaDays.sessions' => fn ($q) => $q->orderBy('starts_at'),
            'agendaDays.sessions.room',
            'agendaDays.sessions.speakers',
        ]);

        $html = view('events.agenda-master-pdf', [
            'event' => $event,
            'css' => $this->compiledCss(),
        ])->render();

        $pdf = $this->chrome($html)->format('A4')->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str($event->name)->slug().'-master-schedule.pdf"',
        ]);
    }
}
