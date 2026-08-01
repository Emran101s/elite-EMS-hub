<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GeneratesXlsxTemplate;
use App\Models\Event;
use App\Models\EventInvoiceItem;
use App\Support\Taxonomy;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * This event's invoice items, as a fillable sheet.
 *
 * Both prices are on it. What an event costs and what it charges are agreed at
 * different times with different people, and a sheet that only carries one of
 * them makes the other somebody's memory.
 */
class EventPricingTemplateController extends Controller
{
    use GeneratesXlsxTemplate;

    public function __invoke(Event $event): StreamedResponse
    {
        $units = collect(EventInvoiceItem::UNITS)->map(fn (array $m) => $m[0])->values()->all();

        $sections = Taxonomy::options('service_section');
        $of = fn (string $key) => $sections[$key] ?? '';

        return $this->xlsxTemplate(
            headers: ['Code', 'Item', 'Category', 'Section', 'Unit', 'Costs us', 'We charge', 'Tax %', 'Detail'],
            example: [
                ['ACC-DBL', 'Double room, 5★', 'Accommodation', $of('hotel'), 'Per room per night', 78, 95, 16,
                    'Negotiated rate for this event only.'],
                ['ACC-SUITE', 'Executive suite', 'Accommodation', $of('hotel'), 'Per room per night', 150, 180, 16, ''],
                ['TRN-APT', 'Airport transfer', 'Transportation', $of('transport'), 'Per vehicle per trip', 28, 35, 16, ''],
                ['CAT-LUN', 'Delegate lunch', 'Catering', $of('hotel'), 'Per person', 17, 22, 16, ''],
                ['AV-STG', 'Main stage and lighting', 'AV & Production', $of('production'), 'Per day', 1500, 1800, 16, ''],
                ['EQP-RAD', 'Two-way radio', 'Comms', $of('equipment'), 'Per day', 4, 6, 16, ''],
                ['CRW-USH', 'Usher / hostess', 'Crew', $of('people'), 'Per person per day', 35, 45, 16, ''],
            ],
            sheetTitle: 'Invoice items',
            filename: Str::slug($event->name).'-invoice-items.xlsx',
            widths: [12, 34, 18, 20, 22, 12, 12, 8, 40],
            lists: ['Section' => array_values($sections), 'Unit' => $units],
            rules: ['D' => 'Section', 'E' => 'Unit'],
        );
    }
}
