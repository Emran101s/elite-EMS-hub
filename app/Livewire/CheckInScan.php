<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventAttendee;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * What a badge's QR opens.
 *
 * The QR carries a URL rather than a bare code so any phone camera reaches
 * this page without a special scanner app — on show day that is the difference
 * between a queue and a door that moves.
 *
 * Unauthenticated, like the registration page, and for the same reason: the
 * person on the door may be a volunteer with a borrowed phone. What protects
 * it is that the URL is unguessable — you need the event's token AND the
 * attendee's reference — and that the only thing it can do is mark somebody
 * present. It cannot read the list, edit anyone, or say who else is coming.
 */
#[Layout('components.layouts.guest', ['width' => 'max-w-md'])]
class CheckInScan extends Component
{
    public Event $event;

    public ?EventAttendee $attendee = null;

    /** found | already | cancelled | unknown | done */
    public string $state = 'unknown';

    public function mount(string $token, string $reference): void
    {
        $this->event = Event::where('registration_token', $token)->firstOrFail();
        $this->attendee = EventAttendee::findByReference($this->event->id, $reference);

        $this->state = match (true) {
            ! $this->attendee => 'unknown',
            $this->attendee->status === 'cancelled' => 'cancelled',
            $this->attendee->checked_in_at !== null => 'already',
            default => 'found',
        };
    }

    /**
     * Admit them.
     *
     * Deliberately a second tap rather than automatic on scan: a phone camera
     * picks up a badge lying on a table as readily as one round a neck, and a
     * door that admits people by accident is worse than one that asks.
     */
    public function admit(): void
    {
        if ($this->state !== 'found') {
            return;
        }

        $this->attendee->update([
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        $this->state = 'done';
    }

    /**
     * The sessions this badge can be admitted to right now.
     *
     * The QR is printed once and cannot carry a room, so the room is decided
     * here: whoever is on the door scans the badge and taps the session in
     * front of them. Today's bookings only — a badge scanned at a Tuesday
     * workshop should not offer Thursday's.
     */
    public function sessionsToday(): \Illuminate\Support\Collection
    {
        if (! $this->attendee) {
            return collect();
        }

        return $this->attendee->sessions()
            ->with('day')
            ->get()
            ->filter(fn ($s) => $s->day?->date?->isToday() ?? true)
            ->sortBy('starts_at')
            ->values();
    }

    /**
     * Mark them present in one session.
     *
     * Separate from admitting them to the event: somebody can be in the
     * building without being in this room, and a room's count is only worth
     * having if it means the people who were in it.
     */
    public function admitToSession(int $sessionId): void
    {
        if (! $this->attendee || $this->attendee->status === 'cancelled') {
            return;
        }

        $booking = $this->attendee->sessions()->whereKey($sessionId)->first();

        if (! $booking || $booking->pivot->checked_in_at) {
            return;
        }

        $this->attendee->sessions()->updateExistingPivot($sessionId, ['checked_in_at' => now()]);

        // Being in a room is being in the building; a door reached by a side
        // entrance should not leave somebody marked absent all day.
        if ($this->attendee->checked_in_at === null) {
            $this->attendee->update(['status' => 'checked_in', 'checked_in_at' => now()]);
            $this->state = 'done';
        }

        $this->attendee->load('sessions');
    }

    public function render()
    {
        return view('livewire.check-in-scan')->title('Check-in · '.$this->event->name);
    }
}
