<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GeneratesXlsxTemplate;
use App\Models\ServiceItem;
use App\Support\Taxonomy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The price list as a fillable sheet.
 *
 * The Unit column is a dropdown of the real units rather than free text,
 * because "per room per night" typed four different ways is four units, and
 * the whole point of the column is that the invoice editor can act on it.
 *
 * Downloading it from inside a section gives that section's sheet: the worked
 * rows are things the section actually sells and the Section column is filled
 * in, because a hotel sending back a rate sheet should not have to decide what
 * "outside production" means.
 */
class ServiceItemTemplateController extends Controller
{
    use GeneratesXlsxTemplate;

    /** [code, item, category, unit, price, detail] per section. */
    private const EXAMPLES = [
        'hotel' => [
            ['ACC-DBL', 'Double room, 5★', 'Accommodation', 'Per room per night', 95, 'Bed and breakfast, city centre.'],
            ['ACC-SUITE', 'Executive suite', 'Accommodation', 'Per room per night', 180, ''],
            ['CAT-LUN', 'Delegate lunch', 'Catering', 'Per person', 22, ''],
            ['CAT-BRK', 'Coffee break', 'Catering', 'Per person', 8, 'Two servings per day.'],
            ['VEN-HALL', 'Ballroom hire', 'Meeting space', 'Per day', 1200, 'Includes house lighting and PA.'],
        ],
        'production' => [
            ['AV-STG', 'Main stage, screens and lighting', 'AV & Production', 'Per day', 1800, ''],
            ['AV-STR', 'Hybrid streaming', 'AV & Production', 'Per day', 950, 'Two cameras, one operator.'],
            ['AV-INT', 'Interpretation booth', 'AV & Production', 'Per day', 420, 'Includes two interpreters.'],
            ['EXH-SQM', 'Exhibition build', 'Exhibition', 'Per square metre', 65, ''],
            ['DOC-PHT', 'Photography', 'Documentation', 'Per day', 450, ''],
        ],
        'equipment' => [
            ['EQP-RAD', 'Two-way radio', 'Comms', 'Per day', 6, 'Per handset, charger included.'],
            ['EQP-LAP', 'Laptop, presentation spec', 'IT', 'Per day', 25, ''],
            ['EQP-PRN', 'Badge printer', 'Registration', 'Per day', 40, ''],
            ['EQP-FUR', 'Lounge furniture set', 'Furniture', 'Each', 180, 'Delivery and collection included.'],
        ],
        'transport' => [
            ['TRN-SED', 'Executive sedan with driver', 'Transportation', 'Per vehicle per day', 120,
                'Up to 10 hours; overtime billed hourly.'],
            ['TRN-VAN', 'Minivan, 7 seats', 'Transportation', 'Per vehicle per day', 90, ''],
            ['TRN-BUS', 'Coach, 50 seats', 'Transportation', 'Per vehicle per trip', 250, ''],
            ['TRN-APT', 'Airport transfer', 'Transportation', 'Per vehicle per trip', 35, ''],
        ],
        'people' => [
            ['CRW-USH', 'Usher / hostess', 'Crew', 'Per person per day', 45, ''],
            ['CRW-TL', 'Team leader', 'Crew', 'Per person per day', 80, ''],
            ['CRW-SEC', 'Security officer', 'Crew', 'Per person per day', 55, '12-hour shift.'],
        ],
        'fees' => [
            ['MGT-FEE', 'Event management fee', 'Management', 'Fixed fee', 0,
                'Priced per engagement — leave the price at 0 and set it on the invoice.'],
            ['MGT-PM', 'Dedicated project manager', 'Management', 'Per day', 250, ''],
        ],
    ];

    public function __invoke(Request $request): StreamedResponse
    {
        $units = collect(ServiceItem::UNITS)->map(fn (array $m) => $m[0])->values()->all();
        $sections = Taxonomy::options('service_section');

        $open = $request->string('section')->toString();
        $open = isset($sections[$open]) ? $open : null;

        // One section's rows, or every section's with its own name against each.
        $rows = $open
            ? array_map(fn (array $r) => [...$r, $open], self::EXAMPLES[$open] ?? [])
            : collect(self::EXAMPLES)
                ->flatMap(fn (array $rows, string $key) => array_map(fn (array $r) => [...$r, $key], $rows))
                ->all();

        $example = array_map(function (array $r) use ($sections) {
            [$code, $name, $category, $unit, $price, $detail, $section] = $r;

            return [$code, $name, $category, $sections[$section] ?? '', $unit, $price, 'JOD', 16, $detail];
        }, $rows);

        return $this->xlsxTemplate(
            headers: ['Code', 'Item', 'Category', 'Section', 'Unit', 'Unit price', 'Currency', 'Tax %', 'Detail'],
            example: $example,
            sheetTitle: $open ? Str::limit($sections[$open], 28, '') : 'Price list',
            filename: 'elite-price-list'.($open ? '-'.Str::slug($sections[$open]) : '').'-template.xlsx',
            widths: [12, 34, 18, 20, 22, 12, 10, 8, 40],
            lists: [
                'Section' => array_values($sections),
                'Unit' => $units,
                'Currency' => ['JOD', 'USD', 'EUR', 'GBP', 'SAR', 'AED'],
            ],
            rules: ['D' => 'Section', 'E' => 'Unit', 'G' => 'Currency'],
        );
    }
}
