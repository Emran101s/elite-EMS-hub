<?php

namespace Tests\Browser;

use App\Models\Event;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * The interactions the PHPUnit suite cannot reach: Livewire + Alpine running in
 * a real browser. These drive the actual dev app (loginAs sets only the auth
 * session) and never submit a form, so no business data is mutated.
 *
 * Everything here was previously "verified" only by forcing panels visible with
 * JavaScript. This proves the click path itself.
 */
class DockAndModalTest extends DuskTestCase
{
    private function event(): Event
    {
        return Event::where('name', 'like', '%World Public Summit%')->firstOrFail()
            ?? Event::firstOrFail();
    }

    private function actor(): User
    {
        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    private function transportUrl(): string
    {
        return '/events/'.$this->event()->id.'?tab=transportation';
    }

    public function test_the_controls_dock_opens_from_its_spine_and_closes_on_outside_click(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->actor())
                ->visit($this->transportUrl())
                ->waitForText('Movements')

                // Collapsed to start.
                ->assertMissing('@dock-panel-controls')

                // Spine opens it.
                ->click('@dock-spine-controls')
                ->waitFor('@dock-panel-controls')
                ->assertSee('Transport Controls')
                ->assertVisible('@dock-panel-controls')

                // Clicking the page body (outside the panel) closes it.
                ->click('h2')
                ->waitUntilMissing('@dock-panel-controls')
                ->waitUntilMissing('@dock-panel-controls')->assertMissing('@dock-panel-controls');
        });
    }

    public function test_opening_one_dock_closes_the_other(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->actor())
                ->visit($this->transportUrl())
                ->waitForText('Movements')

                ->click('@dock-spine-controls')
                ->waitFor('@dock-panel-controls')

                // Documents shares the store, so it evicts Controls.
                ->click('@dock-spine-documents')
                ->waitFor('@dock-panel-documents')
                ->waitUntilMissing('@dock-panel-controls')->assertMissing('@dock-panel-controls');
        });
    }

    public function test_the_open_dock_pushes_the_page_content_aside(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1920, 1080)   // content-shift is an xl-and-up behaviour
                ->loginAs($this->actor())
                ->visit($this->transportUrl())
                ->waitForText('Movements');

            $before = $browser->script(
                "return getComputedStyle(document.querySelector('[data-dock-shift]')).paddingRight;"
            )[0];

            $browser->click('@dock-spine-controls')
                ->waitFor('@dock-panel-controls')
                ->pause(400);   // let the padding transition settle

            $after = $browser->script(
                "return getComputedStyle(document.querySelector('[data-dock-shift]')).paddingRight;"
            )[0];

            $this->assertSame('0px', $before, 'content is flush before opening');
            $this->assertNotSame($before, $after, 'content shifts right when the dock opens');
            $this->assertGreaterThan(300, (int) $after, 'the shift clears the panel width');
        });
    }

    public function test_escape_closes_an_open_dock(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->actor())
                ->visit($this->transportUrl())
                ->waitForText('Movements')
                ->click('@dock-spine-controls')
                ->waitFor('@dock-panel-controls')
                ->keys('@dock-spine-controls', '{escape}')
                ->waitUntilMissing('@dock-panel-controls')
                ->waitUntilMissing('@dock-panel-controls')->assertMissing('@dock-panel-controls');
        });
    }

    public function test_the_movement_modal_opens_accepts_input_and_closes(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->actor())
                ->visit($this->transportUrl())
                ->waitForText('Movements')

                // No dialog until asked for.
                ->assertMissing('@modal')

                // The page-level CTA opens it.
                ->press('＋ Add Movement')
                ->waitFor('@modal')
                ->assertSee('New movement')

                // It is a live form — typing works, without submitting.
                ->type('@modal input[type=text]', 'Queen Alia Airport')
                ->assertInputValue('@modal input[type=text]', 'Queen Alia Airport')

                // Cancel dismisses it; nothing was saved.
                ->press('Cancel')
                ->waitUntilMissing('@modal')
                ->assertMissing('@modal');
        });
    }
}
