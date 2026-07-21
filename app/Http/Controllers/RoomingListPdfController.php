<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use App\Models\EventRoomBlock;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The rooming list as the hotel receives it: who is in which room, and nothing
 * about money. Rates and block totals are deliberately absent from both this
 * controller's payload and the view it renders.
 */
class RoomingListPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event, EventRoomBlock $block): Response
    {
        abort_unless($block->event_id === $event->id, 404);

        $html = view('events.rooming-list-pdf', [
            'event' => $event,
            'block' => $block,
            'rooms' => $block->rooms()->get(),
            'css' => $this->compiledCss(),
        ])->render();

        $pdf = $this->chrome($html)->format('A4')->landscape()->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'
                .Str::slug($event->name ?: 'event').'-'.Str::slug($block->hotel).'-rooming-list.pdf"',
        ]);
    }
}
