<?php

namespace Tests\Feature;

use App\Livewire\Hub\AgendaTab;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Agenda tab as a person actually works it.
 *
 * Three things the builder could not reach from the screen: the day actions
 * (confirm, duplicate, delete) had no door, the programme track could only be
 * set by CSV import despite deciding what a delegate sees, and an empty day
 * said so without offering the way out. docs/19 §3.2 B4.
 */
class AgendaWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Event,1:User} */
    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    public function test_the_day_actions_have_a_door_on_the_screen(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->assertSee('Confirm every session')
            ->assertSee('Duplicate this day')
            ->assertSee('Delete this day');
    }

    public function test_confirming_the_day_from_the_screen_signs_off_its_drafts(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->firstOrFail();
        $day->sessions()->update(['status' => 'draft']);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->set('selectedDayId', $day->id)
            ->call('confirmDay');

        $this->assertSame(0, $day->sessions()->where('status', 'draft')->count());
    }

    public function test_deleting_a_day_takes_its_sessions_and_leaves_the_rest(): void
    {
        [$event, $user] = $this->ctx();
        $days = $event->agendaDays()->orderBy('sort')->get();
        $doomed = $days->first();
        $survivor = $days->get(1);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('deleteDay', $doomed->id);

        $this->assertNull($doomed->fresh());
        $this->assertSame(0, $event->agendaSessions()->where('agenda_day_id', $doomed->id)->count());
        $this->assertNotNull($survivor->fresh(), 'only the day you asked for goes');
    }

    /**
     * The track is the field that decides what a delegate sees, and it was the
     * one field the form did not carry.
     */
    public function test_the_session_form_carries_the_programme_track(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->firstOrFail();

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('newSession', $day->id)
            ->assertSee('Programme track')
            ->assertSee('Main Stage')
            ->set('title', 'Registration desk opens')
            ->set('track', 'Registration')
            ->set('starts_at', '07:30')
            ->set('ends_at', '09:00')
            ->call('saveSession')
            ->assertHasNoErrors();

        $session = $event->agendaSessions()->where('title', 'Registration desk opens')->firstOrFail();
        $this->assertSame('Registration', $session->track);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('editSession', $session->id)
            ->assertSet('track', 'Registration', 'the track comes back when you reopen the session');
    }

    public function test_a_track_already_in_use_is_offered_again(): void
    {
        [$event, $user] = $this->ctx();
        $event->agendaSessions()->firstOrFail()->update(['track' => 'Innovation Lab']);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('newSession')
            ->assertSee('Innovation Lab');
    }

    public function test_an_empty_day_says_how_to_start_it(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->firstOrFail();
        $day->sessions()->delete();

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->set('selectedDayId', $day->id)
            ->assertSee('Nothing scheduled for this day')
            ->assertSee('Add the first session');
    }

    public function test_an_event_with_no_days_says_how_to_start_one(): void
    {
        [$event, $user] = $this->ctx();
        $event->agendaDays()->delete();

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->assertSee('No agenda days yet')
            ->assertSee('Add the first day');
    }

    /**
     * A day of back-of-house work is not an empty day, and the public
     * programme saying "nothing scheduled" would send someone looking for the
     * sessions they just entered.
     */
    public function test_a_day_of_crew_only_work_reads_as_empty_only_to_the_public(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->firstOrFail();
        $day->sessions()->delete();
        $day->sessions()->create([
            'event_id' => $event->id,
            'title' => 'Stage build',
            'type' => 'workshop',
            'track' => 'Setup',
            'status' => 'confirmed',
            'starts_at' => '06:00',
            'ends_at' => '11:00',
        ]);

        $component = Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->set('selectedDayId', $day->id)
            ->call('setView', 'program');

        $component->call('setAudience', 'internal')->assertSee('Stage build');

        $component->call('setAudience', 'public')
            ->assertSee('Nothing on the public programme for this day')
            ->assertSee('Show the internal programme');
    }
}
