<?php

namespace Tests\Feature;

use App\Livewire\Hub\AttendeesTab;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();
        $event->attendees()->delete();

        return [$event, User::where('email', 'emran.itan@elitebhub.com')->firstOrFail()];
    }

    public function test_the_door_queue_puts_pending_arrivals_first(): void
    {
        [$event, $user] = $this->ctx();
        $event->attendees()->create(['name' => 'Aisha Arrived', 'status' => 'checked_in', 'checked_in_at' => now()]);
        $event->attendees()->create(['name' => 'Waleed Waiting', 'status' => 'confirmed']);
        $event->attendees()->create(['name' => 'Zane Cancelled', 'status' => 'cancelled']);

        $doorList = Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->call('toggleCheckinMode')
            ->viewData('doorList');

        // Pending first, arrivals sink, cancellations never reach the door.
        $this->assertSame(['Waleed Waiting', 'Aisha Arrived'], $doorList->pluck('name')->all());
    }

    public function test_checking_in_stamps_the_arrival_time(): void
    {
        [$event, $user] = $this->ctx();
        $a = $event->attendees()->create(['name' => 'Lina Guest', 'status' => 'confirmed']);

        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->call('toggleCheckIn', $a->id);

        $a->refresh();
        $this->assertSame('checked_in', $a->status);
        $this->assertNotNull($a->checked_in_at);

        // Undo (wrong person tapped) clears the stamp.
        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->call('toggleCheckIn', $a->id);
        $this->assertNull($a->fresh()->checked_in_at);
    }

    public function test_a_walk_in_is_registered_and_admitted_in_one_step(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->call('toggleCheckinMode')
            ->set('walkinName', 'Surprise Guest')
            ->set('walkinOrg', 'Local Press')
            ->call('walkIn')
            ->assertHasNoErrors()
            ->assertSet('walkinName', '');   // form clears for the next person

        $a = $event->attendees()->where('name', 'Surprise Guest')->firstOrFail();
        $this->assertSame('checked_in', $a->status);
        $this->assertSame('Walk-in', $a->ticket_type);
        $this->assertNotNull($a->checked_in_at);

        // A nameless walk-in is refused.
        Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->call('walkIn')
            ->assertHasErrors(['walkinName']);
    }

    public function test_the_arrival_counter_tracks_the_last_hour(): void
    {
        [$event, $user] = $this->ctx();
        $event->attendees()->create(['name' => 'Early Bird', 'status' => 'checked_in', 'checked_in_at' => now()->subHours(3)]);
        $event->attendees()->create(['name' => 'Just Now', 'status' => 'checked_in', 'checked_in_at' => now()->subMinutes(5)]);

        $lastHour = Livewire::actingAs($user)->test(AttendeesTab::class, ['event' => $event])
            ->viewData('lastHour');

        $this->assertSame(1, $lastHour);
    }
}
