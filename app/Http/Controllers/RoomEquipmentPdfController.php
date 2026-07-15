<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRoom;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class RoomEquipmentPdfController extends Controller
{
    public function __invoke(Event $event, EventRoom $room): Response
    {
        abort_unless($room->event_id === $event->id, 404);

        $pdf = Pdf::loadView('events.room-equipment-pdf', [
            'event' => $event,
            'room' => $room,
            'theme' => $event->theme(),
            'lines' => $room->equipmentLines(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(str($event->name.'-'.$room->name)->slug().'-equipment.pdf');
    }
}
