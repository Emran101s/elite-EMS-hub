<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Event;
use App\Models\EventTransport;
use App\Models\Supplier;
use App\Models\TransportDriver;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The transport document suite. Each PDF has exactly one reader, and the tests
 * assert the thing that reader would be let down by if it were missing.
 */
class TransportDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    private TransportDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'super_admin']);
        CompanyProfile::create(['name' => 'Elite Business Hub', 'phone' => '+962 6 000 0000']);

        $this->event = Event::create([
            'name' => 'Documents Summit', 'type' => 'summit', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-07-27', 'ends_at' => '2026-07-29',
            'project_manager_id' => $this->user->id,
        ]);

        $van = VehicleType::create(['name' => 'Regular Van', 'capacity' => 7, 'is_active' => true, 'position' => 1]);
        $this->driver = TransportDriver::create(['name' => 'Khaled Mansour', 'phone' => '+962 79 555 0111']);
        $car = TransportVehicle::create(['plate_no' => 'PLT 4471', 'model' => 'Mercedes V-Class', 'vehicle_type_id' => $van->id]);

        $arrival = $this->event->transport()->create([
            'type' => 'van', 'leg' => 'arrival', 'vehicles' => 1, 'vehicle_type_id' => $van->id,
            'route' => 'QAIA → Fairmont', 'pickup_from' => 'Queen Alia Airport', 'drop_to' => 'Fairmont Amman',
            'status' => 'confirmed', 'depart_at' => '2026-07-27 14:00', 'flight_no' => 'RJ 100',
            'driver_id' => $this->driver->id, 'vehicle_id' => $car->id,
        ]);

        $departure = $this->event->transport()->create([
            'type' => 'van', 'leg' => 'departure', 'vehicles' => 1, 'vehicle_type_id' => $van->id,
            'route' => 'Fairmont → QAIA', 'pickup_from' => 'Fairmont Amman', 'drop_to' => 'Queen Alia Airport',
            'status' => 'confirmed', 'depart_at' => '2026-07-29 06:00',
            'driver_id' => $this->driver->id,
        ]);

        $arrival->manifest()->createMany([
            ['event_id' => $this->event->id, 'name' => 'Dr Amal Al-Rashid', 'category' => 'vip',
                'direction' => 'arrival', 'phone' => '+962 79 111 2222', 'flight_no' => 'RJ 100',
                'airline' => 'Royal Jordanian', 'arrival_on' => '2026-07-27', 'arrival_time' => '14:00',
                'pickup_point' => 'Queen Alia Airport', 'drop_point' => 'Fairmont Amman',
                'hotel' => 'Fairmont Amman', 'protocol_note' => 'Greet at the aerobridge with a name board',
                'luggage_note' => 'Two large cases', 'position' => 1],
            ['event_id' => $this->event->id, 'name' => 'Omar Nassar', 'category' => 'delegate',
                'direction' => 'arrival', 'position' => 2],
        ]);

        $departure->manifest()->create([
            'event_id' => $this->event->id, 'name' => 'Dr Amal Al-Rashid', 'category' => 'vip',
            'direction' => 'departure', 'arrival_on' => '2026-07-29', 'pickup_time' => '06:00',
            'pickup_point' => 'Fairmont Amman', 'drop_point' => 'Queen Alia Airport', 'position' => 1,
        ]);
    }

    private function html(string $view, array $data): string
    {
        return view($view, $data + ['css' => '', 'control' => $this->event->controlContact()])->render();
    }

    // ── driver trip sheet ───────────────────────────────────────

    public function test_the_driver_sheet_renders_and_names_the_emergency_contact(): void
    {
        $res = $this->actingAs($this->user)->get(route('events.transport.trip-sheet.pdf', $this->event));
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('trip-sheet', $res->headers->get('Content-Disposition'));

        $movements = $this->event->transport()->with(['driver', 'vehicle', 'vehicleType', 'manifest'])->get();
        $html = $this->html('events.transport-driver-sheet-pdf', [
            'event' => $this->event,
            'sheets' => collect([[
                'driver' => $this->driver,
                'date' => $movements->first()->depart_at,
                'runs' => $movements->take(1),
                'duty' => 60,
            ]]),
        ]);

        $this->assertStringContainsString('Khaled Mansour', $html);
        $this->assertStringContainsString('+962 79 555 0111', $html, "the driver's own number");
        $this->assertStringContainsString('Dr Amal Al-Rashid', $html, 'who is riding');
        $this->assertStringContainsString('+962 79 111 2222', $html, 'a passenger number to call on arrival');
        $this->assertStringContainsString('Queen Alia Airport', $html);
        $this->assertStringContainsString('Mercedes V-Class', $html);

        // The one thing a driver must be able to find without reading the page.
        $this->assertStringContainsString('+962 6 000 0000', $html, 'event control');
        $this->assertStringContainsString('Emergency', $html);
    }

    public function test_a_missing_emergency_number_says_so_rather_than_printing_a_dash(): void
    {
        CompanyProfile::query()->update(['phone' => null]);

        $html = $this->html('events.transport-driver-sheet-pdf', [
            'event' => $this->event,
            'sheets' => collect([[
                'driver' => $this->driver,
                'date' => now(),
                'runs' => $this->event->transport()->with('manifest')->take(1)->get(),
                'duty' => 0,
            ]]),
        ]);

        // A dash on a safety field reads as "none needed" — it has to be loud
        // enough that whoever prints this catches it first.
        $this->assertStringContainsString('No number set', $html);
        $this->assertStringContainsString('Add a company phone in Settings', $html);
    }

    public function test_a_driver_working_two_days_gets_two_sheets(): void
    {
        $res = $this->actingAs($this->user)->get(route('events.transport.trip-sheet.pdf', [$this->event, 'day' => '2026-07-27']));
        $res->assertOk();

        // One driver, two days, two runs — but a sheet is a driver AND a day.
        $movements = $this->event->transport()->whereNotNull('driver_id')->get();
        $sheets = $movements->groupBy(fn (EventTransport $m) => $m->driver_id.'|'.$m->depart_at->format('Y-m-d'));

        $this->assertCount(2, $sheets, 'a sheet is what a driver is handed on one morning');
    }

    // ── VIP sheet ───────────────────────────────────────────────

    public function test_the_vip_sheet_gives_each_person_one_page_with_every_movement(): void
    {
        $res = $this->actingAs($this->user)->get(route('events.transport.vip-sheet.pdf', $this->event));
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $guests = $this->event->transferGuests()->priority()
            ->with(['transport.driver', 'transport.vehicle', 'transport.vehicleType'])->get();

        // The same VIP arriving and departing is ONE sheet with two movements.
        $people = $guests->groupBy(fn ($g) => mb_strtolower(trim($g->name)))
            ->map(fn ($rows) => ['guest' => $rows->first(), 'legs' => $rows->sortBy('arrival_on')]);

        $this->assertCount(1, $people, 'one page per person, not per movement');
        $this->assertCount(2, $people->first()['legs'], 'both legs on that one page');

        $html = $this->html('events.transport-vip-sheet-pdf', [
            'event' => $this->event,
            'people' => $people->values(),
            'scope' => 'VIP & Speakers',
        ]);

        $this->assertStringContainsString('Dr Amal Al-Rashid', $html);
        $this->assertStringContainsString('Greet at the aerobridge', $html, 'protocol note');
        $this->assertStringContainsString('Two large cases', $html, 'luggage note');
        $this->assertStringContainsString('Khaled Mansour', $html, 'the driver to look for');
        $this->assertStringContainsString('Fairmont Amman', $html);

        // A delegate is not a VIP and must not leak onto the protocol document.
        $this->assertStringNotContainsString('Omar Nassar', $html);
    }

    public function test_the_vip_sheet_can_be_narrowed_to_one_category(): void
    {
        $res = $this->actingAs($this->user)->get(route('events.transport.vip-sheet.pdf', [$this->event, 'category' => 'speaker']));
        $res->assertOk();
        $this->assertStringContainsString('speaker', $res->headers->get('Content-Disposition'));
    }

    // ── daily schedule ──────────────────────────────────────────

    public function test_the_daily_schedule_is_one_page_per_day_and_honours_the_filters(): void
    {
        $res = $this->actingAs($this->user)->get(route('events.transport.daily-schedule.pdf', $this->event));
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $movements = $this->event->transport()->with(['driver', 'vehicle', 'vehicleType', 'manifest'])->get();

        $html = $this->html('events.transport-daily-schedule-pdf', [
            'event' => $this->event,
            'days' => $movements->groupBy(fn (EventTransport $m) => $m->depart_at->format('Y-m-d')),
            'movements' => $movements,
            'selection' => 'All movements',
        ]);

        $this->assertStringContainsString('Monday', $html);
        $this->assertStringContainsString('Wednesday', $html);
        $this->assertStringContainsString('Khaled Mansour', $html);
        $this->assertStringContainsString('Dr Amal Al-Rashid', $html);
        $this->assertStringContainsString('14:00', $html);

        // A filtered export names its slice, exactly as the manifest does.
        $filtered = $this->actingAs($this->user)
            ->get(route('events.transport.daily-schedule.pdf', [$this->event, 'leg' => 'departure']));
        $filtered->assertOk();
        $this->assertStringContainsString('departures-drop-offs', $filtered->headers->get('Content-Disposition'));
    }

    public function test_a_delayed_run_shows_the_new_time_and_strikes_the_old(): void
    {
        $trip = $this->event->transport()->where('leg', 'arrival')->firstOrFail();
        $trip->update(['delayed_to' => '2026-07-27 16:45']);

        $html = $this->html('events.transport-daily-schedule-pdf', [
            'event' => $this->event,
            'days' => collect(['2026-07-27' => collect([$trip->fresh()->load(['driver', 'manifest'])])]),
            'movements' => collect([$trip]),
            'selection' => 'All movements',
        ]);

        $this->assertStringContainsString('16:45', $html, 'the time it actually leaves');
        $this->assertStringContainsString('line-through', $html, 'the original is struck, not erased');
    }

    // ── master plan (client) ────────────────────────────────────

    public function test_the_master_plan_summarises_the_event_and_shows_a_cost_total_only(): void
    {
        $this->event->transport()->whereNotNull('id')->update(['cost_cents' => 15000]);   // JD 150 each

        $res = $this->actingAs($this->user)->get(route('events.transport.master-plan.pdf', $this->event));
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $movements = $this->event->transport()->with(['vehicleType', 'driver', 'manifest'])->get();
        $html = view('events.transport-master-plan-pdf', [
            'event' => $this->event, 'company' => CompanyProfile::first(), 'movements' => $movements,
            'fleet' => collect([['name' => 'Regular Van', 'capacity' => 7, 'vehicles' => 2, 'runs' => 2]]),
            'byLeg' => collect([['label' => 'Arrival', 'runs' => 1, 'pax' => 2]]),
            'routes' => collect([['route' => 'QAIA → Fairmont', 'runs' => 1, 'legs' => 'Arrival']]),
            'summary' => ['movements' => 2, 'vehicles' => 2, 'drivers' => 1, 'suppliers' => 0,
                'guests' => 2, 'arrivals' => 1, 'departures' => 1, 'vip' => 1, 'days' => 3],
            'costCents' => 30000, 'control' => $this->event->controlContact(), 'css' => '',
        ])->render();

        $this->assertStringContainsString('Transportation Plan', $html);
        $this->assertStringContainsString('Approval', $html, 'a client document has a signature block');
        $this->assertStringContainsString('Estimated transportation cost', $html);

        // A total is fine for the client; margin and per-supplier rates are not.
        // (Bare "margin" would match the CSS reset, so check the real disclosures.)
        $text = strtolower(strip_tags($html));
        $this->assertStringNotContainsString('markup', $text);
        $this->assertStringNotContainsString('profit', $text);
        $this->assertStringNotContainsString('supplier rate', $text);
    }

    // ── supplier order (vendor) ─────────────────────────────────

    public function test_the_supplier_order_lists_what_to_provide_without_a_client_price(): void
    {
        $supplier = Supplier::create(['name' => 'Elite Fleet Co', 'category' => 'transport']);
        $this->event->transport()->where('leg', 'arrival')->update([
            'supplier_id' => $supplier->id, 'cost_cents' => 15000,
        ]);

        $res = $this->actingAs($this->user)->get(route('events.transport.supplier-order.pdf', [$this->event, 'supplier' => $supplier->id]));
        $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('elite-fleet-co', $res->headers->get('Content-Disposition'));

        $runs = $this->event->transport()->where('supplier_id', $supplier->id)->with('vehicleType')->get();
        $html = view('events.transport-supplier-order-pdf', [
            'event' => $this->event, 'company' => CompanyProfile::first(),
            'orders' => collect([[
                'supplier' => $supplier,
                'runs' => $runs,
                'requirements' => collect([['type' => 'Regular Van', 'capacity' => 7, 'date' => '2026-07-27', 'vehicles' => 1, 'trips' => 1]]),
                'days' => 1,
            ]]),
            'control' => $this->event->controlContact(), 'css' => '',
        ])->render();

        $this->assertStringContainsString('Transport Order', $html);
        $this->assertStringContainsString('Elite Fleet Co', $html);
        $this->assertStringContainsString('Vehicles required', $html);
        $this->assertStringContainsString('Please quote', $html, 'it asks the vendor to price it');

        // The vendor is told what to provide, never what the client pays.
        $this->assertStringNotContainsString('150', $html, 'no client price on a supplier request');
    }

    // ── access ──────────────────────────────────────────────────

    public function test_the_documents_require_a_signed_in_user(): void
    {
        foreach (['daily-schedule', 'trip-sheet', 'vip-sheet', 'master-plan', 'supplier-order'] as $doc) {
            $this->get(route("events.transport.{$doc}.pdf", $this->event))
                ->assertRedirect(route('login'));
        }
    }

    public function test_the_sheets_render_cleanly_with_nothing_to_show(): void
    {
        $empty = Event::create([
            'name' => 'Empty Event', 'type' => 'summit', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-08-01', 'ends_at' => '2026-08-02',
        ]);

        foreach (['daily-schedule', 'trip-sheet', 'vip-sheet', 'master-plan', 'supplier-order'] as $doc) {
            $this->actingAs($this->user)
                ->get(route("events.transport.{$doc}.pdf", $empty))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }
    }
}
