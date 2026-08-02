<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventAccommodation;
use App\Models\EventRoomBlock;
use App\Models\Supplier;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Accommodation works in two moves:
 *   1. Block  — the deal with the hotel: "50 rooms at this rate, these dates".
 *   2. Rooming list — names filled into those rooms over time, until it's full.
 *
 * The rate lives on the block and is internal. The rooming list you send the
 * hotel carries no money at all (see RoomingListPdfController).
 */
class AccommodationTab extends Component
{
    use WithFileUploads;

    public Event $event;

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $expandedId = null;

    // ── Rooming-list import (Excel / CSV into one block) ──
    public $importFile = null;

    public ?int $importBlockId = null;

    public string $importMsg = '';

    // ── Block form ──────────────────────────────────────────────
    #[Validate('nullable|string|max:160')]
    public string $hotel = '';

    /**
     * The hotel from the Venues directory, when it is one of yours.
     *
     * $hotel stays alongside it as the name this block was made with, so a
     * rooming list printed last month keeps reading the same.
     */
    #[Validate('nullable|integer|exists:venues,id')]
    public ?int $venue_id = null;

    #[Validate('nullable|integer|exists:suppliers,id')]
    public ?int $supplier_id = null;

    #[Validate('nullable|string|max:80')]
    public string $room_type = '';

    #[Validate('nullable|string|max:20')]
    public string $occupancy = '';

    #[Validate('required|integer|min:1|max:2000')]
    public int $rooms_count = 10;

    #[Validate('nullable|numeric|min:0')]
    public string $rate = '';

    #[Validate('nullable|date')]
    public string $check_in = '';

    #[Validate('nullable|date|after_or_equal:check_in')]
    public string $check_out = '';

    #[Validate('nullable|date')]
    public string $cutoff_on = '';

    #[Validate('required|in:held,booked,confirmed,cancelled')]
    public string $status = 'held';

    #[Validate('nullable|string|max:60')]
    public string $confirmation_number = '';

    #[Validate('nullable|string|max:400')]
    public string $notes = '';

    // ── Rooming-list quick add ──────────────────────────────────
    /** @var array<int,string> keyed by block id */
    public array $newGuest = [];

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function newBlock(): void
    {
        $this->reset(['editingId', 'hotel', 'venue_id', 'supplier_id', 'room_type', 'occupancy', 'rate',
            'check_in', 'check_out', 'cutoff_on', 'confirmation_number', 'notes']);
        $this->rooms_count = 10;
        $this->status = 'held';
        // Default the stay to the event's own dates — the common case.
        $this->check_in = $this->event->starts_at?->format('Y-m-d') ?? '';
        $this->check_out = $this->event->ends_at?->format('Y-m-d') ?? '';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $b = $this->event->roomBlocks()->findOrFail($id);
        $this->editingId = $b->id;
        $this->hotel = $b->hotel;
        $this->venue_id = $b->venue_id;
        $this->supplier_id = $b->supplier_id;
        $this->room_type = $b->room_type ?? '';
        $this->occupancy = $b->occupancy ?? '';
        $this->rooms_count = max(1, $b->rooms_count);
        $this->rate = $b->rate_cents ? (string) ($b->rate_cents / 100) : '';
        $this->check_in = $b->check_in?->format('Y-m-d') ?? '';
        $this->check_out = $b->check_out?->format('Y-m-d') ?? '';
        $this->cutoff_on = $b->cutoff_on?->format('Y-m-d') ?? '';
        $this->status = $b->status;
        $this->confirmation_number = $b->confirmation_number ?? '';
        $this->notes = $b->notes ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('write');
        $this->validate();

        // Picking a hotel from the directory names the block; typing one still
        // works for a hotel that is not in it yet.
        $venue = $this->venue_id ? Venue::find($this->venue_id) : null;

        // A block has to say where it is, one way or the other. Checked here
        // rather than with required_without, which counts a present-but-null
        // venue_id as present and would let a nameless block through.
        if (! $venue && trim($this->hotel) === '') {
            $this->addError('hotel', 'Pick a hotel from the directory, or type its name.');

            return;
        }

        // Typing the name of a hotel you already have is the same as picking
        // it. People type rather than pick, and the block that results looks
        // identical while being linked to nothing — so what this hotel costs
        // across the book quietly stops counting it.
        if (! $venue && trim($this->hotel) !== '') {
            $venue = Venue::whereRaw('lower(name) = ?', [mb_strtolower(trim($this->hotel))])->first();
        }

        $data = [
            'hotel' => $venue?->name ?: $this->hotel,
            'venue_id' => $venue?->id,
            'supplier_id' => $this->supplier_id,
            'room_type' => $this->room_type ?: null,
            'occupancy' => $this->occupancy ?: null,
            'rooms_count' => max(1, $this->rooms_count),
            'rate_cents' => (int) round((float) ($this->rate ?: 0) * 100),
            'check_in' => $this->check_in ?: null,
            'check_out' => $this->check_out ?: null,
            'cutoff_on' => $this->cutoff_on ?: null,
            'status' => $this->status,
            'confirmation_number' => $this->confirmation_number ?: null,
            'notes' => $this->notes ?: null,
        ];

        if ($this->editingId) {
            $this->event->roomBlocks()->findOrFail($this->editingId)->update($data);
        } else {
            $data['position'] = (int) $this->event->roomBlocks()->max('position') + 1;
            $block = $this->event->roomBlocks()->create($data);
            $this->expandedId = $block->id;   // drop straight into its rooming list
        }

        $this->showForm = false;
        session()->flash('status', 'Room block saved.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('write');
        // Rooming-list rows cascade with the block.
        $this->event->roomBlocks()->whereKey($id)->delete();
        if ($this->expandedId === $id) {
            $this->expandedId = null;
        }
    }

    // ── Rooming list ────────────────────────────────────────────

    /** Names the next free room in the block. */
    public function addRoom(int $blockId): void
    {
        Gate::authorize('write');
        $block = $this->event->roomBlocks()->findOrFail($blockId);
        $name = trim($this->newGuest[$blockId] ?? '');

        if ($name === '') {
            return;
        }

        if ($block->rooms()->count() >= $block->rooms_count) {
            $this->addError('newGuest.'.$blockId, 'This block is full — raise the room count to add more.');

            return;
        }

        // The attendee list is the source of names. An exact match links the two
        // records; anything else becomes a new attendee, so the guest exists in
        // one place and can be edited from either side.
        $attendee = $this->event->attendees()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        if (! $attendee) {
            $attendee = $this->event->attendees()->create([
                'name' => mb_substr($name, 0, 160),
                'ticket_type' => 'Delegate',
                'status' => 'registered',
            ]);
        }

        $this->event->accommodations()->create([
            'block_id' => $block->id,
            'attendee_id' => $attendee->id,
            'hotel' => $block->hotel,
            'guest' => $attendee->name,
            'guest_email' => $attendee->email,
            'guest_phone' => $attendee->phone,
            'room_type' => $block->room_type,
            'occupancy' => $block->occupancy,
            'rooms' => 1,
            'check_in' => $block->check_in,
            'check_out' => $block->check_out,
            'rate_cents' => $block->rate_cents,
            'status' => $block->status === 'cancelled' ? 'cancelled' : 'booked',
            'position' => (int) $block->rooms()->max('position') + 1,
        ]);

        $this->newGuest[$blockId] = '';
    }

    /** Inline field edits on a rooming-list row autosave as you leave the field. */
    public function updateRoom(int $id, string $field, string $value): void
    {
        Gate::authorize('write');

        // Flight details deliberately absent — those belong to Transportation,
        // where a movement owns the flight it meets.
        if (! in_array($field, ['guest', 'guest_email', 'guest_phone', 'sharing_with',
            'room_type', 'occupancy', 'check_in', 'check_out',
            'arrival_time', 'departure_time'], true)) {
            return;
        }

        // Dates come off a date input; an unparseable value clears rather than crashes.
        if (in_array($field, ['check_in', 'check_out'], true)) {
            $value = $value !== '' ? (Carbon::hasFormat($value, 'Y-m-d') ? $value : '') : '';
        }

        if (in_array($field, ['arrival_time', 'departure_time'], true)) {
            $value = preg_match('/^\d{2}:\d{2}$/', $value) ? $value : '';
        }

        $room = $this->event->accommodations()->findOrFail($id);
        $room->update([$field => $value ?: null]);

        // Name and contact details belong to the attendee — keep the two in step
        // so editing here or on the Attendees tab reaches the same record.
        if ($room->attendee && in_array($field, ['guest', 'guest_email', 'guest_phone'], true)) {
            $room->attendee->update([
                ['guest' => 'name', 'guest_email' => 'email', 'guest_phone' => 'phone'][$field] => $value ?: null,
            ]);
        }
    }

    public function deleteRoom(int $id): void
    {
        Gate::authorize('write');
        $this->event->accommodations()->whereKey($id)->delete();
    }

    /**
     * Turns a pre-block booking into a real block. Group bookings made before
     * blocks existed already carry the shape — "70 rooms at the St Regis for
     * World Assembly delegates" — so the guest label becomes the block note
     * and the room count carries over ready to be named.
     */
    public function convertToBlock(int $id): void
    {
        Gate::authorize('write');
        $a = $this->event->accommodations()->whereNull('block_id')->findOrFail($id);

        $block = $this->event->roomBlocks()->create([
            'hotel' => $a->hotel,
            'room_type' => $a->room_type,
            'rooms_count' => max(1, $a->rooms),
            'rate_cents' => $a->rate_cents,
            'check_in' => $a->check_in,
            'check_out' => $a->check_out,
            'status' => $a->status === 'cancelled' ? 'cancelled' : $a->status,
            'confirmation_number' => $a->confirmation_number,
            'notes' => trim(collect([$a->guest, $a->notes])->filter()->implode(' · ')) ?: null,
            'position' => (int) $this->event->roomBlocks()->max('position') + 1,
        ]);

        $a->delete();
        $this->expandedId = $block->id;
        session()->flash('status', 'Converted to a room block — ready to name guests.');
    }

    // ── Import an Excel / CSV rooming list into one block ───────

    /** Column headers we understand, tolerant of spacing/case/synonyms. */
    private function fieldForHeader(string $header): ?string
    {
        $n = preg_replace('/[^a-z0-9]/', '', strtolower($header));

        return match (true) {
            in_array($n, ['name', 'guest', 'guestname', 'fullname', 'delegate', 'attendee'], true) => 'guest',
            in_array($n, ['email', 'guestemail', 'mail', 'emailaddress', 'e'], true) => 'guest_email',
            in_array($n, ['phone', 'mobile', 'guestphone', 'tel', 'telephone', 'contact', 'phonenumber'], true) => 'guest_phone',
            in_array($n, ['roomtype', 'room', 'category', 'grade', 'roomcategory'], true) => 'room_type',
            in_array($n, ['occupancy', 'occ', 'beds', 'bedtype'], true) => 'occupancy',
            in_array($n, ['sharingwith', 'sharewith', 'roommate', 'shareswith', 'sharedwith', 'sharing'], true) => 'sharing_with',
            in_array($n, ['checkin', 'checkindate', 'arrivaldate', 'arrival', 'arrive', 'datein', 'from'], true) => 'check_in',
            in_array($n, ['checkout', 'checkoutdate', 'departuredate', 'departure', 'depart', 'dateout', 'to'], true) => 'check_out',
            in_array($n, ['arrivaltime', 'arrivetime', 'timein', 'eta'], true) => 'arrival_time',
            in_array($n, ['departuretime', 'departtime', 'timeout', 'etd'], true) => 'departure_time',
            in_array($n, ['confirmation', 'confirmationnumber', 'confno', 'conf', 'booking', 'bookingref', 'bookingreference', 'reference', 'ref'], true) => 'confirmation_number',
            in_array($n, ['notes', 'note', 'remarks', 'remark', 'comment', 'comments'], true) => 'notes',
            default => null,
        };
    }

    private function parseSheetDate($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        try {
            return is_numeric($v)
                ? Date::excelToDateTimeObject((float) $v)->format('Y-m-d')
                : Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseSheetTime($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        try {
            return is_numeric($v)
                ? Date::excelToDateTimeObject((float) $v)->format('H:i')
                : Carbon::parse($v)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveOccupancy($v): ?string
    {
        $n = strtolower(trim((string) $v));
        foreach (EventAccommodation::OCCUPANCIES as $key => $label) {
            if ($n === $key || $n === strtolower($label)) {
                return $key;
            }
        }

        return null;
    }

    public function openImport(int $blockId): void
    {
        $this->reset(['importFile', 'importMsg']);
        $this->resetErrorBag('importFile');
        $this->importBlockId = $blockId;
        $this->expandedId = $blockId;
    }

    public function closeImport(): void
    {
        $this->reset(['importFile', 'importBlockId', 'importMsg']);
    }

    public function importRooms(): void
    {
        Gate::authorize('write');
        $this->validate(['importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']]);

        $block = $this->event->roomBlocks()->findOrFail($this->importBlockId);

        try {
            $rows = IOFactory::load($this->importFile->getRealPath())
                ->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable) {
            $this->addError('importFile', 'Could not read that file. Save it as .xlsx or .csv and try again.');

            return;
        }

        // First non-empty row is the header.
        $headerIndex = null;
        foreach ($rows as $i => $row) {
            if (collect($row)->contains(fn ($c) => trim((string) $c) !== '')) {
                $headerIndex = $i;
                break;
            }
        }
        if ($headerIndex === null) {
            $this->addError('importFile', 'The sheet looks empty.');

            return;
        }

        $map = [];
        foreach ($rows[$headerIndex] as $col => $h) {
            if ($field = $this->fieldForHeader((string) $h)) {
                $map[$col] = $field;
            }
        }
        if (! in_array('guest', $map, true)) {
            $this->addError('importFile', 'No “Name” column found. Add a column headed Name (or Guest) and re-upload.');

            return;
        }

        $imported = 0;
        $position = (int) $block->rooms()->max('position');

        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $d = [];
            foreach ($map as $col => $field) {
                $d[$field] = trim((string) ($row[$col] ?? ''));
            }
            $name = $d['guest'] ?? '';
            if ($name === '') {
                continue;
            }

            // The attendee list is the source of names — match or create, so the
            // guest exists once and edits from either side stay in step.
            $attendee = $this->event->attendees()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first()
                ?? $this->event->attendees()->create([
                    'name' => mb_substr($name, 0, 160),
                    'email' => ($d['guest_email'] ?? '') ?: null,
                    'phone' => ($d['guest_phone'] ?? '') ?: null,
                    'ticket_type' => 'Delegate',
                    'status' => 'registered',
                ]);

            $this->event->accommodations()->create([
                'block_id' => $block->id,
                'attendee_id' => $attendee->id,
                'hotel' => $block->hotel,
                'guest' => mb_substr($name, 0, 160),
                'guest_email' => ($d['guest_email'] ?? '') ?: $attendee->email,
                'guest_phone' => ($d['guest_phone'] ?? '') ?: $attendee->phone,
                'sharing_with' => ($d['sharing_with'] ?? '') ?: null,
                'room_type' => ($d['room_type'] ?? '') ?: $block->room_type,
                'occupancy' => $this->resolveOccupancy($d['occupancy'] ?? '') ?? $block->occupancy,
                'rooms' => 1,
                'check_in' => $this->parseSheetDate($d['check_in'] ?? '') ?? $block->check_in?->format('Y-m-d'),
                'check_out' => $this->parseSheetDate($d['check_out'] ?? '') ?? $block->check_out?->format('Y-m-d'),
                'arrival_time' => $this->parseSheetTime($d['arrival_time'] ?? ''),
                'departure_time' => $this->parseSheetTime($d['departure_time'] ?? ''),
                'confirmation_number' => ($d['confirmation_number'] ?? '') ?: null,
                'notes' => ($d['notes'] ?? '') ?: null,
                'rate_cents' => $block->rate_cents,
                'status' => $block->status === 'cancelled' ? 'cancelled' : 'booked',
                'position' => ++$position,
            ]);

            if (++$imported >= 2000) {
                break;
            }
        }

        // Grow the block so every imported guest has a held room.
        $named = $block->rooms()->count();
        if ($named > $block->rooms_count) {
            $block->update(['rooms_count' => $named]);
        }

        $this->reset(['importFile', 'importBlockId']);
        $this->expandedId = $block->id;
        $this->importMsg = $imported === 0
            ? 'No rows with a name were found in that file.'
            : $imported.' '.Str::plural('guest', $imported).' imported into '.$block->hotel.'.';
    }

    public function render()
    {
        $blocks = $this->event->roomBlocks()->with(['rooms', 'supplier'])->get();

        // Rows that predate blocks (or were added outside one) still deserve a home.
        $loose = $this->event->accommodations()->whereNull('block_id')->orderBy('check_in')->get();

        return view('livewire.hub.accommodation-tab', [
            'blocks' => $blocks,
            'loose' => $loose,
            'hotels' => Supplier::orderBy('name')->get(['id', 'name']),
            // The directory, hotels first — this is what makes a hotel a thing
            // you pick rather than a string you retype on every event.
            'venues' => Venue::orderByRaw("case when lower(type) like '%hotel%' then 0 else 1 end")
                ->orderBy('name')->get(['id', 'name', 'type', 'city']),
            // Names to autocomplete against; empty means "go build the list first".
            'attendees' => $this->event->attendees()->orderBy('name')->get(['id', 'name', 'ticket_type']),
            'roomsHeld' => $blocks->sum('rooms_count'),
            'roomsNamed' => $blocks->sum(fn (EventRoomBlock $b) => $b->filled()),
            'roomNightsTotal' => $blocks->sum(fn (EventRoomBlock $b) => $b->roomNights()),
            'costTotal' => $blocks->sum(fn (EventRoomBlock $b) => $b->totalCents()),
        ]);
    }
}
