<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventAttendee;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\Gate;

/**
 * The desk on the day.
 *
 * Scanning a badge works when somebody has a badge. The desk exists for the
 * rest of it: the person who left theirs in the hotel, the one whose name is
 * spelled differently to the list, the one who never registered. That is not
 * an edge case — it is most of the first hour.
 *
 * Deliberately not the attendee list with a filter on top. The list is for
 * planning and answers "who is coming"; this answers "is this person on the
 * list, and can they come in", which is one question asked a few hundred times
 * by somebody standing up.
 */
#[Layout('components.layouts.app', ['title' => 'Arrivals desk', 'hideTitleRow' => true])]
class ArrivalsDesk extends Component
{
    public Event $event;

    public string $q = '';

    /** The last person admitted, so the desk can see it worked. */
    public ?int $justAdmitted = null;

    public function mount(Event $event): void
    {
        $this->event = $event;
    }

    /**
     * Who the desk is looking at.
     *
     * Nothing until somebody types: a list of six hundred names is not an
     * answer to "is Layla on the list", and rendering it costs the search box
     * its speed.
     */
    public function matches(): Collection
    {
        $term = trim($this->q);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        $like = '%'.mb_strtolower($term).'%';

        return $this->event->attendees()
            ->where(fn ($q) => $q
                ->whereRaw('lower(name) like ?', [$like])
                ->orWhereRaw('lower(coalesce(email, "")) like ?', [$like])
                ->orWhereRaw('lower(coalesce(organization, "")) like ?', [$like]))
            ->orderBy('name')
            ->limit(25)
            ->get();
    }

    public function admit(int $id): void
    {
        Gate::authorize('write');
        $attendee = $this->event->attendees()->findOrFail($id);

        if ($attendee->status === 'cancelled' || $attendee->checked_in_at !== null) {
            return;
        }

        $attendee->update(['status' => 'checked_in', 'checked_in_at' => now()]);

        $this->justAdmitted = $attendee->id;
    }

    /**
     * Admitted by mistake.
     *
     * A desk that cannot undo is a desk that argues. Kept deliberately plain —
     * it puts them back where they were, and nothing else.
     */
    public function undo(int $id): void
    {
        Gate::authorize('write');
        $attendee = $this->event->attendees()->findOrFail($id);

        $attendee->update(['status' => 'registered', 'checked_in_at' => null]);

        if ($this->justAdmitted === $id) {
            $this->justAdmitted = null;
        }
    }

    public function render()
    {
        $counts = $this->event->attendees()
            ->selectRaw("count(*) as all_of_them,
                sum(case when checked_in_at is not null then 1 else 0 end) as arrived,
                sum(case when status = 'cancelled' then 1 else 0 end) as cancelled")
            ->first();

        $expected = (int) $counts->all_of_them - (int) $counts->cancelled;
        $arrived = (int) $counts->arrived;

        return view('livewire.arrivals-desk', [
            'matches' => $this->matches(),
            'expected' => $expected,
            'arrived' => $arrived,
            'toCome' => max(0, $expected - $arrived),
            'pct' => $expected > 0 ? (int) round($arrived / $expected * 100) : 0,
            // The last few through the door, so the desk can see itself working.
            'recent' => $this->event->attendees()
                ->whereNotNull('checked_in_at')
                ->latest('checked_in_at')->limit(8)->get(),
        ])->title('Arrivals · '.$this->event->name);
    }
}
