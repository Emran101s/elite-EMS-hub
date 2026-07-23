<?php

namespace Tests\Feature;

use App\Livewire\Hub\TransportationTab;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Attendees → transport: registered attendees can be pulled into the transfer
 * pool without retyping, idempotently, with registration staying the source
 * of truth via the attendee_id link.
 */
class TransportAttendeeLinkTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::create([
            'name' => 'Attendee Link Summit', 'type' => 'summit', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-07-27', 'ends_at' => '2026-07-29',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    public function test_pull_creates_pool_guests_from_attendees_with_fields_mapped(): void
    {
        $event = $this->event();
        $event->attendees()->create([
            'name' => 'Rania Khoury', 'email' => 'rania@example.com', 'phone' => '+962 79 111 2233',
            'organization' => 'Arab League', 'job_title' => 'Protocol Officer', 'status' => 'confirmed', 'vip' => true,
        ]);
        $event->attendees()->create(['name' => 'Omar Zayed', 'status' => 'registered']);
        $event->attendees()->create(['name' => 'Ghada Nasser', 'status' => 'cancelled']);

        Livewire::actingAs($this->admin())->test(TransportationTab::class, ['event' => $event])
            ->call('pullAttendees');

        $guests = $event->transferGuests()->get();
        $this->assertCount(2, $guests); // the cancelled registration stays out

        $rania = $guests->firstWhere('name', 'Rania Khoury');
        $this->assertSame('vip', $rania->category);
        $this->assertSame('arrival', $rania->direction);
        $this->assertSame('+962 79 111 2233', $rania->phone);
        $this->assertSame('Arab League — Protocol Officer', $rania->notes);
        $this->assertNotNull($rania->attendee_id);
        $this->assertSame('delegate', $guests->firstWhere('name', 'Omar Zayed')->category);
    }

    public function test_pulling_again_only_brings_new_attendees(): void
    {
        $event = $this->event();
        $event->attendees()->create(['name' => 'First Guest', 'status' => 'confirmed']);

        $tab = Livewire::actingAs($this->admin())->test(TransportationTab::class, ['event' => $event]);
        $tab->call('pullAttendees');
        $this->assertSame(1, $event->transferGuests()->count());

        // Nothing new → nothing created, and the message says so.
        $tab->call('pullAttendees');
        $this->assertSame(1, $event->transferGuests()->count());

        // A new registration arrives; only that one is pulled.
        $event->attendees()->create(['name' => 'Late Registrant', 'status' => 'registered']);
        $tab->call('pullAttendees');
        $this->assertSame(2, $event->transferGuests()->count());
        $this->assertSame(1, $event->transferGuests()->where('name', 'First Guest')->count());
    }

    public function test_deleting_the_attendee_keeps_the_guest_but_clears_the_link(): void
    {
        $event = $this->event();
        $attendee = $event->attendees()->create(['name' => 'Departing Soon', 'status' => 'confirmed']);

        Livewire::actingAs($this->admin())->test(TransportationTab::class, ['event' => $event])
            ->call('pullAttendees');

        $attendee->delete();

        $guest = $event->transferGuests()->first();
        $this->assertNotNull($guest); // transfer plans survive registration changes
        $this->assertNull($guest->attendee_id);
    }
}
