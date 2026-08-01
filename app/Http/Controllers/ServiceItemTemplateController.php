<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GeneratesXlsxTemplate;
use App\Models\ServiceItem;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The price list as a fillable sheet.
 *
 * The Unit column is a dropdown of the real units rather than free text,
 * because "per room per night" typed four different ways is four units, and
 * the whole point of the column is that the invoice editor can act on it.
 */
class ServiceItemTemplateController extends Controller
{
    use GeneratesXlsxTemplate;

    public function __invoke(): StreamedResponse
    {
        $units = collect(ServiceItem::UNITS)->map(fn (array $m) => $m[0])->values()->all();

        return $this->xlsxTemplate(
            headers: ['Code', 'Item', 'Category', 'Unit', 'Unit price', 'Currency', 'Tax %', 'Detail'],
            example: [
                ['ACC-DBL', 'Double room, 5★', 'Accommodation', 'Per room per night', 95, 'JOD', 16,
                    'Bed and breakfast, city centre.'],
                ['ACC-SUITE', 'Executive suite', 'Accommodation', 'Per room per night', 180, 'JOD', 16, ''],
                ['TRN-SED', 'Executive sedan with driver', 'Transportation', 'Per vehicle per day', 120, 'JOD', 16,
                    'Up to 10 hours; overtime billed hourly.'],
                ['TRN-BUS', 'Coach, 50 seats', 'Transportation', 'Per vehicle per trip', 250, 'JOD', 16, ''],
                ['CAT-LUN', 'Delegate lunch', 'Catering', 'Per person', 22, 'JOD', 16, ''],
                ['CAT-BRK', 'Coffee break', 'Catering', 'Per person', 8, 'JOD', 16, 'Two servings per day.'],
                ['AV-STG', 'Main stage, screens and lighting', 'AV & Production', 'Per day', 1800, 'JOD', 16, ''],
                ['CRW-USH', 'Usher / hostess', 'Crew', 'Per person per day', 45, 'JOD', 16, ''],
                ['EXH-SQM', 'Exhibition build', 'Exhibition', 'Per square metre', 65, 'JOD', 16, ''],
                ['MGT-FEE', 'Event management fee', 'Management', 'Fixed fee', 0, 'JOD', 16,
                    'Priced per engagement — leave the price at 0 and set it on the invoice.'],
            ],
            sheetTitle: 'Price list',
            filename: 'elite-price-list-template.xlsx',
            widths: [12, 34, 18, 22, 12, 10, 8, 40],
            lists: ['Unit' => $units, 'Currency' => ['JOD', 'USD', 'EUR', 'GBP', 'SAR', 'AED']],
            rules: ['D' => 'Unit', 'F' => 'Currency'],
        );
    }
}
