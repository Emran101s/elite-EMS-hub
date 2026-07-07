<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventAgendaDay;
use App\Models\EventAgendaSession;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class AgendaTab extends Component
{
    use WithFileUploads;

    public Event $event;

    public bool $showForm = false;

    public ?int $editingId = null;

    // Session form
    public ?int $agenda_day_id = null;
    public ?int $room_id = null;

    // Inline "add a room" from the session form
    public bool $addingRoom = false;
    public string $newRoomName = '';
    public string $newRoomType = 'breakout';

    #[Validate('required|string|max:160')]
    public string $title = '';

    #[Validate('required')]
    public string $type = 'keynote';

    #[Validate('required')]
    public string $status = 'draft';

    #[Validate('required')]
    public string $starts_at = '09:00';

    #[Validate('required')]
    public string $ends_at = '10:00';

    public string $speaker = '';
    public string $moderator = '';
    public string $track = '';

    // Import
    public bool $showImport = false;
    public $importFile;

    public function mount(): void
    {
        $this->showForm = request('action') === 'add';
        $this->agenda_day_id = $this->event->agendaDays()->value('id');
    }

    public function addDay()
    {
        $last = $this->event->agendaDays()->orderByDesc('sort')->first();
        $nextDate = $last?->date ? $last->date->copy()->addDay() : ($this->event->starts_at ?? now());
        $count = $this->event->agendaDays()->count();

        $this->event->agendaDays()->create([
            'date' => $nextDate,
            'label' => 'Day '.($count + 1),
            'sort' => ($last?->sort ?? 0) + 1,
        ]);

        return $this->redirectTab();
    }

    public function duplicateDay(int $dayId)
    {
        $day = $this->event->agendaDays()->with('sessions')->findOrFail($dayId);

        $copy = $this->event->agendaDays()->create([
            'date' => $day->date?->copy()->addDay() ?? now(),
            'label' => $day->label.' (Copy)',
            'sort' => $this->event->agendaDays()->max('sort') + 1,
        ]);

        foreach ($day->sessions as $session) {
            $copy->sessions()->create([
                'event_id' => $this->event->id,
                'room_id' => $session->room_id,
                'title' => $session->title,
                'type' => $session->type,
                'status' => 'draft',
                'starts_at' => $session->starts_at,
                'ends_at' => $session->ends_at,
                'speaker' => $session->speaker,
                'moderator' => $session->moderator,
                'track' => $session->track,
                'sort' => $session->sort,
            ]);
        }

        session()->flash('status', "Duplicated “{$day->label}”.");

        return $this->redirectTab();
    }

    public function deleteDay(int $dayId)
    {
        $this->event->agendaDays()->whereKey($dayId)->firstOrFail()->delete();

        return $this->redirectTab();
    }

    public function newSession(int $dayId): void
    {
        $this->reset(['editingId', 'title', 'speaker', 'moderator', 'track']);
        $this->type = 'keynote';
        $this->status = 'draft';
        $this->starts_at = '09:00';
        $this->ends_at = '10:00';
        $this->agenda_day_id = $dayId;
        $this->room_id = $this->event->rooms()->value('id');
        // No rooms yet? Drop straight into "add a room" so the picker isn't empty.
        $this->addingRoom = $this->event->rooms()->doesntExist();
        $this->newRoomName = '';
        $this->showForm = true;
    }

    public function toggleAddRoom(): void
    {
        $this->addingRoom = ! $this->addingRoom;
        $this->addingRoom ? $this->room_id = null : $this->newRoomName = '';
    }

    public function editSession(int $sessionId): void
    {
        $this->reset(['addingRoom', 'newRoomName']);
        $s = $this->event->agendaSessions()->findOrFail($sessionId);
        $this->editingId = $s->id;
        $this->agenda_day_id = $s->agenda_day_id;
        $this->room_id = $s->room_id;
        $this->title = $s->title;
        $this->type = $s->type;
        $this->status = $s->status;
        $this->starts_at = substr((string) $s->starts_at, 0, 5);
        $this->ends_at = substr((string) $s->ends_at, 0, 5);
        $this->speaker = (string) $s->speaker;
        $this->moderator = (string) $s->moderator;
        $this->track = (string) $s->track;
        $this->showForm = true;
    }

    public function saveSession()
    {
        $this->validate([
            'agenda_day_id' => ['required', 'exists:event_agenda_days,id'],
            'room_id' => ['nullable', 'exists:event_rooms,id'],
            'type' => ['required', 'in:'.implode(',', EventAgendaSession::TYPES)],
            'status' => ['required', 'in:'.implode(',', EventAgendaSession::STATUSES)],
            'newRoomName' => ['nullable', 'string', 'max:120'],
            'newRoomType' => ['required', 'in:'.implode(',', \App\Models\EventRoom::TYPES)],
        ]);

        // Create a room on the fly if one was typed in.
        if ($this->addingRoom && trim($this->newRoomName) !== '') {
            $this->room_id = $this->event->rooms()->create([
                'name' => trim($this->newRoomName),
                'type' => $this->newRoomType,
            ])->id;
        }

        $data = [
            'agenda_day_id' => $this->agenda_day_id,
            'room_id' => $this->room_id,
            'title' => $this->title,
            'type' => $this->type,
            'status' => $this->status,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'speaker' => $this->speaker ?: null,
            'moderator' => $this->moderator ?: null,
            'track' => $this->track ?: null,
        ];

        if ($this->editingId) {
            $this->event->agendaSessions()->whereKey($this->editingId)->firstOrFail()->update($data);
        } else {
            $this->event->agendaSessions()->create($data + [
                'sort' => ($this->event->agendaSessions()->where('agenda_day_id', $this->agenda_day_id)->max('sort') ?? 0) + 1,
            ]);
        }

        session()->flash('status', $this->editingId ? 'Session updated.' : 'Session added.');

        return $this->redirectTab();
    }

    public function deleteSession(int $sessionId)
    {
        $this->event->agendaSessions()->whereKey($sessionId)->firstOrFail()->delete();

        return $this->redirectTab();
    }

    public function setStatus(int $sessionId, string $status)
    {
        abort_unless(in_array($status, EventAgendaSession::STATUSES, true), 422);
        $this->event->agendaSessions()->whereKey($sessionId)->firstOrFail()->update(['status' => $status]);
    }

    /**
     * Persist a drag-and-drop reorder. $groups = [['dayId'=>x, 'ids'=>[...]], ...]
     * (the source and target lists). Sets agenda_day_id + sequential sort.
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
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $day = $this->event->agendaDays()->orderBy('sort')->first()
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
            $data = array_combine($header, array_pad($row, count($header), ''));
            if (blank($data['title'] ?? '')) {
                continue;
            }

            $this->event->agendaSessions()->create([
                'agenda_day_id' => $day->id,
                'room_id' => $rooms[strtolower(trim($data['room'] ?? ''))]?->id ?? null,
                'title' => trim($data['title']),
                'type' => in_array($data['type'] ?? '', EventAgendaSession::TYPES, true) ? $data['type'] : 'keynote',
                'status' => 'draft',
                'starts_at' => $this->parseTime($data['start'] ?? $data['starts_at'] ?? '09:00'),
                'ends_at' => $this->parseTime($data['end'] ?? $data['ends_at'] ?? '10:00'),
                'speaker' => trim($data['speaker'] ?? '') ?: null,
                'moderator' => trim($data['moderator'] ?? '') ?: null,
                'track' => trim($data['track'] ?? '') ?: null,
                'sort' => $sort++,
            ]);
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
        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'agenda']);
    }

    /**
     * Scheduling conflicts within each day: two sessions clash when their
     * time ranges overlap AND they share the same room or the same speaker.
     * Returns [session_id => ['Room double-booked with “…”', …]].
     */
    private function detectConflicts($days): array
    {
        $conflicts = [];

        foreach ($days as $day) {
            $sessions = $day->sessions->all();

            foreach ($sessions as $a) {
                foreach ($sessions as $b) {
                    if ($a->id === $b->id) {
                        continue;
                    }
                    // overlap: a.start < b.end && a.end > b.start
                    $overlap = $a->starts_at < $b->ends_at && $a->ends_at > $b->starts_at;
                    if (! $overlap) {
                        continue;
                    }

                    if ($a->room_id && $a->room_id === $b->room_id) {
                        $conflicts[$a->id][] = ['type' => 'room', 'text' => 'Room "'.$a->room?->name.'" double-booked with "'.$b->title.'"'];
                    }
                    if ($a->speaker && $a->speaker === $b->speaker) {
                        $conflicts[$a->id][] = ['type' => 'speaker', 'text' => $a->speaker.' also speaks at "'.$b->title.'"'];
                    }
                }
            }
        }

        return $conflicts;
    }

    public function render()
    {
        $days = $this->event->agendaDays()
            ->with(['sessions' => fn ($q) => $q->orderBy('sort')->orderBy('starts_at'), 'sessions.room'])
            ->orderBy('sort')->get();

        return view('livewire.hub.agenda-tab', [
            'days' => $days,
            'rooms' => $this->event->rooms()->orderBy('name')->get(),
            'conflicts' => $this->detectConflicts($days),
        ]);
    }
}
