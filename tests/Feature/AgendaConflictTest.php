<?php

namespace Tests\Feature;

use App\Livewire\Hub\AgendaTab;
use App\Livewire\Hub\SettingsTab;
use App\Livewire\Hub\VenueTab;
use App\Models\Event;
use App\Models\EventAgendaSession;
use App\Models\User;
use App\Models\Venue;
use App\Services\AgendaProgram;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaConflictTest extends TestCase
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

    public function test_same_room_overlap_is_flagged(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->orderBy('sort')->first();
        $room = $event->rooms()->first();

        $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'room_id' => $room->id, 'title' => 'Session A', 'type' => 'panel', 'status' => 'draft', 'starts_at' => '11:00', 'ends_at' => '12:00', 'sort' => 50]);
        $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'room_id' => $room->id, 'title' => 'Session B', 'type' => 'panel', 'status' => 'draft', 'starts_at' => '11:30', 'ends_at' => '12:30', 'sort' => 51]);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->assertSee('scheduling')
            ->assertSee('double-booked with'); // room clash flagged on the card
    }

    public function test_same_speaker_overlap_is_flagged(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->orderBy('sort')->first();
        $rooms = $event->rooms()->take(2)->get();

        $a = $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'room_id' => $rooms[0]->id, 'title' => 'Talk 1', 'type' => 'keynote', 'status' => 'draft', 'starts_at' => '14:00', 'ends_at' => '15:00', 'sort' => 60]);
        $b = $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'room_id' => $rooms[1]->id, 'title' => 'Talk 2', 'type' => 'keynote', 'status' => 'draft', 'starts_at' => '14:30', 'ends_at' => '15:30', 'sort' => 61]);

        // The same roster person billed on two overlapping sessions.
        $chen = $event->speakers()->create(['name' => 'Dr. Chen', 'status' => 'confirmed']);
        $a->speakers()->attach($chen, ['role' => 'keynote', 'sort' => 0]);
        $b->speakers()->attach($chen, ['role' => 'keynote', 'sort' => 0]);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->assertSee('Dr. Chen also speaks at', false);
    }

    public function test_session_bills_multiple_speakers_with_roles(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->orderBy('sort')->first();

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('newSession', $day->id)
            ->set('title', 'Youth Panel')
            ->call('addSpeakerRow', 'moderator')
            ->set('speakerRows.0.name', 'Dr. Salim')
            ->call('addSpeakerRow', 'panelist')
            ->set('speakerRows.1.name', 'Nabil Fahmy')
            ->call('addSpeakerRow', 'panelist')
            ->set('speakerRows.2.name', 'Layla Haddad')
            ->call('saveSession')
            ->assertHasNoErrors();

        $session = $event->agendaSessions()->where('title', 'Youth Panel')->firstOrFail()->load('speakers');

        $this->assertCount(3, $session->speakers);
        // New names are auto-added to the event's speaker roster.
        $this->assertNotNull($event->speakers()->where('name', 'Nabil Fahmy')->first());
        $this->assertSame(
            'Moderator: Dr. Salim · Panellists: Nabil Fahmy, Layla Haddad',
            $session->speakerLine()
        );
    }

    /** Build a detached session for pure programme-shaping tests. */
    private function stubSession(string $title, string $start, string $end, ?string $track = null, string $type = 'panel'): EventAgendaSession
    {
        $s = new EventAgendaSession;
        $s->fill(['title' => $title, 'starts_at' => $start, 'ends_at' => $end, 'track' => $track, 'type' => $type]);
        $s->setRelation('speakers', collect());
        $s->setRelation('room', null);

        return $s;
    }

    public function test_card_shows_its_own_time_when_it_differs_from_the_slot(): void
    {
        $p = (new AgendaProgram)->forDay(collect([
            $this->stubSession('Panel A', '13:30', '15:30', 'Track'),
            $this->stubSession('Panel B', '13:30', '18:00', 'Track'),
        ]));

        $this->assertCount(1, $p['blocks']);
        $cards = collect($p['blocks'][0]['cards'])->keyBy(fn ($c) => $c['session']->title);

        // The slot header spans the longest session, so the shorter one must say so itself.
        $this->assertSame('13:30–18:00', $p['blocks'][0]['time'].'–'.$p['blocks'][0]['end']);
        $this->assertSame('13:30–15:30', $cards['Panel A']['ownTime']);
        $this->assertNull($cards['Panel B']['ownTime']);
    }

    public function test_overlapping_sessions_with_staggered_starts_share_a_slot(): void
    {
        $p = (new AgendaProgram)->forDay(collect([
            $this->stubSession('Track A', '13:30', '15:30', 'Track'),
            $this->stubSession('Track B', '14:00', '16:00', 'Track'),
        ]));

        $this->assertCount(1, $p['blocks'], 'parallel tracks must not split into separate rows');
        $this->assertCount(2, $p['blocks'][0]['cards']);
    }

    public function test_sequential_sessions_do_not_chain_into_one_slot(): void
    {
        $p = (new AgendaProgram)->forDay(collect([
            $this->stubSession('Lunch', '12:00', '13:30', null, 'lunch'),
            $this->stubSession('Panel', '13:30', '15:30', 'Track'),
            $this->stubSession('Coffee', '15:30', '16:00', null, 'break'),
        ]));

        $this->assertCount(3, $p['blocks']);
    }

    public function test_long_session_stays_on_the_programme(): void
    {
        // Ambient is decided by track, never by duration.
        $p = (new AgendaProgram)->forDay(collect([
            $this->stubSession('Full-Day Training Workshop', '09:00', '15:00', 'Track', 'workshop'),
        ]));

        $this->assertCount(1, $p['blocks'], 'a real six-hour session must not be demoted to an ambient pill');
        $this->assertEmpty($p['ambient']);
    }

    public function test_unconfirmed_sessions_are_marked_and_counted(): void
    {
        $draft = $this->stubSession('Draft Panel', '10:00', '11:00', 'Track');
        $draft->status = 'draft';
        $confirmed = $this->stubSession('Signed-off Keynote', '12:00', '13:00', 'Plenary');
        $confirmed->status = 'confirmed';

        $p = (new AgendaProgram)->forDay(collect([$draft, $confirmed]));

        $this->assertSame(1, $p['unconfirmed']);
        $this->assertSame(2, $p['total']);

        $cards = collect($p['blocks'])->flatMap(fn ($b) => $b['cards'])->keyBy(fn ($c) => $c['session']->title);
        // Unapproved work is marked; confirmed work stays clean.
        $this->assertFalse($cards['Draft Panel']['settled']);
        $this->assertSame('Draft', $cards['Draft Panel']['status']);
        $this->assertTrue($cards['Signed-off Keynote']['settled']);
    }

    public function test_confirm_day_settles_every_unconfirmed_session(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->orderBy('sort')->first();
        $event->agendaSessions()->where('agenda_day_id', $day->id)->update(['status' => 'draft']);
        $before = $event->agendaSessions()->where('agenda_day_id', $day->id)->count();

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('selectDay', $day->id)
            ->call('confirmDay');

        $this->assertSame(0, $event->agendaSessions()->where('agenda_day_id', $day->id)
            ->whereIn('status', ['draft', 'waiting_speaker', 'needs_review'])->count());
        $this->assertSame($before, $event->agendaSessions()->where('agenda_day_id', $day->id)
            ->where('status', 'confirmed')->count());
    }

    public function test_session_status_is_editable_and_not_forced_to_confirmed(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->orderBy('sort')->first();

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('newSession', $day->id)
            ->set('title', 'Tentative Slot')
            ->set('status', 'waiting_speaker')
            ->call('saveSession')
            ->assertHasNoErrors();

        $s = $event->agendaSessions()->where('title', 'Tentative Slot')->firstOrFail();
        $this->assertSame('waiting_speaker', $s->status);
        $this->assertFalse($s->isSettled());
    }

    public function test_public_programme_hides_crew_only_tracks(): void
    {
        [$event] = $this->ctx();
        $day = $event->agendaDays()->orderBy('sort')->first();
        $event->agendaSessions()->where('agenda_day_id', $day->id)->delete();

        $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'title' => 'Opening Plenary', 'type' => 'opening', 'status' => 'draft', 'starts_at' => '10:00', 'ends_at' => '11:00', 'track' => 'Plenary', 'sort' => 1]);
        $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'title' => 'Stage Build', 'type' => 'workshop', 'status' => 'draft', 'starts_at' => '06:00', 'ends_at' => '09:00', 'track' => 'Setup', 'sort' => 2]);
        $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'title' => 'Press Interviews', 'type' => 'networking', 'status' => 'draft', 'starts_at' => '11:00', 'ends_at' => '12:00', 'track' => 'Media', 'sort' => 3]);

        $sessions = $event->agendaSessions()->where('agenda_day_id', $day->id)->with('room', 'speakers')->get();
        $svc = new AgendaProgram;

        $titles = fn (array $p) => collect($p['blocks'])->flatMap(fn ($b) => collect($b['cards'])->pluck('session.title'))
            ->merge(collect($p['ambient'])->pluck('title'))->all();

        $internal = $titles($svc->forDay($sessions, 'internal'));
        $this->assertContains('Stage Build', $internal);
        $this->assertContains('Press Interviews', $internal);

        $public = $titles($svc->forDay($sessions, 'public'));
        $this->assertContains('Opening Plenary', $public);
        $this->assertNotContains('Stage Build', $public);
        $this->assertNotContains('Press Interviews', $public);
    }

    public function test_programme_pdf_exports_one_day_and_all_days(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->orderBy('sort')->first();

        $all = $this->actingAs($user)->get(route('events.agenda.program.pdf', $event));
        $all->assertOk();
        $this->assertSame('application/pdf', $all->headers->get('content-type'));

        $one = $this->actingAs($user)->get(route('events.agenda.program.pdf', [$event, 'day' => $day->id]));
        $one->assertOk();
        $this->assertSame('application/pdf', $one->headers->get('content-type'));
    }

    public function test_non_overlapping_sessions_are_not_flagged(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->orderBy('sort')->first();
        $room = $event->rooms()->first();

        // Wipe seeded sessions so only these two clean ones are evaluated.
        $event->agendaSessions()->delete();
        $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'room_id' => $room->id, 'title' => 'Morning', 'type' => 'panel', 'status' => 'draft', 'starts_at' => '09:00', 'ends_at' => '10:00', 'sort' => 1]);
        $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'room_id' => $room->id, 'title' => 'Afternoon', 'type' => 'panel', 'status' => 'draft', 'starts_at' => '10:00', 'ends_at' => '11:00', 'sort' => 2]);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->assertDontSee('scheduling conflict');
    }

    public function test_room_can_be_added_and_appears_for_the_agenda(): void
    {
        [$event, $user] = $this->ctx();
        $before = $event->rooms()->count();

        Livewire::actingAs($user)->test(VenueTab::class, ['event' => $event])
            ->call('newRoom')
            ->set('room_name', 'Innovation Lab')
            ->set('room_type', 'breakout')
            ->set('room_capacity', '80')
            ->call('saveRoom')
            ->assertHasNoErrors();

        $this->assertSame($before + 1, $event->rooms()->count());
        $room = $event->rooms()->where('name', 'Innovation Lab')->firstOrFail();
        $this->assertSame(80, $room->capacity);

        // Now selectable in the agenda room picker.
        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('newSession', $event->agendaDays()->first()->id)
            ->assertSee('Innovation Lab');
    }

    public function test_session_form_can_create_a_room_inline(): void
    {
        [$event, $user] = $this->ctx();
        $gala = Event::where('name', 'EY Annual Gala')->firstOrFail(); // starts with 0 rooms
        $day = $gala->agendaDays()->create(['date' => now(), 'label' => 'Day 1', 'sort' => 0]);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $gala])
            ->call('newSession', $day->id)
            ->set('title', 'Welcome Reception')
            ->set('newRoomName', 'Grand Ballroom') // "…or type a room" — created on save
            ->call('saveSession')
            ->assertHasNoErrors();

        $room = $gala->rooms()->where('name', 'Grand Ballroom')->firstOrFail();
        $this->assertSame($room->id, $gala->agendaSessions()->where('title', 'Welcome Reception')->value('room_id'));
    }

    public function test_assigning_venue_persists(): void
    {
        [$event, $user] = $this->ctx();
        $venue = Venue::where('name', 'Doha Exhibition Center')->firstOrFail();

        // The event's location/venue is set in Settings (not the Venue tab anymore).
        Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event])
            ->set('venue_id', $venue->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($venue->id, $event->fresh()->venue_id);
    }
}
