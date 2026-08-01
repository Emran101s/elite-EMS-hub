<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\EventInvoiceItem;
use App\Models\ServiceItem;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One invoice, and the paper it becomes.
 *
 * The ledger could raise an invoice off a contract installment and then had
 * nothing to say: no way to add a line, correct a price, set the terms, or
 * bill anything the schedule did not already describe. So an invoice for four
 * things had to be four invoices, and a typo was permanent.
 *
 * The form is on the left and the document on the right, and the document is
 * the same partial the PDF renders — the preview IS the invoice, so nothing
 * can look right here and wrong in the client's inbox.
 *
 * A sent invoice is still editable. Real offices reissue: what stops a
 * correction becoming a quiet rewrite is that the number stays, the change is
 * audited, and the money follows the lines rather than the lines following the
 * money.
 */
#[Layout('components.layouts.app', ['hideTitleRow' => true])]
class InvoiceEditor extends Component
{
    public Invoice $invoice;

    /* ── the document's own fields ── */
    public string $bill_to = '';
    public ?int $event_id = null;
    public ?int $client_id = null;
    public string $issued_on = '';
    public string $due_on = '';
    public string $currency = '';
    public string $tax_pct = '0';
    public string $fee_pct = '0';
    public string $notes = '';
    public string $terms = '';

    /** The line being edited, or null. 0 means "a new one". */
    public ?int $editingLine = null;

    public string $description = '';
    public string $qty = '1';
    public string $unit = '';

    /** Money typed into the record box. */
    public string $amount = '';

    /* ── the price list ── */

    /** The catalogue item being priced, or null when typing a line by hand. */
    public ?int $pickedId = null;

    /** One number per factor the unit asks for — rooms, nights, and so on. */
    public array $factors = [];

    public string $catalogueQuery = '';

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice->load(['lines', 'event.client', 'client', 'contract']);
        $this->fill([
            'bill_to' => $invoice->bill_to ?? '',
            'event_id' => $invoice->event_id,
            'client_id' => $invoice->client_id,
            'issued_on' => $invoice->issued_on?->toDateString() ?? '',
            'due_on' => $invoice->due_on?->toDateString() ?? '',
            'currency' => $invoice->currency ?: CompanyProfile::currency(),
            'tax_pct' => rtrim(rtrim(number_format((float) $invoice->tax_pct, 2), '0'), '.'),
            'fee_pct' => rtrim(rtrim(number_format((float) $invoice->fee_pct, 2), '0'), '.'),
            'notes' => $invoice->notes ?? '',
            'terms' => $invoice->terms ?? '',
        ]);
    }

    /* ── the document ── */

    /** Autosaved: an invoice half-typed is still an invoice. */
    public function saveDetails(): void
    {
        Gate::authorize('manage-contract');

        $this->validate([
            'bill_to' => 'nullable|string|max:180',
            'issued_on' => 'nullable|date',
            'due_on' => 'nullable|date',
            'currency' => 'required|string|size:3',
            'tax_pct' => 'nullable|numeric|min:0|max:100',
            'fee_pct' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
            'terms' => 'nullable|string|max:2000',
        ]);

        $event = $this->event_id ? Event::find($this->event_id) : null;

        $this->invoice->update([
            'bill_to' => trim($this->bill_to) ?: null,
            'event_id' => $this->event_id ?: null,
            // Following the event's client keeps "billed to" honest when the
            // invoice is moved from one event to another.
            'client_id' => $this->client_id ?: $event?->client_id,
            'issued_on' => $this->issued_on ?: null,
            'due_on' => $this->due_on ?: null,
            'currency' => mb_strtoupper($this->currency),
            'tax_pct' => (float) ($this->tax_pct ?: 0),
            'fee_pct' => (float) ($this->fee_pct ?: 0),
            'notes' => trim($this->notes) ?: null,
            'terms' => trim($this->terms) ?: null,
        ]);

        $this->invoice = $this->invoice->fresh()->load(['lines', 'event.client', 'client', 'contract']);
    }

    /**
     * Attaching an event adopts that event's management fee.
     *
     * The rate is negotiated per engagement and lives on the event, so an
     * invoice for that event should charge what was agreed rather than the
     * house default it happened to be created with. Only ever offered — the
     * fee already typed here is never overwritten in silence.
     */
    public function updatedEventId($value): void
    {
        $event = $value ? Event::find($value) : null;

        if ($event && $event->management_fee_pct !== null) {
            $this->fee_pct = rtrim(rtrim(number_format((float) $event->management_fee_pct, 2), '0'), '.');
        }

        $this->saveDetails();
    }

    /* ── lines ── */

    public function newLine(): void
    {
        Gate::authorize('manage-contract');

        $this->editingLine = 0;
        $this->description = '';
        $this->qty = '1';
        $this->unit = '';
        $this->pickedId = null;
        $this->factors = [];
    }

    /**
     * Price a line from the catalogue.
     *
     * The item brings its own price and its own way of being counted: an
     * accommodation line asks for rooms and nights, a transport line for
     * vehicles and days. The boxes come from ServiceItem::UNITS rather than
     * being guessed at here.
     */
    public function pick(int $itemId): void
    {
        Gate::authorize('manage-contract');

        $item = $this->priceListItem($itemId);

        if (! $item) {
            return;
        }

        $this->editingLine = 0;
        $this->pickedId = $item->id;
        $this->factors = array_fill(0, count($item->factors()), '1');
        $this->unit = (string) ($this->priceOf($item) / 100);
        $this->description = $item->name;
        $this->syncPicked();
    }

    /**
     * The list this invoice prices from.
     *
     * An invoice attached to an event bills from THAT event's items, because
     * that is where the negotiated price lives — a room agreed at 78 for this
     * summit is not the house 95. An invoice with no event has only the house
     * list to go on, which is the right answer for a one-off.
     */
    private function fromEventList(): bool
    {
        return (bool) $this->invoice->event_id;
    }

    private function priceListItem(int $id): EventInvoiceItem|ServiceItem|null
    {
        return $this->fromEventList()
            ? EventInvoiceItem::where('event_id', $this->invoice->event_id)->find($id)
            : ServiceItem::find($id);
    }

    /** What the client is charged for one of it. */
    private function priceOf(EventInvoiceItem|ServiceItem $item): int
    {
        return $item instanceof EventInvoiceItem ? $item->sell_cents : $item->unit_price_cents;
    }

    /** Typing in a factor box re-prices the line as you go. */
    public function updatedFactors(): void
    {
        $this->syncPicked();
    }

    /** Back to typing a line by hand. */
    public function unpick(): void
    {
        $this->pickedId = null;
        $this->factors = [];
    }

    /**
     * Rewrite the quantity and the description from the factor boxes.
     *
     * The description says how the quantity was arrived at — "Double room —
     * 12 rooms × 3 nights" survives being read six months later; the same line
     * with a quantity of 36 and no explanation does not.
     */
    private function syncPicked(): void
    {
        $item = $this->pickedId ? $this->priceListItem($this->pickedId) : null;

        if (! $item) {
            return;
        }

        $qty = $item->quantityFrom($this->factors);

        $this->qty = rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') ?: '0';
        $this->description = $item->describe($this->factors);
    }

    public function editLine(int $id): void
    {
        Gate::authorize('manage-contract');

        $line = $this->invoice->lines->firstWhere('id', $id);

        if (! $line) {
            return;
        }

        $this->editingLine = $id;
        $this->description = $line->description;
        $this->qty = rtrim(rtrim(number_format($line->qty, 2), '0'), '.');
        $this->unit = (string) ($line->unit_cents / 100);
    }

    public function cancelLine(): void
    {
        $this->reset(['editingLine', 'description', 'qty', 'unit', 'pickedId', 'factors']);
    }

    public function saveLine(): void
    {
        Gate::authorize('manage-contract');

        $this->validate([
            'description' => 'required|string|max:250',
            'qty' => 'required|numeric|min:0',
            'unit' => 'nullable|numeric',
        ], [], ['description' => 'description', 'qty' => 'quantity', 'unit' => 'unit price']);

        $fields = [
            'description' => trim($this->description),
            'qty' => (float) $this->qty,
            'unit_cents' => (int) round((float) ($this->unit ?: 0) * 100),
        ];

        if ($this->editingLine) {
            $this->invoice->lines()->whereKey($this->editingLine)->update($fields);
            // update() on a query builder skips model events, and the schedule
            // has to hear about it — see InvoiceLine::booted().
            $this->invoice->fresh()->load('lines')->syncToSchedule();
        } else {
            $this->invoice->lines()->create($fields + [
                'sort' => (int) $this->invoice->lines->max('sort') + 1,
            ]);
        }

        $this->cancelLine();
        $this->invoice = $this->invoice->fresh()->load(['lines', 'event.client', 'client', 'contract']);
    }

    public function deleteLine(int $id): void
    {
        Gate::authorize('manage-contract');

        $this->invoice->lines()->whereKey($id)->first()?->delete();

        $this->cancelLine();
        $this->invoice = $this->invoice->fresh()->load(['lines', 'event.client', 'client', 'contract']);
    }

    public function moveLine(int $id, int $by): void
    {
        Gate::authorize('manage-contract');

        $lines = $this->invoice->lines->values();
        $at = $lines->search(fn (InvoiceLine $l) => $l->id === $id);

        if ($at === false || ! isset($lines[$at + $by])) {
            return;
        }

        $other = $lines[$at + $by];
        $mine = $lines[$at];

        [$a, $b] = [$mine->sort, $other->sort];
        // Equal sorts would leave the pair in id order and the swap would look
        // broken, so a tie is broken before it is written.
        if ($a === $b) {
            [$a, $b] = [$a, $b + $by];
        }

        $mine->update(['sort' => $b]);
        $other->update(['sort' => $a]);

        $this->invoice = $this->invoice->fresh()->load(['lines', 'event.client', 'client', 'contract']);
    }

    /* ── the life of the document ── */

    public function markSent(): void
    {
        Gate::authorize('manage-contract');

        $this->invoice->update([
            'status' => 'sent',
            'issued_on' => $this->invoice->issued_on ?? now()->toDateString(),
        ]);

        $this->invoice = $this->invoice->fresh()->load(['lines', 'event.client', 'client', 'contract']);
    }

    public function record(): void
    {
        Gate::authorize('manage-contract');

        $cents = trim($this->amount) === '' || ! is_numeric($this->amount)
            ? $this->invoice->outstandingCents()
            : max(0, (int) round((float) $this->amount * 100));

        $this->invoice->update([
            'paid_cents' => min($this->invoice->totalCents(), $this->invoice->paid_cents + $cents),
            'paid_at' => now()->toDateString(),
            'status' => $this->invoice->status === 'draft' ? 'sent' : $this->invoice->status,
        ]);

        $this->amount = '';
        $this->invoice = $this->invoice->fresh()->load(['lines', 'event.client', 'client', 'contract']);
    }

    public function clearPaid(): void
    {
        Gate::authorize('manage-contract');

        $this->invoice->update(['paid_cents' => 0, 'paid_at' => null]);
        $this->invoice = $this->invoice->fresh()->load(['lines', 'event.client', 'client', 'contract']);
    }

    public function void(): void
    {
        Gate::authorize('manage-contract');

        $this->invoice->update(['status' => 'void']);
        $this->invoice = $this->invoice->fresh()->load(['lines', 'event.client', 'client', 'contract']);
    }

    public function destroyDraft()
    {
        Gate::authorize('manage-contract');

        if ($this->invoice->status !== 'draft') {
            return null;
        }

        $this->invoice->delete();

        return $this->redirectRoute('invoices.index', navigate: true);
    }

    public function render()
    {
        $company = CompanyProfile::first();

        // Only what is in use, and only when a line is open — a price list is
        // a tool for writing a line, not furniture on the page.
        $search = function ($q) {
            if ($this->catalogueQuery === '') {
                return;
            }
            $t = '%'.mb_strtolower(trim($this->catalogueQuery)).'%';
            $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$t])
                ->orWhereRaw('lower(coalesce(code, "")) like ?', [$t])
                ->orWhereRaw('lower(coalesce(category, "")) like ?', [$t]));
        };

        $catalogue = $this->editingLine === null
            ? collect()
            : ($this->fromEventList()
                ? EventInvoiceItem::where('event_id', $this->invoice->event_id)->active()
                    ->tap($search)->orderBy('category')->orderBy('name')->limit(60)->get()
                : ServiceItem::active()
                    ->tap($search)->orderBy('category')->orderBy('name')->limit(60)->get());

        return view('livewire.invoice-editor', [
            'catalogue' => $catalogue,
            'picked' => $this->pickedId ? $this->priceListItem($this->pickedId) : null,
            // Which list is on offer, so the screen can say so rather than
            // leaving somebody to wonder why the price is not the house one.
            'priceListIsEvent' => $this->fromEventList(),
            'events' => Event::whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'client_id']),
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'company' => [
                'name' => $company->name ?? config('app.name'),
                'address' => $company?->address,
                'email' => $company?->email,
                'phone' => $company?->phone,
            ],
            'theme' => $this->invoice->event?->theme() ?? [
                'primary' => '#0B1F3A', 'accent' => '#D4AF37',
            ],
        ]);
    }
}
