<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GeneratesXlsxTemplate;
use App\Models\Event;
use App\Models\EventTransport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ready-to-fill Excel manifest template for one movement — the exact columns
 * the TransportationTab importer understands. Blank cells fall back to the run.
 */
class TransportManifestTemplateController extends Controller
{
    use GeneratesXlsxTemplate;

    public function __invoke(Event $event, EventTransport $transport): StreamedResponse
    {
        abort_unless($transport->event_id === $event->id, 404);

        $flight = $transport->flight_no ?: 'RJ 123';
        $date = $transport->arrive_at?->format('Y-m-d') ?? $transport->depart_at?->format('Y-m-d') ?? $event->starts_at?->format('Y-m-d') ?? '2026-07-27';
        $time = $transport->arrive_at?->format('H:i') ?? '14:30';
        $pickup = $transport->pickup_from ?: 'Queen Alia Intl Airport';

        return $this->xlsxTemplate(
            headers: ['Name', 'Airline', 'Flight #', 'Arrival Date', 'Arrival Time', 'Phone', 'Email', 'Pickup Point', 'Notes'],
            example: [
                ['Layla Odeh', 'Royal Jordanian', $flight, $date, $time, '+962 79 000 0000', 'layla@example.com', $pickup, 'VIP — meet & greet'],
                ['Omar Nassar', '', '', '', '', '', '', '', 'Uses the run\'s flight & pickup when blank'],
            ],
            sheetTitle: 'Manifest',
            filename: str($event->name.'-'.($transport->route ?: 'movement'))->slug().'-manifest-template.xlsx',
            widths: [22, 20, 12, 15, 13, 18, 26, 24, 28],
        );
    }
}
