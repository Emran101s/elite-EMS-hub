<?php

namespace Tests\Feature;

use App\Livewire\Hub\TransportationTab;
use App\Models\Event;
use App\Models\EventTransport;
use App\Models\EventTransportPassenger;
use App\Models\TransportServiceType;
use App\Models\User;
use App\Models\VehicleType;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransportManifestTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Event,1:User} */
    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);
        VehicleType::ensureSeeded();
        TransportServiceType::ensureSeeded();

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    private function van(Event $event, int $vehicles = 1): EventTransport
    {
        $van = VehicleType::where('name', 'Regular Van')->firstOrFail();   // max 7

        return $event->transport()->create([
            'type' => 'shuttle',
            'vehicle_type_id' => $van->id,
            'vehicles' => $vehicles,
            'route' => 'Airport → Hotel',
            'pickup_from' => 'Queen Alia Airport',
            'drop_to' => 'Fairmont Amman',
            'flight_no' => 'RJ 512',
            'depart_at' => '2026-10-17 14:20',
            'capacity' => 7 * $vehicles,
            'status' => 'ordered',
        ]);
    }

    public function test_a_van_takes_seven_named_passengers_and_then_refuses_the_eighth(): void
    {
        [$event, $user] = $this->ctx();
        $movement = $this->van($event);

        $this->assertSame(7, $movement->seats());

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event]);

        foreach (range(1, 7) as $n) {
            $c->set("newPax.{$movement->id}", "Passenger {$n}")->call('addPassenger', $movement->id);
        }

        $movement->refresh();
        $this->assertSame(7, $movement->manifest()->count());
        $this->assertSame(0, $movement->seatsFree());
        $this->assertFalse($movement->isOverbooked());

        // The eighth has nowhere to sit.
        $c->set("newPax.{$movement->id}", 'One Too Many')->call('addPassenger', $movement->id)
            ->assertHasErrors("newPax.{$movement->id}");
        $this->assertSame(7, $movement->fresh()->manifest()->count());
    }

    public function test_two_vans_double_the_seats(): void
    {
        [$event] = $this->ctx();

        $this->assertSame(14, $this->van($event, 2)->seats());
    }

    public function test_a_passenger_inherits_the_runs_flight_and_pickup_point(): void
    {
        [$event, $user] = $this->ctx();
        $movement = $this->van($event);

        Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set("newPax.{$movement->id}", 'Dana Haddad')->call('addPassenger', $movement->id);

        $pax = $movement->manifest()->firstOrFail();
        $this->assertSame('RJ 512', $pax->flight_no);
        $this->assertSame('Queen Alia Airport', $pax->pickup_point);
    }

    public function test_passenger_details_save_inline_and_reject_unknown_fields(): void
    {
        [$event, $user] = $this->ctx();
        $movement = $this->van($event);

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set("newPax.{$movement->id}", 'Dana Haddad')->call('addPassenger', $movement->id);

        $pax = $movement->manifest()->firstOrFail();
        $c->call('updatePassenger', $pax->id, 'phone', '+962 79 555 0111')
            ->call('updatePassenger', $pax->id, 'flight_no', 'MS 703')
            ->call('updatePassenger', $pax->id, 'transport_id', '99999');

        $pax->refresh();
        $this->assertSame('+962 79 555 0111', $pax->phone);
        $this->assertSame('MS 703', $pax->flight_no);
        $this->assertSame($movement->id, $pax->transport_id, 'transport_id is not editable');
    }

    public function test_named_passengers_override_the_estimate(): void
    {
        [$event, $user] = $this->ctx();
        $movement = $this->van($event);
        $movement->update(['passengers' => 5]);   // the guess made before names existed

        $this->assertSame(5, $movement->fresh()->paxCount());

        Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set("newPax.{$movement->id}", 'Dana Haddad')->call('addPassenger', $movement->id);

        $this->assertSame(1, $movement->fresh()->paxCount(), 'the manifest is the truth once it exists');
    }

    public function test_the_fleet_count_totals_vehicles_by_type(): void
    {
        [$event, $user] = $this->ctx();
        $sedan = VehicleType::where('name', 'Regular Sedan')->firstOrFail();

        $this->van($event, 2);   // 2 vans
        $this->van($event, 1);   // 1 more van
        $event->transport()->create(['type' => 'shuttle', 'vehicle_type_id' => $sedan->id,
            'vehicles' => 3, 'route' => 'VIP run', 'capacity' => 6, 'status' => 'ordered']);

        $fleet = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->viewData('fleet');

        $this->assertSame(3, $fleet['Regular Van']['vehicles']);
        $this->assertSame(2, $fleet['Regular Van']['runs']);
        $this->assertSame(3, $fleet['Regular Sedan']['vehicles']);
    }

    public function test_duplicating_a_run_does_not_copy_its_passengers(): void
    {
        [$event, $user] = $this->ctx();
        $movement = $this->van($event);

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set("newPax.{$movement->id}", 'Dana Haddad')->call('addPassenger', $movement->id);

        $c->call('duplicate', $movement->id);

        $copy = $event->transport()->where('id', '!=', $movement->id)->latest('id')->firstOrFail();
        $this->assertSame(0, $copy->manifest()->count(), 'a new run needs its own names');
        $this->assertSame('2026-10-18', $copy->depart_at->format('Y-m-d'), 'shifted one day on');
    }

    public function test_deleting_a_movement_frees_its_manifest_rather_than_erasing_it(): void
    {
        [$event, $user] = $this->ctx();
        $movement = $this->van($event);

        Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set("newPax.{$movement->id}", 'Dana Haddad')->call('addPassenger', $movement->id)
            ->call('delete', $movement->id);

        $this->assertSame(0, EventTransportPassenger::where('transport_id', $movement->id)->count(),
            'nobody is left riding a deleted vehicle');

        // The vehicle was a plan; the person is data. Dana still needs a transfer.
        $dana = $event->transferGuests()->where('name', 'Dana Haddad')->first();
        $this->assertNotNull($dana, 'the passenger survives the vehicle');
        $this->assertNull($dana->transport_id, 'and is waiting in the pool');
    }

    public function test_the_manifest_pdf_lists_the_names_and_the_vehicle_count(): void
    {
        [$event, $user] = $this->ctx();
        $movement = $this->van($event, 2);

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event]);
        foreach (['Dana Haddad', 'Omar Nasser'] as $n) {
            $c->set("newPax.{$movement->id}", $n)->call('addPassenger', $movement->id);
        }

        $this->actingAs($user)
            ->get(route('events.transport.pdf', $event))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        // Assert on the source HTML so the content check doesn't depend on PDF internals.
        $movements = $event->transport()->with(['vehicleType', 'serviceType', 'manifest'])->get();
        $html = view('events.transport-manifest-pdf', [
            'event' => $event,
            'days' => $movements->groupBy(fn ($m) => $m->depart_at?->format('Y-m-d') ?? 'unscheduled'),
            'fleet' => collect(['Regular Van' => ['name' => 'Regular Van', 'capacity' => 7,
                'vehicles' => 2, 'runs' => 1, 'pax' => 2]]),
            'movements' => $movements,
            'css' => '',
        ])->render();

        $this->assertStringContainsString('Dana Haddad', $html);
        $this->assertStringContainsString('Omar Nasser', $html);
        $this->assertStringContainsString('RJ 512', $html);
        $this->assertStringContainsString('Vehicles required', $html);
        $this->assertStringContainsString('×2', $html);
    }

    public function test_a_movement_with_no_names_renders_cleanly(): void
    {
        [$event] = $this->ctx();
        $movement = $this->van($event);
        $movement->update(['passengers' => 5]);   // an estimate, nobody named yet

        $movements = $event->transport()->with(['vehicleType', 'serviceType', 'manifest'])->get();
        $html = view('events.transport-manifest-pdf', [
            'event' => $event,
            'days' => $movements->groupBy(fn ($m) => $m->depart_at?->format('Y-m-d') ?? 'unscheduled'),
            'fleet' => collect(),
            'movements' => $movements,
            'css' => '',
        ])->render();

        $this->assertStringContainsString('5 passengers expected', $html);

        // Blade only treats `@` as a directive when it does not follow a word
        // character, so `yet@if(...)` silently prints as text. Guard the whole file.
        foreach (['@if', '@endif', '@foreach', '@endforeach', '@php'] as $leak) {
            $this->assertStringNotContainsString($leak, $html, "unparsed Blade directive {$leak} leaked into the PDF");
        }
    }

    public function test_movements_run_in_date_then_time_order_with_unscheduled_last(): void
    {
        [$event, $user] = $this->ctx();
        $van = VehicleType::where('name', 'Regular Van')->firstOrFail();

        // Created deliberately out of order, including one with no time at all.
        foreach ([
            'later-same-day' => '2026-10-17 21:40',
            'unscheduled' => null,
            'next-day-early' => '2026-10-18 06:15',
            'first' => '2026-10-17 08:00',
        ] as $label => $when) {
            $event->transport()->create(['type' => 'shuttle', 'vehicle_type_id' => $van->id,
                'vehicles' => 1, 'route' => $label, 'depart_at' => $when, 'capacity' => 7, 'status' => 'planned']);
        }

        $order = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->viewData('movements')->flatten()->pluck('route')->all();

        $this->assertSame(
            ['first', 'later-same-day', 'next-day-early', 'unscheduled'],
            $order,
            'earliest first; a run with no time is not the earliest run'
        );
    }

    public function test_the_manifest_shows_flight_pickup_and_driver_but_no_email(): void
    {
        [$event, $user] = $this->ctx();
        $movement = $this->van($event);
        $movement->update(['driver_contact' => '+962 79 555 0100', 'arrive_at' => '2026-10-17 13:50']);

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set("newPax.{$movement->id}", 'Dana Haddad')->call('addPassenger', $movement->id);

        $pax = $movement->manifest()->firstOrFail();

        // Phone and pick-up point stay; email is no longer a manifest field.
        $c->call('updatePassenger', $pax->id, 'phone', '+962 79 555 0111')
            ->call('updatePassenger', $pax->id, 'email', 'dana@icft.org');

        $pax->refresh();
        $this->assertSame('+962 79 555 0111', $pax->phone);
        $this->assertNull($pax->email, 'email is not collected on a transport manifest');

        $movements = $event->transport()->with(['vehicleType', 'serviceType', 'manifest'])->get();
        $html = view('events.transport-manifest-pdf', [
            'event' => $event,
            'days' => $movements->groupBy(fn ($m) => $m->depart_at?->format('Y-m-d') ?? 'unscheduled'),
            'fleet' => collect(),
            'movements' => $movements,
            'css' => '',
        ])->render();

        foreach (['RJ 512', '+962 79 555 0100', 'Driver', 'Pick-up', 'Queen Alia Airport'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertStringNotContainsString('Email', $html);
    }

    public function test_each_passenger_carries_their_own_airline_flight_and_arrival(): void
    {
        [$event, $user] = $this->ctx();
        $movement = $this->van($event);
        $movement->update(['arrive_at' => '2026-10-17 13:50']);

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event]);
        foreach (['Dana Haddad', 'Yusuf Barakat'] as $n) {
            $c->set("newPax.{$movement->id}", $n)->call('addPassenger', $movement->id);
        }

        [$dana, $yusuf] = $movement->manifest()->get()->all();

        // Seeded from the run the seat was booked against.
        $this->assertSame('RJ 512', $dana->flight_no);
        $this->assertSame('2026-10-17', $dana->arrival_on->format('Y-m-d'));
        $this->assertSame('13:50', $dana->arrival_time);

        // Yusuf is on a different airline, landing later the same evening.
        $c->call('updatePassenger', $yusuf->id, 'airline', 'British Airways')
            ->call('updatePassenger', $yusuf->id, 'flight_no', 'BA 147')
            ->call('updatePassenger', $yusuf->id, 'arrival_on', '2026-10-17')
            ->call('updatePassenger', $yusuf->id, 'arrival_time', '21:10');

        $yusuf->refresh();
        $this->assertSame('British Airways', $yusuf->airline);
        $this->assertSame('BA 147', $yusuf->flight_no);
        $this->assertSame('21:10', $yusuf->arrival_time);
        $this->assertSame('British Airways · BA 147 · 17 Oct · 21:10', $yusuf->flightLine());

        // Junk dates and times clear rather than crash or store garbage.
        $c->call('updatePassenger', $yusuf->id, 'arrival_time', 'half nine')
            ->call('updatePassenger', $yusuf->id, 'arrival_on', 'tomorrow');
        $yusuf->refresh();
        $this->assertNull($yusuf->arrival_time);
        $this->assertNull($yusuf->arrival_on);
    }

    public function test_the_export_prints_every_passengers_flight_row(): void
    {
        [$event, $user] = $this->ctx();
        $movement = $this->van($event);

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->set("newPax.{$movement->id}", 'Dana Haddad')->call('addPassenger', $movement->id);

        $pax = $movement->manifest()->firstOrFail();
        $c->call('updatePassenger', $pax->id, 'airline', 'Royal Jordanian')
            ->call('updatePassenger', $pax->id, 'arrival_on', '2026-10-17')
            ->call('updatePassenger', $pax->id, 'arrival_time', '13:50');

        $movements = $event->transport()->with(['vehicleType', 'serviceType', 'manifest'])->get();
        $html = view('events.transport-manifest-pdf', [
            'event' => $event,
            'days' => $movements->groupBy(fn ($m) => $m->depart_at?->format('Y-m-d') ?? 'unscheduled'),
            'fleet' => collect(),
            'movements' => $movements,
            'css' => '',
        ])->render();

        foreach (['Airline', 'Arrival', 'Royal Jordanian', 'RJ 512', '17 Oct · 13:50', 'Dana Haddad'] as $expected) {
            $this->assertStringContainsString($expected, $html, "the manifest must print {$expected}");
        }
    }

    public function test_a_viewer_cannot_add_passengers(): void
    {
        [$event] = $this->ctx();
        $viewer = User::create(['name' => 'Vic Viewer', 'email' => 'v@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);
        $movement = $this->van($event);

        Livewire::actingAs($viewer)->test(TransportationTab::class, ['event' => $event])
            ->set("newPax.{$movement->id}", 'Dana Haddad')
            ->call('addPassenger', $movement->id)
            ->assertForbidden();

        $this->assertSame(0, $movement->manifest()->count());
    }
}
