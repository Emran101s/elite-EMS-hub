<?php

namespace Tests\Feature;

use App\Livewire\Hub\VenueTab;
use App\Livewire\RoomLayoutBuilder;
use App\Models\Event;
use App\Models\EventAgendaDay;
use App\Models\EventAgendaSession;
use App\Models\EventRoom;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A venue is hired for days, and its equipment does not run every one of them.
 *
 * The hire used to be one flat figure somebody multiplied in their head, so a
 * room held for five days and the same room held for one were indistinguishable
 * and nothing could be re-priced when the programme moved.
 */
class VenueDayPricingTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);
        $event = Event::has('rooms')->firstOrFail();

        return [$event, $event->rooms()->first(), User::where('email', 'emran.itan@elitebhub.com')->firstOrFail()];
    }

    /** Sessions in a room are the record of which days it is used. */
    private function bookOn(Event $event, EventRoom $room, int $days): void
    {
        // The seeded room already carries a programme; these tests are about
        // what a known number of days costs, so start from a known number.
        EventAgendaSession::where('room_id', $room->id)->delete();

        for ($i = 0; $i < $days; $i++) {
            $day = EventAgendaDay::create([
                'event_id' => $event->id,
                'date' => now()->addDays($i)->toDateString(),
                'label' => 'Day '.($i + 1),
                'sort' => $i,
            ]);

            EventAgendaSession::create([
                'event_id' => $event->id,
                'agenda_day_id' => $day->id,
                'room_id' => $room->id,
                'title' => 'Session '.($i + 1),
                'starts_at' => '09:00',
                'ends_at' => '17:00',
            ]);
        }
    }

    public function test_hire_is_the_day_rate_times_the_days_on_the_agenda(): void
    {
        [$event, $room] = $this->ctx();

        $room->update(['cost_cents' => 60000, 'days' => null, 'setup_days' => 0, 'requirements' => []]);
        $this->bookOn($event, $room, 5);
        $room->refresh();

        $this->assertSame(5, $room->daysOnTheAgenda());
        $this->assertSame(5, $room->chargedDays());
        $this->assertTrue($room->daysAreCounted());
        $this->assertSame(300000, $room->hireCents());
        $this->assertSame(300000, $room->totalCents());
    }

    public function test_setup_days_are_charged_on_top_and_an_override_wins(): void
    {
        [$event, $room] = $this->ctx();

        $room->update(['cost_cents' => 60000, 'days' => null, 'setup_days' => 1, 'requirements' => []]);
        $this->bookOn($event, $room, 5);
        $room->refresh();

        // Five on the programme plus one to build it.
        $this->assertSame(6, $room->chargedDays());
        $this->assertSame(360000, $room->hireCents());

        // A dark day held between two meetings is paid for but has no session,
        // so somebody has to be able to say so.
        $room->update(['days' => 7]);
        $room->refresh();

        $this->assertFalse($room->daysAreCounted());
        $this->assertSame(8, $room->chargedDays());
        $this->assertSame(480000, $room->hireCents());
    }

    public function test_a_room_with_no_sessions_still_costs_one_day(): void
    {
        [$event] = $this->ctx();

        // A room nobody has scheduled anything in yet — the seeded ones already
        // carry sessions, which is the case the other tests cover.
        $room = $event->rooms()->create([
            'name' => 'Green room', 'type' => 'green_room',
            'cost_cents' => 45000, 'days' => null, 'setup_days' => 0, 'requirements' => [],
        ]);

        $this->assertSame(0, $room->daysOnTheAgenda());
        $this->assertSame(1, $room->chargedDays());
        $this->assertSame(45000, $room->hireCents());
    }

    public function test_equipment_is_priced_per_unit_per_day(): void
    {
        [, $room] = $this->ctx();

        $room->update(['cost_cents' => 0, 'days' => 5, 'setup_days' => 0, 'requirements' => [
            ['id' => 'a', 'name' => 'Table microphone', 'cost_cents' => 600, 'qty' => 12, 'days' => 5],
            ['id' => 'b', 'name' => 'Interpretation booth', 'cost_cents' => 42000, 'qty' => 1, 'days' => 3],
        ]]);
        $room->refresh();

        $this->assertSame(36000, EventRoom::requirementCents($room->requirements[0]));
        $this->assertSame(126000, EventRoom::requirementCents($room->requirements[1]));
        $this->assertSame(162000, $room->requirementsTotalCents());
    }

    /**
     * Rows written before this existed carried a single figure and no counts.
     * Reading them as one unit for one day keeps every old total exactly as it
     * was — the alternative is a budget that changes when nobody touched it.
     */
    public function test_a_requirement_without_quantity_or_days_totals_what_it_always_did(): void
    {
        [, $room] = $this->ctx();

        $room->update(['cost_cents' => 0, 'days' => 4, 'setup_days' => 0, 'requirements' => [
            ['id' => 'old', 'name' => 'Staging', 'cost_cents' => 88000],
        ]]);
        $room->refresh();

        $this->assertSame(88000, $room->requirementsTotalCents());
        $this->assertSame(88000, $room->totalCents());
    }

    public function test_the_venue_form_saves_days_and_leaves_blank_meaning_counted(): void
    {
        [$event, $room, $user] = $this->ctx();

        Livewire::actingAs($user)->test(VenueTab::class, ['event' => $event])
            ->call('editRoom', $room->id)
            ->set('room_cost', '600')
            ->set('room_days', '4')
            ->set('room_setup_days', '2')
            ->call('saveRoom');

        $room->refresh();
        $this->assertSame(4, $room->days);
        $this->assertSame(2, $room->setup_days);
        $this->assertSame(6, $room->chargedDays());
        $this->assertSame(360000, $room->hireCents());

        // Cleared, the agenda answers again.
        Livewire::actingAs($user)->test(VenueTab::class, ['event' => $event])
            ->call('editRoom', $room->id)
            ->set('room_days', '')
            ->call('saveRoom');

        $room->refresh();
        $this->assertNull($room->days);
        $this->assertTrue($room->daysAreCounted());
    }

    /**
     * The day fields live inside a form that only renders when it is open, so
     * nothing else in the suite would have compiled that block.
     */
    public function test_the_room_form_renders_the_day_fields(): void
    {
        [$event, $room, $user] = $this->ctx();

        Livewire::actingAs($user)->test(VenueTab::class, ['event' => $event])
            ->call('editRoom', $room->id)
            ->assertOk()
            ->assertSee('Days held')
            ->assertSee('Setup &amp; teardown days', false)
            ->assertSee('Hire, per day', false)
            ->assertSee('Hire total');

        // And on a new room, where there is no record to read a day count from.
        Livewire::actingAs($user)->test(VenueTab::class, ['event' => $event])
            ->call('newRoom')->assertOk()->assertSee('Days held');
    }

    public function test_the_equipment_editor_records_quantity_and_run(): void
    {
        [$event, $room, $user] = $this->ctx();

        $room->update(['requirements' => [], 'days' => 5]);

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room->fresh()])
            ->set('reqName', 'Table microphone')
            ->set('reqCost', '6')
            ->set('reqQty', '12')
            ->set('reqDays', '5')
            ->call('addRequirement');

        $room->refresh();
        $this->assertSame(12, $room->requirements[0]['qty']);
        $this->assertSame(5, $room->requirements[0]['days']);
        $this->assertSame(36000, $room->requirementsTotalCents());
    }

    public function test_the_days_reach_the_budget(): void
    {
        [$event, $room] = $this->ctx();

        $room->update(['cost_cents' => 60000, 'days' => 5, 'setup_days' => 0, 'requirements' => []]);
        (new \App\Services\BudgetSync)->sync($event->fresh());

        $line = $event->budgetItems()->where('room_id', $room->id)->first();

        $this->assertNotNull($line, 'the venue should have a budget line');
        $this->assertSame(300000, $line->estimated_cents);
    }

    /**
     * The prep sheet printed blank on eleven of thirteen rooms: it read the old
     * `equipment` column while every room's equipment lived in `requirements`.
     */
    public function test_the_equipment_sheet_lists_what_the_venue_tab_holds(): void
    {
        [$event, $room, $user] = $this->ctx();

        $room->update(['equipment' => [], 'requirements' => [
            ['name' => 'Table microphone', 'cost_cents' => 600, 'qty' => 12, 'days' => 5],
            ['name' => 'Interpretation booth', 'cost_cents' => 42000, 'qty' => 1, 'days' => 3, 'status' => 'confirmed'],
        ]]);
        $room->refresh();

        $lines = $room->equipmentLines();
        $this->assertCount(2, $lines, 'the sheet reads the same list the tab edits');
        $this->assertSame(13, $room->equipmentCount());
        // 1 of 13 units confirmed.
        $this->assertSame(8, $room->equipmentReadiness());

        $res = $this->actingAs($user)->get(route('events.room-equipment.pdf', [$event, $room]));

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    /** A row is stamped with a status on write, so no reader has to guard for it. */
    public function test_a_requirement_is_given_a_status_and_an_id(): void
    {
        [, $room] = $this->ctx();

        $room->update(['requirements' => [['name' => 'Staging', 'cost_cents' => 1000]]]);
        $room->refresh();

        $this->assertNotEmpty($room->requirements[0]['id']);
        $this->assertSame('needed', $room->requirements[0]['status']);
    }

    public function test_a_line_walks_along_its_statuses_and_wraps(): void
    {
        [$event, $room, $user] = $this->ctx();

        $room->update(['requirements' => [['name' => 'Projector', 'cost_cents' => 5000]]]);
        $room->refresh();
        $id = $room->requirements[0]['id'];

        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room]);

        foreach (['requested', 'confirmed', 'onsite', 'needed'] as $expected) {
            $c->call('advanceRequirement', $id);
            $this->assertSame($expected, $room->fresh()->requirements[0]['status']);
        }
    }

    public function test_the_floor_plan_pdf_renders_the_plan_and_its_schedule(): void
    {
        [$event, $room, $user] = $this->ctx();

        $room->update([
            'width_m' => 20, 'length_m' => 20, 'cost_cents' => 60000, 'days' => 5, 'setup_days' => 1,
            'layout' => [['id' => 'x', 'type' => 'round', 'x' => 480, 'y' => 280, 'seats' => 8, 'rot' => 37]],
            'requirements' => [['id' => 'a', 'name' => 'Interpretation booth', 'cost_cents' => 42000, 'qty' => 1, 'days' => 3]],
        ]);

        $res = $this->actingAs($user)->get(route('events.room-layout.pdf', [$event, $room->fresh()]));

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }
}
