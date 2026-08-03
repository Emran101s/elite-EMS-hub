<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Support\Taxonomy;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Illuminate\Support\Facades\Gate;

/**
 * Everything about one client, in one place.
 *
 * The pipeline answers "what are we chasing"; this answers "who is this, who do
 * we know there, what have we done for them, and what did they pay". Without it
 * the CRM is a list of deals that happen to share a name.
 */
class ClientRecord extends Component
{
    public Client $client;

    /** Contact form. */
    public bool $showContact = false;

    public ?int $editingContact = null;

    public string $c_name = '';

    public string $c_title = '';

    public string $c_email = '';

    public string $c_phone = '';

    public string $c_notes = '';

    /** Activity form — a relationship is logged even with no live deal. */
    public bool $showActivity = false;

    public string $a_type = 'call';

    public string $a_subject = '';

    public string $a_body = '';

    public ?int $a_contact_id = null;

    public ?string $a_follow_up_on = null;

    public function mount(Client $client): void
    {
        $this->client = $client;
    }

    // ── contacts ─────────────────────────────────────────────────────────

    public function newContact(): void
    {
        $this->reset(['editingContact', 'c_name', 'c_title', 'c_email', 'c_phone', 'c_notes']);
        $this->showContact = true;
    }

    public function editContact(int $id): void
    {
        $contact = $this->client->contacts()->findOrFail($id);

        $this->editingContact = $contact->id;
        $this->c_name = $contact->name;
        $this->c_title = (string) $contact->title;
        $this->c_email = (string) $contact->email;
        $this->c_phone = (string) $contact->phone;
        $this->c_notes = (string) $contact->notes;
        $this->showContact = true;
    }

    public function saveContact(): void
    {
        Gate::authorize('write');
        $data = $this->validate([
            'c_name' => ['required', 'string', 'max:120'],
            'c_title' => ['nullable', 'string', 'max:120'],
            'c_email' => ['nullable', 'email', 'max:160'],
            'c_phone' => ['nullable', 'string', 'max:40'],
            'c_notes' => ['nullable', 'string'],
        ]);

        $payload = [
            'name' => $data['c_name'],
            'title' => $data['c_title'] ?: null,
            'email' => $data['c_email'] ?: null,
            'phone' => $data['c_phone'] ?: null,
            'notes' => $data['c_notes'] ?: null,
        ];

        if ($this->editingContact) {
            $this->client->contacts()->findOrFail($this->editingContact)->update($payload);
        } else {
            $this->client->contacts()->create($payload + [
                // The first person on a client is its primary, without asking.
                'is_primary' => $this->client->contacts()->count() === 0,
            ]);
        }

        $this->showContact = false;
    }

    public function makePrimary(int $id): void
    {
        Gate::authorize('write');
        $this->client->contacts()->update(['is_primary' => false]);
        $this->client->contacts()->whereKey($id)->update(['is_primary' => true]);
    }

    public function deleteContact(int $id): void
    {
        Gate::authorize('write');
        $contact = $this->client->contacts()->findOrFail($id);
        $wasPrimary = $contact->is_primary;
        $contact->delete();

        // Never leave a client without a primary while it still has people.
        if ($wasPrimary && ($next = $this->client->contacts()->first())) {
            $next->update(['is_primary' => true]);
        }
    }

    // ── activity ─────────────────────────────────────────────────────────

    public function logActivity(): void
    {
        Gate::authorize('write');
        $data = $this->validate([
            'a_type' => ['required', Rule::in(array_keys(Taxonomy::options('activity_type')))],
            'a_subject' => ['required', 'string', 'max:160'],
            'a_body' => ['nullable', 'string'],
            'a_contact_id' => ['nullable', 'exists:contacts,id'],
            'a_follow_up_on' => ['nullable', 'date'],
        ]);

        DealActivity::create([
            'client_id' => $this->client->id,
            'contact_id' => $data['a_contact_id'] ?: null,
            'user_id' => auth()->id(),
            'type' => $data['a_type'],
            'subject' => $data['a_subject'],
            'body' => $data['a_body'] ?: null,
            'happened_at' => now(),
            'follow_up_on' => $data['a_follow_up_on'] ?: null,
        ]);

        $this->reset(['a_subject', 'a_body', 'a_follow_up_on', 'a_contact_id']);
        $this->showActivity = false;
    }

    public function completeFollowUp(int $id): void
    {
        Gate::authorize('write');
        DealActivity::whereKey($id)->where('client_id', $this->client->id)->update(['follow_up_done' => true]);
    }

    public function render()
    {
        $deals = $this->client->deals()->with('event')->latest()->get();
        // Archived events are not delivered work — the three that moved into the
        // pipeline are archived, and counting them would inflate both the event
        // count and the lifetime value with work that never happened.
        $events = $this->client->events()->whereNull('archived_at')->orderByDesc('starts_at')->get();
        $won = $deals->where('stage', 'won');
        $closed = $deals->whereIn('stage', ['won', 'lost']);

        return view('livewire.client-record', [
            'contacts' => $this->client->contacts()->orderByDesc('is_primary')->orderBy('name')->get(),
            'openDeals' => $deals->filter(fn (Deal $d) => $d->isOpen())->values(),
            'closedDeals' => $closed->values(),
            'events' => $events,
            'activities' => $this->client->activities()->with(['user', 'deal', 'contact'])->take(20)->get(),
            'stats' => [
                // What they have actually been worth: budgets on events delivered,
                // not the optimistic value of deals still in play.
                'lifetime' => (int) $events->sum('budget_cents'),
                'openValue' => (int) $deals->filter(fn (Deal $d) => $d->isOpen())->sum('value_cents'),
                'events' => $events->count(),
                'winRate' => $closed->count() ? (int) round($won->count() / $closed->count() * 100) : null,
                'lastContact' => $this->client->activities()->max('happened_at'),
            ],
        ])->layout('components.layouts.app', [
            // The title is the client, so it can only be set at render time.
            'title' => $this->client->name,
            'subtitle' => 'Client record — people, deals, events and every conversation.',
            'crumbs' => [
                ['label' => 'Command Center', 'href' => route('home')],
                ['label' => 'CRM', 'href' => route('crm.index')],
                ['label' => $this->client->name],
            ],
        ]);
    }
}
