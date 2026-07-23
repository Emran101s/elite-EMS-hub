<?php

namespace Tests\Feature;

use App\Livewire\Hub\TransportationTab;
use App\Models\Event;
use App\Models\EventTransport;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private function xlsx(array $rows): UploadedFile
    {
        $ss = new Spreadsheet;
        $ss->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($ss))->save($path);
        $content = file_get_contents($path);
        @unlink($path);

        return UploadedFile::fake()->createWithContent('data.xlsx', $content);
    }

    private function event(): Event
    {
        return Event::create([
            'name' => 'Imp Event', 'type' => 'summit', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-07-27', 'ends_at' => '2026-07-29',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    /** A flight list of arrivals and departures. */
    private function guestSheet(): UploadedFile
    {
        return $this->xlsx([
            ['Name', 'Direction', 'Airline', 'Flight #', 'Date', 'Flight Time', 'Pickup Time', 'From', 'To', 'Phone'],
            ['Layla Odeh', 'Arrival', 'Royal Jordanian', 'RJ 100', '2026-07-27', '14:30', '', 'Airport', 'Hotel', '+962 79 000'],
            ['Omar Nassar', 'Arrival', 'Royal Jordanian', 'RJ 100', '2026-07-27', '14:30', '', 'Airport', 'Hotel', ''],
            ['Sara Kamal', 'Arrival', 'Emirates', 'EK 905', '2026-07-27', '19:45', '', 'Airport', 'Hotel', ''],
            ['Layla Odeh', 'Departure', 'Royal Jordanian', 'RJ 201', '2026-07-29', '09:00', '06:00', 'Hotel', 'Airport', ''],
            ['', '', '', '', '', '', '', '', '', ''],   // blank row → skipped
        ]);
    }

    public function test_attendee_template_downloads_with_expected_headers(): void
    {
        $user = $this->admin();
        $event = $this->event();

        $res = $this->actingAs($user)->get(route('events.attendees.template', $event));
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $res->headers->get('content-type'));

        $path = tempnam(sys_get_temp_dir(), 't').'.xlsx';
        file_put_contents($path, $res->streamedContent());
        $ss = IOFactory::load($path);
        @unlink($path);

        $this->assertSame('Attendees', $ss->getActiveSheet()->getTitle());
        $header = $ss->getActiveSheet()->toArray()[0];
        $this->assertSame('Name', $header[0]);
        $this->assertContains('Ticket Type', $header);
        $this->assertCount(1, array_filter($ss->getActiveSheet()->toArray(), fn ($r) => trim((string) ($r[0] ?? '')) !== ''));
    }

    public function test_import_fills_the_guest_pool_without_creating_movements(): void
    {
        $user = $this->admin();
        $event = $this->event();

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set('planFile', $this->guestSheet())
            ->call('importPlan')
            ->assertHasNoErrors();

        // The plan is not invented for you — guests wait to be placed.
        $this->assertSame(0, $event->transport()->count(), 'import creates no vehicles');
        $this->assertSame(4, $event->transferGuests()->count());
        $this->assertSame(4, $event->transferGuests()->whereNull('transport_id')->count());

        $this->assertSame(3, $event->transferGuests()->where('direction', 'arrival')->count());
        $this->assertSame(1, $event->transferGuests()->where('direction', 'departure')->count());

        // A departure carries BOTH times; an arrival's pickup falls back to the landing.
        $out = $event->transferGuests()->where('direction', 'departure')->firstOrFail();
        $this->assertSame('09:00', $out->arrival_time, 'the flight time');
        $this->assertSame('06:00', $out->pickup_time, 'the car leaves earlier');

        $in = $event->transferGuests()->where('flight_no', 'EK 905')->firstOrFail();
        $this->assertSame('19:45', $in->arrival_time);
        $this->assertSame('19:45', $in->pickup_time);

        // The pool reads in flight order: date, then time, then flight number.
        $this->assertSame(
            ['Layla Odeh', 'Omar Nassar', 'Sara Kamal'],
            $event->transferGuests()->where('direction', 'arrival')->flightOrder()->pluck('name')->all()
        );
        $c->assertOk();
    }

    public function test_reimporting_a_corrected_sheet_updates_guests_rather_than_duplicating(): void
    {
        $user = $this->admin();
        $event = $this->event();

        Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set('planFile', $this->guestSheet())->call('importPlan');
        Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set('planFile', $this->guestSheet())->call('importPlan');

        $this->assertSame(4, $event->transferGuests()->count(), 're-import updates, never doubles');
    }

    public function test_guests_are_assigned_to_a_vehicle_and_split_by_capacity(): void
    {
        $user = $this->admin();
        $event = $this->event();
        $van = VehicleType::create(['name' => 'Seven Van', 'capacity' => 7, 'is_active' => true, 'position' => 90]);

        // Twelve arrivals on one flight, waiting in the pool.
        $rows = [['Name', 'Direction', 'Airline', 'Flight #', 'Date', 'Flight Time', 'From', 'To']];
        foreach (range(1, 12) as $n) {
            $rows[] = ["Guest {$n}", 'Arrival', 'Royal Jordanian', 'RJ 100', '2026-07-27', '14:30', 'Airport', 'Hotel'];
        }

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set('planFile', $this->xlsx($rows))
            ->call('importPlan');

        // Book the van yourself, then place everyone on that flight onto it.
        $move = $event->transport()->create([
            'route' => 'Airport → Hotel', 'type' => 'van', 'vehicle_type_id' => $van->id,
            'vehicles' => 1, 'status' => 'ordered', 'depart_at' => '2026-07-27 14:30',
        ]);

        $c->call('pickFlight', 'RJ 100')
            ->assertCount('pickedGuests', 12)
            ->call('assignPicked', $move->id)
            ->assertCount('pickedGuests', 0);

        $move->refresh();
        $this->assertSame(0, $event->transferGuests()->whereNull('transport_id')->count());
        $this->assertSame(2, $move->vehicles, 'the fleet grows to fit');

        $byVehicle = $move->manifestByVehicle();
        $this->assertCount(7, $byVehicle[1], 'first van fills to capacity');
        $this->assertCount(5, $byVehicle[2], 'the remainder rides in the second');

        // And a guest can be sent back to the pool.
        $c->call('unassignGuest', $byVehicle[2]->first()->id);
        $this->assertSame(1, $event->transferGuests()->whereNull('transport_id')->count());
    }

    public function test_suggest_runs_groups_the_pool_and_sizes_the_vehicle(): void
    {
        $user = $this->admin();
        $event = $this->event();
        VehicleType::create(['name' => 'Small', 'capacity' => 4, 'is_active' => true, 'position' => 1]);
        VehicleType::create(['name' => 'Bus', 'capacity' => 20, 'is_active' => true, 'position' => 2]);

        $rows = [['Name', 'Direction', 'Airline', 'Flight #', 'Date', 'Flight Time', 'From', 'To']];
        foreach (range(1, 9) as $n) {
            $rows[] = ["Big {$n}", 'Arrival', 'Royal Jordanian', 'RJ 100', '2026-07-27', '14:30', 'Airport', 'Hotel'];
        }
        foreach (range(1, 2) as $n) {
            $rows[] = ["Few {$n}", 'Arrival', 'Emirates', 'EK 905', '2026-07-27', '19:45', 'Airport', 'Hotel'];
        }

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set('planFile', $this->xlsx($rows))
            ->call('importPlan')
            ->call('suggestGrouping');

        // One run per pickup window, and nobody left waiting.
        $this->assertSame(2, $event->transport()->count());
        $this->assertSame(0, $event->transferGuests()->whereNull('transport_id')->count());

        $moves = $event->transport()->with('vehicleType')->orderBy('depart_at')->get();

        // Nine people get the bus, not three of the 4-seaters.
        $this->assertSame('Bus', $moves[0]->vehicleType->name);
        $this->assertSame(1, $moves[0]->vehicles);
        $this->assertSame('RJ 100', $moves[0]->flight_no);
        $this->assertSame('2026-07-27 14:30', $moves[0]->depart_at->format('Y-m-d H:i'));

        // Two people take the smallest vehicle that fits.
        $this->assertSame('Small', $moves[1]->vehicleType->name);

        $c->assertOk();
    }

    public function test_dragging_a_guest_onto_a_run_assigns_them(): void
    {
        $user = $this->admin();
        $event = $this->event();
        $van = VehicleType::create(['name' => 'Seven', 'capacity' => 7, 'is_active' => true, 'position' => 1]);

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set('planFile', $this->guestSheet())
            ->call('importPlan');

        $move = $event->transport()->create([
            'route' => 'Airport → Hotel', 'type' => 'van', 'vehicle_type_id' => $van->id,
            'vehicles' => 1, 'status' => 'ordered', 'depart_at' => '2026-07-27 14:30',
        ]);
        $guest = $event->transferGuests()->where('name', 'Sara Kamal')->firstOrFail();

        // Dragging a guest that is NOT selected moves only that guest.
        $c->call('dropGuest', $guest->id, $move->id);
        $this->assertSame($move->id, $guest->fresh()->transport_id);
        $this->assertSame(1, $move->manifest()->count());

        // Dragging one of a selection moves the whole selection.
        $rest = $event->transferGuests()->whereNull('transport_id')->where('direction', 'arrival')->pluck('id')->all();
        foreach ($rest as $id) {
            $c->call('toggleGuest', $id);
        }
        $c->call('dropGuest', $rest[0], $move->id);

        $this->assertSame(0, $event->transferGuests()->where('direction', 'arrival')->whereNull('transport_id')->count());
        $this->assertSame(3, $move->fresh()->manifest()->count());
    }

    public function test_a_filled_in_template_round_trips_with_real_date_and_time_cells(): void
    {
        $user = $this->admin();
        $event = $this->event();

        // Mimic Excel: real date/time cells rather than strings.
        $ss = new Spreadsheet;
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray([['Name', 'Direction', 'Airline', 'Flight #', 'Date', 'Flight Time', 'Pickup Time', 'From', 'To']], null, 'A1');
        $sheet->fromArray([['Layla Odeh', 'Departure', 'Royal Jordanian', 'RJ 201', null, null, null, 'Hotel', 'Airport']], null, 'A2');
        $sheet->setCellValue('E2', Date::PHPToExcel(new \DateTime('2026-07-29')));
        $sheet->getStyle('E2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->setCellValue('F2', 9 / 24);        // 09:00 flight
        $sheet->getStyle('F2')->getNumberFormat()->setFormatCode('hh:mm');
        $sheet->setCellValue('G2', 6 / 24);        // 06:00 pickup
        $sheet->getStyle('G2')->getNumberFormat()->setFormatCode('hh:mm');

        $path = tempnam(sys_get_temp_dir(), 'rt').'.xlsx';
        (new Xlsx($ss))->save($path);
        $content = file_get_contents($path);
        @unlink($path);

        Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set('planFile', UploadedFile::fake()->createWithContent('filled.xlsx', $content))
            ->call('importPlan')
            ->assertHasNoErrors();

        $guest = $event->transferGuests()->firstOrFail();
        $this->assertSame('2026-07-29', $guest->arrival_on->format('Y-m-d'));
        $this->assertSame('09:00', $guest->arrival_time);
        $this->assertSame('06:00', $guest->pickup_time);
        $this->assertSame('departure', $guest->direction);
    }

    public function test_guest_template_carries_dropdowns_and_no_vehicle_columns(): void
    {
        $user = $this->admin();
        $event = $this->event();

        $res = $this->actingAs($user)->get(route('events.transport.plan-template', $event));
        $res->assertOk();

        $path = tempnam(sys_get_temp_dir(), 't').'.xlsx';
        file_put_contents($path, $res->streamedContent());
        $ss = IOFactory::load($path);
        @unlink($path);

        $this->assertSame('Guests', $ss->getActiveSheet()->getTitle());
        $header = $ss->getActiveSheet()->toArray()[0];
        $this->assertSame('Name', $header[0]);
        $this->assertContains('Flight Time', $header);
        $this->assertContains('Pickup Time', $header);

        // The vehicle is booked in the app, so the guest sheet never asks about it.
        foreach (['Status', 'Vehicle Type', 'Service'] as $gone) {
            $this->assertNotContains($gone, $header, "{$gone} does not belong on a guest list");
        }

        // Category is what later splits this one list into the VIP, speaker and
        // shuttle sheets, so the template has to ask for it up front.
        $this->assertContains('Category', $header);

        // Dropdowns and pickers still ride along on a Lists sheet. Looked up by
        // header name — column letters shift every time the sheet gains a field.
        $this->assertContains('Lists', $ss->getSheetNames());
        $sheet = $ss->getSheetByName('Guests');

        $columnOf = function (string $name) use ($header) {
            $i = array_search($name, $header, true);
            $this->assertNotFalse($i, "the template has a {$name} column");

            return Coordinate::stringFromColumnIndex($i + 1);
        };

        $expected = [
            'Category' => 'list', 'Direction' => 'list', 'Airline' => 'list',
            'From' => 'list', 'To' => 'list', 'Hotel' => 'list',
            'Date' => 'date', 'Flight Time' => 'time', 'Pickup Time' => 'time',
        ];

        foreach ($expected as $name => $type) {
            $col = $columnOf($name);
            $this->assertSame($type, $sheet->getCell($col.'2')->getDataValidation()->getType(),
                "{$name} (column {$col})");
        }
    }

    public function test_the_import_reads_whatever_the_sheet_calls_a_vip(): void
    {
        $user = $this->admin();
        $event = $this->event();

        $file = $this->xlsx([
            ['Name', 'Category', 'Direction', 'Flight #', 'Date', 'Flight Time', 'From', 'To'],
            ['Layla Odeh', 'V.I.P.', 'Arrival', 'RJ 100', '2026-07-27', '14:30', 'Airport', 'Hotel'],
            ['Omar Nassar', 'Keynote Speaker', 'Arrival', 'RJ 100', '2026-07-27', '14:30', 'Airport', 'Hotel'],
            ['Sara Kamal', 'Press', 'Arrival', 'RJ 100', '2026-07-27', '14:30', 'Airport', 'Hotel'],
            ['Dana Haddad', 'Something Odd', 'Arrival', 'RJ 100', '2026-07-27', '14:30', 'Airport', 'Hotel'],
        ]);

        Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set('planFile', $file)->call('importPlan')->assertHasNoErrors();

        $by = $event->transferGuests()->pluck('category', 'name');
        $this->assertSame('vip', $by['Layla Odeh']);
        $this->assertSame('speaker', $by['Omar Nassar']);
        $this->assertSame('media', $by['Sara Kamal']);
        $this->assertSame('delegate', $by['Dana Haddad'], 'an unknown label never wrongly promotes someone');
    }

    public function test_transport_manifest_imports_into_a_movement(): void
    {
        $user = $this->admin();
        $event = $this->event();
        $move = $event->transport()->create([
            'type' => 'van', 'vehicles' => 2, 'route' => 'Airport → Hotel',
            'pickup_from' => 'Queen Alia Airport', 'flight_no' => 'RJ 100',
            'arrive_at' => '2026-07-27 14:30', 'status' => 'planned',
        ]);

        $file = $this->xlsx([
            ['Name', 'Airline', 'Flight #', 'Arrival Date', 'Arrival Time', 'Pickup Point'],
            ['Layla Odeh', 'Royal Jordanian', 'RJ 205', '2026-07-27', '16:45', 'Terminal 2'],
            ['Omar Nassar', '', '', '', '', ''],   // blanks fall back to the run
            ['', '', '', '', '', ''],
        ]);

        Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->call('openImport', $move->id)
            ->set('importFile', $file)
            ->call('importPassengers')
            ->assertHasNoErrors();

        $pax = $move->manifest()->get();
        $this->assertCount(2, $pax);

        $layla = $pax->firstWhere('name', 'Layla Odeh');
        $this->assertSame('Royal Jordanian', $layla->airline);
        $this->assertSame('RJ 205', $layla->flight_no);
        $this->assertSame('Terminal 2', $layla->pickup_point);
        $this->assertSame($event->id, $layla->event_id, 'manifest rows know their event');

        $omar = $pax->firstWhere('name', 'Omar Nassar');
        $this->assertSame('RJ 100', $omar->flight_no);
        $this->assertSame('Queen Alia Airport', $omar->pickup_point);
    }

    public function test_deleting_a_vehicle_returns_its_guests_to_the_pool(): void
    {
        $user = $this->admin();
        $event = $this->event();
        VehicleType::create(['name' => 'Bus', 'capacity' => 20, 'is_active' => true, 'position' => 1]);

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set('planFile', $this->guestSheet())
            ->call('importPlan')
            ->call('suggestGrouping');

        $this->assertGreaterThan(0, $event->transport()->count(), 'runs were created');
        $assigned = $event->transferGuests()->whereNotNull('transport_id')->count();
        $this->assertGreaterThan(0, $assigned);

        $c->call('deleteAllMovements');

        $this->assertSame(0, $event->transport()->count(), 'every vehicle is gone');
        $this->assertSame(4, $event->transferGuests()->count(), 'the people survive their vehicle');
        $this->assertSame(4, $event->transferGuests()->whereNull('transport_id')->count(),
            'and are waiting in the pool, not deleted');
    }

    public function test_guests_can_be_deleted_in_bulk_and_wholesale(): void
    {
        $user = $this->admin();
        $event = $this->event();

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set('planFile', $this->guestSheet())
            ->call('importPlan');

        $this->assertSame(4, $event->transferGuests()->count());

        $sara = $event->transferGuests()->where('name', 'Sara Kamal')->firstOrFail();
        $c->set('pickedGuests', [$sara->id])->call('deletePicked');

        $this->assertSame(3, $event->transferGuests()->count());
        $this->assertSame(0, $event->transferGuests()->where('name', 'Sara Kamal')->count());

        $c->call('deleteAllGuests');
        $this->assertSame(0, $event->transferGuests()->count(), 'both legs cleared');
    }

    public function test_a_guest_can_be_moved_straight_from_one_vehicle_to_another(): void
    {
        $user = $this->admin();
        $event = $this->event();
        VehicleType::create(['name' => 'Small', 'capacity' => 4, 'is_active' => true, 'position' => 1]);

        $a = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Run A', 'status' => 'planned']);
        $b = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Run B', 'status' => 'planned']);

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set('planFile', $this->guestSheet())
            ->call('importPlan');

        $guest = $event->transferGuests()->where('name', 'Layla Odeh')->where('direction', 'arrival')->firstOrFail();

        $c->call('moveGuest', $guest->id, (string) $a->id);
        $this->assertSame($a->id, $guest->fresh()->transport_id);

        // The point of the control: straight across, no trip back through the pool.
        $c->call('moveGuest', $guest->id, (string) $b->id);
        $this->assertSame($b->id, $guest->fresh()->transport_id);
        $this->assertSame(0, $a->manifest()->count(), 'the old run is emptied');

        $c->call('moveGuest', $guest->id, '');
        $this->assertNull($guest->fresh()->transport_id, 'an empty value sends them back to the pool');
    }

    public function test_the_manifest_pdf_exports_only_the_selected_leg(): void
    {
        $user = $this->admin();
        $event = $this->event();

        $in = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Airport → Hotel',
            'leg' => 'arrival', 'status' => 'planned', 'depart_at' => '2026-07-27 14:30']);
        $out = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Hotel → Airport',
            'leg' => 'departure', 'status' => 'planned', 'depart_at' => '2026-07-29 06:00']);
        $other = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Hotel → Venue',
            'leg' => 'other', 'status' => 'planned', 'depart_at' => '2026-07-28 10:00']);

        // Departure times are the one thing unique to a row — the leg names
        // themselves also appear in the tab strip.
        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event]);
        $c->call('setLeg', 'arrival')
            ->assertSee('14:30')
            ->assertDontSee('06:00')
            ->assertDontSee('10:00');

        $c->call('setLeg', 'other')
            ->assertSee('10:00')
            ->assertDontSee('14:30');

        $this->assertSame(['leg' => 'other'], $c->instance()->exportFilters());

        $all = $event->transport()->get();
        $this->assertSame($out->id, EventTransport::selection($all, 'departure', '')->first()->id);
        $this->assertSame($other->id, EventTransport::selection($all, 'other', '')->first()->id);
        $this->assertCount(3, EventTransport::selection($all, '', ''), 'no leg means everything');

        // A day narrows within the leg rather than across everything.
        $this->assertCount(1, EventTransport::selection($all, 'departure', '2026-07-29'));
        $this->assertCount(0, EventTransport::selection($all, 'departure', '2026-07-27'));

        $res = $this->actingAs($user)->get(route('events.transport.pdf', [$event, 'leg' => 'arrival']));
        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('arrivals-pick-ups', $res->headers->get('Content-Disposition'),
            'a filtered export names its slice in the filename');

        // Unused, but proves the value is not a free-for-all.
        $this->assertSame($in->id, EventTransport::selection($all, 'arrival', '')->first()->id);
        $c->call('setLeg', 'nonsense');
        $this->assertSame('', $c->instance()->filterLeg, 'an unknown leg falls back to showing everything');
    }

    public function test_a_manually_added_movement_takes_a_leg_and_lands_in_its_tab(): void
    {
        $user = $this->admin();
        $event = $this->event();

        Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->call('newItem')
            ->set('leg', 'departure')
            ->set('pickup_from', 'Hotel')
            ->set('drop_to', 'Queen Alia International Airport')
            ->set('depart_at', '2026-07-29T06:00')
            ->call('save')
            ->assertHasNoErrors();

        $m = $event->transport()->firstOrFail();
        $this->assertSame('departure', $m->leg);
        $this->assertCount(1, EventTransport::selection($event->transport()->get(), 'departure', ''));
    }

    public function test_the_pool_only_offers_vehicles_that_match_the_guests_leg(): void
    {
        $user = $this->admin();
        $event = $this->event();

        $arr = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Airport → Hotel',
            'leg' => 'arrival', 'status' => 'planned', 'depart_at' => '2026-07-27 14:30']);
        $dep = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Hotel → Airport',
            'leg' => 'departure', 'status' => 'planned', 'depart_at' => '2026-07-29 06:00']);
        $shuttle = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Hotel → Venue',
            'leg' => 'other', 'status' => 'planned', 'depart_at' => '2026-07-28 09:00']);

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set('planFile', $this->guestSheet())
            ->call('importPlan');

        $targets = fn () => collect($c->viewData('assignTargets'))->pluck('id')->all();

        // Arrivals: the arrival run and the shuttle, never the departure run.
        $c->call('setGuestLeg', 'arrival');
        $this->assertEqualsCanonicalizing([$arr->id, $shuttle->id], $targets(),
            'an arriving guest cannot be put on a departure vehicle');

        $c->call('setGuestLeg', 'departure');
        $this->assertEqualsCanonicalizing([$dep->id, $shuttle->id], $targets());
    }

    public function test_movements_are_numbered_and_can_be_assigned_by_number(): void
    {
        $user = $this->admin();
        $event = $this->event();

        $one = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Airport → Hotel',
            'leg' => 'arrival', 'status' => 'planned', 'depart_at' => '2026-07-27 14:30']);
        $two = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Airport → Hotel 2',
            'leg' => 'arrival', 'status' => 'planned', 'depart_at' => '2026-07-27 19:45']);
        $dep = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Hotel → Airport',
            'leg' => 'departure', 'status' => 'planned', 'depart_at' => '2026-07-29 06:00']);

        $this->assertSame([1, 2, 3], [$one->ref_no, $two->ref_no, $dep->ref_no], 'numbered on creation');

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set('planFile', $this->guestSheet())
            ->call('importPlan');

        $arriving = $event->transferGuests()->where('direction', 'arrival')->pluck('id')->all();

        // "#2" and "2" mean the same thing.
        $c->set('pickedGuests', $arriving)->set('assignRef', '#2')->call('assignToNumber');
        $this->assertSame(3, $two->manifest()->count());
        $this->assertSame('', $c->instance()->assignRef, 'the box clears after use');

        // A number that isn't there says so instead of failing silently.
        $c->set('pickedGuests', $arriving)->set('assignRef', '99')->call('assignToNumber');
        $this->assertStringContainsString('no car #99', $c->instance()->planMsg);

        // The leg guard holds here too: arrivals cannot go on the departure car.
        $c->set('pickedGuests', $arriving)->set('assignRef', '3')->call('assignToNumber');
        $this->assertSame(0, $dep->manifest()->count());
        $this->assertStringContainsString('departure movement', $c->instance()->planMsg);

        // Numbers are never reused — a printed sheet for car 2 stays car 2.
        $two->delete();
        $next = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Late addition',
            'leg' => 'arrival', 'status' => 'planned']);
        $this->assertSame(4, $next->ref_no);
    }

    public function test_the_leg_is_inferred_for_movements_that_predate_the_field(): void
    {
        // Who is riding beats any guess from the route.
        $this->assertSame('arrival',
            EventTransport::inferLeg('Hotel', 'Venue', 'Hotel → Venue', ['arrival', 'arrival', 'departure']));

        // Otherwise, whichever end is the airport decides it.
        $this->assertSame('arrival', EventTransport::inferLeg('Queen Alia International Airport', 'Hotel'));
        $this->assertSame('departure', EventTransport::inferLeg('Hotel', 'QAIA Terminal 2'));

        // A route string alone is still enough.
        $this->assertSame('departure', EventTransport::inferLeg('', '', 'Hotel → Airport'));

        // Neither end an airport, nobody aboard: not an airport run.
        $this->assertSame('other', EventTransport::inferLeg('Hotel', 'Venue', 'Hotel → Venue'));
    }

    public function test_taking_a_passenger_off_a_vehicle_returns_them_to_the_pool(): void
    {
        $user = $this->admin();
        $event = $this->event();
        VehicleType::create(['name' => 'Bus', 'capacity' => 20, 'is_active' => true, 'position' => 1]);

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set('planFile', $this->guestSheet())
            ->call('importPlan')
            ->call('suggestGrouping');

        $guest = $event->transferGuests()->whereNotNull('transport_id')->firstOrFail();

        $c->call('unassignGuest', $guest->id);

        $this->assertNotNull($guest->fresh(), 'the guest still exists');
        $this->assertNull($guest->fresh()->transport_id, 'and is back in the pool');
        $this->assertNull($guest->fresh()->vehicle_no);
    }

    public function test_manifest_template_downloads_with_expected_headers(): void
    {
        $user = $this->admin();
        $event = $this->event();
        $move = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Airport → Hotel', 'status' => 'planned', 'flight_no' => 'RJ 100']);

        $res = $this->actingAs($user)->get(route('events.transport.template', [$event, $move]));
        $res->assertOk();

        $path = tempnam(sys_get_temp_dir(), 't').'.xlsx';
        file_put_contents($path, $res->streamedContent());
        $ss = IOFactory::load($path);
        @unlink($path);

        $this->assertSame('Manifest', $ss->getActiveSheet()->getTitle());
        $header = $ss->getActiveSheet()->toArray()[0];
        $this->assertSame('Name', $header[0]);
        $this->assertContains('Flight #', $header);
        $this->assertContains('Pickup Point', $header);
    }
}
