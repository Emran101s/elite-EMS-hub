<?php

namespace Tests\Feature;

use App\Livewire\CommandPalette;
use App\Models\Event;
use App\Models\User;
use App\Support\NavPanel;
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

    /**
     * Nothing in the registry is unreachable.
     *
     * The nav is two tiers now: the rail carries areas and each area's panel
     * carries what is inside it, so Suppliers is no longer a link on the home
     * page — it is a link on the Library page. The property worth holding is
     * unchanged though, just measured across the areas rather than on one
     * screen: every module the platform has built must be reachable from the
     * chrome somewhere. The hand-kept pill list drifted once and left CRM,
     * Suppliers, Venues, Team and Assets with no way in at all.
     */
    public function test_every_module_in_the_registry_is_reachable_from_the_chrome(): void
    {
        [, $user] = $this->ctx();

        // Every area's landing page, which is every panel there is.
        $chrome = collect(NavPanel::AREAS)->pluck('route')
            ->push(NavPanel::SETTINGS['route'])
            ->filter(fn (string $route) => Route::has($route))
            ->map(fn (string $route) => $this->actingAs($user)->get(route($route))->assertOk()->getContent())
            ->implode("\n");

        foreach (config('modules.nav') as $key => $module) {
            if (! Route::has($module['route'])) {
                continue;
            }

            $this->assertStringContainsString(
                'href="'.route($module['route']).'"',
                $chrome,
                $key.' is built but nothing in the chrome links to it'
            );
        }
    }

    /**
     * Every area is ON the rail, rather than five of them behind a menu.
     *
     * The menu they were behind spent months opening into a clipping box,
     * which nobody noticed because nobody could see it.
     */
    public function test_every_area_is_on_the_rail(): void
    {
        [, $user] = $this->ctx();

        $page = $this->actingAs($user)->get(route('home'))->assertOk();

        foreach (NavPanel::AREAS as $key => $area) {
            if (! Route::has($area['route'])) {
                continue;
            }

            $page->assertSee('title="'.$area['label'].'"', false);
        }

        // Settings is not an area, so it is not on the rail — it sits in the
        // panel's command dock with the other two things you reach for without
        // reading. Still one click from anywhere, which is the point.
        $page->assertSee(route('settings.index'), false);
    }

    /**
     * The rail must not be a clipping box.
     *
     * Its hover labels are painted OUTSIDE it (absolute, left-full), so any
     * overflow that is not visible swallows them — which is exactly what
     * happened to the More menu inside the scrolling pill row, and what
     * happened again to these labels an hour after that was fixed. A test,
     * because twice is a pattern.
     */
    public function test_the_rail_does_not_clip_the_labels_that_hang_off_it(): void
    {
        [, $user] = $this->ctx();

        $html = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();

        // The nav's OWN class list, not its subtree: the decorative layer
        // inside it is absolutely positioned and clips itself on purpose, to
        // keep its orbit arcs from bleeding across the page. That is fine.
        // What must never clip is the box the labels hang off.
        $railBox = (string) str($html)->before('aria-label="Areas"')->afterLast('<nav ');
        $rail = (string) str($html)->after('aria-label="Areas"')->before('</nav>');

        $this->assertStringContainsString('left-full', $rail, 'the labels still hang off the rail');
        $this->assertStringNotContainsString('overflow-hidden', $railBox,
            'the rail clips, so its hover labels are painted where nobody can see them');
        $this->assertStringNotContainsString('overflow-x-auto', $railBox);
        $this->assertStringNotContainsString('overflow-y-auto', $railBox);
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
