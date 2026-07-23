<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventAgendaSession;
use App\Models\User;
use App\Services\AgendaProgram;
use App\Services\AgendaTimeline;
use Illuminate\Support\Carbon;
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

    /** Screen view: 'timeline' (room lanes) or 'program' (Main Stage board). */
    public string $view = 'timeline';

    /** Programme audience: 'internal' (everything) or 'public' (delegate-facing). */
    public string $audience = 'internal';

    // Modal
    public bool $showForm = false;

    public ?int $editingId = null;

    // Session form
    public ?int $agenda_day_id = null;

    public ?int $room_id = null;

    public string $newRoomName = '';

    public string $title = '';

    public string $type = 'panel';

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
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['timeline', 'program'], true) ? $view : 'timeline';
    }

    public function setAudience(string $audience): void
    {
        $this->audience = isset(AgendaProgram::AUDIENCES[$audience]) ? $audience : 'internal';
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
            if ($name === '' || ! isset(EventAgendaSession::SPEAKER_ROLES[$role])) {
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
        $this->reset(['editingId', 'title', 'description', 'capacity', 'newRoomName']);
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
            'format' => ['required', 'in:'.implode(',', array_keys(EventAgendaSession::FORMATS))],
            'status' => ['required', 'in:'.implode(',', EventAgendaSession::STATUSES)],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['required'],
            'ends_at' => ['required'],
            'speakerRows.*.name' => ['nullable', 'string', 'max:120'],
            'speakerRows.*.role' => ['required', 'in:'.implode(',', array_keys(EventAgendaSession::SPEAKER_ROLES))],
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
    }

    public function toggleFlag(int $sessionId): void
    {
        $s = $this->event->agendaSessions()->whereKey($sessionId)->firstOrFail();
        $s->update(['flagged' => ! $s->flagged]);
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
     * Scheduling conflicts on a day: overlapping sessions sharing a room or speaker.
     * Returns [session_id => [reason, …]].
     */
    private function detectConflicts($sessions): array
    {
        $conflicts = [];
        foreach ($sessions as $a) {
            foreach ($sessions as $b) {
                if ($a->id === $b->id) {
                    continue;
                }
                if (! ($a->starts_at < $b->ends_at && $a->ends_at > $b->starts_at)) {
                    continue;
                }
                if ($a->room_id && $a->room_id === $b->room_id) {
                    $conflicts[$a->id][] = 'Room "'.$a->room?->name.'" double-booked with "'.$b->title.'"';
                }
                // The same person billed on two overlapping sessions.
                $shared = $a->speakers->pluck('name', 'id')->intersectByKeys($b->speakers->pluck('name', 'id'));
                foreach ($shared as $name) {
                    $conflicts[$a->id][] = $name.' also speaks at "'.$b->title.'"';
                }
            }
        }

        return $conflicts;
    }

    /**
     * Timeline geometry for the selected day: room lanes with time-positioned blocks.
     */
    private function buildTimeline($sessions): ?array
    {
        return app(AgendaTimeline::class)->forSessions($sessions);
    }

    public function render()
    {
        $days = $this->event->agendaDays()
            ->with(['sessions' => fn ($q) => $q->orderBy('starts_at'), 'sessions.room', 'sessions.speakers'])
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

        return view('livewire.hub.agenda-tab', [
            'days' => $days,
            'day' => $day,
            'programDays' => $programDays,
            'timeline' => $day ? $this->buildTimeline($sessions) : null,
            'conflicts' => $this->detectConflicts($sessions),
            'legend' => collect(self::PALETTE)->only($sessions->pluck('type')->unique()->all())->values()->unique(0)->values(),
            'rooms' => $this->event->rooms()->orderBy('name')->get(),
            'speakerOptions' => $this->event->speakers()->pluck('name')
                ->merge(User::orderBy('name')->pluck('name'))
                ->unique()->filter()->values(),
            'roles' => EventAgendaSession::SPEAKER_ROLES,
            // Built-in types + any custom ones already used, prettified for the datalist.
            'typeOptions' => collect(EventAgendaSession::TYPES)
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
