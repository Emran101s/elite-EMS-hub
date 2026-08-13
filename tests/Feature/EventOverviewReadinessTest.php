<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventAgendaDay;
use App\Models\EventAgendaSession;
use App\Models\EventTransport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Overview's delivery doors used to cover three modules (Speakers, Brief,
 * Contract) out of the whole workflow spine — a PM had to already know the
 * spine by heart to know Agenda, Venue, Transport and Registration were
 * sitting empty. This locks in that all six spine modules now read their own
 * state, and that a disabled module's door disappears rather than pointing
 * at a tab that bounces straight back to Overview.
 */
class EventOverviewReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_untouched_modules_read_not_started(): void
    {
        $event = Event::factory()->create();

        $html = $this->actingAs($this->actor())
            ->get(route('events.hub', $event))->assertOk()->getContent();

        // brief, contract, agenda, venue, transport, registration — all
        // untouched (6) — plus the Orbit Journey's own 7 non-active stage
        // cards, which on a blank event carry no meters/attention signal and
        // so read "Not started" too (Phase E.2; see
        // docs/32-event-hub-orbit-journey-architecture.md §4).
        $this->assertSame(6 + 7, substr_count($html, 'Not started'));
    }

    public function test_a_full_agenda_reads_ready(): void
    {
        $event = Event::factory()->create();
        $day = EventAgendaDay::create(['event_id' => $event->id, 'date' => now()->addWeek(), 'label' => 'Day 1']);
        EventAgendaSession::create([
            'event_id' => $event->id, 'agenda_day_id' => $day->id,
            'title' => 'Opening keynote', 'starts_at' => '09:00', 'ends_at' => '10:00', 'status' => 'confirmed',
        ]);

        $html = $this->actingAs($this->actor())
            ->get(route('events.hub', $event->fresh()))->assertOk()->getContent();

        $this->assertStringContainsString('1 session', $html);
    }

    public function test_a_flagged_movement_needs_attention_not_in_progress(): void
    {
        $event = Event::factory()->create();
        EventTransport::create([
            'event_id' => $event->id, 'route' => 'Airport pickup', 'status' => 'issue',
            'issue_note' => 'No driver assigned',
        ]);

        $html = $this->actingAs($this->actor())
            ->get(route('events.hub', $event->fresh()))->assertOk()->getContent();

        $this->assertStringContainsString('Needs attention', $html);
    }

    public function test_registration_reads_ready_once_the_event_opens_it(): void
    {
        $event = Event::factory()->create(['registration_open' => true]);

        $html = $this->actingAs($this->actor())
            ->get(route('events.hub', $event))->assertOk()->getContent();

        $this->assertStringContainsString('Registration', $html);
        // brief, contract, agenda, venue, transport are still untouched —
        // only registration moved (5) — plus the Orbit Journey's own 7
        // non-active stage cards (unaffected by registration_open).
        $this->assertSame(5 + 7, substr_count($html, 'Not started'));
    }

    /** A disabled module's door must not appear at all — it would only bounce back to Overview. */
    public function test_a_disabled_modules_door_is_hidden(): void
    {
        $event = Event::factory()->create([
            'enabled_modules' => array_values(array_diff(array_keys(Event::HUB_MODULES), ['venue'])),
        ]);

        $html = $this->actingAs($this->actor())
            ->get(route('events.hub', $event))->assertOk()->getContent();

        $this->assertStringNotContainsString('No venue selected', $html);
    }
}
