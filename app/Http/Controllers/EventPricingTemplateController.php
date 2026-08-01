<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GeneratesXlsxTemplate;
use App\Models\Event;
use App\Models\EventInvoiceItem;
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

        return $this->xlsxTemplate(
            headers: ['Code', 'Item', 'Category', 'Unit', 'Costs us', 'We charge', 'Tax %', 'Detail'],
            example: [
                ['ACC-DBL', 'Double room, 5★', 'Accommodation', 'Per room per night', 78, 95, 16,
                    'Negotiated rate for this event only.'],
                ['ACC-SUITE', 'Executive suite', 'Accommodation', 'Per room per night', 150, 180, 16, ''],
                ['TRN-APT', 'Airport transfer', 'Transportation', 'Per vehicle per trip', 28, 35, 16, ''],
                ['CAT-LUN', 'Delegate lunch', 'Catering', 'Per person', 17, 22, 16, ''],
                ['AV-STG', 'Main stage and lighting', 'AV & Production', 'Per day', 1500, 1800, 16, ''],
                ['CRW-USH', 'Usher / hostess', 'Crew', 'Per person per day', 35, 45, 16, ''],
            ],
            sheetTitle: 'Invoice items',
            filename: Str::slug($event->name).'-invoice-items.xlsx',
            widths: [12, 34, 18, 22, 12, 12, 8, 40],
            lists: ['Unit' => $units],
            rules: ['D' => 'Unit'],
        );
    }
}
