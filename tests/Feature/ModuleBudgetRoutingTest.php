<?php

namespace Tests\Feature;

use App\Livewire\Hub\AccommodationTab;
use App\Livewire\Hub\TransportationTab;
use App\Models\Event;
use App\Models\EventRoomBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A module says where its costs land in the budget.
 *
 * The mapping was decided in code — everything guest-facing in one category,
 * every hall in another — so an event that budgets transport apart from
 * accommodation had no way to say so, and the categories the desk is free to
 * rename and add had entries nothing could ever be pointed at.
 */
class ModuleBudgetRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        $event = Event::factory()->create(['budget_status' => 'draft']);
        $event->ensureBudgetCategories();

        return $event->fresh();
    }

    private function block(Event $event): EventRoomBlock
    {
        return EventRoomBlock::create([
            'event_id' => $event->id, 'hotel' => 'Mövenpick', 'rooms_count' => 10,
            'rate_cents' => 100_00, 'check_in' => '2026-09-05', 'check_out' => '2026-09-07',
            'status' => 'confirmed',
        ]);
    }

    public function test_a_module_keeps_its_sensible_default(): void
    {
        $event = $this->event();

        $this->assertSame('Attendee & Guest Services', $event->moduleBudgetCategory('stay'));
        $this->assertSame('Venues', $event->moduleBudgetCategory('venue'));
    }

    public function test_pointing_a_module_at_a_category_refiles_what_is_already_there(): void
    {
        $event = $this->event();
        $this->block($event);

        $line = $event->fresh()->budgetItems()->where('source_type', 'room_block')->firstOrFail();
        $this->assertSame('Attendee & Guest Services', $line->category);

        Livewire::actingAs(User::factory()->create(['role' => 'manager']))
            ->test(AccommodationTab::class, ['event' => $event])
            ->call('routeCostsTo', 'Logistics');

        // Re-filed at once, not at some later sync: the Budget tab must not
        // disagree with the choice just made on this one.
        $this->assertSame('Logistics', $event->fresh()->budgetItems()
            ->where('source_type', 'room_block')->firstOrFail()->category);
    }

    public function test_two_modules_can_go_to_two_different_places(): void
    {
        $event = $this->event();
        $user = User::factory()->create(['role' => 'manager']);

        Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->call('routeCostsTo', 'Attendee & Guest Services');
        Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event->fresh()])
            ->call('routeCostsTo', 'Logistics');

        $event = $event->fresh();

        $this->assertSame('Attendee & Guest Services', $event->moduleBudgetCategory('stay'));
        $this->assertSame('Logistics', $event->moduleBudgetCategory('transport'));
    }

    /** A line filed under a name no category carries shows up in no section. */
    public function test_a_category_this_event_does_not_have_is_refused(): void
    {
        $event = $this->event();

        $this->assertFalse($event->routeModuleCosts('stay', 'A Category Nobody Made'));
        $this->assertSame('Attendee & Guest Services', $event->fresh()->moduleBudgetCategory('stay'));
    }

    public function test_a_renamed_away_category_falls_back_rather_than_stranding_the_lines(): void
    {
        $event = $this->event();
        $event->routeModuleCosts('stay', 'Logistics');
        $this->assertSame('Logistics', $event->fresh()->moduleBudgetCategory('stay'));

        $event->budgetCategories()->where('name', 'Logistics')->delete();

        $this->assertSame('Attendee & Guest Services', $event->fresh()->moduleBudgetCategory('stay'),
            'the choice pointed at something that no longer exists, so the default answers');
    }

    public function test_the_picker_offers_this_events_own_categories(): void
    {
        $event = $this->event();

        $routing = Livewire::actingAs(User::factory()->create(['role' => 'manager']))
            ->test(TransportationTab::class, ['event' => $event])
            ->instance()->budgetRouting();

        $this->assertSame('transport', $routing['module']);
        $this->assertSame('Transport', $routing['label']);
        $this->assertContains('Logistics', $routing['categories']);
        $this->assertSame($event->budgetCategories()->pluck('name')->all(), $routing['categories']);
    }

    public function test_a_viewer_cannot_move_a_modules_costs(): void
    {
        $event = $this->event();

        Livewire::actingAs(User::factory()->create(['role' => 'viewer']))
            ->test(AccommodationTab::class, ['event' => $event])
            ->call('routeCostsTo', 'Logistics')
            ->assertForbidden();
    }
}
