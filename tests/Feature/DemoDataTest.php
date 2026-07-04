<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_is_idempotent(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(6, Event::count());
        $this->assertSame(6, Supplier::count());
        $this->assertSame(128, Task::count());
        $this->assertSame(6, User::count());
    }

    public function test_module_pages_render_seeded_data(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();

        $this->actingAs($user)->get('/events')->assertOk()
            ->assertSee('ICFT 2026')->assertSee('Tech Expo 2026');

        $this->actingAs($user)->get('/events?view=list')->assertOk()
            ->assertSee('Royal Convention Centre');

        $this->actingAs($user)->get('/suppliers')->assertOk()
            ->assertSee('Creative Vision Co.')->assertSee('4.9');

        $this->actingAs($user)->get('/venues')->assertOk()
            ->assertSee('Doha Exhibition Center');

        $this->actingAs($user)->get('/projects')->assertOk()
            ->assertSee('Conference Season 2026');

        $this->actingAs($user)->get('/team')->assertOk()
            ->assertSee('Layla Haddad')->assertSee('Operations Manager');

        $this->actingAs($user)->get('/tasks')->assertOk();
    }

    public function test_command_center_kpis_reflect_seeded_data(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();

        $response = $this->actingAs($user)->get('/')->assertOk();

        $response->assertSee('Total Events');
        $response->assertSee('56'); // open tasks: 36 in progress + 20 pending
        $response->assertSeeText('2'); // at-risk events: NDI Workshop + Tech Expo
    }

    public function test_event_relationships_are_wired(): void
    {
        $this->seed(DemoDataSeeder::class);

        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        $this->assertSame('Royal Convention Centre', $event->venue->name);
        $this->assertSame('Conference Season 2026', $event->project->name);
        $this->assertContains('Creative Vision Co.', $event->suppliers->pluck('name')->all());
        $this->assertGreaterThan(0, $event->tasks()->count());
    }
}
