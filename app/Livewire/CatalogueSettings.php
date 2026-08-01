<?php

namespace App\Livewire;

use App\Models\ServiceItem;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * The price list: what the company sells, and what one of each costs.
 *
 * Every invoice line used to be typed from nothing, so the same room rate was
 * entered a dozen different ways and nobody could answer "what do we charge for
 * a double room" without opening an old invoice.
 *
 * The unit is the point. Accommodation is sold per room per night, transport
 * per vehicle per day — see ServiceItem::UNITS, which says how many numbers it
 * takes to count each thing, so the invoice editor can ask for rooms AND nights
 * rather than leaving somebody to multiply in their head.
 */
#[Layout('components.layouts.app', [
    'title' => 'Price list',
    'subtitle' => 'What the company sells, what one of each costs, and how it is counted.',
])]
class CatalogueSettings extends Component
{
    use WithFileUploads;

    public string $q = '';
    public string $category = 'all';
    public bool $showInactive = false;

    /** The row being edited; 0 means a new one, null means none. */
    public ?int $editingId = null;

    public string $code = '';
    public string $name = '';
    public string $itemCategory = '';
    public string $detail = '';
    public string $unit = 'item';
    public string $price = '';
    public string $currency = 'JOD';
    public string $tax = '';
    public bool $active = true;

    /** Import */
    public $importFile = null;
    public string $importMsg = '';

    public function newItem(): void
    {
        Gate::authorize('write');

        $this->reset(['code', 'name', 'itemCategory', 'detail', 'price', 'tax']);
        $this->editingId = 0;
        $this->unit = 'item';
        $this->currency = 'JOD';
        $this->active = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('write');

        $item = ServiceItem::findOrFail($id);

        $this->editingId = $id;
        $this->code = $item->code ?? '';
        $this->name = $item->name;
        $this->itemCategory = $item->category ?? '';
        $this->detail = $item->detail ?? '';
        $this->unit = $item->unit;
        $this->price = (string) ($item->unit_price_cents / 100);
        $this->currency = $item->currency;
        $this->tax = $item->tax_pct === null ? '' : (string) $item->tax_pct;
        $this->active = $item->active;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'code', 'name', 'itemCategory', 'detail', 'price', 'tax']);
    }

    public function save(): void
    {
        Gate::authorize('write');

        $this->validate([
            'name' => 'required|string|max:180',
            'code' => 'nullable|string|max:40|unique:service_items,code'.($this->editingId ? ','.$this->editingId : ''),
            'itemCategory' => 'nullable|string|max:80',
            'unit' => 'required|in:'.implode(',', array_keys(ServiceItem::UNITS)),
            'price' => 'nullable|numeric|min:0',
            'currency' => 'required|string|size:3',
            'tax' => 'nullable|numeric|min:0|max:100',
        ], [], ['itemCategory' => 'category', 'price' => 'unit price', 'tax' => 'tax rate']);

        $fields = [
            'code' => trim($this->code) ?: null,
            'name' => trim($this->name),
            'category' => trim($this->itemCategory) ?: null,
            'detail' => trim($this->detail) ?: null,
            'unit' => $this->unit,
            'unit_price_cents' => (int) round((float) ($this->price ?: 0) * 100),
            'currency' => mb_strtoupper($this->currency),
            // Blank means "whatever the document is set to"; a number overrides.
            'tax_pct' => trim($this->tax) === '' ? null : (float) $this->tax,
            'active' => $this->active,
        ];

        if ($this->editingId) {
            ServiceItem::findOrFail($this->editingId)->update($fields);
        } else {
            ServiceItem::create($fields + ['sort' => (int) ServiceItem::max('sort') + 1]);
        }

        $this->cancel();
    }

    /** Retired rather than deleted where it has been used — history is history. */
    public function toggleActive(int $id): void
    {
        Gate::authorize('write');

        $item = ServiceItem::findOrFail($id);
        $item->update(['active' => ! $item->active]);
    }

    public function destroy(int $id): void
    {
        Gate::authorize('write');

        ServiceItem::findOrFail($id)->delete();
        $this->cancel();
    }

    /**
     * Import a filled template.
     *
     * Matching on code where there is one makes a re-import a correction rather
     * than a duplicate — which is what somebody re-uploading a sheet means, and
     * the alternative is a price list with everything in it twice.
     */
    public function import(): void
    {
        Gate::authorize('write');

        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120']);

        $rows = IOFactory::load($this->importFile->getRealPath())
            ->getActiveSheet()->toArray(null, true, true, false);

        // The template's own header row, and any blank rows people leave behind.
        array_shift($rows);

        $units = collect(ServiceItem::UNITS)
            ->mapWithKeys(fn (array $m, string $k) => [mb_strtolower($m[0]) => $k])->all();

        $made = $fixed = $skipped = 0;

        foreach ($rows as $row) {
            [$code, $name, $category, $unitLabel, $price, $currency, $tax, $detail] =
                array_pad(array_slice($row, 0, 8), 8, null);

            $name = trim((string) $name);

            if ($name === '') {
                $skipped++;

                continue;
            }

            $fields = [
                'name' => $name,
                'category' => trim((string) $category) ?: null,
                'unit' => $units[mb_strtolower(trim((string) $unitLabel))] ?? 'item',
                'unit_price_cents' => (int) round((float) str_replace(',', '', (string) $price) * 100),
                'currency' => mb_strtoupper(trim((string) $currency) ?: 'JOD'),
                'tax_pct' => is_numeric($tax) ? (float) $tax : null,
                'detail' => trim((string) $detail) ?: null,
                'active' => true,
            ];

            $code = trim((string) $code) ?: null;

            $existing = $code ? ServiceItem::where('code', $code)->first() : null;

            if ($existing) {
                $existing->update($fields);
                $fixed++;
            } else {
                ServiceItem::create($fields + ['code' => $code, 'sort' => (int) ServiceItem::max('sort') + 1]);
                $made++;
            }
        }

        $this->importMsg = trim(collect([
            $made ? $made.' added' : null,
            $fixed ? $fixed.' updated' : null,
            $skipped ? $skipped.' skipped (no name)' : null,
        ])->filter()->implode(' · ')) ?: 'Nothing to import — the sheet was empty.';

        $this->reset('importFile');
    }

    public function render()
    {
        $items = ServiceItem::query()
            ->when(! $this->showInactive, fn ($q) => $q->active())
            ->when($this->category !== 'all', fn ($q) => $q->where('category', $this->category))
            ->when($this->q !== '', function ($q) {
                $t = '%'.mb_strtolower(trim($this->q)).'%';
                $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$t])
                    ->orWhereRaw('lower(coalesce(code, "")) like ?', [$t])
                    ->orWhereRaw('lower(coalesce(category, "")) like ?', [$t]));
            })
            ->orderBy('category')->orderBy('sort')->orderBy('name')
            ->get();

        return view('livewire.catalogue-settings', [
            'items' => $items,
            'groups' => $items->groupBy(fn (ServiceItem $i) => $i->category ?: 'Uncategorised'),
            'categories' => ServiceItem::query()->whereNotNull('category')
                ->distinct()->orderBy('category')->pluck('category'),
            'total' => ServiceItem::count(),
        ]);
    }
}
