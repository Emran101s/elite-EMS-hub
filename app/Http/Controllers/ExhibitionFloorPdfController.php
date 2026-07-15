<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ExhibitionFloorPdfController extends Controller
{
    public function __invoke(Event $event): Response
    {
        $event->ensureExhibitionHall();

        $halls = $event->exhibitionHalls()
            ->with(['exhibitors' => fn ($q) => $q->where('status', '!=', 'cancelled')->whereNotNull('booth_x')])
            ->get();

        $pdf = Pdf::loadView('events.exhibition-floor-pdf', [
            'event' => $event,
            'theme' => $event->theme(),
            'halls' => $halls,
        ])->setPaper('a4', 'landscape');

        return $pdf->download(str($event->name)->slug().'-floor-plan.pdf');
    }
}
