<?php

namespace Tests\Feature;

use App\Livewire\EventCreate;
use App\Models\Event;
use App\Models\EventAvatar;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\EventAvatarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_library_seeds_nine_avatars_idempotently(): void
    {
        $this->seed(EventAvatarSeeder::class);
        $this->seed(EventAvatarSeeder::class);

        $this->assertSame(9, EventAvatar::count());
        $this->assertSame(9, EventAvatar::active()->count());
        $this->assertSame(7, EventAvatar::whereNotNull('image_path')->count()); // uploaded 3D renders
    }

    public function test_recommendation_matches_event_types(): void
    {
        $this->seed(EventAvatarSeeder::class);

        $this->assertSame('international-conference', EventAvatar::recommendedFor('conference')->first()->slug);
        $this->assertSame('international-conference', EventAvatar::recommendedFor('summit')->first()->slug);
        $this->assertSame('gala-dinner', EventAvatar::recommendedFor('gala_dinner')->first()->slug);
        $this->assertSame('exhibition', EventAvatar::recommendedFor('career_fair')->first()->slug);
        $this->assertSame('exhibition', EventAvatar::recommendedFor('exhibition')->first()->slug);
        $this->assertSame('workshop', EventAvatar::recommendedFor('workshop')->first()->slug);
        $this->assertSame('vip-event', EventAvatar::recommendedFor('private_dinner')->first()->slug);
        $this->assertSame('vip-event', EventAvatar::recommendedFor('embassy_event')->first()->slug);
        $this->assertSame('festival-outdoor', EventAvatar::recommendedFor('outdoor_event')->first()->slug);
    }

    public function test_avatar_library_page_renders_and_filters(): void
    {
        $this->seed(EventAvatarSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/events/avatars')->assertOk()
            ->assertSee('Event Avatar Library')
            ->assertSee('Luxury Ballroom')
            ->assertSee('Outdoor Event Island');

        $this->actingAs($user)->get('/events/avatars?category=workshop')->assertOk()
            ->assertSee('Learning Center')
            ->assertDontSee('Luxury Ballroom');
    }

    public function test_template_auto_assigns_avatar_and_pre_enables_modules(): void
    {
        $this->seed(EventAvatarSeeder::class);
        $user = User::factory()->create();

        // Gala template → gala-dinner avatar + its module set (incl. sponsors, no agenda).
        Livewire::actingAs($user)->test(EventCreate::class)
            ->set('new_client', 'Falcon Holdings')
            ->set('name', 'Falcon Gala 2027')
            ->set('starts_at', '2027-03-10')
            ->call('chooseTemplate', 'gala')
            ->assertSet('modules', ['tasks', 'budget', 'suppliers', 'venue', 'sponsors'])
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::where('name', 'Falcon Gala 2027')->firstOrFail();

        $this->assertSame('gala-dinner', $event->avatar->slug);
        $this->assertSame('gala_dinner', $event->type);
        $this->assertSame('draft', $event->stage);
        $this->assertSame('Falcon Holdings', $event->client->name);
        $this->assertContains('sponsors', $event->enabled_modules);
        $this->assertNotContains('agenda', $event->enabled_modules);
    }

    public function test_status_pill_maps_to_stage(): void
    {
        $this->seed(EventAvatarSeeder::class);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(EventCreate::class)
            ->set('new_client', 'Acme')
            ->set('name', 'Confirmed Congress')
            ->set('starts_at', '2027-05-01')
            ->set('statusPill', 'confirmed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('confirmed', Event::where('name', 'Confirmed Congress')->value('stage'));
    }

    public function test_avatars_render_in_hub_and_events_list(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();

        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('data-avatar="convention-center"', false) // ICFT's branded render
            ->assertSee('data-avatar="vip-event"', false);

        $this->actingAs($user)->get('/events')->assertOk()
            ->assertSee('data-avatar="exhibition"', false);
    }
}
