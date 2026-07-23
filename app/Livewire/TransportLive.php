<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventTransport;
use App\Models\EventTransportPassenger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Event-day operations, designed for a phone held in one hand in a hotel lobby.
 *
 * Now / Next / Later beats a timeline on a small screen: the question at 06:00
 * is never "show me the week", it's "what is happening and what is about to".
 * Every action here is one or two taps, because a board that is fiddly to update
 * stops being updated, and then the whole view is decoration.
 */
#[Layout('components.layouts.app', ['title' => 'Live Transport'])]
class TransportLive extends Component
{
    public Event $event;

    /** Which day is being run. Defaults to today, or the event's first day. */
    public string $day = '';

    /** Movement id whose issue box is open. */
    public ?int $issueFor = null;

    public string $issueNote = '';

    /** Movement id whose manifest is expanded. */
    public ?int $openId = null;

    public string $flash = '';

    /** Anything departing within this many minutes counts as "next". */
    private const NEXT_WINDOW_MINUTES = 120;

    public function mount(Event $event): void
    {
        $this->event = $event;

        $today = now()->toDateString();
        $days = $this->availableDays();

        $this->day = $days->contains($today)
            ? $today
            : ($days->first() ?? $today);
    }

    /** @return Collection<int,string> */
    private function availableDays(): Collection
    {
        return $this->event->transport()
            ->whereNotNull('depart_at')
            ->pluck('depart_at')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()->sort()->values();
    }

    public function setDay(string $day): void
    {
        $this->day = $day;
        $this->openId = null;
        $this->issueFor = null;
    }

    public function toggleOpen(int $id): void
    {
        $this->openId = $this->openId === $id ? null : $id;
    }

    // ── the two taps that matter ────────────────────────────────

    /** The car has set off. */
    public function start(int $id): void
    {
        Gate::authorize('write');
        $m = $this->event->transport()->findOrFail($id);
        $m->update(['status' => 'in_progress', 'started_at' => now(), 'issue_note' => null]);
        $this->flash = 'Car '.$m->refLabel().' is on the way.';
    }

    /** Delivered. Drops to the bottom of the board. */
    public function arrive(int $id): void
    {
        Gate::authorize('write');
        $m = $this->event->transport()->findOrFail($id);
        $m->update(['status' => 'completed', 'completed_at' => now()]);
        $this->flash = 'Car '.$m->refLabel().' completed.';
    }

    /** Back to confirmed — for the mis-tap, which happens on a phone. */
    public function undo(int $id): void
    {
        Gate::authorize('write');
        $m = $this->event->transport()->findOrFail($id);
        $m->update(['status' => 'confirmed', 'started_at' => null, 'completed_at' => null]);
        $this->flash = 'Car '.$m->refLabel().' put back to confirmed.';
    }

    /**
     * Push a pickup back. Relative minutes rather than a time picker — nobody
     * types a timestamp while standing at a barrier.
     */
    public function delay(int $id, int $minutes): void
    {
        Gate::authorize('write');
        $m = $this->event->transport()->findOrFail($id);

        $from = $m->effectiveDeparture() ?? now();
        $m->update(['delayed_to' => $from->copy()->addMinutes($minutes)]);

        $this->flash = 'Car '.$m->refLabel().' now leaves '.$m->fresh()->effectiveDeparture()->format('H:i').'.';
    }

    public function clearDelay(int $id): void
    {
        Gate::authorize('write');
        $this->event->transport()->whereKey($id)->update(['delayed_to' => null]);
    }

    public function openIssue(int $id): void
    {
        $this->issueFor = $this->issueFor === $id ? null : $id;
        $this->issueNote = $this->issueFor
            ? (string) $this->event->transport()->find($id)?->issue_note
            : '';
    }

    /** An issue always carries a note — "something is wrong" alone helps nobody. */
    public function flagIssue(int $id): void
    {
        Gate::authorize('write');

        if (trim($this->issueNote) === '') {
            $this->addError('issueNote', 'Say what is wrong — whoever picks this up needs to know.');

            return;
        }

        $m = $this->event->transport()->findOrFail($id);
        $m->update(['status' => 'issue', 'issue_note' => trim($this->issueNote)]);

        $this->issueFor = null;
        $this->issueNote = '';
        $this->flash = 'Issue flagged on car '.$m->refLabel().'.';
    }

    public function resolveIssue(int $id): void
    {
        Gate::authorize('write');
        $m = $this->event->transport()->findOrFail($id);
        $m->update(['status' => 'confirmed', 'issue_note' => null]);
        $this->flash = 'Car '.$m->refLabel().' cleared.';
    }

    /** Marked at the barrier when someone simply does not appear. */
    public function toggleNoShow(int $guestId): void
    {
        Gate::authorize('write');
        $g = $this->event->transferGuests()->findOrFail($guestId);
        $g->update(['no_show_at' => $g->no_show_at ? null : now()]);
        $this->flash = $g->no_show_at ? $g->name.' marked as a no-show.' : $g->name.' un-marked.';
    }

    /**
     * The board: everything for the chosen day, bucketed by what the operations
     * team needs to look at first.
     *
     * @return array<string,Collection<int,EventTransport>>
     */
    public function board(): array
    {
        $runs = $this->event->transport()
            ->with(['driver', 'vehicle', 'vehicleType', 'manifest'])
            ->whereDate('depart_at', $this->day)
            ->get()
            ->sortBy(fn (EventTransport $m) => $m->effectiveDeparture()?->timestamp ?? PHP_INT_MAX);

        $soon = now()->addMinutes(self::NEXT_WINDOW_MINUTES);

        return [
            // Anything wrong jumps the queue regardless of its time.
            'issues' => $runs->where('status', 'issue')->values(),
            'now' => $runs->where('status', 'in_progress')->values(),
            'next' => $runs->filter(fn (EventTransport $m) => ! $m->isSettled()
                && $m->status !== 'in_progress' && $m->status !== 'issue'
                && $m->effectiveDeparture()
                && $m->effectiveDeparture()->lte($soon))->values(),
            'later' => $runs->filter(fn (EventTransport $m) => ! $m->isSettled()
                && $m->status !== 'in_progress' && $m->status !== 'issue'
                && (! $m->effectiveDeparture() || $m->effectiveDeparture()->gt($soon)))->values(),
            'done' => $runs->filter(fn (EventTransport $m) => $m->isSettled())->values(),
        ];
    }

    public function render()
    {
        $board = $this->board();

        return view('livewire.transport-live', [
            'board' => $board,
            'days' => $this->availableDays(),
            'noShows' => $this->event->transferGuests()->whereNotNull('no_show_at')->count(),
            'unstaffed' => $this->event->transport()
                ->whereDate('depart_at', $this->day)
                ->whereNull('driver_id')
                ->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'categories' => EventTransportPassenger::CATEGORIES,
        ]);
    }
}
