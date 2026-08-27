<?php

namespace App\Livewire\Hub;

use App\Livewire\Concerns\BulkSelectable;
use App\Livewire\Concerns\RoutesCostsToBudget;
use App\Models\Event;
use App\Models\Requirement;
use App\Models\Venue;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

class VenueTab extends Component
{
    use BulkSelectable, RoutesCostsToBudget;

    public Event $event;

    /**
     * Assign (or clear) the event's venue right here, instead of sending the
     * user off to the Settings form to find one field. Once set, the panel
     * links straight to that venue's own Venue Studio — the two-way link that
     * used to be missing between a booking and the place it happens.
     */
    public function setVenue($venueId): void
    {
        Gate::authorize('write');
        $venueId = $venueId === '' || $venueId === null ? null : (int) $venueId;
        validator(['venue_id' => $venueId], ['venue_id' => ['nullable', 'exists:venues,id']])->validate();
        $this->event->update(['venue_id' => $venueId]);
        // Drop the cached relation so the re-render shows the new venue (and
        // its own spaces, which feed the room form's "Venue's own space" list).
        $this->event->refresh();
    }

    /** Delete the selected rooms. */
    public function deleteSelected(): void
    {
        Gate::authorize('write');
        $this->event->rooms()->whereIn('id', $this->selectedIds())->delete();
        $this->clearSelection();
    }

    // Room form
    public bool $showRoomForm = false;

    public ?int $editingRoomId = null;

    public string $room_name = '';

    public string $room_type = 'breakout';

    public string $room_capacity = '';

    public string $room_cost = '';

    /** Blank means "count them off the agenda" — see EventRoom::chargedDays(). */
    public string $room_days = '';

    public string $room_setup_days = '';

    /** Optional — which of the venue's own permanent spaces this booking is. */
    public string $room_venue_space_id = '';

    // Event-wide requirements (not tied to a venue). Per-venue requirements now
    // live inside each venue's detail (RoomLayoutBuilder → Requirements tab).
    public string $evReqName = '';

    public string $evReqCost = '';

    public function newRoom(): void
    {
        $this->reset(['editingRoomId', 'room_name', 'room_capacity', 'room_cost', 'room_days', 'room_setup_days', 'room_venue_space_id']);
        $this->room_type = 'Breakout';
        $this->showRoomForm = true;
    }

    public function editRoom(int $roomId): void
    {
        $room = $this->event->rooms()->findOrFail($roomId);
        $this->editingRoomId = $room->id;
        $this->room_name = $room->name;
        // Prettified for editing; normalised back to a slug on save.
        $this->room_type = str($room->type)->replace('_', ' ')->title()->toString();
        $this->room_capacity = (string) ($room->capacity ?? '');
        $this->room_cost = $room->cost_cents ? (string) ($room->cost_cents / 100) : '';
        $this->room_days = $room->days !== null ? (string) $room->days : '';
        $this->room_setup_days = $room->setup_days ? (string) $room->setup_days : '';
        $this->room_venue_space_id = (string) ($room->venue_space_id ?? '');
        $this->showRoomForm = true;
    }

    public function saveRoom()
    {
        Gate::authorize('write');
        $this->validate([
            'room_name' => ['required', 'string', 'max:120'],
            'room_type' => ['required', 'string', 'max:40'],   // built-in or a custom label
            'room_capacity' => ['nullable', 'integer', 'min:0'],
            'room_cost' => ['nullable', 'numeric', 'min:0'],
            'room_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'room_setup_days' => ['nullable', 'integer', 'min:0', 'max:60'],
            'room_venue_space_id' => ['nullable', 'integer', 'exists:venue_spaces,id'],
        ]);

        $data = [
            'name' => $this->room_name,
            'type' => str($this->room_type)->snake()->toString() ?: 'room',
            'capacity' => $this->room_capacity !== '' ? (int) $this->room_capacity : null,
            'cost_cents' => $this->room_cost !== '' ? (int) round((float) $this->room_cost * 100) : 0,
            // Left blank on purpose: null means the agenda's count, which stays
            // right when the programme moves. A number is somebody overruling it.
            'days' => $this->room_days !== '' ? (int) $this->room_days : null,
            'setup_days' => $this->room_setup_days !== '' ? (int) $this->room_setup_days : 0,
            // Optional — "this booking is the venue's own Main Hall." Never
            // syncs this room's own layout/price from the space; it's a
            // pointer, not a template.
            'venue_space_id' => $this->room_venue_space_id !== '' ? (int) $this->room_venue_space_id : null,
        ];

        if ($this->editingRoomId) {
            $this->event->rooms()->whereKey($this->editingRoomId)->firstOrFail()->update($data);
        } else {
            $this->event->rooms()->create($data);
        }

        session()->flash('status', $this->editingRoomId ? 'Room updated.' : "Room “{$this->room_name}” added — it's now available in the Agenda.");

        return $this->redirectTab();
    }

    public function deleteRoom(int $roomId)
    {
        Gate::authorize('write');
        // Sessions in this room fall back to "no room" (nullOnDelete on the FK).
        $this->event->rooms()->whereKey($roomId)->firstOrFail()->delete();

        return $this->redirectTab();
    }

    // ── Event-wide requirements (feed the budget's "Event Requirements") ──
    public function addEventRequirement(): void
    {
        Gate::authorize('write');
        $this->validate([
            'evReqName' => ['required', 'string', 'max:120'],
            'evReqCost' => ['nullable', 'numeric', 'min:0'],
        ]);
        $reqs = $this->event->event_requirements ?? [];
        $reqs[] = [
            'id' => Str::random(8),
            'name' => trim($this->evReqName),
            'cost_cents' => $this->evReqCost !== '' ? (int) round((float) $this->evReqCost * 100) : 0,
        ];
        $this->event->update(['event_requirements' => $reqs]);
        $this->reset(['evReqName', 'evReqCost']);
    }

    public function removeEventRequirement(string $reqId): void
    {
        Gate::authorize('write');
        $this->event->update([
            'event_requirements' => collect($this->event->event_requirements ?? [])->reject(fn ($r) => ($r['id'] ?? null) === $reqId)->values()->all(),
        ]);
    }

    /** Fill the event-wide add form from a catalog requirement. */
    public function pickEvReq($catalogId): void
    {
        if ($r = Requirement::find($catalogId)) {
            $this->evReqName = $r->name;
            $this->evReqCost = $r->unit_price_cents ? (string) ($r->unit_price_cents / 100) : '';
        }
    }

    private function redirectTab()
    {
        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'venue']);
    }

    public function budgetModule(): string
    {
        return 'venue';
    }

    public function render()
    {
        return view('livewire.hub.venue-tab', [
            'rooms' => $this->event->rooms()->withCount('sessions')->orderBy('name')->get(),
            'catalog' => Requirement::orderBy('name')->get(),
            'venueSpaces' => $this->event->venue?->spaces ?? collect(),
            'venues' => Venue::orderBy('name')->get(['id', 'name', 'city']),
        ]);
    }
}
