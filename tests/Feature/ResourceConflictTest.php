<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Venue;
use App\Services\CommandCenterService;
use App\Services\ResourceConflicts;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceConflictTest extends TestCase
{
    use RefreshDatabase;

    /** Two overlapping events, cleanly isolated from the demo data. */
    private function pair(array $a = [], array $b = []): array
    {
        $this->seed(DemoDataSeeder::class);
        Event::query()->update(['archived_at' => now()]);   // silence the demo set

        $mk = fn (array $extra) => Event::create($extra + [
            'type' => 'conference', 'stage' => 'planning', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-09-10', 'ends_at' => '2026-09-12', 'currency' => 'JOD',
        ]);

        return [$mk(['name' => 'Alpha Summit'] + $a), $mk(['name' => 'Beta Forum'] + $b)];
    }

    public function test_a_double_booked_venue_is_a_risk(): void
    {
        $this->seed(DemoDataSeeder::class);   // pair() re-seeds; it's idempotent
        $venue = Venue::firstOrFail();
        [$a, $b] = $this->pair(['venue_id' => $venue->id], ['venue_id' => $venue->id]);

        $conflicts = app(ResourceConflicts::class)->detect();

        $venueClash = $conflicts->firstWhere('type', 'venue');
        $this->assertNotNull($venueClash);
        $this->assertSame('risk', $venueClash['severity']);
        $this->assertSame($venue->name, $venueClash['label']);
        $this->assertSame(['Alpha Summit', 'Beta Forum'], collect($venueClash['events'])->pluck('name')->all());
    }

    public function test_non_overlapping_events_do_not_clash(): void
    {
        $this->seed(DemoDataSeeder::class);   // pair() re-seeds; it's idempotent
        $venue = Venue::firstOrFail();
        [$a, $b] = $this->pair(['venue_id' => $venue->id], ['venue_id' => $venue->id]);
        $b->update(['starts_at' => '2026-09-13', 'ends_at' => '2026-09-14']);   // day after A ends

        $this->assertNull(app(ResourceConflicts::class)->detect()->firstWhere('type', 'venue'));
    }

    public function test_the_same_person_on_two_overlapping_events_is_flagged(): void
    {
        [$a, $b] = $this->pair();
        $person = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
        $a->teamMembers()->attach($person, ['role' => 'project_manager']);
        $b->teamMembers()->attach($person, ['role' => 'operations_lead']);

        $clash = app(ResourceConflicts::class)->detect()->firstWhere('type', 'team');
        $this->assertNotNull($clash);
        $this->assertSame($person->name, $clash['label']);
        $this->assertSame('warn', $clash['severity']);
    }

    public function test_suppliers_are_only_flagged_past_capacity(): void
    {
        [$a, $b] = $this->pair();
        $supplier = Supplier::firstOrFail();
        $a->suppliers()->attach($supplier);
        $b->suppliers()->attach($supplier);

        // Two overlapping engagements is normal business, not a conflict.
        $this->assertNull(app(ResourceConflicts::class)->detect()->firstWhere('type', 'supplier'));

        // A fourth concurrent engagement crosses capacity (3).
        foreach (['Gamma Expo', 'Delta Gala'] as $name) {
            $e = Event::create(['name' => $name, 'type' => 'conference', 'stage' => 'planning',
                'city' => 'Amman', 'country' => 'Jordan', 'starts_at' => '2026-09-10', 'ends_at' => '2026-09-12', 'currency' => 'JOD']);
            $e->suppliers()->attach($supplier);
        }

        $clash = app(ResourceConflicts::class)->detect()->firstWhere('type', 'supplier');
        $this->assertNotNull($clash);
        $this->assertStringContainsString('4 overlapping engagements', $clash['detail']);
    }

    public function test_conflicts_surface_as_command_center_alerts(): void
    {
        $this->seed(DemoDataSeeder::class);   // pair() re-seeds; it's idempotent
        $venue = Venue::firstOrFail();
        $this->pair(['venue_id' => $venue->id], ['venue_id' => $venue->id]);

        $titles = app(CommandCenterService::class)->alerts()->pluck('title')->implode(' | ');
        $this->assertStringContainsString('Venue double-booked: '.$venue->name, $titles);
    }
}
