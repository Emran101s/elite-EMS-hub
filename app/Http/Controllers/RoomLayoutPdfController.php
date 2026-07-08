<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRoom;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class RoomLayoutPdfController extends Controller
{
    public function __invoke(Event $event, EventRoom $room): Response
    {
        abort_unless($room->event_id === $event->id, 404);

        $pdf = Pdf::loadView('events.room-layout-pdf', [
            'event' => $event,
            'room' => $room,
            'theme' => $event->theme(),
            'elements' => $room->layout ?? [],
        ])->setPaper('a4', 'landscape');

        return $pdf->download(str($event->name.'-'.$room->name)->slug().'-layout.pdf');
    }
}
