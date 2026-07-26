<?php

namespace Tests\Feature;

use App\Livewire\CommandPalette;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The shared shell: breadcrumbs on every page, and a search that works. */
class PlatformChromeTest extends TestCase
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

    public function test_global_pages_carry_the_breadcrumb(): void
    {
        [, $user] = $this->ctx();

        $this->actingAs($user)->get(route('suppliers.index'))
            ->assertOk()
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('Command Center');
    }

    public function test_the_event_hub_breadcrumb_names_event_and_module(): void
    {
        [$event, $user] = $this->ctx();

        $res = $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'budget']));
        $res->assertOk()
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSeeInOrder(['Command Center', 'Events', $event->name, 'Budget']);
    }

    public function test_the_command_palette_finds_things_across_the_workspace(): void
    {
        [$event, $user] = $this->ctx();
        $event->tasks()->create(['title' => 'Chase the LED wall quote', 'status' => 'pending']);

        Livewire::actingAs($user)->test(CommandPalette::class)
            ->set('q', 'ICFT')
            ->assertSee('ICFT 2026')
            ->set('q', 'LED wall')
            ->assertSee('Chase the LED wall quote')
            ->set('q', 'zzz-nothing')
            ->assertSee('Nothing matches');
    }

    public function test_the_palette_asks_for_two_characters(): void
    {
        [, $user] = $this->ctx();

        Livewire::actingAs($user)->test(CommandPalette::class)
            ->set('q', 'a')
            ->assertSee('at least two characters');
    }
}
