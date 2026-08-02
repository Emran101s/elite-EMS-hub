<?php

namespace Tests\Feature;

use App\Livewire\EventsIndex;
use App\Livewire\Hub\SettingsTab;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Deleting an event, from either end.
 *
 * Archive is the reversible one and stays a click. This is not: twenty tables
 * cascade off an event, so the screen says what goes, what survives it, and
 * whether money has moved — and then asks for the name in writing.
 */
class DeleteEventTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function event(string $name = 'A Test Summit'): Event
    {
        return Event::factory()->create([
            'name' => $name, 'stage' => 'planning',
        ]);
    }

    /* ── from inside the event ── */

    public function test_the_name_has_to_be_typed(): void
    {
        $event = $this->event();

        $c = Livewire::actingAs($this->manager())->test(SettingsTab::class, ['event' => $event])
            ->call('askToDelete')
            ->set('confirmName', 'A Test Summ')       // nearly
            ->call('destroyEvent')
            ->assertHasErrors('confirmName');

        $this->assertModelExists($event);

        $c->set('confirmName', 'a test summit')       // case is not the point
            ->call('destroyEvent')
            ->assertRedirect(route('events.index'));

        $this->assertModelMissing($event);
    }

    public function test_the_panel_counts_what_would_go_and_what_would_stay(): void
    {
        $event = $this->event();
        $event->tasks()->create(['title' => 'Book the hall', 'status' => 'todo', 'priority' => 'normal']);
        $event->tasks()->create(['title' => 'Sign the venue', 'status' => 'todo', 'priority' => 'normal']);
        $event->roomBlocks()->create(['hotel' => 'Somewhere', 'rooms_count' => 10, 'rate_cents' => 100_00,
            'check_in' => now()->addMonth(), 'check_out' => now()->addMonth()->addDay(), 'status' => 'held']);

        Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'sent', 'event_id' => $event->id]);

        $c = Livewire::actingAs($this->manager())->test(SettingsTab::class, ['event' => $event->fresh()])
            ->call('askToDelete');

        $inventory = $c->viewData('inventory');

        $this->assertSame(2, $inventory['destroys']['task']);
        $this->assertSame(1, $inventory['destroys']['room block']);
        $this->assertSame(1, $inventory['keeps']['invoice'], 'the money outlives the project');
    }

    /** Money that has moved is worth saying out loud before anybody deletes. */
    public function test_a_paid_invoice_is_named_in_the_warning(): void
    {
        $event = $this->event();

        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'sent', 'event_id' => $event->id]);
        $invoice->lines()->create(['description' => 'Deposit', 'qty' => 1, 'unit_cents' => 5_000_00]);
        $invoice->update(['paid_cents' => 5_000_00, 'paid_at' => now()]);

        $money = Livewire::actingAs($this->manager())->test(SettingsTab::class, ['event' => $event->fresh()])
            ->call('askToDelete')->viewData('inventory')['money'];

        $this->assertSame(['1 paid invoice'], $money);
    }

    /** An invoice survives its event, unattached, rather than vanishing with it. */
    public function test_deleting_an_event_keeps_its_invoices(): void
    {
        $event = $this->event();
        $invoice = Invoice::create(['number' => Invoice::nextNumber(), 'status' => 'sent', 'event_id' => $event->id]);

        Livewire::actingAs($this->manager())->test(SettingsTab::class, ['event' => $event])
            ->call('askToDelete')->set('confirmName', $event->name)->call('destroyEvent');

        $this->assertModelExists($invoice);
        $this->assertNull($invoice->fresh()->event_id);
    }

    public function test_everything_that_hangs_off_the_event_goes_with_it(): void
    {
        $event = $this->event();
        $task = $event->tasks()->create(['title' => 'Book the hall', 'status' => 'todo', 'priority' => 'normal']);
        $block = $event->roomBlocks()->create(['hotel' => 'Somewhere', 'rooms_count' => 4, 'rate_cents' => 100_00,
            'check_in' => now()->addMonth(), 'check_out' => now()->addMonth()->addDay(), 'status' => 'held']);

        Livewire::actingAs($this->manager())->test(SettingsTab::class, ['event' => $event])
            ->call('askToDelete')->set('confirmName', $event->name)->call('destroyEvent');

        $this->assertModelMissing($task);
        $this->assertModelMissing($block);
    }

    public function test_a_coordinator_cannot_delete_an_event(): void
    {
        $event = $this->event();
        $coordinator = User::factory()->create(['role' => 'coordinator']);

        Livewire::actingAs($coordinator)->test(SettingsTab::class, ['event' => $event])
            ->call('askToDelete')->assertForbidden();

        $this->assertModelExists($event);
    }

    /* ── from the list ── */

    public function test_a_row_can_be_deleted_from_the_list(): void
    {
        $event = $this->event('One To Remove');

        Livewire::actingAs($this->manager())->test(EventsIndex::class)
            ->call('deleteEvent', $event->id);

        $this->assertModelMissing($event);
    }

    public function test_several_can_be_ticked_and_deleted_together(): void
    {
        $a = $this->event('First');
        $b = $this->event('Second');
        $keep = $this->event('Not This One');

        Livewire::actingAs($this->manager())->test(EventsIndex::class)
            ->call('toggleSelect', $a->id)
            ->call('toggleSelect', $b->id)
            ->call('toggleSelect', $keep->id)
            ->call('toggleSelect', $keep->id)      // ticked, then unticked
            ->call('deleteSelected')
            ->assertSet('selectedIds', []);

        $this->assertModelMissing($a);
        $this->assertModelMissing($b);
        $this->assertModelExists($keep);
    }

    public function test_the_list_offers_delete_only_to_those_who_may(): void
    {
        $this->event('Deletable');

        $manager = Livewire::actingAs($this->manager())->test(EventsIndex::class)->set('view', 'list');
        $this->assertStringContainsString('Delete permanently', $manager->html());

        $viewer = Livewire::actingAs(User::factory()->create(['role' => 'viewer']))
            ->test(EventsIndex::class)->set('view', 'list');
        $this->assertStringNotContainsString('Delete permanently', $viewer->html());
    }
}
