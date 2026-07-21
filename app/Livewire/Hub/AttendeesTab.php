<?php

namespace App\Livewire\Hub;

use App\Livewire\Concerns\BulkSelectable;
use App\Models\CompanyProfile;
use App\Models\Event;
use App\Models\EventAttendee;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

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

    public $importFile;

    public function mount(): void
    {
        $this->ticket_type = CompanyProfile::current()->ticketTypes()[0] ?? 'Delegate';
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

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows));
        $find = function (array $needles, ?int $fallback) use ($header) {
            foreach ($header as $i => $h) {
                foreach ($needles as $n) {
                    if ($h !== '' && str_contains($h, $n)) {
                        return $i;
                    }
                }
            }

            return $fallback;
        };
        $cName = $find(['name'], 0);
        $cEmail = $find(['email', 'e-mail'], 1);
        $cOrg = $find(['organi', 'company', 'org'], null);
        $cPhone = $find(['phone', 'mobile', 'tel'], null);
        $cTicket = $find(['ticket', 'type', 'category'], null);
        $cAmount = $find(['amount', 'fee', 'price', 'paid'], null);
        $cTitle = $find(['job', 'title', 'position', 'role'], null);
        $cDiet = $find(['diet', 'allerg', 'meal'], null);
        $cVip = $find(['vip'], null);
        $cNotes = $find(['note', 'comment', 'remark'], null);

        $cell = fn (array $row, ?int $i) => $i !== null ? (trim((string) ($row[$i] ?? '')) ?: null) : null;

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

            $data = array_filter([
                'name' => mb_substr($name, 0, 160),
                'email' => $cell($row, $cEmail),
                'phone' => $cell($row, $cPhone),
                'organization' => $cell($row, $cOrg),
                'job_title' => $cell($row, $cTitle),
                'dietary' => $cell($row, $cDiet),
                'notes' => $cell($row, $cNotes),
                'ticket_type' => $cell($row, $cTicket) ?: 'Delegate',
                'amount_cents' => (int) round($amount * 100),
            ], fn ($v) => $v !== null);

            // Re-importing a corrected sheet should fix the rows, not double them.
            // Email is the reliable key; without one, fall back to the name.
            $existing = ($email = $data['email'] ?? null)
                ? $this->event->attendees()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first()
                : $this->event->attendees()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

            if ($existing) {
                $existing->update($data + ['vip' => $vip || $existing->vip]);
                $updated++;

                continue;
            }

            $this->event->attendees()->create($data + ['vip' => $vip, 'status' => 'registered']);
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
            return \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath())->getActiveSheet()->toArray(null, true, false, false);
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
