<?php

namespace Tests\Feature;

use App\Livewire\Hub\BudgetTab;
use App\Livewire\Hub\SettingsTab;
use App\Livewire\TaxonomySettings;
use App\Models\Event;
use App\Models\Supplier;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Support\Taxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

    /** Make a term and hand it back, since most of these start with one. */
    private function add(User $user, string $taxonomy, string $label, ?int $parentId = null): TaxonomyTerm
    {
        Livewire::actingAs($user)->test(TaxonomySettings::class)
            ->call('pick', $taxonomy)
            ->call('newTerm', $parentId)
            ->set('label', $label)
            ->call('save');

        Taxonomy::forget();

        return TaxonomyTerm::in($taxonomy)->where('key', Taxonomy::deriveKey($taxonomy, $label))->firstOrFail();
    }

    public function test_a_term_can_sit_under_another_and_is_offered_beneath_it(): void
    {
        $user = $this->boot();

        $parent = $this->add($user, 'supplier_category', 'Production Services');
        $child = $this->add($user, 'supplier_category', 'Rigging', $parent->id);

        $this->assertSame($parent->id, $child->parent_id);

        // A sub-category is a real option, offered directly after its parent.
        $rows = collect(Taxonomy::optionRows('supplier_category'));
        $at = $rows->search(fn ($r) => $r['key'] === $parent->key);

        $this->assertSame(0, $rows[$at]['depth']);
        $this->assertSame($child->key, $rows[$at + 1]['key'], 'a child follows its parent');
        $this->assertSame(1, $rows[$at + 1]['depth']);
        $this->assertArrayHasKey($child->key, Taxonomy::options('supplier_category'));
    }

    /**
     * Nesting is one level deep. Everything that would make it deeper — or
     * circular — flattens rather than erroring, so the screen cannot be used
     * to build a tree nobody can read.
     */
    public function test_nesting_never_goes_more_than_one_level_deep(): void
    {
        $user = $this->boot();

        $parent = $this->add($user, 'supplier_category', 'Production Services');
        $child = $this->add($user, 'supplier_category', 'Rigging', $parent->id);

        // A grandchild: parented to something that is already a child.
        $grandchild = $this->add($user, 'supplier_category', 'Truss Hire', $child->id);
        $this->assertNull($grandchild->parent_id, 'a child cannot itself be a parent');

        // A term parented to itself.
        Livewire::actingAs($user)->test(TaxonomySettings::class)
            ->call('pick', 'supplier_category')
            ->call('edit', $parent->id)
            ->set('parent_id', $parent->id)
            ->call('save');

        $this->assertNull($parent->fresh()->parent_id, 'a term cannot be its own parent');

        // A term that already has children being made into one.
        $other = $this->add($user, 'supplier_category', 'Logistics Services');

        Livewire::actingAs($user)->test(TaxonomySettings::class)
            ->call('pick', 'supplier_category')
            ->call('edit', $parent->id)
            ->set('parent_id', $other->id)
            ->call('save');

        $this->assertNull($parent->fresh()->parent_id, 'a term with children stays at the top');
    }

    public function test_hiding_a_parent_hides_the_branch_but_labels_still_resolve(): void
    {
        $user = $this->boot();

        $parent = $this->add($user, 'supplier_category', 'Production Services');
        $child = $this->add($user, 'supplier_category', 'Rigging', $parent->id);

        Livewire::actingAs($user)->test(TaxonomySettings::class)->call('toggleActive', $parent->id);
        Taxonomy::forget();

        $offered = Taxonomy::options('supplier_category');
        $this->assertArrayNotHasKey($parent->key, $offered);
        $this->assertArrayNotHasKey($child->key, $offered, 'a sub-category of something you no longer offer is not offered');

        // But every record on either of them still reads correctly.
        $this->assertSame('Rigging', Taxonomy::label('supplier_category', $child->key));
        $this->assertSame('Production Services', Taxonomy::label('supplier_category', $parent->key));
    }

    public function test_deleting_a_parent_promotes_its_children_rather_than_taking_them_with_it(): void
    {
        $user = $this->boot();

        $parent = $this->add($user, 'supplier_category', 'Production Services');
        $child = $this->add($user, 'supplier_category', 'Rigging', $parent->id);

        // Neither is a system term and nothing uses them, so the parent goes.
        Livewire::actingAs($user)->test(TaxonomySettings::class)
            ->call('pick', 'supplier_category')
            ->call('delete', $parent->id);

        $this->assertNull(TaxonomyTerm::find($parent->id));
        $this->assertModelExists($child->fresh());
        $this->assertNull($child->fresh()->parent_id, 'the child is still a real value on real records');
    }

    /**
     * Every list must actually be wired to something. A list nobody's screen
     * reads from is a table, not a setting — and the `on` pair is what makes
     * the usage count (and therefore the delete guard) real.
     */
    public function test_every_list_points_at_a_column_that_exists(): void
    {
        $this->boot();

        foreach (Taxonomy::LISTS as $key => $meta) {
            [$table, $column] = $meta['on'];

            $this->assertTrue(
                Schema::hasColumn($table, $column),
                "{$key} claims to be stored on {$table}.{$column}, which does not exist"
            );

            $this->assertNotEmpty(Taxonomy::defaults($key), "{$key} has no seed");
            $this->assertContains($meta['stores'], ['key', 'label'], "{$key} must say how records store it");
        }
    }

    /**
     * The key a new term gets has to match the shape the list was written in,
     * or it will never match a record. This checks both shapes at once.
     */
    public function test_a_new_term_takes_the_shape_of_the_list_it_joins(): void
    {
        $user = $this->boot();

        // Income sources are machine keys: 'Sponsor Rebate' => sponsor_rebate.
        $keyed = $this->add($user, 'income_source', 'Sponsor Rebate');
        $this->assertSame('sponsor_rebate', $keyed->key);

        // Bedroom grades are the words themselves: records hold "Presidential".
        $worded = $this->add($user, 'room_category', 'Presidential');
        $this->assertSame('Presidential', $worded->key);
    }

    public function test_a_new_income_source_is_offered_on_the_budget_screen(): void
    {
        $user = $this->boot();
        // fresh() so the column defaults the factory does not set (currency,
        // stage) are on the instance the screen is handed.
        $event = Event::factory()->create(['stage' => 'planning'])->fresh();

        $this->add($user, 'income_source', 'Sponsor Rebate');

        Livewire::actingAs($user)->test(BudgetTab::class, ['event' => $event])
            ->assertSee('Sponsor Rebate')
            ->set('incomeSource', 'sponsor_rebate')
            ->set('incomeDesc', 'Rebate from the headline sponsor')
            ->set('incomeAmount', '1200')
            ->call('saveIncome')
            ->assertHasNoErrors();

        $this->assertSame('sponsor_rebate', $event->incomeItems()->latest('id')->first()->source);
    }

    /**
     * A value in the data with no term of its own is invisible in Settings,
     * uncounted, and unprotected by the guard that stops you deleting
     * something in use. The constants had drifted — suppliers were filed under
     * thirteen categories Supplier::CATEGORIES never listed — so adopt()
     * takes on whatever the records actually hold.
     */
    public function test_a_value_already_in_the_data_gets_a_term_of_its_own(): void
    {
        $this->boot();

        // A category no constant ever named, written straight to the column.
        Supplier::factory()->create(['category' => 'drone_operations']);
        $this->assertSame(1, Taxonomy::adopt(), 'exactly the one value with no term');

        $adopted = TaxonomyTerm::in('supplier_category')->where('key', 'drone_operations')->first();

        $this->assertNotNull($adopted, 'a value the data uses must be offered');
        $this->assertSame('Drone Operations', $adopted->label);
        $this->assertFalse($adopted->is_system, 'the platform never named it — the data did');
        $this->assertSame(1, Taxonomy::usage('supplier_category')['drone_operations']);
    }

    /**
     * Sessions are saved through str()->snake(), so this list is stored as
     * keys. It was seeded as words once, and every existing session stopped
     * resolving — nothing errored, the labels just went blank.
     */
    public function test_session_types_are_stored_in_the_shape_the_agenda_form_saves(): void
    {
        $this->boot();

        $this->assertSame('key', Taxonomy::meta('session_type', 'stores'));
        $this->assertArrayHasKey('gala_dinner', Taxonomy::options('session_type'));
        $this->assertSame('Gala Dinner', Taxonomy::label('session_type', 'gala_dinner'));

        // The datalist offers the words; the form snake-cases them back.
        $this->assertContains('Gala Dinner', Taxonomy::labels('session_type'));
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
