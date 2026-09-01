<?php

namespace Tests\Feature;

use App\Livewire\VenuesManager;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VenuesManagerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::create(['name' => 'Ops', 'email' => 'ops@ebh.test',
            'password' => bcrypt('x'), 'role' => 'admin']);
    }

    public function test_a_venue_can_be_added_with_the_full_location_record(): void
    {
        Livewire::actingAs($this->actor())->test(VenuesManager::class)
            ->call('newItem')
            ->set('name', 'Fairmont Amman')
            ->set('type', 'Hotel')
            ->set('address', 'King Hussein Business Park')
            ->set('city', 'Amman')
            ->set('country', 'Jordan')
            ->set('capacity', 500)
            ->set('contact_name', 'Events Desk')
            ->set('contact_phone', '+962 6 000 0000')
            ->set('contact_email', 'events@fairmont.com')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $v = Venue::where('name', 'Fairmont Amman')->firstOrFail();
        $this->assertSame('Hotel', $v->type);
        $this->assertSame('Amman', $v->city);
        $this->assertSame(500, $v->capacity);
        $this->assertSame('events@fairmont.com', $v->contact_email);
        $this->assertSame('Amman, Jordan', $v->locationLine());
    }

    public function test_a_venue_can_be_edited(): void
    {
        $v = Venue::create(['name' => 'Old Hall', 'city' => 'Amman', 'capacity' => 100]);

        Livewire::actingAs($this->actor())->test(VenuesManager::class)
            ->call('edit', $v->id)
            ->assertSet('name', 'Old Hall')
            ->set('name', 'Grand Hall')
            ->set('capacity', 800)
            ->call('save');

        $this->assertSame('Grand Hall', $v->fresh()->name);
        $this->assertSame(800, $v->fresh()->capacity);
    }

    public function test_deleting_a_venue_unlinks_events_rather_than_breaking_them(): void
    {
        $v = Venue::create(['name' => 'Doomed Venue', 'city' => 'Amman']);
        $event = Event::create(['name' => 'Test Event', 'type' => 'conference', 'stage' => 'planning',
            'city' => 'Amman', 'country' => 'Jordan', 'venue_id' => $v->id]);

        Livewire::actingAs($this->actor())->test(VenuesManager::class)->call('delete', $v->id);

        $this->assertNull(Venue::find($v->id));
        $this->assertNotNull($event->fresh(), 'the event survives');
        $this->assertNull($event->fresh()->venue_id, 'it just loses the venue link');
    }

    public function test_name_is_required(): void
    {
        Livewire::actingAs($this->actor())->test(VenuesManager::class)
            ->call('newItem')->set('name', '')->call('save')
            ->assertHasErrors('name');
    }

    public function test_search_narrows_the_list(): void
    {
        Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman', 'type' => 'Hotel']);
        Venue::create(['name' => 'Dead Sea Resort', 'city' => 'Sweimeh', 'type' => 'Hotel']);

        Livewire::actingAs($this->actor())->test(VenuesManager::class)
            ->set('search', 'Fairmont')
            ->assertSee('Fairmont Amman')
            ->assertDontSee('Dead Sea Resort');
    }

    /**
     * A venue with no type used to belong to no tab at all.
     *
     * The "not a hotel" filter was NOT (type LIKE '%Hotel%'), and in SQL that
     * is NULL — not true — when type is NULL. So an untyped venue was dropped
     * from the Halls & sites list AND from its count, and could only be found
     * under "All venues". Half this directory was untyped.
     */
    public function test_a_venue_with_no_type_still_belongs_to_halls_and_sites(): void
    {
        Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman', 'type' => 'Hotel']);
        Venue::create(['name' => 'City Conference Centre', 'city' => 'Amman', 'type' => 'Conference Centre']);
        Venue::create(['name' => 'Untyped Hall', 'city' => 'Amman']);

        $screen = Livewire::actingAs($this->actor())->test(VenuesManager::class)
            ->set('filter', 'other');

        $screen->assertSee('Untyped Hall')
            ->assertSee('City Conference Centre')
            ->assertDontSee('Fairmont Amman');

        $this->assertSame(2, $screen->viewData('counts')['other'], 'the untyped venue is counted, not dropped');
    }

    public function test_a_viewer_cannot_add_or_delete(): void
    {
        $viewer = User::create(['name' => 'Vic', 'email' => 'v@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);

        Livewire::actingAs($viewer)->test(VenuesManager::class)
            ->call('newItem')->set('name', 'Sneaky')->call('save')
            ->assertForbidden();

        $this->assertSame(0, Venue::count());
    }

    public function test_the_settings_venues_page_loads(): void
    {
        $this->actingAs($this->actor())->get(route('venues.index'))->assertOk()->assertSee('Venues');
    }
}
