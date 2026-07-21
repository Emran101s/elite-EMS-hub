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

        $halls = $event->exhibitionHalls()->with('booths.exhibitor')->get();

        $booths = $halls->flatMap->booths;
        $sales = [
            'total' => $booths->count(),
            'sold' => $booths->filter(fn ($b) => $b->status() === 'sold')->count(),
            'reserved' => $booths->filter(fn ($b) => $b->status() === 'reserved')->count(),
            'soldValue' => $booths->filter(fn ($b) => $b->status() === 'sold')->sum('price_cents'),
            'pipelineValue' => $booths->filter(fn ($b) => $b->status() === 'reserved')->sum('price_cents'),
        ];

        $pdf = Pdf::loadView('events.exhibition-floor-pdf', [
            'event' => $event,
            'theme' => $event->theme(),
            'halls' => $halls,
            'sales' => $sales,
        ])->setPaper('a4', 'landscape');

        return $pdf->download(str($event->name)->slug().'-floor-plan.pdf');
    }
}
