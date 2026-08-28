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

        // Event Command Header pass: Overview's More Doors row only carries
        // the four modules the Module Navigation Bar doesn't surface
        // directly — brief, contract, registration (3, on a blank event;
        // speakers has no status chip, only a count) — agenda/venue/
        // transport moved off Overview entirely, since the nav bar already
        // covers them. The Stage Radar/Orbit Journey ring is gone (removed
        // outright, not replaced by another lifecycle diagram), so its 8
        // stage cards no longer count here.
        //
        // Module Header/Inspector role-separation pass: Overview's own
        // Inspector no longer falls back to Agenda's metrics (which used to
        // read "Not started" on a blank event) — it reads the event's own
        // readiness gates instead. A blank event has no agenda/speakers/
        // suppliers/transport gates to fail, so the only gate that applies
        // (Venue assigned) sits alongside two gates that start met (Approvals
        // cleared, No open severe risk) — 2 of 3 met reads "On Track", not
        // "Not started". So: 3 from the More Doors row.
        //
        // The Universal Module Header (hub/module-header.blade.php) is back
        // above every tab's content — on Overview it renders the Overview
        // module's own status pill, which reads "Not started" at 0% on a
        // blank event. That's a 4th, legitimate hit.
        $this->assertSame(4, substr_count($html, 'Not started'));
    }

    public function test_a_full_agenda_reads_ready(): void
    {
        // Mission Control pass: the Agenda door (and its "N sessions" line)
        // is gone from Overview — Agenda is rail-only now. The real
        // equivalent signal on today's Overview is Mission Timeline picking
        // up the session itself, since it starts in the future.
        $event = Event::factory()->create();
        $day = EventAgendaDay::create(['event_id' => $event->id, 'date' => now()->addWeek(), 'label' => 'Day 1']);
        EventAgendaSession::create([
            'event_id' => $event->id, 'agenda_day_id' => $day->id,
            'title' => 'Opening keynote', 'starts_at' => '09:00', 'ends_at' => '10:00', 'status' => 'confirmed',
        ]);

        $html = $this->actingAs($this->actor())
            ->get(route('events.hub', $event->fresh()))->assertOk()->getContent();

        $this->assertStringContainsString('Opening keynote', $html);
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
        // Only brief + contract are left "Not started" in the More Doors row
        // (registration itself now reads Ready) — 2 from that row, plus the
        // Universal Module Header's own "Not started" pill for Overview (see
        // test_untouched_modules_read_not_started) — 3.
        $this->assertSame(3, substr_count($html, 'Not started'));
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
