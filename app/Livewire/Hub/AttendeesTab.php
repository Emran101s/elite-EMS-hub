<?php

namespace App\Livewire\Hub;

use App\Livewire\Concerns\BulkSelectable;
use App\Models\CompanyProfile;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Support\Badge;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AttendeesTab extends Component
{
    use BulkSelectable;
    use WithFileUploads;

    public Event $event;

    // filters
    public string $search = '';

    public string $filterStatus = '';

    public string $filterTicket = '';

    // form
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $organization = '';

    public string $job_title = '';

    public string $ticket_type = 'Delegate';

    public string $status = 'registered';

    public string $amount = '';

    public bool $vip = false;

    public string $dietary = '';

    public string $notes = '';

    // import
    public bool $showImport = false;

    /** Mirrors of the event's own registration settings, for the form. */
    public string $registrationCapacity = '';

    public string $registrationNote = '';

    public $importFile;

    public function mount(): void
    {
        $this->ticket_type = CompanyProfile::current()->ticketTypes()[0] ?? 'Delegate';
        $this->registrationCapacity = (string) ($this->event->registration_capacity ?? '');
        $this->registrationNote = (string) ($this->event->registration_note ?? '');
        $this->badge = Badge::template($this->event);
    }

    private function ticketTypes(): array
    {
        return CompanyProfile::current()->ticketTypes();
    }

    public function newItem(): void
    {
        $this->reset(['editingId', 'name', 'email', 'phone', 'organization', 'job_title', 'amount', 'vip', 'dietary', 'notes']);
        $this->ticket_type = $this->ticketTypes()[0] ?? 'Delegate';
        $this->status = 'registered';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $a = $this->event->attendees()->findOrFail($id);
        $this->editingId = $a->id;
        $this->name = $a->name;
        $this->email = $a->email ?? '';
        $this->phone = $a->phone ?? '';
        $this->organization = $a->organization ?? '';
        $this->job_title = $a->job_title ?? '';
        $this->ticket_type = $a->ticket_type;
        $this->status = $a->status;
        $this->amount = $a->amount_cents ? (string) ($a->amount_cents / 100) : '';
        $this->vip = (bool) $a->vip;
        $this->dietary = $a->dietary ?? '';
        $this->notes = $a->notes ?? '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'ticket_type' => ['required', 'string', 'max:60'],
            'status' => ['required', Rule::in(EventAttendee::STATUSES)],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data = [
            'name' => trim($this->name),
            'email' => trim($this->email) ?: null,
            'phone' => trim($this->phone) ?: null,
            'organization' => trim($this->organization) ?: null,
            'job_title' => trim($this->job_title) ?: null,
            'ticket_type' => $this->ticket_type,
            'status' => $this->status,
            'amount_cents' => $this->amount !== '' ? (int) round((float) $this->amount * 100) : 0,
            'vip' => $this->vip,
            'dietary' => trim($this->dietary) ?: null,
            'notes' => trim($this->notes) ?: null,
            'checked_in_at' => $this->status === 'checked_in' ? now() : null,
        ];

        if ($this->editingId) {
            $this->event->attendees()->whereKey($this->editingId)->firstOrFail()->update($data);
        } else {
            $this->event->attendees()->create($data);
        }

        $this->showForm = false;
        $this->reset(['editingId']);
        session()->flash('status', 'Attendee saved.');
    }

    public function delete(int $id): void
    {
        $this->event->attendees()->whereKey($id)->delete();
    }

    public function deleteSelected(): void
    {
        $this->event->attendees()->whereIn('id', $this->selectedIds())->delete();
        $this->clearSelection();
    }

    /** Quick on-site check-in toggle. */
    public function toggleCheckIn(int $id): void
    {
        $a = $this->event->attendees()->findOrFail($id);
        $in = $a->status === 'checked_in';
        $a->update([
            'status' => $in ? 'confirmed' : 'checked_in',
            'checked_in_at' => $in ? null : now(),
        ]);
    }

    /* ── Check-in mode: the door on show day ── */

    /** Full-screen arrival flow — search, tap, next person in line. */
    public bool $checkinMode = false;

    /** Walk-in quick registration. */
    public string $walkinName = '';

    public string $walkinOrg = '';

    public function toggleCheckinMode(): void
    {
        $this->checkinMode = ! $this->checkinMode;
        $this->reset(['search', 'filterStatus', 'filterTicket', 'showForm', 'showImport']);
    }

    /** Someone at the door who never registered — capture and admit in one tap. */
    public function walkIn(): void
    {
        $this->validate(['walkinName' => ['required', 'string', 'max:120']]);

        $this->event->attendees()->create([
            'name' => trim($this->walkinName),
            'organization' => trim($this->walkinOrg) ?: null,
            'ticket_type' => 'Walk-in',
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        $this->reset(['walkinName', 'walkinOrg']);
    }

    public function import(): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:8192']]);

        $rows = $this->readRows($this->importFile);
        if (empty($rows)) {
            $this->addError('importFile', 'That file looks empty.');

            return;
        }

        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), array_shift($rows));

        /**
         * A column is found by what the heading says.
         *
         * The sheet's columns are this event's own questions now, so the old
         * fixed positions cannot describe it. Matching on the heading also
         * means a sheet somebody rearranged still imports, and a column the
         * form does not ask is simply not there.
         */
        $at = function (array $needles) use ($header) {
            foreach ($header as $i => $h) {
                foreach ($needles as $n) {
                    if ($h !== '' && str_contains($h, mb_strtolower($n))) {
                        return $i;
                    }
                }
            }

            return null;
        };

        $cell = fn (array $row, ?int $i) => $i !== null ? (trim((string) ($row[$i] ?? '')) ?: null) : null;

        // Each question knows its own column: by its label first, and by the
        // words the old fixed sheet used, so a template downloaded last month
        // still imports.
        // Kept exactly as loose as they were: a sheet somebody else prepared
        // says "Attendee Type" or "Company", and refusing it would be a
        // regression dressed up as tidiness.
        $legacy = [
            'name' => ['name'],
            'email' => ['email', 'e-mail'],
            'organization' => ['organi', 'company', 'org'],
            'phone' => ['phone', 'mobile', 'tel'],
            'job_title' => ['job', 'title', 'position', 'role'],
            'ticket_type' => ['ticket', 'type', 'category'],
            'dietary' => ['diet', 'allerg', 'meal'],
            'notes' => ['note', 'comment', 'remark'],
        ];

        $fields = $this->event->registrationForm();
        $columns = [];

        foreach ($fields as $field) {
            $columns[$field->key] = [
                'field' => $field,
                'index' => $at([$field->label, ...($legacy[$field->maps_to ?? ''] ?? [])]),
            ];
        }

        // Columns the desk records that the form does not ask. None of these is
        // a question you put to a registrant, which is why the default form has
        // no question for them — and why looking only at the form's questions
        // would quietly drop them from every sheet.
        $cAmount = $at(['amount', 'fee', 'price', 'paid']);
        $cVip = $at(['vip']);
        $cNotes = isset($columns['notes']) ? null : $at(['note', 'comment', 'remark']);
        $cName = $columns['name']['index'] ?? $at(['name']) ?? 0;

        $imported = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $name = trim((string) ($row[$cName] ?? ''));

            if ($name === '') {
                continue;
            }

            $amount = $cAmount !== null ? (float) preg_replace('/[^0-9.]/', '', (string) ($row[$cAmount] ?? '')) : 0;
            $vip = $cVip !== null
                && in_array(mb_strtolower((string) ($row[$cVip] ?? '')), ['1', 'y', 'yes', 'true', 'vip'], true);

            $data = ['name' => mb_substr($name, 0, 160)];
            $answers = [];

            $seats = [];

            foreach ($columns as $key => ['field' => $field, 'index' => $i]) {
                $value = $cell($row, $i);

                // Sessions arrive as titles, because that is what a person
                // typing a spreadsheet has. Anything that does not match a
                // session on this agenda is skipped rather than invented.
                if ($field->isSessions()) {
                    if ($value !== null) {
                        $wanted = collect(explode(',', $value))->map(fn ($t) => mb_strtolower(trim($t)))->filter();

                        $seats = $field->sessionChoices()
                            ->filter(fn ($sess) => $wanted->contains(mb_strtolower($sess->title)))
                            ->pluck('id')->all();
                    }

                    continue;
                }

                if ($field->maps_to) {
                    if ($value !== null && $field->maps_to !== 'name') {
                        $data[$field->maps_to] = $value;
                    }

                    continue;
                }

                // A several-choice answer arrives as one cell; it is stored the
                // way the form stores it so both look the same afterwards.
                if ($value !== null) {
                    $answers[$key] = $field->type === 'multiselect'
                        ? array_values(array_filter(array_map('trim', explode(',', $value))))
                        : $value;
                }
            }

            $data['ticket_type'] = $data['ticket_type'] ?? 'Delegate';
            $data['amount_cents'] = (int) round($amount * 100);

            if ($cNotes !== null && ($note = $cell($row, $cNotes)) !== null) {
                $data['notes'] = $note;
            }

            if ($answers !== []) {
                $data['answers'] = $answers;
            }

            // Re-importing a corrected sheet should fix the rows, not double them.
            // Email is the reliable key; without one, fall back to the name.
            $existing = ($email = $data['email'] ?? null)
                ? $this->event->attendees()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first()
                : $this->event->attendees()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

            if ($existing) {
                $existing->update($data + ['vip' => $vip || $existing->vip]);

                // Only when the sheet had the column: an import that does not
                // mention sessions must not empty the ones already booked.
                if ($seats !== []) {
                    $existing->sessions()->syncWithoutDetaching($seats);
                }

                $updated++;

                continue;
            }

            $attendee = $this->event->attendees()->create($data + ['vip' => $vip, 'status' => 'registered']);

            if ($seats !== []) {
                $attendee->sessions()->sync($seats);
            }

            $imported++;
        }

        $this->showImport = false;
        $this->reset('importFile');

        $msg = "Imported {$imported} ".str('attendee')->plural($imported);
        $msg .= $updated ? ", updated {$updated} existing." : '.';
        session()->flash('status', $msg);
    }

    private function readRows($file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            return IOFactory::load($file->getRealPath())->getActiveSheet()->toArray(null, true, false, false);
        }
        $rows = [];
        if (($h = fopen($file->getRealPath(), 'r')) !== false) {
            while (($row = fgetcsv($h)) !== false) {
                $rows[] = $row;
            }
            fclose($h);
        }

        return $rows;
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Public registration
    // ══════════════════════════════════════════════════════════════════════

    public bool $showRegistration = false;

    public function toggleRegistration(): void
    {
        Gate::authorize('write');

        $this->event->update(['registration_open' => ! $this->event->registration_open]);

        session()->flash('status', $this->event->registration_open
            ? 'Registration is open. Share the link.'
            : 'Registration is closed. The link now says so rather than 404ing.');
    }

    /**
     * A new link, when the old one has been shared somewhere it should not
     * have been. Everyone who already registered keeps their place; only the
     * URL changes.
     */
    public function newRegistrationLink(): void
    {
        Gate::authorize('write');

        $this->event->update(['registration_token' => Str::lower(Str::random(24))]);

        session()->flash('status', 'New registration link. The old one no longer opens anything.');
    }

    public function saveRegistrationSettings(): void
    {
        Gate::authorize('write');

        $data = $this->validate([
            'registrationCapacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'registrationNote' => ['nullable', 'string', 'max:600'],
        ]);

        $this->event->update([
            'registration_capacity' => $data['registrationCapacity'] !== '' ? (int) $data['registrationCapacity'] : null,
            'registration_note' => $data['registrationNote'] ?: null,
        ]);

        session()->flash('status', 'Registration settings saved.');
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Badges
    // ══════════════════════════════════════════════════════════════════════

    public bool $showBadge = false;

    /** The event's badge template, edited live so the preview keeps up. */
    public array $badge = [];

    public function saveBadge(): void
    {
        Gate::authorize('write');

        $this->validate([
            'badge.size' => ['required', Rule::in(array_keys(Badge::SIZES))],
            'badge.accent' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'badge.footer' => ['nullable', 'string', 'max:80'],
        ], ['badge.accent.regex' => 'A colour must be a hex value like #D4AF37.']);

        // Only the keys the template declares, so nothing from a browser can
        // put arbitrary JSON on the event.
        $this->event->update([
            'badge_template' => array_intersect_key($this->badge, Badge::DEFAULTS),
        ]);

        session()->flash('status', 'Badge design saved.');
    }

    public function resetBadge(): void
    {
        Gate::authorize('write');

        $this->event->update(['badge_template' => null]);
        $this->badge = Badge::DEFAULTS;

        session()->flash('status', 'Badge design back to the default.');
    }

    /**
     * Somebody real to preview against, so the design is judged on a name that
     * has to fit rather than on "Jane Smith".
     */
    public function badgeSample(): EventAttendee
    {
        return $this->event->attendees()->where('status', '!=', 'cancelled')->orderByDesc('id')->first()
            ?? new EventAttendee([
                'name' => 'Abdulrahman Al-Khalifa',
                'organization' => 'Jordan Investment Commission',
                'job_title' => 'Head of Investor Relations',
                'ticket_type' => 'VIP',
            ]);
    }

    public function render()
    {
        $all = $this->event->attendees()->orderByDesc('id')->get();
        $active = $all->where('status', '!=', 'cancelled');

        $list = $all
            ->when($this->filterStatus !== '', fn ($c) => $c->where('status', $this->filterStatus))
            ->when($this->filterTicket !== '', fn ($c) => $c->where('ticket_type', $this->filterTicket))
            ->when($this->search !== '', fn ($c) => $c->filter(fn ($a) => str_contains(mb_strtolower($a->name.' '.$a->email.' '.$a->organization), mb_strtolower($this->search))))
            ->values();

        $capacity = (int) ($this->event->expected_participants ?? 0);
        $registered = $active->count();

        // Door view: the queue you're actually working — arrivals pending first,
        // matching the search, checked-in sinking to the bottom.
        $doorList = $this->checkinMode
            ? $active
                ->when($this->search !== '', fn ($c) => $c->filter(fn ($a) => str_contains(mb_strtolower($a->name.' '.$a->email.' '.$a->organization), mb_strtolower($this->search))))
                ->sortBy(fn ($a) => [$a->status === 'checked_in' ? 1 : 0, mb_strtolower($a->name)])
                ->values()
            : collect();

        return view('livewire.hub.attendees-tab', [
            'attendees' => $list,
            'doorList' => $doorList,
            'lastHour' => $all->filter(fn ($a) => $a->checked_in_at?->gt(now()->subHour()))->count(),
            'ticketTypes' => $this->ticketTypes(),
            'stats' => [
                'registered' => $registered,
                'capacity' => $capacity,
                'fillPct' => $capacity > 0 ? min(100, (int) round($registered / $capacity * 100)) : null,
                'checkedIn' => $all->where('status', 'checked_in')->count(),
                'confirmed' => $all->whereIn('status', ['confirmed', 'checked_in'])->count(),
                'cancelled' => $all->where('status', 'cancelled')->count(),
                'vips' => $active->where('vip', true)->count(),
                'revenue' => $active->sum('amount_cents'),
            ],
            'byTicket' => $active->groupBy('ticket_type')->map->count()->sortDesc(),
        ]);
    }
}
