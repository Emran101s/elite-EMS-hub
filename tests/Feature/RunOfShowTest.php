<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunOfShowTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    public function test_run_of_show_renders_timeline_and_legend(): void
    {
        [$event, $user] = $this->ctx();

        $this->actingAs($user)->get(route('events.run-of-show', $event))
            ->assertOk()
            ->assertSee('Run of Show')
            ->assertSee('EVT-'.str_pad($event->id, 3, '0', STR_PAD_LEFT))
            ->assertSee('Opening Ceremony')   // a seeded session plots on the timeline
            ->assertSee('Main Hall')           // room lane
            ->assertSee('Legend')
            ->assertSee('Event Overview');
    }

    public function test_selecting_a_day_scopes_the_timeline(): void
    {
        [$event, $user] = $this->ctx();
        $day2 = $event->agendaDays()->orderBy('sort')->skip(1)->first();

        $this->actingAs($user)->get(route('events.run-of-show', [$event, 'day' => $day2->id]))
            ->assertOk()
            ->assertSee('Keynote: AI in Capital Markets'); // a Day 2 session
    }

    public function test_empty_day_shows_prompt(): void
    {
        [$event, $user] = $this->ctx();
        $empty = $event->agendaDays()->create(['date' => '2026-10-25', 'label' => 'Day X', 'sort' => 9]);

        $this->actingAs($user)->get(route('events.run-of-show', [$event, 'day' => $empty->id]))
            ->assertOk()
            ->assertSee('Nothing scheduled for this day');
    }
}
