<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventAgendaSession;
use App\Models\EventRoom;
use App\Models\EventSpeaker;
use App\Models\User;
use App\Services\AgendaConflicts;
use App\Services\AgendaProgram;
use App\Services\AgendaTimeline;
use App\Support\Taxonomy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

class AgendaTab extends Component
{
    use WithFileUploads;

    /** Session type → [legend label, hex]. */
    public const PALETTE = [
        'opening' => ['Opening', '#0B1F3A'], 'keynote' => ['Keynote', '#8B5CF6'],
        'panel' => ['Session', '#3B82F6'], 'workshop' => ['Workshop', '#22C55E'],
        'break' => ['Break', '#94A3B8'], 'lunch' => ['Meal', '#D4AF37'],
        'networking' => ['Session', '#3B82F6'], 'exhibition' => ['Session', '#3B82F6'],
        'gala_dinner' => ['Meal', '#D4AF37'], 'closing' => ['Closing', '#0B1F3A'],
    ];

    public Event $event;

    public ?int $selectedDayId = null;

    /** The session the Inspector is showing. Null → the Inspector shows day insights instead. */
    public ?int $selectedSessionId = null;

    /** Left-rail status filter — dims blocks whose status isn't checked. Empty = show everything. */
    public array $statusFilter = [];

    /** Screen view: timeline (room lanes) · rooms · tracks · sessions · program. */
    public string $view = 'timeline';

    /** Rooms View: which single room to focus on. Null = every room. */
    public ?int $roomsViewFilter = null;

    /** Session List search — title or a billed speaker's name. */
    public string $sessionSearch = '';

    /** Whether the event-wide Clash Check summary is open. */
    public bool $showClashSummary = false;

    /** Programme audience: 'internal' (everything) or 'public' (delegate-facing). */
    public string $audience = 'internal';

    /** Filters the venue rail. A room list you have to scroll is a room list you scan. */
    public string $venueSearch = '';

    // Modal
    public bool $showForm = false;

    public ?int $editingId = null;

    // Session form
    public ?int $agenda_day_id = null;

    public ?int $room_id = null;

    public string $newRoomName = '';

    public string $title = '';

    public string $type = 'panel';

    /**
     * Which lane of the programme this belongs to.
     *
     * Load-bearing rather than a label: the track decides whether a session
     * heads its slot, folds into the all-day strip, or is kept off the public
     * programme entirely. Until now it could only be set by CSV import, so the
     * one field that shapes what a delegate sees was the one field the person
     * building the programme could not reach.
     */
    public string $track = '';

    public string $format = 'in_person';

    public string $status = 'draft';

    public string $capacity = '';

    public string $starts_at = '09:00';

    public string $ends_at = '10:00';

    public string $description = '';

    public bool $flagged = false;

    /** Repeatable speaker billing: [['name' => 'Dr Salim', 'role' => 'moderator'], …]. */
    public array $speakerRows = [];

    // Import
    public bool $showImport = false;

    public $importFile;

    public function mount(): void
    {
        // Keep the day the user was on across saves (redirectTab passes ?day=…).
        $requested = (int) request('day');
        $this->selectedDayId = ($requested && $this->event->agendaDays()->whereKey($requested)->exists())
            ? $requested
            : $this->event->agendaDays()->value('id');
        if (request('action') === 'add') {
            $this->newSession($this->selectedDayId);
        }
    }

    public function selectDay(int $dayId): void
    {
        $this->selectedDayId = $dayId;
        // A session inspected from a different day no longer has a block on screen.
        $this->selectedSessionId = null;
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['timeline', 'rooms', 'tracks', 'sessions', 'speakers', 'program'], true) ? $view : 'timeline';
    }

    public function setAudience(string $audience): void
    {
        $this->audience = isset(AgendaProgram::AUDIENCES[$audience]) ? $audience : 'internal';
    }

    /** Rooms View's room picker — null (or an id this event doesn't have) means every room. */
    public function selectRoomFilter(?int $roomId): void
    {
        $this->roomsViewFilter = $roomId && $this->event->rooms()->whereKey($roomId)->exists() ? $roomId : null;
    }

    /**
     * Click a block, a Tracks card, or a Session List row to glance at it in
     * the Inspector — this does not open the edit modal. The session may
     * belong to a day other than the one on screen (the Session List spans
     * every day), so selecting one also brings its day forward; every other
     * view reads the same $selectedDayId, so it stays in view underneath.
     * Pass null to close the Inspector and fall back to insights.
     */
    public function selectSession(?int $sessionId): void
    {
        $session = $sessionId ? $this->event->agendaSessions()->whereKey($sessionId)->first() : null;

        if (! $session) {
            $this->selectedSessionId = null;

            return;
        }

        $this->selectedDayId = $session->agenda_day_id;
        $this->selectedSessionId = $session->id;
    }

    /** Left-rail status chips — which statuses stay at full opacity on the board. */
    public function toggleStatusFilter(string $status): void
    {
        if (! array_key_exists($status, EventAgendaSession::STATUS_META)) {
            return;
        }
        $this->statusFilter = in_array($status, $this->statusFilter, true)
            ? array_values(array_diff($this->statusFilter, [$status]))
            : [...$this->statusFilter, $status];
    }

    /** Sign off every unconfirmed session on the selected day in one go. */
    public function confirmDay()
    {
        Gate::authorize('write');
        if (! $this->selectedDayId) {
            return null;
        }

        $unsettled = collect(EventAgendaSession::STATUS_META)
            ->reject(fn ($meta) => $meta[1])->keys()->all();

        $n = $this->event->agendaSessions()
            ->where('agenda_day_id', $this->selectedDayId)
            ->whereIn('status', $unsettled)
            ->update(['status' => 'confirmed']);

        session()->flash('status', $n
            ? "Confirmed {$n} ".str('session')->plural($n).'.'
            : 'Every session on this day was already confirmed.');

        return $this->redirectTab();
    }

    /* ── Speaker billing ── */

    public function addSpeakerRow(string $role = 'panelist'): void
    {
        $this->speakerRows[] = ['name' => '', 'role' => $role];
    }

    public function removeSpeakerRow(int $index): void
    {
        unset($this->speakerRows[$index]);
        $this->speakerRows = array_values($this->speakerRows);
    }

    /**
     * Resolve the form's speaker rows against the event roster (creating people
     * who don't exist yet) and sync them onto the session with their roles.
     */
    private function syncSpeakers(EventAgendaSession $session): void
    {
        $attach = [];

        foreach (array_values($this->speakerRows) as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $role = $row['role'] ?? 'panelist';
            if ($name === '' || ! isset(Taxonomy::options('speaker_role')[$role])) {
                continue;
            }

            $speaker = $this->event->speakers()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                ?? $this->event->speakers()->create([
                    'name' => $name,
                    'status' => 'invited',
                    'is_keynote' => $role === 'keynote',
                ]);

            $attach[$speaker->id] = ['role' => $role, 'sort' => $i];
        }

        $session->speakers()->sync($attach);
    }

    /* ── Days ── */

    public function addDay()
    {
        Gate::authorize('write');
        $last = $this->event->agendaDays()->orderByDesc('date')->first();
        $count = $this->event->agendaDays()->count();
        $day = $this->event->agendaDays()->create([
            'date' => $last?->date ? $last->date->copy()->addDay() : ($this->event->starts_at ?? now()),
            'label' => 'Day '.($count + 1),
            'sort' => $count + 1,
        ]);
        // Keep sort strictly by date so a new day always lands in the right place.
        $this->event->agendaDays()->orderBy('date')->orderBy('id')->get()
            ->each(fn ($d, $i) => $d->update(['sort' => $i + 1]));
        $this->selectedDayId = $day->id;

        return $this->redirectTab();
    }

    public function duplicateDay(int $dayId)
    {
        Gate::authorize('write');
        $day = $this->event->agendaDays()->with('sessions.speakers')->findOrFail($dayId);
        $copy = $this->event->agendaDays()->create([
            'date' => $day->date?->copy()->addDay() ?? now(),
            'label' => $day->label.' (Copy)',
            'sort' => $this->event->agendaDays()->max('sort') + 1,
        ]);
        foreach ($day->sessions as $s) {
            $new = $copy->sessions()->create($s->only([
                'event_id', 'room_id', 'title', 'type', 'format', 'capacity',
                'starts_at', 'ends_at', 'speaker', 'moderator', 'track', 'description', 'sort',
            ]) + ['event_id' => $this->event->id, 'status' => 'draft']);

            // Carry the speaker line-up (and each person's role) onto the copy.
            $new->speakers()->sync(
                $s->speakers->mapWithKeys(fn ($sp) => [
                    $sp->id => ['role' => $sp->pivot->role, 'sort' => $sp->pivot->sort],
                ])->all()
            );
        }
        session()->flash('status', "Duplicated “{$day->label}”.");

        return $this->redirectTab();
    }

    public function deleteDay(int $dayId)
    {
        Gate::authorize('write');
        $this->event->agendaDays()->whereKey($dayId)->firstOrFail()->delete();

        return $this->redirectTab();
    }

    /* ── Sessions ── */

    public function newSession(?int $dayId = null): void
    {
        $this->reset(['editingId', 'title', 'description', 'capacity', 'newRoomName', 'track']);
        $this->speakerRows = [];
        $this->type = 'Panel';
        $this->format = 'in_person';
        $this->status = 'draft';
        $this->flagged = false;
        $this->starts_at = '09:00';
        $this->ends_at = '10:00';
        $this->agenda_day_id = $dayId ?? $this->selectedDayId ?? $this->event->agendaDays()->value('id');
        $this->room_id = null;
        $this->showForm = true;
    }

    public function editSession(int $sessionId): void
    {
        $this->reset(['newRoomName']);
        $s = $this->event->agendaSessions()->with('speakers')->findOrFail($sessionId);
        $this->editingId = $s->id;
        $this->agenda_day_id = $s->agenda_day_id;
        $this->room_id = $s->room_id;
        $this->title = $s->title;
        // Show the type prettily; it's normalised back to a slug on save.
        $this->type = str($s->type)->replace('_', ' ')->title()->toString();
        $this->track = (string) $s->track;
        $this->format = $s->format ?? 'in_person';
        $this->status = $s->status ?? 'draft';
        $this->capacity = (string) ($s->capacity ?? '');
        $this->starts_at = substr((string) $s->starts_at, 0, 5);
        $this->ends_at = substr((string) $s->ends_at, 0, 5);
        $this->description = (string) $s->description;
        $this->flagged = (bool) $s->flagged;
        $this->speakerRows = $s->speakers
            ->map(fn ($sp) => ['name' => $sp->name, 'role' => $sp->pivot->role])
            ->values()->all();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    public function saveSession()
    {
        Gate::authorize('write');
        $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'agenda_day_id' => ['required', 'exists:event_agenda_days,id'],
            'room_id' => ['nullable', 'exists:event_rooms,id'],
            'type' => ['required', 'string', 'max:40'],   // built-in or a custom label
            'track' => ['nullable', 'string', 'max:60'],  // known lane or a custom one
            'format' => ['required', 'in:'.implode(',', array_keys(Taxonomy::options('session_format')))],
            'status' => ['required', 'in:'.implode(',', EventAgendaSession::STATUSES)],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['required'],
            'ends_at' => ['required'],
            'speakerRows.*.name' => ['nullable', 'string', 'max:120'],
            'speakerRows.*.role' => ['required', 'in:'.implode(',', array_keys(Taxonomy::options('speaker_role')))],
        ]);

        // Type a room → create it on the fly.
        if (trim($this->newRoomName) !== '') {
            $this->room_id = $this->event->rooms()->create(['name' => trim($this->newRoomName), 'type' => 'breakout'])->id;
        }

        // Normalise the type to a slug so built-in colours/legends keep matching,
        // while still allowing any custom label (Setup, Rehearsal, Arriving, …).
        $type = str($this->type)->snake()->toString() ?: 'session';

        $data = [
            'agenda_day_id' => $this->agenda_day_id,
            'room_id' => $this->room_id,
            'title' => $this->title,
            'type' => $type,
            'track' => trim($this->track) ?: null,
            'format' => $this->format,
            'capacity' => $this->capacity !== '' ? (int) $this->capacity : null,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'description' => $this->description ?: null,
            'flagged' => $this->flagged,
            'status' => $this->status,
        ];

        if ($this->editingId) {
            $session = $this->event->agendaSessions()->whereKey($this->editingId)->firstOrFail();
            $session->update($data);
        } else {
            $session = $this->event->agendaSessions()->create($data + [
                'sort' => ($this->event->agendaSessions()->where('agenda_day_id', $this->agenda_day_id)->max('sort') ?? 0) + 1,
            ]);
        }

        $this->syncSpeakers($session);

        $this->selectedDayId = $this->agenda_day_id;
        session()->flash('status', $this->editingId ? 'Session updated.' : 'Session added.');

        return $this->redirectTab();
    }

    public function deleteSession(int $sessionId): void
    {
        Gate::authorize('write');
        $this->event->agendaSessions()->whereKey($sessionId)->firstOrFail()->delete();
        $this->showForm = false;
        if ($this->selectedSessionId === $sessionId) {
            $this->selectedSessionId = null;
        }
    }

    public function toggleFlag(int $sessionId): void
    {
        Gate::authorize('write');
        $s = $this->event->agendaSessions()->whereKey($sessionId)->firstOrFail();
        $s->update(['flagged' => ! $s->flagged]);
    }

    /**
     * Clone a single session onto the same day, back to draft — the
     * Inspector's "Duplicate" action. Same per-row copy `duplicateDay()`
     * already does, just for one session instead of a whole day.
     */
    public function duplicateSession(int $sessionId)
    {
        Gate::authorize('write');
        $s = $this->event->agendaSessions()->with('speakers')->whereKey($sessionId)->firstOrFail();

        $copy = $this->event->agendaSessions()->create($s->only([
            'agenda_day_id', 'room_id', 'title', 'type', 'format', 'capacity', 'starts_at', 'ends_at', 'track', 'description',
        ]) + [
            'status' => 'draft',
            'sort' => ($this->event->agendaSessions()->where('agenda_day_id', $s->agenda_day_id)->max('sort') ?? 0) + 1,
        ]);

        $copy->speakers()->sync(
            $s->speakers->mapWithKeys(fn ($sp) => [$sp->id => ['role' => $sp->pivot->role, 'sort' => $sp->pivot->sort]])->all()
        );

        $this->selectedSessionId = $copy->id;
        session()->flash('status', 'Duplicated “'.$s->title.'”.');

        return $this->redirectTab();
    }

    /**
     * Inspector's "Move room" quick action — the room half of what dragging a
     * block onto a lane already does, without touching the time.
     */
    public function assignRoom(int $sessionId, $roomId = null): void
    {
        Gate::authorize('write');
        $s = $this->event->agendaSessions()->whereKey($sessionId)->firstOrFail();

        $s->update([
            'room_id' => ($roomId !== null && $roomId !== '' && (int) $roomId !== 0 && $this->event->rooms()->whereKey((int) $roomId)->exists())
                ? (int) $roomId : null,
        ]);
    }

    /** Inspector's "Add speaker" — opens the same edit modal with a blank row ready to fill. */
    public function quickAddSpeaker(int $sessionId): void
    {
        $this->editSession($sessionId);
        $this->addSpeakerRow('panelist');
    }

    /** Inspector's "Publish" — the one status a session is considered final in. */
    public function publishSession(int $sessionId): void
    {
        Gate::authorize('write');
        $this->event->agendaSessions()->whereKey($sessionId)->firstOrFail()->update(['status' => 'final']);
    }

    /**
     * Drag-to-reschedule: set a new start (minutes from midnight, snapped to 5)
     * keeping the duration, and optionally move to the room lane it was dropped on.
     */
    public function moveSession(int $sessionId, int $startMin, $roomId = null): void
    {
        Gate::authorize('write');
        $s = $this->event->agendaSessions()->whereKey($sessionId)->firstOrFail();

        $toMin = fn (string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $dur = max($toMin($s->ends_at) - $toMin($s->starts_at), 5);

        $startMin = (int) (round($startMin / 5) * 5);
        $startMin = max(0, min($startMin, 24 * 60 - $dur));
        $end = $startMin + $dur;

        $data = [
            'starts_at' => sprintf('%02d:%02d', intdiv($startMin, 60), $startMin % 60),
            'ends_at' => sprintf('%02d:%02d', intdiv($end, 60), $end % 60),
        ];

        // Empty target → Unassigned lane; a real room id must exist for this event.
        if ($roomId === null || $roomId === '' || (int) $roomId === 0) {
            $data['room_id'] = null;
        } elseif ($this->event->rooms()->whereKey((int) $roomId)->exists()) {
            $data['room_id'] = (int) $roomId;
        }

        $s->update($data);
    }

    /**
     * Kept for drag reorder support (persists day + sort).
     */
    public function reorder(array $groups): void
    {
        Gate::authorize('write');
        foreach ($groups as $group) {
            $dayId = (int) ($group['dayId'] ?? 0);
            if (! $this->event->agendaDays()->whereKey($dayId)->exists()) {
                continue;
            }
            foreach (($group['ids'] ?? []) as $index => $sessionId) {
                $this->event->agendaSessions()->whereKey((int) $sessionId)
                    ->update(['agenda_day_id' => $dayId, 'sort' => $index]);
            }
        }
    }

    public function import()
    {
        Gate::authorize('write');
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $day = $this->event->agendaDays()->orderBy('date')->first()
            ?? $this->event->agendaDays()->create(['date' => $this->event->starts_at ?? now(), 'label' => 'Day 1', 'sort' => 0]);
        $rooms = $this->event->rooms()->get()->keyBy(fn ($r) => strtolower($r->name));
        $handle = fopen($this->importFile->getRealPath(), 'r');
        $header = null;
        $imported = 0;
        $sort = ($this->event->agendaSessions()->where('agenda_day_id', $day->id)->max('sort') ?? 0) + 1;

        while (($row = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => strtolower(trim($h)), $row);

                continue;
            }
            $d = array_combine($header, array_pad($row, count($header), ''));
            if (blank($d['title'] ?? '')) {
                continue;
            }
            $session = $this->event->agendaSessions()->create([
                'agenda_day_id' => $day->id,
                'room_id' => $rooms[strtolower(trim($d['room'] ?? ''))]?->id ?? null,
                'title' => trim($d['title']),
                'type' => in_array($d['type'] ?? '', EventAgendaSession::TYPES, true) ? $d['type'] : 'panel',
                'format' => 'in_person',
                'status' => 'confirmed',
                'starts_at' => $this->parseTime($d['start'] ?? $d['starts_at'] ?? '09:00'),
                'ends_at' => $this->parseTime($d['end'] ?? $d['ends_at'] ?? '10:00'),
                'sort' => $sort++,
            ]);

            // "speaker" / "moderator" columns accept several names, separated by ; or |
            foreach ([['speaker', 'panelist'], ['moderator', 'moderator']] as [$column, $role]) {
                $names = collect(preg_split('/[;|]/', (string) ($d[$column] ?? '')))
                    ->map(fn ($n) => trim($n))->filter();

                foreach ($names->values() as $i => $name) {
                    $speaker = $this->event->speakers()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                        ?? $this->event->speakers()->create(['name' => $name, 'status' => 'invited']);
                    $session->speakers()->syncWithoutDetaching([$speaker->id => ['role' => $role, 'sort' => $i]]);
                }
            }
            $imported++;
        }
        fclose($handle);
        session()->flash('status', "Imported {$imported} ".str('session')->plural($imported).'.');

        return $this->redirectTab();
    }

    private function parseTime(string $value): string
    {
        try {
            return Carbon::parse(trim($value))->format('H:i');
        } catch (\Throwable) {
            return '09:00';
        }
    }

    private function redirectTab()
    {
        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'agenda', 'day' => $this->selectedDayId]);
    }

    /**
     * What this session is waiting on before it can really be called ready —
     * the Inspector's Dependencies section. The scheduling findings (room
     * and speaker overlaps, missing room/speaker, capacity, unconfirmed
     * speakers) come from the same event-wide AgendaConflicts findings
     * every other view reads — never a second, separately-computed list.
     * Only the room's own equipment readiness is checked here directly:
     * it isn't a scheduling conflict, so it isn't part of those findings.
     *
     * @return array<int, array{label: string, tone: string}>
     */
    private function dependencies(EventAgendaSession $session, Collection $eventFindings): array
    {
        $items = collect();

        foreach (app(AgendaConflicts::class)->forSession($eventFindings, $session->id) as $finding) {
            $items->push([
                'label' => $finding['message'],
                'tone' => in_array($finding['severity'], ['critical', 'high'], true) ? 'risk' : 'warn',
            ]);
        }

        // What the room itself still needs to bring — its own equipment list, not yet ready.
        if ($session->room) {
            foreach ($session->room->equipmentLines() as $line) {
                if (! in_array($line['status'], ['confirmed', 'onsite'], true)) {
                    $items->push([
                        'label' => $session->room->name.': '.$line['name'].' — '.ucfirst($line['status']),
                        'tone' => 'warn',
                    ]);
                }
            }
        }

        return $items->all();
    }

    /**
     * The venue rail: every room the event has, what it is carrying today, and
     * whether it is in trouble.
     *
     * A room list that does not say which room is double-booked is a room list.
     * Three states, worst first: a conflict (something is wrong), a warning (a
     * session is booked past what the room seats), or clear.
     */
    private function venueRail(Collection $sessions, array $conflicts): Collection
    {
        return $this->event->rooms()
            ->when(trim($this->venueSearch) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->venueSearch).'%'))
            ->orderBy('name')->get()->map(function (EventRoom $room) use ($sessions, $conflicts) {
                $mine = $sessions->where('room_id', $room->id);

                // Booked past what the room seats — the "capacity risk" signal.
                $over = $mine->filter(fn ($s) => $room->capacity > 0 && $s->capacity > $room->capacity);

                return [
                    'room' => $room,
                    'sessions' => $mine->count(),
                    'conflicts' => $mine->filter(fn ($s) => isset($conflicts[$s->id]))->count(),
                    'over' => $over->count(),
                    'state' => match (true) {
                        $mine->contains(fn ($s) => isset($conflicts[$s->id])) => 'conflict',
                        $over->isNotEmpty() => 'warning',
                        default => 'clear',
                    },
                ];
            });
    }

    /**
     * What the right-hand rail reports: where today stands, what is waiting on
     * somebody, and what is on next.
     *
     * Every figure is counted from the sessions in front of you rather than
     * stored, so it cannot go stale against the board it sits beside.
     */
    private function insights(Collection $sessions, array $conflicts): array
    {
        $now = now();
        $today = $sessions->filter(fn ($s) => $s->day?->date?->isToday());

        $byStatus = collect(EventAgendaSession::STATUS_META)
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta[0],
                'hex' => $meta[2] ?? '#94A3B8',
                'count' => $sessions->where('status', $key)->count(),
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values();

        // The next session that has not started yet, on a day that is today.
        $next = $today
            ->filter(fn ($s) => $s->day?->date?->setTimeFromTimeString($s->starts_at)?->isFuture())
            ->sortBy('starts_at')
            ->first();

        return [
            'byStatus' => $byStatus,
            'settled' => $sessions->filter->isSettled()->count(),
            'total' => $sessions->count(),
            'pct' => $sessions->count()
                ? (int) round($sessions->filter->isSettled()->count() / $sessions->count() * 100)
                : 0,
            // Three things that each need a person to do something.
            'awaitingSpeakers' => $sessions->where('status', 'waiting_speaker')->count(),
            'capacityRisks' => $sessions->filter(fn ($s) => $s->room && $s->room->capacity > 0 && $s->capacity > $s->room->capacity)->count(),
            'doubleBookings' => collect($conflicts)->count(),
            'next' => $next,
            'nextIn' => $next && $next->day?->date
                ? $now->diffInMinutes($next->day->date->copy()->setTimeFromTimeString($next->starts_at), false)
                : null,
        ];
    }

    /**
     * Tracks View: the day's sessions grouped by track, in running order —
     * the same grouping the left rail's Tracks summary already counts, just
     * with the sessions themselves instead of a number. Unassigned sorts
     * last: it is where a session lands when nobody has chosen a lane for
     * it yet, not a track in its own right.
     *
     * @return Collection<int, array{name: string, sessions: Collection}>
     */
    private function trackGroups(Collection $sessions): Collection
    {
        $groups = $sessions->groupBy(fn ($s) => $s->track ?: 'Unassigned')
            ->map(fn ($group, $name) => ['name' => $name, 'sessions' => $group->sortBy('starts_at')->values()]);

        $unassigned = $groups->pull('Unassigned');
        $groups = $groups->sortKeys();
        if ($unassigned) {
            $groups->put('Unassigned', $unassigned);
        }

        return $groups->values();
    }

    /**
     * Session List View: every session on the event, across every day —
     * the one view meant for scanning or auditing the whole programme at
     * once rather than one day at a time. Filtered by the same search box
     * and the same status chips the left rail's Filters section already
     * drives on the board.
     */
    private function sessionList(Collection $days): Collection
    {
        $term = mb_strtolower(trim($this->sessionSearch));

        return $days->flatMap->sessions
            ->when($term !== '', fn ($c) => $c->filter(fn ($s) => str_contains(mb_strtolower($s->title), $term)
                || $s->speakers->contains(fn ($sp) => str_contains(mb_strtolower($sp->name), $term))))
            ->when($this->statusFilter, fn ($c) => $c->whereIn('status', $this->statusFilter))
            ->sortBy(fn ($s) => $s->agenda_day_id.'-'.$s->starts_at)
            ->values();
    }

    /**
     * Speaker View: every billed speaker's schedule across the whole
     * event, and what each one is still waiting on. Every figure here is
     * read off data that already exists — the roster's own profile fields,
     * the session's own room/time columns, and the same event-wide
     * AgendaConflicts findings every other view reads (critical severity
     * only, so "Conflict" here means the same thing it means everywhere
     * else on the board).
     *
     * @param  array<int, array<int, array<int, string>>>  $conflictsByDay
     * @param  array<int, string>  $severityBySession
     * @return Collection<int, array>
     */
    private function speakerBoard(Collection $days, array $conflictsByDay, array $severityBySession): Collection
    {
        return $this->event->speakers()->orderBy('sort_order')->orderBy('name')->get()
            ->map(function (EventSpeaker $speaker) use ($days, $conflictsByDay, $severityBySession) {
                $rows = $days->flatMap->sessions
                    ->filter(fn ($s) => $s->speakers->contains('id', $speaker->id))
                    ->sortBy(fn ($s) => $s->agenda_day_id.'-'.$s->starts_at)
                    ->map(fn ($s) => [
                        'session' => $s,
                        'severity' => $severityBySession[$s->id] ?? null,
                        'hasConflict' => isset($conflictsByDay[$s->agenda_day_id][$s->id]),
                        'missingTime' => blank($s->starts_at) || blank($s->ends_at),
                        'missingRoom' => $s->room_id === null,
                        'unconfirmed' => ! $s->isSettled(),
                    ])
                    ->values();

                // What "missing details" means for a person we might need to
                // brief or reach — a topic to introduce them by, and some way
                // to actually contact them.
                $missingDetails = collect([
                    blank($speaker->topic) ? 'topic' : null,
                    (blank($speaker->email) && blank($speaker->phone)) ? 'contact info' : null,
                ])->filter()->values();

                // The worst severity across everything this person is billed
                // on — what the card header's own marker is picked from.
                $worstSeverity = $rows->pluck('severity')->filter()
                    ->sortBy(fn ($s) => AgendaConflicts::SEVERITY_ORDER[$s] ?? 9)
                    ->first();

                return [
                    'speaker' => $speaker,
                    'rows' => $rows,
                    'sessionCount' => $rows->count(),
                    'hasConflict' => $rows->contains('hasConflict', true),
                    'worstSeverity' => $worstSeverity,
                    'unconfirmedCount' => $rows->where('unconfirmed', true)->count(),
                    'missingDetails' => $missingDetails,
                    'needsAction' => $rows->contains('hasConflict', true)
                        || $rows->contains('unconfirmed', true)
                        || $rows->contains('missingRoom', true)
                        || $rows->contains('missingTime', true)
                        || $missingDetails->isNotEmpty(),
                ];
            });
    }

    /**
     * The Clash Check summary: every event-wide finding, worst first, with
     * enough context (which day, which room, which speaker, which session
     * to jump to) to act on without leaving the panel. Same findings the
     * rest of the board already reads — this is a different shape of the
     * same list, not a second opinion.
     *
     * @return Collection<int, array>
     */
    private function clashSummaryRows(Collection $findings, Collection $days): Collection
    {
        return $findings
            ->sortBy(fn ($f) => AgendaConflicts::SEVERITY_ORDER[$f['severity']] ?? 9)
            ->map(function ($f) use ($days) {
                $dayLabels = collect($f['sessions'])
                    ->unique()
                    ->map(fn ($dayId) => $days->firstWhere('id', $dayId)?->date?->format('D j M') ?? '—')
                    ->unique()
                    ->values();

                return [
                    'type' => $f['type'],
                    'typeLabel' => AgendaConflicts::TYPE_LABELS[$f['type']] ?? $f['type'],
                    'severity' => $f['severity'],
                    'message' => $f['message'],
                    'dayLabels' => $dayLabels,
                    'roomName' => $f['roomName'],
                    'speakerName' => $f['speakerName'],
                    'primarySessionId' => array_key_first($f['sessions']),
                ];
            })
            ->values();
    }

    /**
     * Timeline geometry for the selected day: room lanes with time-positioned blocks.
     */
    private function buildTimeline($sessions): ?array
    {
        $timeline = app(AgendaTimeline::class)->forSessions($sessions);

        // The room search used to filter a list of rooms in a side rail. The
        // rooms are the board's own lanes now, so it filters those instead —
        // same question ("show me this room"), asked of the thing you are
        // actually looking at. An empty result still returns the timeline so
        // the board can say nothing matched, rather than collapsing.
        $needle = trim($this->venueSearch);

        if ($needle !== '' && $timeline) {
            $timeline['lanes'] = collect($timeline['lanes'])
                ->filter(fn ($lane) => str_contains(
                    mb_strtolower((string) ($lane['room'] ?? '')),   // AgendaTimeline keys lanes by room NAME
                    mb_strtolower($needle),
                ))
                ->values();
        }

        return $timeline;
    }

    public function render()
    {
        $days = $this->event->agendaDays()
            ->with(['sessions' => fn ($q) => $q->orderBy('starts_at'), 'sessions.room', 'sessions.speakers', 'sessions.day'])
            ->orderBy('date')->orderBy('id')->get();

        $day = $days->firstWhere('id', $this->selectedDayId) ?? $days->first();
        $sessions = $day?->sessions ?? collect();

        // Day stats
        $toMin = fn (string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $totalMin = $sessions->sum(fn ($s) => max($toMin($s->ends_at) - $toMin($s->starts_at), 0));

        $programDays = $day ? [[
            'label' => $day->label,
            'date' => $day->date?->format('D · M j') ?? '',
            'program' => app(AgendaProgram::class)->forDay($sessions, $this->audience),
        ]] : [];

        $selectedSession = $this->selectedSessionId ? $sessions->firstWhere('id', $this->selectedSessionId) : null;

        // The single computation every conflict-aware surface on this tab
        // reads from — the day banner, the pins and rings, every Agenda
        // view's conflict badge, the Inspector's Dependencies, and the
        // Clash Check summary below. bySessionAndDay() with 'critical'
        // reproduces exactly what the board's red ring has always meant
        // (a same-room or same-speaker overlap); the full findings list
        // carries the rest (missing room/speaker, capacity, unconfirmed)
        // to the places that show more than just the ring.
        $agendaConflicts = app(AgendaConflicts::class);
        $eventFindings = $agendaConflicts->forEvent($this->event, $days);
        $conflictsByDay = $agendaConflicts->bySessionAndDay($eventFindings, ['critical']);
        $conflicts = $conflictsByDay[$day?->id] ?? [];
        // Every Agenda surface's severity marker (block corner dot, row dot,
        // card badge) is picked from this one map — critical still means
        // exactly what $conflicts above means; this just also carries
        // high/medium/low so those can be shown a size and colour quieter
        // than the red ring, instead of not shown at all.
        $severityBySession = $agendaConflicts->severityBySession($eventFindings);

        return view('livewire.hub.agenda-tab', [
            'selectedSession' => $selectedSession,
            'dependencies' => $selectedSession ? $this->dependencies($selectedSession, $eventFindings) : [],
            'clashSummary' => $this->clashSummaryRows($eventFindings, $days),
            'severityBySession' => $severityBySession,
            // The left rail's read-only summary — counts only.
            'trackSummary' => $sessions->groupBy(fn ($s) => $s->track ?: 'Unassigned')
                ->map(fn ($g, $name) => ['name' => $name, 'count' => $g->count()])
                ->sortByDesc('count')->values(),
            // Tracks View — the same day's sessions, grouped and in order,
            // Unassigned always last since it is a fallback bucket, not a
            // track anyone chose.
            'trackGroups' => $this->trackGroups($sessions),
            // Session List View spans every day, so it gets its own query.
            'sessionList' => $this->sessionList($days),
            'conflictsByDay' => $conflictsByDay,
            // Speaker View — every roster speaker's schedule across the
            // event, and the sessions nobody is billed on yet.
            'speakerBoard' => $this->speakerBoard($days, $conflictsByDay, $severityBySession),
            'unassignedSessions' => $days->flatMap->sessions
                ->filter(fn ($s) => $s->speakers->isEmpty())
                ->sortBy(fn ($s) => $s->agenda_day_id.'-'.$s->starts_at)
                ->values(),
            // Every day with how settled it is, so the day strip says which one
            // still needs work rather than only which one you are looking at.
            'dayCards' => $days->map(fn ($d) => [
                'model' => $d,
                'sessions' => $d->sessions->count(),
                'pct' => $d->sessions->count()
                    ? (int) round($d->sessions->filter->isSettled()->count() / $d->sessions->count() * 100)
                    : 0,
            ]),
            // The venue rail: what is in each room today, and whether it is in
            // trouble. A room list without its trouble is a room list.
            'venues' => $this->venueRail($sessions, $conflicts),
            'insights' => $this->insights($sessions, $conflicts),
            'days' => $days,
            'day' => $day,
            'programDays' => $programDays,
            'timeline' => $day ? $this->buildTimeline($sessions) : null,
            'conflicts' => $conflicts,
            'legend' => collect(self::PALETTE)->only($sessions->pluck('type')->unique()->all())->values()->unique(0)->values(),
            'rooms' => $this->event->rooms()->orderBy('name')->get(),
            'speakerOptions' => $this->event->speakers()->pluck('name')
                ->merge(User::orderBy('name')->pluck('name'))
                ->unique()->filter()->values(),
            'roles' => Taxonomy::options('speaker_role'),
            // The lanes that change how the programme draws, plus any this
            // event already uses — a track typed once should be offered twice.
            'trackOptions' => collect(AgendaProgram::tracks())
                ->union($this->event->agendaSessions()->distinct()->pluck('track')
                    ->filter()->mapWithKeys(fn (string $t) => [$t => 'Already used on this event'])->all()),
            // Built-in types + any custom ones already used, prettified for the datalist.
            'typeOptions' => collect(Taxonomy::labels('session_type'))
                ->merge($this->event->agendaSessions()->distinct()->pluck('type'))
                ->map(fn ($t) => str($t)->replace('_', ' ')->title()->toString())
                ->unique()->sort()->values(),
            'stats' => [
                'sessions' => $sessions->count(),
                'hours' => round($totalMin / 60, 1),
                'speakers' => $sessions->flatMap(fn ($s) => $s->speakers->pluck('id'))->unique()->count(),
                'rooms' => $sessions->pluck('room.name')->filter()->unique()->count(),
            ],
        ]);
    }
}
