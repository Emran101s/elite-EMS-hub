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

    public function test_avatar_library_seeds_six_avatars_idempotently(): void
    {
        $this->seed(EventAvatarSeeder::class);
        $this->seed(EventAvatarSeeder::class);

        $this->assertSame(6, EventAvatar::count());
        $this->assertSame(6, EventAvatar::active()->count());
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

    public function test_create_event_auto_recommends_avatar_by_type(): void
    {
        $this->seed(EventAvatarSeeder::class);
        $user = User::factory()->create();

        $galaAvatar = EventAvatar::where('slug', 'gala-dinner')->first();
        $conferenceAvatar = EventAvatar::where('slug', 'international-conference')->first();

        Livewire::actingAs($user)->test(EventCreate::class)
            ->assertSet('avatar_id', $conferenceAvatar->id) // default type = conference
            ->set('type', 'gala_dinner')
            ->assertSet('avatar_id', $galaAvatar->id);
    }

    public function test_manual_avatar_choice_survives_type_changes(): void
    {
        $this->seed(EventAvatarSeeder::class);
        $user = User::factory()->create();

        $festival = EventAvatar::where('slug', 'festival-outdoor')->first();

        Livewire::actingAs($user)->test(EventCreate::class)
            ->call('chooseAvatar', $festival->id)
            ->set('type', 'gala_dinner')
            ->assertSet('avatar_id', $festival->id);
    }

    public function test_saving_creates_event_with_avatar(): void
    {
        $this->seed(EventAvatarSeeder::class);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(EventCreate::class)
            ->set('name', 'Falcon Summit 2027')
            ->set('type', 'conference')
            ->set('city', 'Amman')
            ->set('country', 'Jordan')
            ->set('starts_at', '2027-03-10')
            ->set('budget', '150000')
            ->set('new_client', 'Falcon Holdings')
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::where('name', 'Falcon Summit 2027')->firstOrFail();

        $this->assertSame('international-conference', $event->avatar->slug);
        $this->assertSame(15000000, $event->budget_cents);
        $this->assertSame('planning', $event->status);
        $this->assertSame('draft', $event->stage);
        $this->assertSame('Falcon Holdings', $event->client->name);
        $this->assertSame('#0B1F3A', $event->primary_color);
    }

    public function test_avatars_render_in_hub_and_events_list(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();

        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('data-avatar="international-conference"', false)
            ->assertSee('data-avatar="vip-event"', false);

        $this->actingAs($user)->get('/events')->assertOk()
            ->assertSee('data-avatar="exhibition"', false);
    }
}
