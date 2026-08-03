<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use App\Models\EventRoom;
use Illuminate\Http\Response;

/**
 * The load-in sheet for one venue.
 *
 * This printed blank on eleven of thirteen rooms, because it read the old
 * `equipment` column while every room's equipment was being entered into
 * `requirements` — two lists, both called "Equipment" on screen. There is one
 * list now, and this reads it.
 *
 * Deliberately not the schedule on sheet 2 of the floor plan. That one is
 * priced and goes to the client; this goes to whoever is unloading the truck,
 * so it carries a tick box, a quantity, the days a thing is needed and where
 * its preparation has got to — and no money at all.
 */
class RoomEquipmentPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event, EventRoom $room): Response
    {
        abort_unless($room->event_id === $event->id, 404);

        $html = view('events.room-equipment-pdf', [
            'event' => $event,
            'room' => $room,
            'theme' => $event->theme(),
            'lines' => $room->equipmentLines(),
            'css' => $this->compiledCss(),
        ])->render();

        $pdf = $this->chrome($html)->format('A4')->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'
                .str($event->name.'-'.$room->name)->slug().'-equipment.pdf"',
        ]);
    }
}
