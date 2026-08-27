<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventContract;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every document in the book, across every event.
 *
 * Each event already keeps its own Deck of contracts. This is the layer above:
 * the question "what is waiting on a pen" is not a question about one event,
 * and answering it used to mean opening every event in turn and reading its
 * Contract tab.
 *
 * Nothing is re-derived here. Status, value, the signature count and the
 * pipeline column all come from EventContract's own helpers, so a figure on
 * this page and the same figure inside an event cannot drift apart.
 */
#[Layout('components.layouts.app', [
    'title' => 'Contracts',
    'hideTitleRow' => true,
])]
class ContractsRegister extends Component
{
    /** register · pipeline */
    #[Url(as: 'view')]
    public string $view = 'register';

    #[Url(as: 'q')]
    public string $q = '';

    /** Soft Command MDA — selected document in Signature / Payment Panel. */
    #[Url(as: 'selected')]
    public ?int $selectedId = null;

    /** A status key, or 'all'. */
    #[Url(as: 'status')]
    public string $status = 'all';

    /** A type key from EventContract::TYPES, or 'all'. */
    #[Url(as: 'type')]
    public string $type = 'all';

    /** reference · event · value · due — how the register is ordered. */
    public string $sort = 'due';

    public function select(int $id): void
    {
        $this->selectedId = $this->selectedId === $id ? null : $id;
    }

    public function setView(string $view): void
    {
        if (in_array($view, ['register', 'pipeline'], true)) {
            $this->view = $view;
        }
    }

    public function setStatus(string $status): void
    {
        $this->status = $status === 'all' || in_array($status, EventContract::STATUSES, true)
            ? $status : 'all';
        $this->selectedId = null;
    }

    public function setType(string $type): void
    {
        $this->type = $type === 'all' || array_key_exists($type, EventContract::TYPES)
            ? $type : 'all';
    }

    public function sortBy(string $key): void
    {
        if (in_array($key, ['reference', 'event', 'value', 'due'], true)) {
            $this->sort = $key;
        }
    }

    /**
     * One query for the page, with everything the rows read eager-loaded.
     *
     * signatories and payments are counted in PHP rather than by the database
     * because the model's own helpers do the counting — signedCount() knows
     * that an unsigned signatory is a null signed_at, and duplicating that rule
     * in a withCount is how the two come to disagree.
     */
    private function documents(): Collection
    {
        return EventContract::query()
            ->with(['event.client', 'party', 'signatories', 'payments'])
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->get()
            ->filter(function (EventContract $c) {
                if ($this->status !== 'all' && $c->status !== $this->status) {
                    return false;
                }
                if ($this->type !== 'all' && $c->type !== $this->type) {
                    return false;
                }
                if ($this->q === '') {
                    return true;
                }

                $hay = mb_strtolower(implode(' ', array_filter([
                    $c->displayTitle(), $c->reference, $c->event?->name,
                    $c->event?->client?->name, $c->party?->name,
                ])));

                return str_contains($hay, mb_strtolower(trim($this->q)));
            })
            ->values();
    }

    public function render()
    {
        $docs = $this->documents();

        // The next unpaid installment is what makes a contract urgent, so it is
        // what the register sorts and dates by — not the row's created_at.
        $nextDue = fn (EventContract $c) => $c->payments
            ->first(fn ($p) => $p->status() !== 'paid')?->due_on;

        $sorted = match ($this->sort) {
            'reference' => $docs->sortBy(fn ($c) => $c->reference ?? '~'),
            'event' => $docs->sortBy(fn ($c) => $c->event?->name ?? '~'),
            'value' => $docs->sortByDesc(fn ($c) => $c->valueCents()),
            // Undated documents sort last rather than as the epoch.
            default => $docs->sortBy(fn ($c) => $nextDue($c)?->timestamp ?? PHP_INT_MAX),
        };

        $value = fn (Collection $c) => $c->sum(fn (EventContract $d) => $d->valueCents());
        $awaiting = $docs->whereIn('status', ['sent', 'partially_signed']);
        $signed = $docs->where('status', 'signed');

        // Outstanding money is counted off the installments, not the contract
        // value: a signed contract with three of four installments paid is not
        // owed in full, and the register should not imply that it is.
        $outstanding = $docs->sum(
            fn (EventContract $c) => $c->payments->sum(fn ($p) => $p->outstandingCents())
        );

        $docs = $sorted->values();
        $selected = $docs->firstWhere('id', $this->selectedId) ?? $docs->first();

        return view('livewire.contracts-register', [
            'docs' => $docs,
            'selected' => $selected,
            'nextDue' => $nextDue,
            'lanes' => collect(['draft' => 'Draft', 'sent' => 'Out for signature', 'signed' => 'Signed', 'void' => 'Void'])
                ->map(fn (string $label, string $key) => [
                    'key' => $key,
                    'label' => $label,
                    'docs' => $sorted->filter(fn (EventContract $c) => $c->pipelineColumn() === $key)->values(),
                ])
                // Void is only drawn when something is actually void — an empty
                // lane you have to read to dismiss is a lane worth not drawing.
                ->reject(fn (array $lane) => $lane['key'] === 'void' && $lane['docs']->isEmpty())
                ->values(),
            'figures' => [
                ['label' => 'Documents', 'value' => (string) $docs->count(), 'icon' => 'document', 'tone' => 'navy',
                    'note' => Event::whereNull('archived_at')->count().' events in the book'],
                ['label' => 'Awaiting', 'value' => (string) $awaiting->count(), 'icon' => 'clock',
                    'tone' => $awaiting->isEmpty() ? 'blue' : 'gold', 'note' => 'Sent or part-signed'],
                ['label' => 'Signed', 'value' => (string) $signed->count(), 'icon' => 'check', 'tone' => 'green',
                    'note' => 'Fully executed'],
                ['label' => 'Contracted', 'value' => $this->money($value($docs)), 'icon' => 'currency', 'tone' => 'violet',
                    'note' => 'Across every document'],
                ['label' => 'Outstanding', 'value' => $this->money($outstanding), 'icon' => 'chart',
                    'tone' => $outstanding > 0 ? 'red' : 'green', 'note' => 'Still to collect'],
            ],
        ]);
    }

    /** Short money: a register has no room for 35,000,000.00. */
    private function money(int $cents): string
    {
        $v = $cents / 100;

        return 'JD'.match (true) {
            abs($v) >= 1_000_000 => round($v / 1_000_000, 1).'M',
            abs($v) >= 1_000 => round($v / 1_000).'K',
            default => number_format($v),
        };
    }
}
