<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GeneratesXlsxTemplate;
use App\Models\Event;
use App\Models\EventTransportPassenger;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ready-to-fill Excel template for the transport plan — one row per traveller.
 * Dropdowns are built from this workspace's own catalogues and ship on a "Lists"
 * sheet inside the same file, so they keep working wherever it's opened.
 */
class TransportPlanTemplateController extends Controller
{
    use GeneratesXlsxTemplate;

    public function __invoke(Event $event): StreamedResponse
    {
        $d1 = $event->starts_at?->format('Y-m-d') ?? '2026-06-14';
        $d2 = $event->ends_at?->format('Y-m-d') ?? $d1;

        // Places this event actually uses: its venue, its hotels, the local airport.
        $places = collect([$event->venue?->name])
            ->merge($event->rooms()->pluck('name'))
            ->merge($event->roomBlocks()->pluck('hotel'))
            ->merge(['Queen Alia International Airport', 'Hotel', 'Airport'])
            ->filter()->unique()->values()->all();

        // Only what describes the GUEST. Vehicle, service and status belong to the
        // vehicle you book afterwards — not to a sheet filled from a flight list.
        $hotels = collect($event->roomBlocks()->pluck('hotel'))->filter()->unique()->values()->all();

        $lists = [
            'Direction' => ['Arrival', 'Departure'],
            // Category drives the VIP sheet, the speaker sheet and priority
            // handling — one column, three documents.
            'Category' => array_values(EventTransportPassenger::CATEGORIES),
            'Location' => $places,
            'Hotel' => $hotels ?: ['Hotel'],
            'Airline' => ['Royal Jordanian', 'Emirates', 'Qatar Airways', 'Turkish Airlines',
                'Saudia', 'Etihad', 'EgyptAir', 'Gulf Air', 'Lufthansa', 'British Airways'],
        ];

        return $this->xlsxTemplate(
            headers: ['Name', 'Category', 'Direction', 'Airline', 'Flight #', 'Date',
                'Flight Time', 'Pickup Time', 'From', 'To', 'Hotel', 'Phone', 'Notes'],
            example: [
                // Arrivals — the pickup IS the landing, so Pickup Time can be left blank.
                ['Layla Odeh', 'VIP', 'Arrival', 'Royal Jordanian', 'RJ 100', $d1, '14:30', '', 'Queen Alia International Airport', 'Hotel', $hotels[0] ?? 'Hotel', '+962 79 000 0000', 'Meet & greet at arrivals'],
                ['Omar Nassar', 'Speaker', 'Arrival', 'Royal Jordanian', 'RJ 100', $d1, '14:30', '', 'Queen Alia International Airport', 'Hotel', $hotels[0] ?? 'Hotel', '', 'Same flight'],
                // Departure — the flight leaves at 09:00 but the car collects at 06:00.
                ['Layla Odeh', 'VIP', 'Departure', 'Royal Jordanian', 'RJ 201', $d2, '09:00', '06:00', 'Hotel', 'Queen Alia International Airport', $hotels[0] ?? 'Hotel', '', '3 hours before the flight'],
            ],
            sheetTitle: 'Guests',
            filename: str($event->name)->slug().'-transport-guests-template.xlsx',
            widths: [24, 13, 13, 20, 12, 13, 13, 13, 30, 30, 22, 18, 28],
            lists: $lists,
            rules: [
                'B' => 'Category',
                'C' => 'Direction',
                'D' => 'Airline',
                'F' => 'date',
                'G' => 'time',
                'H' => 'time',
                'I' => 'Location',
                'J' => 'Location',
                'K' => 'Hotel',
            ],
        );
    }
}
