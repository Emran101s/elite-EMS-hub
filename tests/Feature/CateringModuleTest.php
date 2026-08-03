<?php

namespace Tests\Feature;

use App\Livewire\Hub\CateringTab;
use App\Models\Event;
use App\Models\EventCateringItem;
use App\Models\User;
use App\Services\BudgetSync;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Food & Beverage: a coffee break, a lunch and a gala dinner at an outside
 * restaurant are three different commitments, each with its own date,
 * headcount and rate — not one "catering" figure nobody can break down.
 */
class CateringModuleTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);
        $event = Event::has('rooms')->firstOrFail();

        return [$event, $event->rooms()->first(), User::where('email', 'emran.itan@elitebhub.com')->firstOrFail()];
    }

    public function test_a_per_person_occasion_multiplies_by_covers(): void
    {
        [$event] = $this->ctx();

        $c = $event->cateringItems()->create([
            'title' => 'Morning coffee break', 'type' => 'coffee_break',
            'venue_mode' => 'in_house', 'headcount' => 60, 'cost_cents' => 600, 'per_person' => true,
        ]);

        $this->assertSame(36000, $c->totalCents());
    }

    public function test_a_flat_occasion_ignores_headcount(): void
    {
        [$event] = $this->ctx();

        $c = $event->cateringItems()->create([
            'title' => 'Gala dinner', 'type' => 'dinner', 'venue_mode' => 'outside',
            'location' => 'Fakhreldin Restaurant', 'headcount' => 55, 'cost_cents' => 8500000, 'per_person' => false,
        ]);

        $this->assertSame(8500000, $c->totalCents());
        $this->assertSame('Fakhreldin Restaurant', $c->venueLabel());
    }

    public function test_an_in_house_occasion_is_labelled_by_its_room(): void
    {
        [$event, $room] = $this->ctx();

        $c = $event->cateringItems()->create([
            'title' => 'Working lunch', 'type' => 'lunch', 'venue_mode' => 'in_house', 'room_id' => $room->id,
        ]);

        $this->assertSame($room->name, $c->venueLabel());
    }

    public function test_the_module_is_enabled_whether_or_not_an_event_has_a_captured_list(): void
    {
        [$event] = $this->ctx();
        $this->assertTrue($event->moduleEnabled('catering'));

        // The real gap this closes: an event whose module list WAS captured
        // before Food & Beverage existed must still show it, via the backfill
        // migration rather than the null-means-everything fallback.
        $event->update(['enabled_modules' => array_values(array_diff(
            array_keys(\App\Models\Event::HUB_MODULES), ['catering'],
        ))]);
        $this->assertFalse($event->fresh()->moduleEnabled('catering'));
    }

    public function test_the_tab_creates_an_in_house_occasion_and_clears_the_outside_fields(): void
    {
        [$event, $room, $user] = $this->ctx();

        Livewire::actingAs($user)->test(CateringTab::class, ['event' => $event])
            ->call('newItem')
            ->set('title', 'Welcome coffee break')
            ->set('type', 'coffee_break')
            ->set('occasion_date', '2026-09-07')
            ->set('venue_mode', 'in_house')
            ->set('room_id', (string) $room->id)
            ->set('location', 'should be dropped')
            ->set('headcount', '60')
            ->set('cost', '6')
            ->set('per_person', true)
            ->call('save');

        $c = $event->cateringItems()->firstOrFail();
        $this->assertSame('Welcome coffee break', $c->title);
        $this->assertSame($room->id, $c->room_id);
        $this->assertNull($c->location);
        $this->assertSame(36000, $c->totalCents());
    }

    public function test_the_tab_creates_an_outside_occasion_and_clears_the_room(): void
    {
        [$event, $room, $user] = $this->ctx();

        Livewire::actingAs($user)->test(CateringTab::class, ['event' => $event])
            ->call('newItem')
            ->set('title', 'Delegates gala dinner')
            ->set('venue_mode', 'outside')
            ->set('room_id', (string) $room->id)
            ->set('location', 'Fakhreldin Restaurant, Amman')
            ->set('cost', '85000')
            ->set('per_person', false)
            ->call('save');

        $c = $event->cateringItems()->firstOrFail();
        $this->assertNull($c->room_id);
        $this->assertSame('Fakhreldin Restaurant, Amman', $c->location);
        $this->assertSame(8500000, $c->totalCents());
    }

    public function test_setstatus_and_delete(): void
    {
        [$event, , $user] = $this->ctx();
        $c = $event->cateringItems()->create(['title' => 'Lunch', 'venue_mode' => 'in_house']);

        $component = Livewire::actingAs($user)->test(CateringTab::class, ['event' => $event])
            ->call('setStatus', $c->id, 'confirmed');
        $this->assertSame('confirmed', $c->fresh()->status);

        $component->call('delete', $c->id);
        $this->assertModelMissing($c);
    }

    public function test_costs_sync_to_the_food_and_beverage_category(): void
    {
        [$event] = $this->ctx();

        $confirmed = $event->cateringItems()->create([
            'title' => 'Coffee break', 'venue_mode' => 'in_house',
            'headcount' => 60, 'cost_cents' => 600, 'per_person' => true, 'status' => 'confirmed',
        ]);
        $cancelled = $event->cateringItems()->create([
            'title' => 'Cancelled reception', 'venue_mode' => 'in_house', 'cost_cents' => 500000, 'status' => 'cancelled',
        ]);

        (new BudgetSync)->sync($event->fresh());

        $line = $event->budgetItems()->where('source_type', 'catering')->where('source_id', $confirmed->id)->first();
        $this->assertNotNull($line);
        $this->assertSame('Food & Beverage', $line->category);
        $this->assertSame(36000, $line->estimated_cents);

        $this->assertNull($event->budgetItems()->where('source_type', 'catering')->where('source_id', $cancelled->id)->first());
    }

    public function test_routing_costs_elsewhere_refiles_existing_lines(): void
    {
        [$event, , $user] = $this->ctx();
        $event->cateringItems()->create(['title' => 'Lunch', 'venue_mode' => 'in_house', 'cost_cents' => 100000, 'status' => 'confirmed']);
        $event->ensureBudgetCategories();
        $other = $event->budgetCategories()->get()->first(fn ($c) => $c->name !== 'Food & Beverage')->name;

        Livewire::actingAs($user)->test(CateringTab::class, ['event' => $event])
            ->call('routeCostsTo', $other);

        $line = $event->fresh()->budgetItems()->where('source_type', 'catering')->first();
        $this->assertSame($other, $line->category);
    }

    public function test_an_uncosted_occasion_is_flagged_as_pending(): void
    {
        [$event] = $this->ctx();
        $event->cateringItems()->create(['title' => 'Closing reception', 'venue_mode' => 'in_house', 'cost_cents' => 0]);

        $pending = (new BudgetSync)->pending($event->fresh());

        $this->assertTrue(collect($pending)->contains(fn ($p) => $p['module'] === 'Food & Beverage'));
    }

    public function test_a_cancelled_occasion_is_not_pending(): void
    {
        [$event] = $this->ctx();
        $event->cateringItems()->create(['title' => 'Dropped', 'venue_mode' => 'in_house', 'cost_cents' => 0, 'status' => 'cancelled']);

        $pending = (new BudgetSync)->pending($event->fresh());

        $this->assertFalse(collect($pending)->contains(fn ($p) => $p['module'] === 'Food & Beverage'));
    }

    public function test_writing_a_catering_item_re_syncs_the_budget_on_its_own(): void
    {
        [$event] = $this->ctx();

        $event->cateringItems()->create([
            'title' => 'Lunch', 'venue_mode' => 'in_house', 'cost_cents' => 50000, 'status' => 'confirmed',
        ]);

        // No manual sync() call — the observer is what should have made this line exist.
        $line = $event->budgetItems()->where('source_type', 'catering')->first();
        $this->assertNotNull($line, 'writing a catering item should sync the budget on its own');
        $this->assertSame(50000, $line->estimated_cents);
    }

    public function test_the_tab_page_renders_grouped_by_date(): void
    {
        [$event, , $user] = $this->ctx();
        $event->cateringItems()->create(['title' => 'Coffee', 'venue_mode' => 'in_house', 'occasion_date' => '2026-09-07']);

        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'catering']))
            ->assertOk()->assertSee('Food & Beverage')->assertSee('Coffee');
    }
}
