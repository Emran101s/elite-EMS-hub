<?php

namespace Tests\Feature;

use App\Livewire\CommandPalette;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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

    public function test_every_module_in_the_registry_is_reachable_from_the_chrome(): void
    {
        [, $user] = $this->ctx();

        $page = $this->actingAs($user)->get(route('home'))->assertOk();

        // The nav used to be a second list kept by hand, and it drifted: CRM was
        // built and never added, so it and four others could not be reached.
        foreach (config('modules.nav') as $key => $module) {
            if (! Route::has($module['route'])) {
                continue;
            }

            $page->assertSee('href="'.route($module['route']).'"', false);
        }
    }

    /**
     * The More menu is not inside the scrolling pill row.
     *
     * It was, and overflow-x-auto makes a clipping box, so the menu opened
     * correctly and then had 310px of itself cut off — which looks exactly
     * like a button that does nothing. A reachability test cannot catch that:
     * the links were all in the HTML, just invisible. This asserts the one
     * structural fact that made them invisible.
     */
    public function test_the_more_menu_sits_outside_the_scrolling_pill_row(): void
    {
        [, $user] = $this->ctx();

        $html = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();

        $nav = str($html)->after('<nav')->before('</nav>');

        $this->assertStringContainsString('overflow-x-auto', (string) $nav, 'the pills still scroll');
        $this->assertStringNotContainsString('data-menu', (string) $nav,
            'the More menu must not live inside a scroll container — it gets clipped');
        $this->assertStringContainsString('data-menu', $html, 'but it is still on the page');
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
