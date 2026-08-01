<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Proposal;
use App\Models\ProposalLine;
use App\Models\ServiceItem;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One offer, and the paper it becomes.
 *
 * The desk could start a proposal from a deal and then had nothing to say: the
 * only line was the one carried over from the deal's value, and there was no
 * way to add a second, mark one optional, or price any of it from the list of
 * what the company actually sells.
 *
 * Form on the left, document on the right, and the document is the same partial
 * the PDF renders — the preview IS the offer.
 *
 * Lines are priced from the same catalogue the invoice editor uses, so a room
 * quoted at proposal time and the same room billed six months later come from
 * one price.
 */
#[Layout('components.layouts.app', ['hideTitleRow' => true])]
class ProposalEditor extends Component
{
    public Proposal $proposal;

    /* ── the document ── */
    public string $title = '';
    public ?int $client_id = null;
    public ?int $contact_id = null;
    public string $issued_on = '';
    public string $valid_until = '';
    public string $currency = '';
    public string $tax_pct = '0';
    public string $fee_pct = '0';
    public string $summary = '';
    public string $terms = '';

    /* ── the line being written ── */
    public ?int $editingLine = null;
    public string $description = '';
    public string $detail = '';
    public string $qty = '1';
    public string $unit = '';
    public bool $optional = false;

    /* ── the price list ── */
    public ?int $pickedId = null;
    public array $factors = [];
    public string $catalogueQuery = '';

    /** A reason typed into the decline box. */
    public string $reason = '';

    public function mount(Proposal $proposal): void
    {
        $this->proposal = $proposal->load(['lines', 'client', 'contact', 'owner', 'deal', 'event']);
        $this->fill([
            'title' => $proposal->title,
            'client_id' => $proposal->client_id,
            'contact_id' => $proposal->contact_id,
            'issued_on' => $proposal->issued_on?->toDateString() ?? '',
            'valid_until' => $proposal->valid_until?->toDateString() ?? '',
            'currency' => $proposal->currency ?: CompanyProfile::currency(),
            'tax_pct' => $this->pct($proposal->tax_pct),
            'fee_pct' => $this->pct($proposal->fee_pct),
            'summary' => $proposal->summary ?? '',
            'terms' => $proposal->terms ?? '',
        ]);
    }

    private function pct(?float $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    }

    private function refresh(): void
    {
        $this->proposal = $this->proposal->fresh()->load(['lines', 'client', 'contact', 'owner', 'deal', 'event']);
    }

    public function saveDetails(): void
    {
        Gate::authorize('manage-contract');

        $this->validate([
            'title' => 'required|string|max:200',
            'issued_on' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'currency' => 'required|string|size:3',
            'tax_pct' => 'nullable|numeric|min:0|max:100',
            'fee_pct' => 'nullable|numeric|min:0|max:100',
            'summary' => 'nullable|string|max:3000',
            'terms' => 'nullable|string|max:3000',
        ]);

        $this->proposal->update([
            'title' => trim($this->title),
            'client_id' => $this->client_id ?: null,
            'contact_id' => $this->contact_id ?: null,
            'issued_on' => $this->issued_on ?: null,
            'valid_until' => $this->valid_until ?: null,
            'currency' => mb_strtoupper($this->currency),
            'tax_pct' => (float) ($this->tax_pct ?: 0),
            'fee_pct' => (float) ($this->fee_pct ?: 0),
            'summary' => trim($this->summary) ?: null,
            'terms' => trim($this->terms) ?: null,
        ]);

        $this->refresh();
    }

    /**
     * Put the house fee on an offer that has none.
     *
     * Offered rather than assumed: an offer that starts from a deal's value is
     * already priced whole, and adding a fee to that silently inflates it.
     * Once it is priced from real lines the fee belongs, and this is one click.
     */
    public function applyHouseFee(): void
    {
        Gate::authorize('manage-contract');

        $this->fee_pct = $this->pct(CompanyProfile::feePct());
        $this->saveDetails();
    }

    /* ── lines ── */

    public function newLine(): void
    {
        Gate::authorize('manage-contract');

        $this->editingLine = 0;
        $this->reset(['description', 'detail', 'unit', 'pickedId', 'factors', 'optional']);
        $this->qty = '1';
    }

    /** Price a line from the catalogue — see ServiceItem::UNITS. */
    public function pick(int $itemId): void
    {
        Gate::authorize('manage-contract');

        $item = ServiceItem::findOrFail($itemId);

        $this->editingLine = 0;
        $this->pickedId = $item->id;
        $this->factors = array_fill(0, count($item->factors()), '1');
        $this->unit = (string) ($item->unit_price_cents / 100);
        $this->detail = $item->detail ?? '';
        $this->syncPicked();
    }

    public function updatedFactors(): void
    {
        $this->syncPicked();
    }

    public function unpick(): void
    {
        $this->pickedId = null;
        $this->factors = [];
    }

    private function syncPicked(): void
    {
        $item = $this->pickedId ? ServiceItem::find($this->pickedId) : null;

        if (! $item) {
            return;
        }

        $this->qty = rtrim(rtrim(number_format($item->quantityFrom($this->factors), 2, '.', ''), '0'), '.') ?: '0';
        $this->description = $item->describe($this->factors);
    }

    public function editLine(int $id): void
    {
        Gate::authorize('manage-contract');

        $line = $this->proposal->lines->firstWhere('id', $id);

        if (! $line) {
            return;
        }

        $this->editingLine = $id;
        $this->description = $line->description;
        $this->detail = $line->detail ?? '';
        $this->qty = rtrim(rtrim(number_format($line->qty, 2), '0'), '.');
        $this->unit = (string) ($line->unit_cents / 100);
        $this->optional = $line->optional;
        $this->pickedId = null;
        $this->factors = [];
    }

    public function cancelLine(): void
    {
        $this->reset(['editingLine', 'description', 'detail', 'qty', 'unit', 'optional', 'pickedId', 'factors']);
    }

    public function saveLine(): void
    {
        Gate::authorize('manage-contract');

        $this->validate([
            'description' => 'required|string|max:250',
            'detail' => 'nullable|string|max:500',
            'qty' => 'required|numeric|min:0',
            'unit' => 'nullable|numeric',
        ], [], ['qty' => 'quantity', 'unit' => 'unit price']);

        $fields = [
            'description' => trim($this->description),
            'detail' => trim($this->detail) ?: null,
            'qty' => (float) $this->qty,
            'unit_cents' => (int) round((float) ($this->unit ?: 0) * 100),
            'optional' => $this->optional,
        ];

        if ($this->editingLine) {
            $this->proposal->lines()->whereKey($this->editingLine)->update($fields);
        } else {
            $this->proposal->lines()->create($fields + [
                'sort' => (int) $this->proposal->lines->max('sort') + 1,
            ]);
        }

        $this->cancelLine();
        $this->refresh();
    }

    public function deleteLine(int $id): void
    {
        Gate::authorize('manage-contract');

        $this->proposal->lines()->whereKey($id)->first()?->delete();
        $this->cancelLine();
        $this->refresh();
    }

    /** Toggling optional from the list, without opening the line. */
    public function toggleOptional(int $id): void
    {
        Gate::authorize('manage-contract');

        $line = $this->proposal->lines->firstWhere('id', $id);
        $line?->update(['optional' => ! $line->optional]);

        $this->refresh();
    }

    public function moveLine(int $id, int $by): void
    {
        Gate::authorize('manage-contract');

        $lines = $this->proposal->lines->values();
        $at = $lines->search(fn (ProposalLine $l) => $l->id === $id);

        if ($at === false || ! isset($lines[$at + $by])) {
            return;
        }

        $other = $lines[$at + $by];
        $mine = $lines[$at];

        [$a, $b] = [$mine->sort, $other->sort];

        if ($a === $b) {
            [$a, $b] = [$a, $b + $by];
        }

        $mine->update(['sort' => $b]);
        $other->update(['sort' => $a]);

        $this->refresh();
    }

    /* ── the life of an offer ── */

    public function send(): void
    {
        Gate::authorize('manage-contract');

        $this->proposal->update([
            'status' => 'sent',
            'issued_on' => $this->proposal->issued_on ?? now()->toDateString(),
            'valid_until' => $this->proposal->valid_until ?? now()->addDays(30)->toDateString(),
        ]);

        $this->refresh();
    }

    /** The client said yes: the deal is won and the event opens. */
    public function accept(): void
    {
        Gate::authorize('manage-contract');

        $this->proposal->load(['lines', 'deal'])->accept();
        $this->refresh();
    }

    public function decline(): void
    {
        Gate::authorize('manage-contract');

        $this->proposal->decline(trim($this->reason) ?: null);
        $this->reason = '';
        $this->refresh();
    }

    public function extend(int $days = 30): void
    {
        Gate::authorize('manage-contract');

        $this->proposal->update(['valid_until' => now()->addDays($days)->toDateString()]);
        $this->refresh();
    }

    public function destroyDraft()
    {
        Gate::authorize('manage-contract');

        if ($this->proposal->status !== 'draft') {
            return null;
        }

        $this->proposal->delete();

        return $this->redirectRoute('proposals.index', navigate: true);
    }

    public function render()
    {
        $company = CompanyProfile::first();

        // Only while a line is open — a price list is a tool for writing a
        // line, not furniture on the page.
        $catalogue = $this->editingLine === null
            ? collect()
            : ServiceItem::active()
                ->when($this->catalogueQuery !== '', function ($q) {
                    $t = '%'.mb_strtolower(trim($this->catalogueQuery)).'%';
                    $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$t])
                        ->orWhereRaw('lower(coalesce(code, "")) like ?', [$t])
                        ->orWhereRaw('lower(coalesce(category, "")) like ?', [$t]));
                })
                ->orderBy('category')->orderBy('name')->limit(60)->get();

        return view('livewire.proposal-editor', [
            'catalogue' => $catalogue,
            'picked' => $this->pickedId ? ServiceItem::find($this->pickedId) : null,
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'contacts' => $this->client_id
                ? Contact::where('client_id', $this->client_id)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'company' => [
                'name' => $company->name ?? config('app.name'),
                'address' => $company?->address,
                'email' => $company?->email,
                'phone' => $company?->phone,
            ],
            'theme' => ['primary' => '#0B1F3A', 'accent' => '#D4AF37'],
        ]);
    }
}
