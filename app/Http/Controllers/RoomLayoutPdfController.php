<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use App\Models\EventRoom;
use Illuminate\Http\Response;

/**
 * The floor plan, as a drawing sheet.
 *
 * This used to go through DomPDF, which cannot rotate an element, nest a
 * transform or draw a reliable circle — so the export could not show the plan
 * the builder draws. It re-implemented the plan in a crippled dialect, and the
 * result did not resemble what anybody had arranged on screen.
 *
 * Headless Chrome renders the builder's own geometry, at the builder's own
 * 960×560 coordinates, through the builder's own element component, scaled onto
 * the sheet. Every rotation, round table and seat number survives, so the
 * drawing IS the layout rather than an impression of it.
 *
 * Sheet one is the plan. Sheet two is the schedule — what the room costs, for
 * how many days, and every piece of equipment that goes in it: a plan handed to
 * a venue without its equipment list is half a brief.
 */
class RoomLayoutPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event, EventRoom $room): Response
    {
        abort_unless($room->event_id === $event->id, 404);

        $html = view('events.room-layout-pdf', [
            'event' => $event,
            'room' => $room,
            'theme' => $event->theme(),
            'elements' => $room->layout ?? [],
            'css' => $this->compiledCss(),
        ])->render();

        $pdf = $this->chrome($html)->format('A4')->landscape()->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'
                .str($event->name.'-'.$room->name)->slug().'-floor-plan.pdf"',
        ]);
    }
}
