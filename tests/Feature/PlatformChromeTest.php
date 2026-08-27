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
     * The map draws no door that does not open.
     *
     * Messages, Guests, Assets and Help were drawn greyed out to show the
     * shape of the product before it was all built. New staff read them as
     * broken software and clicked them anyway, so docs/18 called them in.
     * NavPanel now drops a row whose route is not registered, which is the
     * part worth holding: the next unbuilt idea cannot re-enter the nav by
     * being added to the registry a release early.
     */
    public function test_the_panel_draws_no_rows_that_go_nowhere(): void
    {
        [, $user] = $this->ctx();

        $pages = collect(NavPanel::AREAS)->pluck('route')
            ->push(NavPanel::SETTINGS['route'])
            ->filter(fn (string $route) => Route::has($route))
            ->map(fn (string $route) => $this->actingAs($user)->get(route($route))->assertOk()->getContent());

        foreach ($pages as $html) {
            $panel = (string) str($html)->after('aria-label="Sections"')->before('</nav>');

            $this->assertStringNotContainsString('aria-disabled', $panel, 'a nav row is drawn dead');
            $this->assertStringNotContainsString('Coming soon', $html);
            $this->assertStringNotContainsString('Help &amp; Support', $html, 'the help row has no page behind it');

            foreach (['Messages', 'Guests', 'Assets'] as $ghost) {
                $this->assertStringNotContainsString(">{$ghost}<", $panel,
                    $ghost.' is back in the nav without a page behind it');
            }
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
     * The rail names its areas out loud, and does not clip them.
     *
     * The eight area icons carry no meaning on their own (truck = Operations,
     * sparkles = Intelligence), so each one now shows its label inline in the
     * rail — not only in a hover flyout nobody discovers. This asserts the
     * labels are actually rendered inside the rail, and that the rail is not a
     * clipping box that would swallow a label wrapping to a second line.
     */
    public function test_the_rail_names_its_areas_and_does_not_clip_them(): void
    {
        [, $user] = $this->ctx();

        $html = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();

        $railBox = (string) str($html)->before('aria-label="Areas"')->afterLast('<nav ');
        $rail = (string) str($html)->after('aria-label="Areas"')->before('</nav>');

        // Every area's label is visible text in the rail, not just a title="".
        foreach (NavPanel::AREAS as $area) {
            if (Route::has($area['route'])) {
                $this->assertStringContainsString('>'.$area['label'].'</span>', $rail,
                    "the rail shows the '{$area['label']}' label inline, not only on hover");
            }
        }

        // A label that wraps must not be clipped by an overflow on the rail box.
        $this->assertStringNotContainsString('overflow-hidden', $railBox,
            'the rail clips, so a wrapped area label is painted where nobody can see it');
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
