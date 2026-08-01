<?php

namespace App\Livewire;

use App\Models\Deal;
use App\Models\Proposal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every offer in the book, and every deal still waiting for one.
 *
 * The pipeline has a "proposal" stage and had nothing behind it — the stage
 * recorded that an offer had been made and the offer itself lived in somebody's
 * outbox. So the page opens with the deals that have reached that stage and
 * have no document, the same way Invoices opens with what has not been raised.
 *
 * Accepting an offer wins its deal, and winning is what creates the event, so
 * the figure the client agreed to becomes the event's budget without anybody
 * retyping it. That is the whole point of the page.
 */
#[Layout('components.layouts.app', [
    'title' => 'Proposals',
    'subtitle' => 'What has been offered, what is still out, and what came back a yes.',
])]
class ProposalsDesk extends Component
{
    /** all · draft · sent · expired · accepted · declined */
    #[Url(as: 'state')]
    public string $state = 'all';

    #[Url(as: 'q')]
    public string $q = '';

    /** A decline reason typed into a row, keyed by proposal id. */
    public array $reason = [];

    public bool $showReady = true;

    public function setState(string $state): void
    {
        $this->state = in_array($state, ['all', 'draft', 'sent', 'expired', 'accepted', 'declined'], true)
            ? $state : 'all';
    }

    public function toggleReady(): void
    {
        $this->showReady = ! $this->showReady;
    }

    /* ── the life of an offer ── */

    public function draftFor(int $dealId)
    {
        Gate::authorize('manage-contract');

        $deal = Deal::findOrFail($dealId);

        // One live offer per deal. A superseded offer is declined or expired
        // first, which keeps the history rather than quietly stacking two.
        if ($deal->proposals()->whereIn('status', ['draft', 'sent'])->exists()) {
            return;
        }

        $proposal = Proposal::forDeal($deal);

        // Straight into the editor: an offer with one carried-over line is the
        // start of the work, not the end of it.
        return $this->redirectRoute('proposals.edit', $proposal, navigate: true);
    }

    public function send(int $id): void
    {
        Gate::authorize('manage-contract');

        $proposal = Proposal::findOrFail($id);

        $proposal->update([
            'status' => 'sent',
            'issued_on' => $proposal->issued_on ?? now()->toDateString(),
            'valid_until' => $proposal->valid_until ?? now()->addDays(30)->toDateString(),
        ]);
    }

    /** The client said yes: the deal is won and the event opens. */
    public function accept(int $id): void
    {
        Gate::authorize('manage-contract');

        Proposal::with(['lines', 'deal'])->findOrFail($id)->accept();
    }

    public function decline(int $id): void
    {
        Gate::authorize('manage-contract');

        Proposal::findOrFail($id)->decline($this->reason[$id] ?? null);

        unset($this->reason[$id]);
    }

    /** An expired offer can be put back on the table with a new date. */
    public function extend(int $id, int $days = 30): void
    {
        Gate::authorize('manage-contract');

        Proposal::findOrFail($id)->update([
            'valid_until' => now()->addDays($days)->toDateString(),
        ]);
    }

    /** Only a draft goes. An offer that has been out stays in the book. */
    public function destroyDraft(int $id): void
    {
        Gate::authorize('manage-contract');

        $proposal = Proposal::findOrFail($id);

        if ($proposal->status === 'draft') {
            $proposal->delete();
        }
    }

    /* ── reading ── */

    private function proposals(): Collection
    {
        return Proposal::query()
            ->with(['lines', 'deal', 'client', 'contact', 'event', 'owner'])
            ->get()
            ->filter(function (Proposal $p) {
                if ($this->state !== 'all' && $p->state() !== $this->state) {
                    return false;
                }
                if ($this->q === '') {
                    return true;
                }

                $hay = mb_strtolower(implode(' ', array_filter([
                    $p->number, $p->title, $p->client?->name,
                    $p->contact?->name, $p->deal?->title,
                ])));

                return str_contains($hay, mb_strtolower(trim($this->q)));
            })
            ->values();
    }

    public function render()
    {
        $rows = $this->proposals()
            ->sortByDesc(fn (Proposal $p) => [$p->issued_on?->timestamp ?? 0, $p->id])
            ->values();

        // Deals that reached the proposal stage with nothing sent. This is what
        // the page is for: the stage said an offer had been made, and no offer
        // existed.
        $ready = Deal::query()
            ->with(['client', 'owner'])
            ->whereIn('stage', ['proposal', 'negotiation'])
            ->whereDoesntHave('proposals', fn ($q) => $q->whereIn('status', ['draft', 'sent', 'accepted']))
            ->orderBy('expected_close_on')
            ->get();

        // Across the book, not the filtered view.
        $all = Proposal::with('lines')->get();
        $live = $all->filter->isLive();
        $expired = $all->filter(fn (Proposal $p) => $p->state() === 'expired');
        $accepted = $all->where('status', 'accepted');
        $decided = $all->whereIn('status', ['accepted', 'declined']);

        return view('livewire.proposals-desk', [
            'rows' => $rows,
            'ready' => $ready,
            'figures' => [
                ['label' => 'Out there', 'value' => $this->money($live->sum(fn ($p) => $p->totalCents())),
                    'icon' => 'document', 'tone' => 'blue',
                    'note' => $live->count().' awaiting an answer'],
                ['label' => 'Expiring', 'value' => (string) $live->filter(fn ($p) => ($p->daysLeft() ?? 99) <= 7)->count(),
                    'icon' => 'clock', 'tone' => 'gold', 'note' => 'Within seven days'],
                ['label' => 'Expired', 'value' => (string) $expired->count(),
                    'icon' => 'bell', 'tone' => $expired->isEmpty() ? 'green' : 'red',
                    'note' => 'Lapsed without an answer'],
                ['label' => 'Won', 'value' => $this->money($accepted->sum(fn ($p) => $p->totalCents())),
                    'icon' => 'check', 'tone' => 'green',
                    'note' => $accepted->count().' accepted'],
                // Of the offers that got an answer — a proposal still out has
                // not lost, and counting it as one flatters nothing.
                ['label' => 'Win rate', 'value' => $decided->isEmpty() ? '—'
                    : round($accepted->count() / $decided->count() * 100).'%',
                    'icon' => 'chart', 'tone' => 'violet',
                    'note' => $decided->count().' decided'],
            ],
        ]);
    }

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
