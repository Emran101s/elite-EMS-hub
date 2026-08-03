<?php

namespace App\Livewire;

use App\Models\Requirement;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Gate;

#[Layout('components.layouts.app', ['title' => 'Equipment Catalog'])]
class RequirementsCatalog extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('nullable|numeric|min:0')]
    public string $price = '';

    #[Validate('nullable|string|max:200')]
    public string $notes = '';

    public string $search = '';

    // ── import ──
    public bool $showImport = false;

    public $importFile;

    public function newItem(): void
    {
        $this->reset(['editingId', 'name', 'price', 'notes']);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $r = Requirement::findOrFail($id);
        $this->editingId = $r->id;
        $this->name = $r->name;
        $this->price = $r->unit_price_cents ? (string) ($r->unit_price_cents / 100) : '';
        $this->notes = $r->notes ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('write');
        $this->validate();
        $data = [
            'name' => trim($this->name),
            'unit_price_cents' => $this->price !== '' ? (int) round((float) $this->price * 100) : 0,
            'notes' => trim($this->notes) ?: null,
        ];
        $this->editingId
            ? Requirement::whereKey($this->editingId)->update($data)
            : Requirement::create($data);

        $this->showForm = false;
        $this->reset(['editingId']);
        session()->flash('status', 'Equipment saved.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('write');
        Requirement::whereKey($id)->delete();
    }

    /** Import equipment from an Excel (.xlsx/.xls) or CSV file. */
    public function import(): void
    {
        Gate::authorize('write');
        $this->validate(['importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:8192']]);

        $rows = $this->readRows($this->importFile);
        if (empty($rows)) {
            $this->addError('importFile', 'That file looks empty.');

            return;
        }

        // Map columns from the header row (fall back to positional: name, price, notes).
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows));
        $find = function (array $needles, int $fallback) use ($header) {
            foreach ($header as $i => $h) {
                foreach ($needles as $n) {
                    if ($h !== '' && str_contains($h, $n)) {
                        return $i;
                    }
                }
            }

            return $fallback;
        };
        $nameCol = $find(['name', 'equipment', 'item'], 0);
        $priceCol = $find(['price', 'cost', 'rate', 'amount'], 1);
        $notesCol = $find(['note', 'description', 'spec'], 2);

        $imported = 0;
        foreach ($rows as $row) {
            $name = trim((string) ($row[$nameCol] ?? ''));
            if ($name === '') {
                continue;
            }
            $rawPrice = (string) ($row[$priceCol] ?? '');
            $price = (float) preg_replace('/[^0-9.]/', '', $rawPrice);

            Requirement::create([
                'name' => mb_substr($name, 0, 120),
                'unit_price_cents' => (int) round($price * 100),
                'notes' => trim((string) ($row[$notesCol] ?? '')) ?: null,
            ]);
            $imported++;
        }

        $this->showImport = false;
        $this->reset('importFile');
        session()->flash('status', "Imported {$imported} ".str('item')->plural($imported).' from the file.');
    }

    /** Read every row of the uploaded spreadsheet/CSV into a plain array. */
    private function readRows($file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, ['xlsx', 'xls'], true)) {
            $sheet = IOFactory::load($file->getRealPath())->getActiveSheet();

            return $sheet->toArray(null, true, false, false);
        }

        $rows = [];
        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }
            fclose($handle);
        }

        return $rows;
    }

    public function render()
    {
        $items = Requirement::when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')->get();

        return view('livewire.requirements-catalog', ['items' => $items]);
    }
}
