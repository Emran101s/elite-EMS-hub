<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventInvoiceItem;
use App\Models\ServiceItem;
use App\Support\Taxonomy;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * What this event will be invoiced for, and what each of those costs us.
 *
 * The house price list in Settings is a starting point, not a rate card: a room
 * negotiated at 95 for one summit is 78 for the next, and the cost behind it
 * moves too. So nothing is rolled in automatically — an event's list is
 * prepared here, before anything is invoiced, and the invoice editor bills from
 * it.
 *
 * Cost and sell both live on the row, which is what makes the margin knowable
 * before the work is done rather than after.
 */
class PricingTab extends Component
{
    use WithFileUploads;

    public Event $event;

    public string $q = '';

    public bool $showInactive = false;

    /** The row being edited; 0 means a new one, null means none. */
    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $itemCategory = '';

    public string $itemSection = '';

    public string $detail = '';

    public string $unit = 'item';

    public string $cost = '';

    public string $sell = '';

    public string $tax = '';

    public bool $active = true;

    /** Pulling from the house list. */
    public bool $showCatalogue = false;

    public string $catalogueQuery = '';

    public $importFile = null;

    public string $importMsg = '';

    public function mount(Event $event): void
    {
        $this->event = $event;
    }

    /* ── the rows ── */

    public function newItem(): void
    {
        Gate::authorize('manage-budget');

        $this->reset(['code', 'name', 'itemCategory', 'itemSection', 'detail', 'cost', 'sell', 'tax']);
        $this->editingId = 0;
        $this->unit = 'item';
        $this->active = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-budget');

        $item = $this->event->invoiceItems()->findOrFail($id);

        $this->editingId = $id;
        $this->code = $item->code ?? '';
        $this->name = $item->name;
        $this->itemCategory = $item->category ?? '';
        $this->itemSection = $item->section ?? '';
        $this->detail = $item->detail ?? '';
        $this->unit = $item->unit;
        $this->cost = (string) ($item->cost_cents / 100);
        $this->sell = (string) ($item->sell_cents / 100);
        $this->tax = $item->tax_pct === null ? '' : (string) $item->tax_pct;
        $this->active = $item->active;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'code', 'name', 'itemCategory', 'itemSection', 'detail', 'cost', 'sell', 'tax']);
    }

    public function save(): void
    {
        Gate::authorize('manage-budget');

        $this->validate([
            'name' => 'required|string|max:180',
            'code' => 'nullable|string|max:40',
            'itemCategory' => 'nullable|string|max:80',
            'itemSection' => 'nullable|string|in:'.implode(',', Taxonomy::values('service_section')),
            'unit' => 'required|in:'.implode(',', array_keys(EventInvoiceItem::UNITS)),
            'cost' => 'nullable|numeric|min:0',
            'sell' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0|max:100',
        ], [], ['itemCategory' => 'category']);

        $code = trim($this->code) ?: null;

        // Unique within the event, not across the book: two events may both
        // sell ACC-DBL at different prices, which is the whole point.
        if ($code) {
            $clash = $this->event->invoiceItems()->where('code', $code)
                ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))->exists();

            if ($clash) {
                $this->addError('code', 'This event already prices an item with that code.');

                return;
            }
        }

        $fields = [
            'code' => $code,
            'name' => trim($this->name),
            'category' => trim($this->itemCategory) ?: null,
            'section' => $this->itemSection ?: null,
            'detail' => trim($this->detail) ?: null,
            'unit' => $this->unit,
            // Rounded to a tenth of a cent, not a whole one — cost_cents and
            // sell_cents are decimal(15,1) now, so a typed 127.116 keeps its
            // third decimal instead of being rounded to 127.12 on save.
            'cost_cents' => round((float) ($this->cost ?: 0) * 100, 1),
            'sell_cents' => round((float) ($this->sell ?: 0) * 100, 1),
            'currency' => $this->event->currency ?: 'JOD',
            'tax_pct' => trim($this->tax) === '' ? null : (float) $this->tax,
            'active' => $this->active,
        ];

        if ($this->editingId) {
            $this->event->invoiceItems()->findOrFail($this->editingId)->update($fields);
        } else {
            $this->event->invoiceItems()->create($fields + [
                'sort' => (int) $this->event->invoiceItems()->max('sort') + 1,
            ]);
        }

        $this->cancel();
    }

    public function toggleActive(int $id): void
    {
        Gate::authorize('manage-budget');

        $item = $this->event->invoiceItems()->findOrFail($id);
        $item->update(['active' => ! $item->active]);
    }

    public function destroy(int $id): void
    {
        Gate::authorize('manage-budget');

        $this->event->invoiceItems()->findOrFail($id)->delete();
        $this->cancel();
    }

    /* ── pulling from the house list ── */

    public function toggleCatalogue(): void
    {
        $this->showCatalogue = ! $this->showCatalogue;
    }

    /**
     * Copy one house item onto this event.
     *
     * Deliberately one at a time and never automatic: a stale copy of a price
     * nobody agreed is worse than no copy at all.
     */
    public function pullFromHouse(int $serviceItemId): void
    {
        Gate::authorize('manage-budget');

        EventInvoiceItem::fromCatalogue($this->event, ServiceItem::findOrFail($serviceItemId));
    }

    /* ── import ── */

    public function import(): void
    {
        Gate::authorize('manage-budget');

        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120']);

        $rows = IOFactory::load($this->importFile->getRealPath())
            ->getActiveSheet()->toArray(null, true, true, false);

        // Columns are found by their heading rather than counted, so a sheet
        // that gains a column later does not silently shift every value on it
        // one place to the left.
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), array_shift($rows) ?: []);

        $at = function (array $row, string $name, array $also = []) use ($header) {
            foreach ([$name, ...$also] as $label) {
                $i = array_search($label, $header, true);

                if ($i !== false) {
                    return $row[$i] ?? null;
                }
            }

            return null;
        };

        $units = collect(EventInvoiceItem::UNITS)
            ->mapWithKeys(fn (array $m, string $k) => [mb_strtolower($m[0]) => $k])->all();

        $sections = collect(Taxonomy::options('service_section'))
            ->mapWithKeys(fn (string $label, string $key) => [mb_strtolower($label) => $key])->all();

        $made = $fixed = $skipped = 0;

        foreach ($rows as $row) {
            $code = $at($row, 'code');
            $name = trim((string) $at($row, 'item', ['name']));
            $category = $at($row, 'category');
            $sectionLabel = $at($row, 'section');
            $unitLabel = $at($row, 'unit', ['sold by']);
            $cost = $at($row, 'costs us', ['cost']);
            $sell = $at($row, 'we charge', ['sell', 'price']);
            $tax = $at($row, 'tax %', ['tax']);
            $detail = $at($row, 'detail');

            if ($name === '') {
                $skipped++;

                continue;
            }

            $money = fn ($v) => round((float) str_replace(',', '', (string) $v) * 100, 1);

            $fields = [
                'name' => $name,
                'category' => trim((string) $category) ?: null,
                'section' => $sections[mb_strtolower(trim((string) $sectionLabel))] ?? null,
                'unit' => $units[mb_strtolower(trim((string) $unitLabel))] ?? 'item',
                'cost_cents' => $money($cost),
                'sell_cents' => $money($sell),
                'currency' => $this->event->currency ?: 'JOD',
                'tax_pct' => is_numeric($tax) ? (float) $tax : null,
                'detail' => trim((string) $detail) ?: null,
                'active' => true,
            ];

            $code = trim((string) $code) ?: null;

            // A code already on THIS event is a correction; re-uploading a
            // repriced sheet is what somebody doing it means.
            $existing = $code ? $this->event->invoiceItems()->where('code', $code)->first() : null;

            if ($existing) {
                $existing->update($fields);
                $fixed++;
            } else {
                $this->event->invoiceItems()->create($fields + [
                    'code' => $code,
                    'sort' => (int) $this->event->invoiceItems()->max('sort') + 1,
                ]);
                $made++;
            }
        }

        $this->importMsg = trim(collect([
            $made ? $made.' added' : null,
            $fixed ? $fixed.' repriced' : null,
            $skipped ? $skipped.' skipped (no name)' : null,
        ])->filter()->implode(' · ')) ?: 'Nothing to import — the sheet was empty.';

        $this->reset('importFile');
    }

    public function render()
    {
        $items = $this->event->invoiceItems()
            ->when(! $this->showInactive, fn ($q) => $q->where('active', true))
            ->search($this->q)
            ->get();

        // What is already priced here, so the house list can say so rather than
        // offering the same room twice.
        $taken = $this->event->invoiceItems()->pluck('service_item_id')->filter()->all();

        return view('livewire.hub.pricing-tab', [
            'items' => $items,
            'groups' => $items->groupBy(fn (EventInvoiceItem $i) => $i->category ?: 'Uncategorised'),
            'catalogue' => $this->showCatalogue
                ? ServiceItem::active()->search($this->catalogueQuery)
                    ->orderBy('section')->orderBy('category')->orderBy('name')->get()
                : collect(),
            'taken' => $taken,
            'sections' => Taxonomy::options('service_section'),
            'underwater' => $items->filter->isUnderwater(),
            'totals' => [
                'cost' => $items->sum('cost_cents'),
                'sell' => $items->sum('sell_cents'),
            ],
        ]);
    }
}
