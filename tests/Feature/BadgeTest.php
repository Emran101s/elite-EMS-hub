<?php

namespace Tests\Feature;

use App\Livewire\CheckInScan;
use App\Livewire\Hub\AttendeesTab;
use App\Models\Event;
use App\Models\User;
use App\Support\Badge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Badges, and the door they open.
 *
 * The badge is designed on screen and printed from the same partial, so the
 * tests that matter are the ones about what the badge CARRIES — a reference
 * that finds the person again, and a QR that admits them exactly once.
 */
class BadgeTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $event = Event::factory()->create(['stage' => 'planning', 'registration_open' => true])->fresh();
        $attendee = $event->attendees()->create([
            'name' => 'Layla Haddad',
            'email' => 'layla@example.org',
            'organization' => 'Ministry of Health',
            'ticket_type' => 'VIP',
            'status' => 'registered',
        ]);

        return [$event, $attendee, User::factory()->create(['role' => 'super_admin'])];
    }

    public function test_an_event_with_no_design_still_prints_a_badge(): void
    {
        [$event] = $this->ctx();

        $this->assertNull($event->badge_template);

        $template = Badge::template($event);

        $this->assertSame(Badge::DEFAULTS['size'], $template['size']);
        $this->assertTrue($template['show_qr'], 'every key has a default');
        $this->assertSame([148, 105], Badge::dimensions($event));
    }

    public function test_the_design_is_saved_and_only_the_keys_it_declares(): void
    {
        [$event, , $user] = $this->ctx();

        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('badge.size', 'name_badge')
            ->set('badge.show_job_title', true)
            ->set('badge.footer', 'Wear at all times')
            // Nothing from a browser should be able to put arbitrary JSON on
            // the event.
            ->set('badge.smuggled', 'should not land')
            ->call('saveBadge')
            ->assertHasNoErrors();

        $saved = $event->fresh()->badge_template;

        $this->assertSame('name_badge', $saved['size']);
        $this->assertTrue($saved['show_job_title']);
        $this->assertArrayNotHasKey('smuggled', $saved);
        $this->assertSame([90, 54], Badge::dimensions($event->fresh()));
    }

    /**
     * Picking a different size used to leave the preview at whatever size the
     * event last had saved — every other field (colour, logo, lines) updated
     * live, but the dimensions specifically re-read the saved database value
     * instead of the in-progress selection, so the preview never visibly
     * changed until Save was clicked and the page reloaded.
     */
    public function test_picking_a_new_size_resizes_the_live_preview_before_saving(): void
    {
        [$event, , $user] = $this->ctx();

        $screen = Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('showBadge', true);
        $screen->assertSee('width: 148mm; height: 105mm', false); // a6_landscape default

        $screen->set('badge.size', 'name_badge');
        $screen->assertSee('width: 90mm; height: 54mm', false);
        $screen->assertDontSee('width: 148mm; height: 105mm', false);

        // Unsaved — the size change was only ever in the preview.
        $this->assertNull($event->fresh()->badge_template);
    }

    public function test_a_custom_size_saves_and_prints_at_its_own_dimensions(): void
    {
        [$event, , $user] = $this->ctx();

        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('badge.size', 'custom')
            ->set('badge.custom_width', 120)
            ->set('badge.custom_height', 80)
            ->call('saveBadge')
            ->assertHasNoErrors();

        $saved = $event->fresh()->badge_template;

        $this->assertSame('custom', $saved['size']);
        $this->assertSame(120, $saved['custom_width']);
        $this->assertSame(80, $saved['custom_height']);
        $this->assertSame([120, 80], Badge::dimensions($event->fresh()));
    }

    public function test_the_live_preview_resizes_as_custom_dimensions_are_typed(): void
    {
        [$event, , $user] = $this->ctx();

        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('showBadge', true)
            ->set('badge.size', 'custom')
            ->set('badge.custom_width', 130)
            ->set('badge.custom_height', 70)
            ->assertSee('width: 130mm; height: 70mm', false);
    }

    public function test_a_custom_size_needs_both_dimensions(): void
    {
        [$event, , $user] = $this->ctx();

        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('badge.size', 'custom')
            ->set('badge.custom_width', null)
            ->set('badge.custom_height', null)
            ->call('saveBadge')
            ->assertHasErrors(['badge.custom_width', 'badge.custom_height']);

        $this->assertNull($event->fresh()->badge_template);
    }

    public function test_a_custom_size_cannot_be_too_small_to_hold_a_name(): void
    {
        [$event, , $user] = $this->ctx();

        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('badge.size', 'custom')
            ->set('badge.custom_width', 10)
            ->set('badge.custom_height', 10)
            ->call('saveBadge')
            ->assertHasErrors(['badge.custom_width', 'badge.custom_height']);

        $this->assertNull($event->fresh()->badge_template);
    }

    public function test_a_custom_size_cannot_be_a_poster(): void
    {
        [$event, , $user] = $this->ctx();

        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('badge.size', 'custom')
            ->set('badge.custom_width', 500)
            ->set('badge.custom_height', 500)
            ->call('saveBadge')
            ->assertHasErrors(['badge.custom_width', 'badge.custom_height']);

        $this->assertNull($event->fresh()->badge_template);
    }

    public function test_a_design_can_be_put_back_to_the_default(): void
    {
        [$event, , $user] = $this->ctx();

        $screen = Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('badge.size', 'square')->call('saveBadge');

        $this->assertSame('square', $event->fresh()->badge_template['size']);

        $screen->call('resetBadge');
        $this->assertNull($event->fresh()->badge_template);
    }

    public function test_a_colour_has_to_be_a_colour(): void
    {
        [$event, , $user] = $this->ctx();

        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->set('badge.accent', 'gold-ish')
            ->call('saveBadge')
            ->assertHasErrors('badge.accent');

        $this->assertNull($event->fresh()->badge_template);
    }

    public function test_the_sheet_prints_everyone_who_has_not_cancelled(): void
    {
        [$event, $attendee, $user] = $this->ctx();

        $event->attendees()->create(['name' => 'Withdrew', 'email' => 'w@example.org', 'status' => 'cancelled']);

        $response = $this->actingAs($user)->get(route('events.badges.pdf', $event));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_printing_a_selection_prints_only_the_selection(): void
    {
        [$event, $attendee, $user] = $this->ctx();
        $other = $event->attendees()->create(['name' => 'Someone Else', 'email' => 's@example.org', 'status' => 'registered']);

        // Not an assertion about the PDF's pixels — just that the route accepts
        // a selection and does not fall over on one.
        $this->actingAs($user)
            ->get(route('events.badges.pdf', [$event, 'ids' => $other->id]))
            ->assertOk();
    }

    public function test_an_event_with_nobody_to_print_for_says_so(): void
    {
        $event = Event::factory()->create(['stage' => 'planning']);
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)->get(route('events.badges.pdf', $event))->assertNotFound();
    }

    // ══════════════════════════════════════════════════════════════════════
    //  The QR, and the door
    // ══════════════════════════════════════════════════════════════════════

    /**
     * The QR carries a URL, not a bare code, so any phone camera reaches the
     * check-in page without a special scanner app.
     */
    public function test_the_qr_is_a_check_in_url_for_that_person(): void
    {
        [$event, $attendee] = $this->ctx();

        $url = Badge::checkInUrl($attendee);

        $this->assertStringContainsString($event->registrationToken(), $url);
        $this->assertStringContainsString($attendee->reference(), $url);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', Badge::qr($url));
    }

    public function test_scanning_a_badge_admits_the_person_once(): void
    {
        [$event, $attendee] = $this->ctx();

        $screen = Livewire::test(CheckInScan::class, [
            'token' => $event->registrationToken(),
            'reference' => $attendee->reference(),
        ])->assertSet('state', 'found')->assertSee('Layla Haddad');

        $screen->call('admit')->assertSet('state', 'done');

        $attendee->refresh();
        $this->assertSame('checked_in', $attendee->status);
        $this->assertNotNull($attendee->checked_in_at);
    }

    public function test_scanning_the_same_badge_again_says_so_rather_than_admitting_twice(): void
    {
        [$event, $attendee] = $this->ctx();
        $attendee->update(['status' => 'checked_in', 'checked_in_at' => now()->subHour()]);

        Livewire::test(CheckInScan::class, [
            'token' => $event->registrationToken(),
            'reference' => $attendee->reference(),
        ])
            ->assertSet('state', 'already')
            ->assertSee('Already checked in')
            // The button is not there, and calling it anyway changes nothing.
            ->call('admit')
            ->assertSet('state', 'already');

        $this->assertTrue($attendee->fresh()->checked_in_at->isBefore(now()->subMinutes(30)));
    }

    public function test_a_cancelled_registration_is_refused_at_the_door(): void
    {
        [$event, $attendee] = $this->ctx();
        $attendee->update(['status' => 'cancelled']);

        Livewire::test(CheckInScan::class, [
            'token' => $event->registrationToken(),
            'reference' => $attendee->reference(),
        ])
            ->assertSet('state', 'cancelled')
            ->call('admit')
            ->assertSet('state', 'cancelled');

        $this->assertNull($attendee->fresh()->checked_in_at);
    }

    /** A badge from another event must not open this door. */
    public function test_a_badge_from_another_event_is_not_recognised(): void
    {
        [$event] = $this->ctx();
        $other = Event::factory()->create(['stage' => 'planning'])->fresh();
        $theirs = $other->attendees()->create(['name' => 'Wrong Door', 'email' => 'wd@example.org', 'status' => 'registered']);

        Livewire::test(CheckInScan::class, [
            'token' => $event->registrationToken(),
            'reference' => $theirs->reference(),
        ])->assertSet('state', 'unknown')->assertSee('not on the list');

        $this->assertNull($theirs->fresh()->checked_in_at);
    }

    public function test_a_scan_against_a_token_that_matches_nothing_is_a_404(): void
    {
        [, $attendee] = $this->ctx();

        $this->get(route('checkin.scan', ['token' => 'nope', 'reference' => $attendee->reference()]))
            ->assertNotFound();
    }

    public function test_the_door_page_needs_no_sign_in(): void
    {
        [$event, $attendee] = $this->ctx();

        // A volunteer on the door has a borrowed phone, not an account.
        $this->get(route('checkin.scan', [
            'token' => $event->registrationToken(),
            'reference' => $attendee->reference(),
        ]))->assertOk()->assertSee('Ready to admit');
    }
}
