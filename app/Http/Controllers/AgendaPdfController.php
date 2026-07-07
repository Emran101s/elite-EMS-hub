<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class AgendaPdfController extends Controller
{
    public function __invoke(Event $event): Response
    {
        $event->load(['avatar', 'venue', 'agendaDays' => fn ($q) => $q->orderBy('sort'),
            'agendaDays.sessions' => fn ($q) => $q->orderBy('sort')->orderBy('starts_at'), 'agendaDays.sessions.room']);

        $pdf = Pdf::loadView('events.agenda-pdf', [
            'event' => $event,
            'theme' => $event->theme(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(str($event->name)->slug().'-agenda.pdf');
    }
}
