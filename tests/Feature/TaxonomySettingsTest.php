<?php

namespace Tests\Feature;

use App\Livewire\Hub\SettingsTab;
use App\Livewire\TaxonomySettings;
use App\Models\Event;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Support\Taxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The lists the platform draws from.
 *
 * These lists were PHP constants and are now rows, which means they sit on top
 * of live data: thousands of records already store the keys. Every test here
 * guards the one rule that makes that safe — a key, once written, never moves.
 * Break it and records do not error, they quietly stop resolving.
 */
class TaxonomySettingsTest extends TestCase
{
    use RefreshDatabase;

    private function boot(): User
    {
        Taxonomy::forget();

        return User::factory()->create();
    }

    public function test_the_lists_are_seeded_by_migration_and_seeding_again_changes_nothing(): void
    {
        $this->boot();

        $before = TaxonomyTerm::count();
        $this->assertGreaterThan(0, $before, 'the migration seeds the lists');

        // Every list must have arrived, or a dropdown somewhere is empty.
        foreach (array_keys(Taxonomy::LISTS) as $taxonomy) {
            $this->assertGreaterThan(0, TaxonomyTerm::in($taxonomy)->count(), $taxonomy.' is empty');
        }

        $this->assertSame(0, Taxonomy::seed(), 'seeding twice must add nothing');
        $this->assertSame($before, TaxonomyTerm::count());
    }

    public function test_a_key_is_derived_once_and_never_changes(): void
    {
        $user = $this->boot();

        $screen = Livewire::actingAs($user)->test(TaxonomySettings::class)
            ->call('pick', 'event_type')
            ->call('newTerm')
            ->set('label', 'Board Retreat')
            ->call('save');

        $term = TaxonomyTerm::in('event_type')->where('key', 'board_retreat')->firstOrFail();
        $this->assertSame('Board Retreat', $term->label);

        // Renaming is the whole point of the screen — but the key must not follow.
        $screen->call('edit', $term->id)
            ->set('label', 'Executive Retreat')
            ->call('save');

        $term->refresh();
        $this->assertSame('Executive Retreat', $term->label);
        $this->assertSame('board_retreat', $term->key, 'records store the key; renaming must not move it');
    }

    public function test_the_same_thing_cannot_be_added_to_a_list_twice(): void
    {
        $user = $this->boot();

        Livewire::actingAs($user)->test(TaxonomySettings::class)
            ->call('pick', 'event_type')
            ->call('newTerm')
            ->set('label', 'Conference')          // already seeded from Event::TYPES
            ->call('save')
            ->assertHasErrors('key');

        $this->assertSame(1, TaxonomyTerm::in('event_type')->where('key', 'conference')->count());
    }

    public function test_a_term_the_platform_names_in_code_is_hidden_rather_than_deleted(): void
    {
        $user = $this->boot();
        $system = TaxonomyTerm::in('event_type')->where('is_system', true)->firstOrFail();

        Livewire::actingAs($user)->test(TaxonomySettings::class)->call('delete', $system->id);

        $this->assertModelExists($system->fresh());
        $this->assertFalse($system->fresh()->is_active, 'a system term goes quiet, it does not disappear');
    }

    public function test_a_term_you_added_yourself_can_be_deleted(): void
    {
        $user = $this->boot();

        // Deal sources are written onto records as the words themselves, so a
        // new one keeps its wording as the key rather than being snake-cased.
        $screen = Livewire::actingAs($user)->test(TaxonomySettings::class)
            ->call('pick', 'deal_source')
            ->call('newTerm')
            ->set('label', 'Golf Day')
            ->call('save');

        $mine = TaxonomyTerm::in('deal_source')->where('key', 'Golf Day')->firstOrFail();
        $this->assertFalse($mine->is_system);

        $screen->call('delete', $mine->id);

        $this->assertNull(TaxonomyTerm::find($mine->id));
    }

    /**
     * The guard that matters most: a term records are sitting on cannot be
     * deleted out from under them, however it was created.
     */
    public function test_a_term_records_are_using_is_hidden_rather_than_deleted(): void
    {
        $user = $this->boot();

        Livewire::actingAs($user)->test(TaxonomySettings::class)
            ->call('pick', 'event_type')
            ->call('newTerm')
            ->set('label', 'Board Retreat')
            ->call('save');

        $mine = TaxonomyTerm::in('event_type')->where('key', 'board_retreat')->firstOrFail();
        $this->assertFalse($mine->is_system, 'nothing in code names it, so only usage can protect it');

        Event::factory()->create(['type' => 'board_retreat', 'stage' => 'planning']);
        Taxonomy::forget();

        $this->assertSame(1, Taxonomy::usage('event_type')['board_retreat'] ?? 0);

        Livewire::actingAs($user)->test(TaxonomySettings::class)->call('delete', $mine->id);

        $this->assertModelExists($mine->fresh());
        $this->assertFalse($mine->fresh()->is_active);
    }

    public function test_hiding_a_type_stops_offering_it_without_breaking_records_that_use_it(): void
    {
        $user = $this->boot();
        $term = TaxonomyTerm::in('event_type')->where('key', 'conference')->firstOrFail();

        Livewire::actingAs($user)->test(TaxonomySettings::class)->call('toggleActive', $term->id);
        Taxonomy::forget();

        $this->assertArrayNotHasKey('conference', Taxonomy::options('event_type'), 'no longer offered');
        $this->assertSame($term->label, Taxonomy::label('event_type', 'conference'), 'still reads on old records');
    }

    public function test_a_key_written_before_the_list_existed_still_resolves(): void
    {
        $this->boot();

        // Nothing in the table, nothing in the constants — the worst case.
        $this->assertSame('Ghost Gala', Taxonomy::label('event_type', 'ghost_gala'));
        $this->assertSame('—', Taxonomy::label('event_type', null));
    }

    public function test_the_order_people_drag_them_into_is_the_order_they_are_offered_in(): void
    {
        $user = $this->boot();

        $ids = TaxonomyTerm::in('event_type')->pluck('id')->all();
        $reversed = array_reverse($ids);

        Livewire::actingAs($user)->test(TaxonomySettings::class)->call('reorder', $reversed);
        Taxonomy::forget();

        $this->assertSame($reversed, TaxonomyTerm::in('event_type')->pluck('id')->all());
    }

    /**
     * The reason the manager exists. A list nobody's screen reads from is a
     * table, not a setting — so this walks a term from Settings to the form
     * that offers it and the record that keeps it.
     */
    public function test_a_type_added_in_settings_reaches_the_screen_that_offers_it(): void
    {
        $user = $this->boot();

        Livewire::actingAs($user)->test(TaxonomySettings::class)
            ->call('pick', 'event_type')
            ->call('newTerm')
            ->set('label', 'Board Retreat')
            ->call('save');

        Taxonomy::forget();
        $event = Event::factory()->create(['type' => 'conference', 'stage' => 'planning']);

        Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event])
            ->assertSee('Board Retreat')            // offered on the event's own settings
            ->set('type', 'board_retreat')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('board_retreat', $event->fresh()->type);
    }

    public function test_an_event_keeps_a_type_that_was_later_retired(): void
    {
        $user = $this->boot();
        $event = Event::factory()->create(['type' => 'gala_dinner', 'stage' => 'planning']);

        $term = TaxonomyTerm::in('event_type')->where('key', 'gala_dinner')->firstOrFail();
        Livewire::actingAs($user)->test(TaxonomySettings::class)->call('toggleActive', $term->id);
        Taxonomy::forget();

        // Saving anything else about the event must not fail because its own
        // type is no longer on the list, and must not silently change it.
        Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event])
            ->set('name', 'Renamed gala')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('gala_dinner', $event->fresh()->type);
    }

    public function test_the_screens_render_and_settings_links_to_them(): void
    {
        $user = $this->boot();

        $this->actingAs($user)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Types &amp; Lists', false)
            ->assertSee(route('taxonomies.index'));

        $this->actingAs($user)->get(route('taxonomies.index'))
            ->assertOk()
            ->assertSee('Event types')
            ->assertSee('Risk categories')
            ->assertSee('Deal sources');
    }
}
