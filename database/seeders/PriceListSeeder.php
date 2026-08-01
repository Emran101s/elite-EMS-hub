<?php

namespace Database\Seeders;

use App\Models\ServiceItem;
use Illuminate\Database\Seeder;

/**
 * A price list to start from, rather than an empty page.
 *
 * Idempotent on the code, so running it again corrects the rows it owns and
 * leaves anything added by hand alone — the same rule the importer follows.
 * Every price here is a placeholder: the units are the part worth keeping.
 */
class PriceListSeeder extends Seeder
{
    /** [code, name, category, unit, price in cents, detail] */
    private const ITEMS = [
        ['ACC-DBL', 'Double room, 5★', 'Accommodation', 'room_night', 9500, 'Bed and breakfast.'],
        ['ACC-SGL', 'Single room, 5★', 'Accommodation', 'room_night', 7500, null],
        ['ACC-SUITE', 'Executive suite', 'Accommodation', 'room_night', 18000, null],

        ['TRN-SED', 'Executive sedan with driver', 'Transportation', 'vehicle_day', 12000, 'Up to 10 hours.'],
        ['TRN-VAN', 'Minivan, 7 seats', 'Transportation', 'vehicle_day', 9000, null],
        ['TRN-BUS', 'Coach, 50 seats', 'Transportation', 'vehicle_trip', 25000, null],
        ['TRN-APT', 'Airport transfer', 'Transportation', 'vehicle_trip', 3500, null],

        ['CAT-LUN', 'Delegate lunch', 'Catering', 'person', 2200, null],
        ['CAT-BRK', 'Coffee break', 'Catering', 'person', 800, null],
        ['CAT-GAL', 'Gala dinner', 'Catering', 'person', 6500, null],

        ['AV-STG', 'Main stage, screens and lighting', 'AV & Production', 'day', 180000, null],
        ['AV-STR', 'Hybrid streaming', 'AV & Production', 'day', 95000, null],
        ['AV-INT', 'Interpretation booth', 'AV & Production', 'day', 42000, 'Includes two interpreters.'],

        ['CRW-USH', 'Usher / hostess', 'Crew', 'person_day', 4500, null],
        ['CRW-TL', 'Team leader', 'Crew', 'person_day', 8000, null],

        ['EXH-SQM', 'Exhibition build', 'Exhibition', 'sqm', 6500, null],

        ['DOC-PHT', 'Photography', 'Documentation', 'day', 45000, null],
        ['DOC-VID', 'Video production', 'Documentation', 'day', 85000, null],

        ['MGT-FEE', 'Event management fee', 'Management', 'fixed', 0, 'Priced per engagement.'],
    ];

    public function run(): void
    {
        foreach (self::ITEMS as $i => [$code, $name, $category, $unit, $price, $detail]) {
            ServiceItem::updateOrCreate(['code' => $code], [
                'name' => $name,
                'category' => $category,
                'unit' => $unit,
                'unit_price_cents' => $price,
                'currency' => 'JOD',
                'tax_pct' => 16,
                'detail' => $detail,
                'active' => true,
                'sort' => $i,
            ]);
        }
    }
}
