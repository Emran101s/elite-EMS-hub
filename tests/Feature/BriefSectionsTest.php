<?php

namespace Tests\Feature;

use App\Livewire\Hub\BriefTab;
use App\Models\Event;
use App\Models\EventBrief;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A brief is a standard document — the same twelve sections on every event.
 *
 * Not every event has sponsors or an exhibition floor, though, and a heading
 * with nothing under it reads to a client as something nobody bothered to fill
 * in. So a section can come off THIS brief without leaving the platform, and
 * what was written in it is kept rather than destroyed.
 */
class BriefSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function tab(Event $event, ?User $user = null)
    {
        return Livewire::actingAs($user ?? User::factory()->create(['role' => 'manager']))
            ->test(BriefTab::class, ['event' => $event]);
    }

    private function event(): Event
    {
        return Event::factory()->create(['stage' => 'planning']);
    }

    public function test_every_event_starts_with_the_same_twelve_sections(): void
    {
        $a = $this->event();
        $b = $this->event();

        $this->assertSame(array_keys(EventBrief::SECTIONS), array_keys($this->tab($a)->viewData('sections')));
        $this->assertSame(array_keys(EventBrief::SECTIONS), array_keys($this->tab($b)->viewData('sections')));
        // 13 since the Scope of Work joined the spine as a 'sourced' section:
        // written on the Scope tab, rendered here, never copied.
        $this->assertCount(13, EventBrief::SECTIONS);
    }

    public function test_a_section_can_be_taken_off_this_brief_only(): void
    {
        $event = $this->event();
        $other = $this->event();

        $this->tab($event)->call('removeSection', 'sponsors');

        $this->assertArrayNotHasKey('sponsors', $this->tab($event->fresh())->viewData('sections'));
        $this->assertArrayHasKey('sponsors', $this->tab($other)->viewData('sections'),
            'another event keeps it — this is one brief, not the platform');
    }

    public function test_what_was_written_survives_and_comes_back(): void
    {
        $event = $this->event();

        $c = $this->tab($event)
            ->set('data.sponsors', [['area' => 'Gold tier', 'notes' => 'Three sold']])
            ->call('removeSection', 'sponsors');

        $brief = EventBrief::where('event_id', $event->id)->firstOrFail();

        $this->assertSame(['sponsors'], $brief->hidden_sections);
        $this->assertSame('Gold tier', $brief->data['sponsors'][0]['area'], 'kept, not destroyed');

        $c->call('restoreSection', 'sponsors');

        $this->assertArrayHasKey('sponsors', $this->tab($event->fresh())->viewData('sections'));
        $this->assertSame('Gold tier', $event->fresh()->brief->data['sponsors'][0]['area']);
    }

    public function test_the_removed_ones_are_offered_back(): void
    {
        $event = $this->event();

        $c = $this->tab($event)->call('removeSection', 'branding')->call('removeSection', 'risks');

        $this->assertSame(['branding', 'risks'], array_keys($c->viewData('hiddenSections')));
    }

    /** The export is the editor — a section off one is off the other. */
    public function test_a_removed_section_does_not_come_back_in_the_pdf(): void
    {
        $event = $this->event();
        $user = User::factory()->create(['role' => 'manager']);

        $this->actingAs($user)->get(route('events.brief.pdf', $event))->assertOk();

        $this->tab($event, $user)->call('removeSection', 'sponsors');

        $brief = EventBrief::where('event_id', $event->id)->firstOrFail();

        $this->assertArrayNotHasKey('sponsors', $brief->sections());
        $this->assertArrayHasKey('sponsors', $brief->hiddenSections());
    }

    /** Typing already saves; the button is what says so. */
    public function test_the_save_button_confirms_on_its_own_section(): void
    {
        $event = $this->event();

        $c = $this->tab($event)
            ->set('data.overview', 'A summit for the region.')
            ->call('saveSection', 'overview')
            ->assertSet('savedSection', 'overview');

        $this->assertSame('A summit for the region.', $event->fresh()->brief->data['overview']);

        // A further edit clears the flash — it belonged to what was saved.
        $c->set('data.overview', 'Changed again.')->assertSet('savedSection', null);
    }

    public function test_a_viewer_cannot_take_a_section_off(): void
    {
        $event = $this->event();

        $this->tab($event, User::factory()->create(['role' => 'viewer']))
            ->call('removeSection', 'sponsors')->assertForbidden();
    }
}
