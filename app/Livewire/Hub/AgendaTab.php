<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventAgendaSession;
use App\Models\EventRoom;
use App\Models\User;
use Illuminate\Support\Carbon;
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
    public string $capacity = '';
    public string $starts_at = '09:00';
    public string $ends_at = '10:00';
    public string $speaker = '';
    public string $speakerPick = '';
    public string $description = '';
    public bool $flagged = false;

    // Import
    public bool $showImport = false;
    public $importFile;

    public function mount(): void
    {
        $this->selectedDayId = $this->event->agendaDays()->orderBy('sort')->value('id');
        if (request('action') === 'add') {
            $this->newSession($this->selectedDayId);
        }
    }

    public function selectDay(int $dayId): void
    {
        $this->selectedDayId = $dayId;
    }

    public function updatedSpeakerPick(string $value): void
    {
        if ($value !== '') {
            $this->speaker = $value;
        }
    }

    /* ── Days ── */

    public function addDay()
    {
        $last = $this->event->agendaDays()->orderByDesc('sort')->first();
        $count = $this->event->agendaDays()->count();
        $day = $this->event->agendaDays()->create([
            'date' => $last?->date ? $last->date->copy()->addDay() : ($this->event->starts_at ?? now()),
            'label' => 'Day '.($count + 1),
            'sort' => ($last?->sort ?? 0) + 1,
        ]);
        $this->selectedDayId = $day->id;

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
        foreach ($day->sessions as $s) {
            $copy->sessions()->create($s->only([
                'event_id', 'room_id', 'title', 'type', 'format', 'capacity',
                'starts_at', 'ends_at', 'speaker', 'moderator', 'track', 'description', 'sort',
            ]) + ['event_id' => $this->event->id, 'status' => 'draft']);
        }
        session()->flash('status', "Duplicated “{$day->label}”.");

        return $this->redirectTab();
    }

    public function deleteDay(int $dayId)
    {
        $this->event->agendaDays()->whereKey($dayId)->firstOrFail()->delete();

        return $this->redirectTab();
    }

    /* ── Sessions ── */

    public function newSession(?int $dayId = null): void
    {
        $this->reset(['editingId', 'title', 'speaker', 'speakerPick', 'description', 'capacity', 'newRoomName']);
        $this->type = 'panel';
        $this->format = 'in_person';
        $this->flagged = false;
        $this->starts_at = '09:00';
        $this->ends_at = '10:00';
        $this->agenda_day_id = $dayId ?? $this->selectedDayId ?? $this->event->agendaDays()->value('id');
        $this->room_id = null;
        $this->showForm = true;
    }

    public function editSession(int $sessionId): void
    {
        $this->reset(['newRoomName', 'speakerPick']);
        $s = $this->event->agendaSessions()->findOrFail($sessionId);
        $this->editingId = $s->id;
        $this->agenda_day_id = $s->agenda_day_id;
        $this->room_id = $s->room_id;
        $this->title = $s->title;
        $this->type = $s->type;
        $this->format = $s->format ?? 'in_person';
        $this->capacity = (string) ($s->capacity ?? '');
        $this->starts_at = substr((string) $s->starts_at, 0, 5);
        $this->ends_at = substr((string) $s->ends_at, 0, 5);
        $this->speaker = (string) $s->speaker;
        $this->description = (string) $s->description;
        $this->flagged = (bool) $s->flagged;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    public function saveSession()
    {
        $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'agenda_day_id' => ['required', 'exists:event_agenda_days,id'],
            'room_id' => ['nullable', 'exists:event_rooms,id'],
            'type' => ['required', 'in:'.implode(',', EventAgendaSession::TYPES)],
            'format' => ['required', 'in:'.implode(',', array_keys(EventAgendaSession::FORMATS))],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['required'],
            'ends_at' => ['required'],
        ]);

        // Type a room → create it on the fly.
        if (trim($this->newRoomName) !== '') {
            $this->room_id = $this->event->rooms()->create(['name' => trim($this->newRoomName), 'type' => 'breakout'])->id;
        }

        $data = [
            'agenda_day_id' => $this->agenda_day_id,
            'room_id' => $this->room_id,
            'title' => $this->title,
            'type' => $this->type,
            'format' => $this->format,
            'capacity' => $this->capacity !== '' ? (int) $this->capacity : null,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'speaker' => $this->speaker ?: null,
            'description' => $this->description ?: null,
            'flagged' => $this->flagged,
        ];

        if ($this->editingId) {
            $this->event->agendaSessions()->whereKey($this->editingId)->firstOrFail()->update($data);
        } else {
            $this->event->agendaSessions()->create($data + [
                'status' => 'confirmed',
                'sort' => ($this->event->agendaSessions()->where('agenda_day_id', $this->agenda_day_id)->max('sort') ?? 0) + 1,
            ]);
        }

        $this->selectedDayId = $this->agenda_day_id;
        session()->flash('status', $this->editingId ? 'Session updated.' : 'Session added.');

        return $this->redirectTab();
    }

    public function deleteSession(int $sessionId): void
    {
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
            $d = array_combine($header, array_pad($row, count($header), ''));
            if (blank($d['title'] ?? '')) {
                continue;
            }
            $this->event->agendaSessions()->create([
                'agenda_day_id' => $day->id,
                'room_id' => $rooms[strtolower(trim($d['room'] ?? ''))]?->id ?? null,
                'title' => trim($d['title']),
                'type' => in_array($d['type'] ?? '', EventAgendaSession::TYPES, true) ? $d['type'] : 'panel',
                'format' => 'in_person',
                'status' => 'confirmed',
                'starts_at' => $this->parseTime($d['start'] ?? $d['starts_at'] ?? '09:00'),
                'ends_at' => $this->parseTime($d['end'] ?? $d['ends_at'] ?? '10:00'),
                'speaker' => trim($d['speaker'] ?? '') ?: null,
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
                if ($a->speaker && $a->speaker === $b->speaker) {
                    $conflicts[$a->id][] = $a->speaker.' also speaks at "'.$b->title.'"';
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
        if ($sessions->isEmpty()) {
            return null;
        }
        $toMin = fn (string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $startMin = (int) (floor($sessions->min(fn ($s) => $toMin($s->starts_at)) / 60) * 60);
        $endMin = (int) (ceil($sessions->max(fn ($s) => $toMin($s->ends_at)) / 60) * 60);
        $startMin = min($startMin, $endMin - 60);
        $span = max($endMin - $startMin, 60);

        $hours = [];
        for ($m = $startMin; $m <= $endMin; $m += 60) {
            $hours[] = ['label' => sprintf('%02d:00', intdiv($m, 60)), 'left' => round(($m - $startMin) / $span * 100, 3)];
        }

        $lanes = $sessions->groupBy(fn ($s) => $s->room?->name ?? 'Unassigned')->map(fn ($group, $room) => [
            'room' => $room,
            'room_id' => $group->first()->room_id,
            'blocks' => $group->map(function ($s) use ($toMin, $startMin, $span) {
                [$legend, $hex] = self::PALETTE[$s->type] ?? ['Session', '#3B82F6'];
                $sMin = $toMin($s->starts_at);
                $dMin = $toMin($s->ends_at) - $sMin;

                return [
                    'session' => $s,
                    'left' => round(($sMin - $startMin) / $span * 100, 3),
                    'width' => round(max($dMin, 15) / $span * 100, 3),
                    'startMin' => $sMin,
                    'durMin' => $dMin,
                    'hex' => $hex, 'legend' => $legend,
                ];
            })->values(),
        ])->values();

        return ['hours' => $hours, 'lanes' => $lanes, 'startMin' => $startMin, 'span' => $span];
    }

    public function render()
    {
        $days = $this->event->agendaDays()
            ->with(['sessions' => fn ($q) => $q->orderBy('starts_at'), 'sessions.room'])
            ->orderBy('sort')->get();

        $day = $days->firstWhere('id', $this->selectedDayId) ?? $days->first();
        $sessions = $day?->sessions ?? collect();

        // Day stats
        $toMin = fn (string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $totalMin = $sessions->sum(fn ($s) => max($toMin($s->ends_at) - $toMin($s->starts_at), 0));

        return view('livewire.hub.agenda-tab', [
            'days' => $days,
            'day' => $day,
            'timeline' => $day ? $this->buildTimeline($sessions) : null,
            'conflicts' => $this->detectConflicts($sessions),
            'legend' => collect(self::PALETTE)->only($sessions->pluck('type')->unique()->all())->values()->unique(0)->values(),
            'rooms' => $this->event->rooms()->orderBy('name')->get(),
            'speakerOptions' => User::orderBy('name')->pluck('name')
                ->merge($this->event->agendaSessions()->whereNotNull('speaker')->pluck('speaker'))
                ->unique()->filter()->values(),
            'stats' => [
                'sessions' => $sessions->count(),
                'hours' => round($totalMin / 60, 1),
                'speakers' => $sessions->pluck('speaker')->filter()->unique()->count(),
                'rooms' => $sessions->pluck('room.name')->filter()->unique()->count(),
            ],
        ]);
    }
}
