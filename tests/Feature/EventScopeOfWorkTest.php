<?php

namespace Tests\Feature;

use App\Livewire\Hub\ScopeTab;
use App\Models\Event;
use App\Models\EventBrief;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Scope of Work: what the client asked us to deliver, written on its own
 * tab and rendered by the Event Brief.
 *
 * The rule worth guarding is that the brief keeps no copy. A scope typed into
 * two places disagrees with itself the first time one of them is revised, and
 * this platform has been corrected for that more than once.
 */
class EventScopeOfWorkTest extends TestCase
{
    use RefreshDatabase;

    private function make(): array
    {
        $user = User::create(['name' => 'PM', 'email' => 'pm@scope.test', 'password' => bcrypt('x'), 'role' => 'manager']);
        $event = Event::factory()->create(['stage' => 'planning']);

        return [$user, $event];
    }

    public function test_a_scope_line_is_written_and_revised_on_its_own_tab(): void
    {
        [$user, $event] = $this->make();

        Livewire::actingAs($user)->test(ScopeTab::class, ['event' => $event])
            ->call('newItem')
            ->set('title', 'Full event management')
            ->set('body', 'Planning, suppliers and on-site supervision.')
            ->set('area', 'management')
            ->call('save');

        $item = $event->scopeItems()->firstOrFail();
        $this->assertSame('Full event management', $item->title);
        $this->assertFalse($item->is_exclusion);

        Livewire::actingAs($user)->test(ScopeTab::class, ['event' => $event])
            ->call('edit', $item->id)
            ->set('title', 'Full event management and supervision')
            ->call('save');

        $this->assertSame('Full event management and supervision', $item->fresh()->title);
        $this->assertSame(1, $event->scopeItems()->count(), 'revising edits the line, it does not add another');
    }

    public function test_exclusions_are_kept_apart_from_what_we_are_delivering(): void
    {
        [$user, $event] = $this->make();

        $event->scopeItems()->create(['area' => 'management', 'title' => 'Event management']);
        $event->scopeItems()->create(['area' => 'general', 'title' => 'Interpretation', 'is_exclusion' => true]);

        $screen = Livewire::actingAs($user)->test(ScopeTab::class, ['event' => $event]);

        $this->assertSame(1, $screen->viewData('groups')->sum(fn ($g) => $g['rows']->count()));
        $this->assertCount(1, $screen->viewData('exclusions'));
        $screen->assertSee('Not included in this scope');
    }

    /* ══ the rule ══ */

    public function test_the_brief_reads_the_scope_and_keeps_no_copy_of_it(): void
    {
        [$user, $event] = $this->make();

        $event->scopeItems()->create([
            'area' => 'management',
            'title' => 'Full event management',
            'body' => 'Planning, suppliers and on-site supervision.',
        ]);

        $brief = EventBrief::forEvent($event);

        // The section exists on the spine, and is sourced rather than stored.
        $this->assertArrayHasKey('scope', EventBrief::SECTIONS);
        $this->assertSame('sourced', EventBrief::SECTIONS['scope'][2]);
        $this->assertArrayNotHasKey('scope', $brief->data,
            'the brief must hold no copy of the scope — it renders the Scope tab');

        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'brief']))
            ->assertOk()
            ->assertSee('Full event management');
    }

    public function test_revising_the_scope_changes_what_the_brief_shows(): void
    {
        [$user, $event] = $this->make();
        $item = $event->scopeItems()->create(['area' => 'management', 'title' => 'Original wording']);

        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'brief']))->assertSee('Original wording');

        $item->update(['title' => 'Revised wording']);

        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'brief']))
            ->assertSee('Revised wording')
            ->assertDontSee('Original wording');
    }

    public function test_a_viewer_cannot_write_the_scope(): void
    {
        [, $event] = $this->make();
        $viewer = User::create(['name' => 'Vic', 'email' => 'v@scope.test', 'password' => bcrypt('x'), 'role' => 'viewer']);

        Livewire::actingAs($viewer)->test(ScopeTab::class, ['event' => $event])
            ->call('newItem')->assertForbidden();
    }
}
