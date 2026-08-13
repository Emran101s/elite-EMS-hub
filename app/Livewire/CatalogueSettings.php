<?php

namespace App\Livewire;

use App\Models\CompanyProfile;
use App\Models\ServiceItem;
use App\Support\Taxonomy;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
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
 *
 * The list is divided into sections by where a service comes from — what the
 * hotel provides, what is brought in from outside, what is rented. Each section
 * is its own tab with its own import, because those sheets arrive separately
 * from separate people. A document picking a price still searches all of them.
 */
#[Layout('components.layouts.app', [
    'title' => 'Price list',
    'hideTitleRow' => true,
])]
class CatalogueSettings extends Component
{
    use WithFileUploads;

    public string $q = '';
    public string $category = 'all';
    public bool $showInactive = false;

    /**
     * The open section — a taxonomy key, 'all', or 'none' for the unfiled.
     * In the URL so a section can be linked to and bookmarked.
     */
    #[Url(as: 'section')]
    public string $section = 'all';

    /** The row being edited; 0 means a new one, null means none. */
    public ?int $editingId = null;

    public string $code = '';
    public string $name = '';
    public string $itemCategory = '';
    public string $itemSection = '';
    public string $detail = '';
    public string $unit = 'item';
    public string $price = '';
    public string $currency = '';
    public string $tax = '';
    public bool $active = true;

    /** Import */
    public $importFile = null;
    public string $importMsg = '';

    /**
     * Open a section.
     *
     * The category filter is dropped on the way: "Catering" is a category in
     * Hotel services and means nothing in Equipment, so carrying it across
     * shows an empty list and looks like a bug.
     */
    public function pickSection(string $section): void
    {
        $this->section = $section;
        $this->category = 'all';
        $this->importMsg = '';
        $this->cancel();
    }

    public function newItem(): void
    {
        Gate::authorize('write');

        $this->reset(['code', 'name', 'itemCategory', 'detail', 'price', 'tax']);
        $this->editingId = 0;
        $this->unit = 'item';
        $this->currency = CompanyProfile::currency();
        $this->active = true;

        // Adding while a section is open files it there, which is what somebody
        // who just clicked that tab means.
        $this->itemSection = in_array($this->section, ['all', 'none'], true) ? '' : $this->section;
    }

    public function edit(int $id): void
    {
        Gate::authorize('write');

        $item = ServiceItem::findOrFail($id);

        $this->editingId = $id;
        $this->code = $item->code ?? '';
        $this->name = $item->name;
        $this->itemCategory = $item->category ?? '';
        $this->itemSection = $item->section ?? '';
        $this->detail = $item->detail ?? '';
        $this->unit = $item->unit;
        $this->price = (string) ($item->unit_price_cents / 100);
        $this->currency = $item->currency;
        $this->tax = $item->tax_pct === null ? '' : (string) $item->tax_pct;
        $this->active = $item->active;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'code', 'name', 'itemCategory', 'itemSection', 'detail', 'price', 'tax']);
    }

    public function save(): void
    {
        Gate::authorize('write');

        $this->validate([
            'name' => 'required|string|max:180',
            'code' => 'nullable|string|max:40|unique:service_items,code'.($this->editingId ? ','.$this->editingId : ''),
            'itemCategory' => 'nullable|string|max:80',
            'itemSection' => 'nullable|string|in:'.implode(',', Taxonomy::values('service_section')),
            'unit' => 'required|in:'.implode(',', array_keys(ServiceItem::UNITS)),
            'price' => 'nullable|numeric|min:0',
            'currency' => 'required|string|size:3',
            'tax' => 'nullable|numeric|min:0|max:100',
        ], [], ['itemCategory' => 'category', 'price' => 'unit price', 'tax' => 'tax rate']);

        $fields = [
            'code' => trim($this->code) ?: null,
            'name' => trim($this->name),
            'category' => trim($this->itemCategory) ?: null,
            'section' => $this->itemSection ?: null,
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
     *
     * Importing while a section is open files every row into it. That is the
     * point of the per-section import: the hotel's sheet and the production
     * house's sheet arrive separately and neither of them names a section.
     */
    public function import(): void
    {
        Gate::authorize('write');

        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120']);

        $rows = IOFactory::load($this->importFile->getRealPath())
            ->getActiveSheet()->toArray(null, true, true, false);

        // Columns are found by their heading, not by counting.
        //
        // The sheet gained a Section column in the middle, and a positional
        // reader would have taken the section of every older sheet still out
        // there for its unit — silently, and only visibly wrong weeks later on
        // an invoice. Anything the heading row does not name is simply absent.
        $header = array_map(
            fn ($h) => mb_strtolower(trim((string) $h)),
            array_shift($rows) ?: []
        );

        $at = function (array $row, string $name, array $also = []) use ($header) {
            foreach ([$name, ...$also] as $label) {
                $i = array_search($label, $header, true);

                if ($i !== false) {
                    return $row[$i] ?? null;
                }
            }

            return null;
        };

        $units = collect(ServiceItem::UNITS)
            ->mapWithKeys(fn (array $m, string $k) => [mb_strtolower($m[0]) => $k])->all();

        // The sheet may name its own section by label; the open tab is the
        // default for every row that does not.
        $sections = collect(Taxonomy::options('service_section'))
            ->mapWithKeys(fn (string $label, string $key) => [mb_strtolower($label) => $key])->all();

        $openSection = in_array($this->section, ['all', 'none'], true) ? null : $this->section;

        $made = $fixed = $skipped = 0;

        foreach ($rows as $row) {
            $code = $at($row, 'code');
            $name = trim((string) $at($row, 'item', ['name']));
            $category = $at($row, 'category');
            $sectionLabel = $at($row, 'section');
            $unitLabel = $at($row, 'unit', ['sold by']);
            $price = $at($row, 'unit price', ['price']);
            $currency = $at($row, 'currency');
            $tax = $at($row, 'tax %', ['tax']);
            $detail = $at($row, 'detail');

            if ($name === '') {
                $skipped++;

                continue;
            }

            $fields = [
                'name' => $name,
                'category' => trim((string) $category) ?: null,
                'section' => $sections[mb_strtolower(trim((string) $sectionLabel))] ?? $openSection,
                'unit' => $units[mb_strtolower(trim((string) $unitLabel))] ?? 'item',
                'unit_price_cents' => (int) round((float) str_replace(',', '', (string) $price) * 100),
                'currency' => mb_strtoupper(trim((string) $currency) ?: CompanyProfile::currency()),
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
            ->when($this->section !== 'all', fn ($q) => $q->inSection($this->section))
            ->when($this->category !== 'all', fn ($q) => $q->where('category', $this->category))
            ->search($this->q)
            ->orderBy('category')->orderBy('sort')->orderBy('name')
            ->get();

        // Every section gets a tab whether or not anything is in it yet — an
        // empty section with an import button is how a section gets filled.
        $counts = ServiceItem::query()
            ->when(! $this->showInactive, fn ($q) => $q->active())
            ->selectRaw('coalesce(section, \'none\') as s, count(*) as n')
            ->groupBy('s')->pluck('n', 's');

        return view('livewire.catalogue-settings', [
            'items' => $items,
            'groups' => $items->groupBy(fn (ServiceItem $i) => $i->category ?: 'Uncategorised'),
            'categories' => ServiceItem::query()
                ->when($this->section !== 'all', fn ($q) => $q->inSection($this->section))
                ->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'sections' => Taxonomy::options('service_section'),
            'counts' => $counts,
            'total' => ServiceItem::count(),
        ]);
    }
}
