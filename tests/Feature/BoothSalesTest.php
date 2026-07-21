<?php

namespace Tests\Feature;

use App\Livewire\ExhibitionFloorPlan;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BoothSalesTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);
        $event = Event::where('name', 'Tech Expo 2026')->firstOrFail();
        $event->booths()->delete();
        $event->exhibitors()->delete();
        $event->ensureExhibitionHall();

        return [$event, User::where('email', 'emran.itan@elitebhub.com')->firstOrFail()];
    }

    public function test_a_booth_is_inventory_before_it_is_a_sale(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(ExhibitionFloorPlan::class, ['event' => $event])
            ->call('addBooth');

        $booth = $event->booths()->firstOrFail();
        $this->assertSame('available', $booth->status());
        $this->assertSame('B01', $booth->number);

        // Numbers auto-increment and never collide.
        Livewire::actingAs($user)->test(ExhibitionFloorPlan::class, ['event' => $event])->call('addBooth');
        $this->assertSame(['B01', 'B02'], $event->booths()->orderBy('number')->pluck('number')->all());
    }

    public function test_assigning_an_exhibitor_is_the_sale(): void
    {
        [$event, $user] = $this->ctx();
        $reserved = $event->exhibitors()->create(['company' => 'Reserved Co', 'status' => 'reserved']);
        $paid = $event->exhibitors()->create(['company' => 'Paid Co', 'status' => 'paid']);

        $c = Livewire::actingAs($user)->test(ExhibitionFloorPlan::class, ['event' => $event]);
        $c->call('addBooth')->call('addBooth');
        [$b1, $b2] = $event->booths()->orderBy('number')->get();

        $c->call('setBoothPrice', $b1->id, 5000);
        $c->call('assignExhibitor', $b1->id, $reserved->id);
        $c->call('assignExhibitor', $b2->id, $paid->id);

        // Status derives from the buyer, so floor plan and money can't disagree.
        $this->assertSame('reserved', $b1->fresh()->status());
        $this->assertSame('sold', $b2->fresh()->status());

        // The exhibitor record follows the booth: number always, fee when unset.
        $this->assertSame('B01', $reserved->fresh()->booth_number);
        $this->assertSame(500000, $reserved->fresh()->fee_cents);

        // A booth can't be double-sold.
        $other = $event->exhibitors()->create(['company' => 'Late Co', 'status' => 'reserved']);
        $c->call('assignExhibitor', $b1->id, $other->id);
        $this->assertSame($reserved->id, $b1->fresh()->exhibitor_id);
    }

    public function test_releasing_puts_the_booth_back_on_sale(): void
    {
        [$event, $user] = $this->ctx();
        $ex = $event->exhibitors()->create(['company' => 'Acme', 'status' => 'confirmed']);

        $c = Livewire::actingAs($user)->test(ExhibitionFloorPlan::class, ['event' => $event]);
        $c->call('addBooth');
        $booth = $event->booths()->firstOrFail();
        $c->call('assignExhibitor', $booth->id, $ex->id);
        $this->assertSame('sold', $booth->fresh()->status());

        $c->call('releaseExhibitor', $booth->id);
        $this->assertSame('available', $booth->fresh()->status());
        $this->assertNull($ex->fresh()->booth_number);
    }

    public function test_sales_stats_add_up(): void
    {
        [$event, $user] = $this->ctx();
        $hall = $event->exhibitionHalls()->firstOrFail();
        $sold = $event->exhibitors()->create(['company' => 'Sold Co', 'status' => 'paid']);
        $res = $event->exhibitors()->create(['company' => 'Res Co', 'status' => 'reserved']);

        $event->booths()->create(['hall_id' => $hall->id, 'exhibitor_id' => $sold->id, 'number' => 'B01', 'price_cents' => 800000, 'x' => 5, 'y' => 5, 'w_m' => 3, 'h_m' => 3]);
        $event->booths()->create(['hall_id' => $hall->id, 'exhibitor_id' => $res->id, 'number' => 'B02', 'price_cents' => 500000, 'x' => 10, 'y' => 5, 'w_m' => 3, 'h_m' => 3]);
        $event->booths()->create(['hall_id' => $hall->id, 'number' => 'B03', 'price_cents' => 300000, 'x' => 15, 'y' => 5, 'w_m' => 3, 'h_m' => 3]);

        $sales = Livewire::actingAs($user)->test(ExhibitionFloorPlan::class, ['event' => $event])
            ->viewData('sales');

        $this->assertSame(3, $sales['total']);
        $this->assertSame(1, $sales['sold']);
        $this->assertSame(1, $sales['reserved']);
        $this->assertSame(1, $sales['available']);
        $this->assertSame(800000, $sales['soldValue']);
        $this->assertSame(500000, $sales['pipelineValue']);
        $this->assertSame(300000, $sales['openValue']);
    }

    public function test_booth_numbers_stay_unique_per_event(): void
    {
        [$event, $user] = $this->ctx();

        $c = Livewire::actingAs($user)->test(ExhibitionFloorPlan::class, ['event' => $event]);
        $c->call('addBooth')->call('addBooth');
        [$b1, $b2] = $event->booths()->orderBy('number')->get();

        // Renaming onto a taken number is refused.
        $c->call('setBoothNumber', $b2->id, 'B01');
        $this->assertSame('B02', $b2->fresh()->number);

        $c->call('setBoothNumber', $b2->id, 'ISLAND-1');
        $this->assertSame('ISLAND-1', $b2->fresh()->number);
    }
}
