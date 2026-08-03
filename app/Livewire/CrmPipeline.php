<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\User;
use App\Services\DealPipeline;
use App\Support\Taxonomy;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\Gate;

/**
 * The pipeline — the half of the business that happens before an event exists.
 *
 * Deliberately the same shape as the Events board: lanes across, an Inspector
 * down the right, nothing that expands in place. A deal that is won leaves this
 * board and appears on that one.
 */
#[Layout('components.layouts.app', [
    'title' => 'CRM',
    'subtitle' => 'Every opportunity, from first conversation to signed event.',
])]
class CrmPipeline extends Component
{
    public string $q = '';

    public ?int $selectedId = null;

    /** Deal form. */
    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $client_id = null;

    public ?int $contact_id = null;

    public ?int $owner_id = null;

    public string $title = '';

    public string $stage = 'enquiry';

    public string $value = '';

    public ?string $expected_close_on = null;

    public ?string $expected_event_on = null;

    public ?string $source = null;

    public string $notes = '';

    /** Activity form. */
    public bool $showActivity = false;

    public string $a_type = 'call';

    public string $a_subject = '';

    public string $a_body = '';

    public ?string $a_follow_up_on = null;

    /** Losing a deal asks why — a pipeline without loss reasons teaches nothing. */
    public ?int $losingId = null;

    public string $lostReason = '';

    public function select(int $id): void
    {
        $this->selectedId = $this->selectedId === $id ? null : $id;
    }

    // ── the deal form ────────────────────────────────────────────────────

    public function newDeal(): void
    {
        $this->reset(['editingId', 'title', 'value', 'expected_close_on', 'expected_event_on', 'source', 'notes', 'contact_id']);
        $this->stage = 'enquiry';
        $this->client_id = Client::query()->value('id');
        $this->owner_id = auth()->id();
        $this->showForm = true;
    }

    public function editDeal(int $id): void
    {
        $deal = Deal::findOrFail($id);

        $this->editingId = $deal->id;
        $this->client_id = $deal->client_id;
        $this->contact_id = $deal->contact_id;
        $this->owner_id = $deal->owner_id;
        $this->title = $deal->title;
        $this->stage = $deal->stage;
        $this->value = $deal->value_cents ? (string) ($deal->value_cents / 100) : '';
        $this->expected_close_on = $deal->expected_close_on?->toDateString();
        $this->expected_event_on = $deal->expected_event_on?->toDateString();
        $this->source = $deal->source;
        $this->notes = (string) $deal->notes;
        $this->showForm = true;
    }

    public function saveDeal(): void
    {
        Gate::authorize('write');
        $data = $this->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:160'],
            'stage' => ['required', Rule::in(array_keys(Deal::STAGES))],
            'value' => ['nullable', 'numeric', 'min:0'],
            'expected_close_on' => ['nullable', 'date'],
            'expected_event_on' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
        ]);

        $payload = [
            'client_id' => $data['client_id'],
            'contact_id' => $data['contact_id'] ?: null,
            'owner_id' => $data['owner_id'] ?: null,
            'title' => $data['title'],
            'type' => 'conference',
            'value_cents' => (int) round((float) ($data['value'] ?: 0) * 100),
            'expected_close_on' => $data['expected_close_on'] ?: null,
            'expected_event_on' => $data['expected_event_on'] ?: null,
            'source' => $data['source'] ?: null,
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->editingId) {
            $deal = Deal::findOrFail($this->editingId);
            $deal->update($payload);
            // A stage change still has to go through the pipeline, so winning
            // from the edit form creates the event exactly as the board does.
            if ($deal->stage !== $data['stage']) {
                app(DealPipeline::class)->moveTo($deal, $data['stage']);
            }
        } else {
            $deal = Deal::create($payload + [
                'stage' => $data['stage'],
                'probability' => Deal::STAGES[$data['stage']][1] ?? 0,
            ]);
            $this->selectedId = $deal->id;
        }

        $this->showForm = false;
    }

    // ── moving through the pipeline ──────────────────────────────────────

    public function moveTo(int $id, string $stage): void
    {
        Gate::authorize('manage-events');
        if (! array_key_exists($stage, Deal::STAGES)) {
            return;
        }

        $deal = Deal::findOrFail($id);

        if ($stage === 'lost') {
            $this->losingId = $deal->id;
            $this->lostReason = '';

            return;
        }

        if ($stage === 'won') {
            $event = app(DealPipeline::class)->win($deal);
            session()->flash('status', "“{$deal->title}” won — opened as an event. Set its dates and venue in the hub.");
            $this->redirectRoute('events.hub', $event);

            return;
        }

        app(DealPipeline::class)->moveTo($deal, $stage);
    }

    public function confirmLost(): void
    {
        Gate::authorize('manage-events');
        $this->validate(['lostReason' => ['nullable', 'string', 'max:200']]);

        app(DealPipeline::class)->lose(Deal::findOrFail($this->losingId), $this->lostReason ?: null);
        $this->losingId = null;
    }

    public function reopen(int $id): void
    {
        Gate::authorize('manage-events');
        app(DealPipeline::class)->moveTo(Deal::findOrFail($id), 'negotiation');
    }

    // ── the activity log ─────────────────────────────────────────────────

    public function logActivity(): void
    {
        Gate::authorize('write');
        $deal = Deal::findOrFail($this->selectedId);

        $data = $this->validate([
            'a_type' => ['required', Rule::in(array_keys(Taxonomy::options('activity_type')))],
            'a_subject' => ['required', 'string', 'max:160'],
            'a_body' => ['nullable', 'string'],
            'a_follow_up_on' => ['nullable', 'date'],
        ]);

        DealActivity::create([
            'deal_id' => $deal->id,
            'client_id' => $deal->client_id,
            'contact_id' => $deal->contact_id,
            'user_id' => auth()->id(),
            'type' => $data['a_type'],
            'subject' => $data['a_subject'],
            'body' => $data['a_body'] ?: null,
            'happened_at' => now(),
            'follow_up_on' => $data['a_follow_up_on'] ?: null,
        ]);

        $this->reset(['a_subject', 'a_body', 'a_follow_up_on']);
        $this->showActivity = false;
    }

    public function completeFollowUp(int $id): void
    {
        Gate::authorize('write');
        DealActivity::whereKey($id)->update(['follow_up_done' => true]);
    }

    public function render()
    {
        $deals = Deal::with(['client', 'contact', 'owner', 'event'])
            ->when($this->q !== '', fn ($query) => $query->where(function ($w) {
                $w->where('title', 'like', "%{$this->q}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$this->q}%"));
            }))
            ->orderBy('position')
            ->orderByDesc('value_cents')
            ->get();

        $selected = $this->selectedId ? $deals->firstWhere('id', $this->selectedId) : null;

        return view('livewire.crm-pipeline', [
            'lanes' => collect(Deal::OPEN)->map(fn ($key) => [
                'key' => $key,
                'label' => Deal::STAGES[$key][0],
                'hex' => Deal::STAGES[$key][2],
                'deals' => $deals->where('stage', $key)->values(),
            ]),
            'closed' => $deals->whereIn('stage', ['won', 'lost'])->take(8),
            'selected' => $selected,
            'activities' => $selected ? $selected->activities()->with('user')->take(12)->get() : collect(),
            'forecast' => app(DealPipeline::class)->forecast(),
            'dueFollowUps' => DealActivity::due()->with(['deal', 'client'])->orderBy('follow_up_on')->take(6)->get(),
            'clients' => Client::orderBy('name')->get(),
            'contacts' => $this->client_id ? Contact::where('client_id', $this->client_id)->orderBy('name')->get() : collect(),
            'users' => User::orderBy('name')->get(),
        ]);
    }
}
