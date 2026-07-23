<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GeneratesXlsxTemplate;
use App\Models\Event;
use App\Models\EventRoomBlock;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A ready-to-fill Excel rooming-list template for one hotel block — the exact
 * columns the AccommodationTab importer understands.
 */
class RoomingTemplateController extends Controller
{
    use GeneratesXlsxTemplate;

    public function __invoke(Event $event, EventRoomBlock $block): StreamedResponse
    {
        abort_unless($block->event_id === $event->id, 404);

        $ci = $block->check_in?->format('Y-m-d') ?? $event->starts_at?->format('Y-m-d') ?? '2026-07-27';
        $co = $block->check_out?->format('Y-m-d') ?? $event->ends_at?->format('Y-m-d') ?? '2026-07-29';
        $rt = $block->room_type ?: 'Deluxe';

        return $this->xlsxTemplate(
            headers: ['Name', 'Email', 'Phone', 'Room Type', 'Occupancy', 'Sharing With',
                'Check In', 'Check Out', 'Arrival Time', 'Departure Time', 'Confirmation #', 'Notes'],
            example: [
                ['Layla Odeh', 'layla@example.com', '+962 79 000 0000', $rt, 'Twin', 'Sara Kamal', $ci, $co, '14:30', '11:00', 'CN-1001', 'Late check-out requested'],
                ['Omar Nassar', 'omar@example.com', '', $rt, 'Single', '', '', '', '', '', '', 'Uses block dates when left blank'],
            ],
            sheetTitle: 'Rooming List',
            filename: str($event->name.'-'.$block->hotel)->slug().'-rooming-template.xlsx',
            widths: [22, 24, 16, 16, 14, 18, 14, 14, 13, 14, 16, 32],
        );
    }
}
