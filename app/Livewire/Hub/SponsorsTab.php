<?php

namespace App\Livewire\Hub;

use App\Livewire\Concerns\BulkSelectable;
use App\Models\Event;
use App\Models\EventSponsor;
use Livewire\Attributes\Validate;
use Livewire\Component;

class SponsorsTab extends Component
{
    use BulkSelectable;

    public Event $event;

    public function deleteSelected(): void
    {
        $this->event->sponsors()->whereIn('id', $this->selectedIds())->delete();
        $this->clearSelection();
    }

    // ── sponsor form ──
    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('nullable|string|max:80')]
    public string $package = '';

    #[Validate('nullable|numeric|min:0')]
    public string $amount = '';

    #[Validate('nullable|numeric|min:0')]
    public string $paid = '';

    #[Validate('required|in:pending,partial,paid')]
    public string $payment_status = 'pending';

    #[Validate('nullable|string|max:400')]
    public string $notes = '';

    // ── sponsorship target ──
    public string $sponsorshipTarget = '';

    // ── package catalog management ──
    public string $newPackageName = '';

    public string $newPackagePrice = '';

    public string $newPackageSlots = '';

    public ?int $editingPackageId = null;

    public string $packageEditName = '';

    public string $packageEditPrice = '';

    public string $packageEditBlurb = '';

    public string $packageEditBenefits = '';

    public string $packageEditSlots = '';

    public function mount(): void
    {
        $this->event->ensureSponsorPackages();
        $this->sponsorshipTarget = $this->event->sponsorship_target_cents ? (string) ($this->event->sponsorship_target_cents / 100) : '';
    }

    public function updatedSponsorshipTarget(): void
    {
        $this->event->update([
            'sponsorship_target_cents' => is_numeric($this->sponsorshipTarget) ? (int) round((float) $this->sponsorshipTarget * 100) : null,
        ]);
    }

    // ── Selling a sponsorship ─────────────────────────────────
    public function newItem(): void
    {
        $this->reset(['editingId', 'name', 'amount', 'paid', 'notes']);
        $this->payment_status = 'pending';
        $first = $this->event->sponsorPackages()->first();
        $this->package = $first?->name ?? '';
        $this->amount = $first && $first->price_cents ? (string) ($first->price_cents / 100) : '';
        $this->showForm = true;
    }

    /** Picking a package auto-fills the deal amount from its preset price. */
    public function updatedPackage($value): void
    {
        $pkg = $this->event->sponsorPackages()->where('name', $value)->first();
        if ($pkg) {
            $this->amount = $pkg->price_cents ? (string) ($pkg->price_cents / 100) : '';
        }
    }

    public function edit(int $id): void
    {
        $s = $this->event->sponsors()->findOrFail($id);
        $this->editingId = $s->id;
        $this->name = $s->name;
        $this->package = $s->package ?? '';
        $this->amount = $s->amount_cents ? (string) ($s->amount_cents / 100) : '';
        $this->paid = $s->paid_cents ? (string) ($s->paid_cents / 100) : '';
        $this->payment_status = in_array($s->payment_status, EventSponsor::PAYMENT_STATUSES, true) ? $s->payment_status : 'pending';
        $this->notes = $s->notes ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        // Enforce the package's max slots — can't sell more than are available.
        if ($this->package) {
            $pkg = $this->event->sponsorPackages()->where('name', $this->package)->first();
            if ($pkg && $pkg->slots !== null) {
                $sold = $this->event->sponsors()->where('package', $this->package)
                    ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))->count();
                if ($sold >= $pkg->slots) {
                    $this->addError('package', "All {$pkg->slots} “{$this->package}” slot".($pkg->slots === 1 ? '' : 's').' are already sold.');

                    return;
                }
            }
        }

        $data = [
            'name' => $this->name,
            'package' => $this->package ?: null,
            'amount_cents' => (int) round((float) ($this->amount ?: 0) * 100),
            'paid_cents' => (int) round((float) ($this->paid ?: 0) * 100),
            'payment_status' => $this->payment_status,
            'notes' => $this->notes ?: null,
        ];

        $this->editingId
            ? $this->event->sponsors()->findOrFail($this->editingId)->update($data)
            : $this->event->sponsors()->create($data);

        $this->showForm = false;
        session()->flash('status', 'Sponsor saved.');
    }

    public function delete(int $id): void
    {
        $this->event->sponsors()->whereKey($id)->delete();
    }

    // ── Package catalog ───────────────────────────────────────
    public function addPackage(): void
    {
        $this->validate([
            'newPackageName' => ['required', 'string', 'max:60'],
            'newPackagePrice' => ['nullable', 'numeric', 'min:0'],
            'newPackageSlots' => ['nullable', 'integer', 'min:1'],
        ]);
        $name = trim($this->newPackageName);

        if ($this->event->sponsorPackages()->get()->contains(fn ($p) => mb_strtolower($p->name) === mb_strtolower($name))) {
            $this->addError('newPackageName', 'That package already exists.');

            return;
        }

        $this->event->sponsorPackages()->create([
            'name' => $name,
            'price_cents' => $this->newPackagePrice !== '' ? (int) round((float) $this->newPackagePrice * 100) : 0,
            'slots' => $this->newPackageSlots !== '' ? (int) $this->newPackageSlots : null,
            'position' => (int) $this->event->sponsorPackages()->max('position') + 1,
        ]);
        $this->reset(['newPackageName', 'newPackagePrice', 'newPackageSlots']);
    }

    public function startEditPackage(int $id): void
    {
        $p = $this->event->sponsorPackages()->findOrFail($id);
        $this->editingPackageId = $p->id;
        $this->packageEditName = $p->name;
        $this->packageEditPrice = $p->price_cents ? (string) ($p->price_cents / 100) : '';
        $this->packageEditBlurb = $p->blurb ?? '';
        $this->packageEditBenefits = implode("\n", $p->benefits ?? []);
        $this->packageEditSlots = $p->slots !== null ? (string) $p->slots : '';
    }

    public function cancelEditPackage(): void
    {
        $this->reset(['editingPackageId', 'packageEditName', 'packageEditPrice', 'packageEditBlurb', 'packageEditBenefits', 'packageEditSlots']);
    }

    public function savePackage(): void
    {
        $this->validate([
            'packageEditName' => ['required', 'string', 'max:60'],
            'packageEditPrice' => ['nullable', 'numeric', 'min:0'],
            'packageEditBlurb' => ['nullable', 'string', 'max:160'],
            'packageEditSlots' => ['nullable', 'integer', 'min:1'],
        ]);
        $p = $this->event->sponsorPackages()->findOrFail($this->editingPackageId);
        $newName = trim($this->packageEditName);

        // Keep already-sold sponsors pointing at the renamed package.
        if ($newName !== $p->name) {
            $this->event->sponsors()->where('package', $p->name)->update(['package' => $newName]);
        }

        // Benefits: one perk per line.
        $benefits = collect(preg_split('/\r\n|\r|\n/', $this->packageEditBenefits))
            ->map(fn ($b) => trim($b))->filter()->values()->all();

        $p->update([
            'name' => $newName,
            'price_cents' => $this->packageEditPrice !== '' ? (int) round((float) $this->packageEditPrice * 100) : 0,
            'slots' => $this->packageEditSlots !== '' ? (int) $this->packageEditSlots : null,
            'blurb' => trim($this->packageEditBlurb) ?: null,
            'benefits' => $benefits ?: null,
        ]);
        $this->cancelEditPackage();
    }

    public function deletePackage(int $id): void
    {
        // Existing sponsor deals keep their package name + amount; only the catalog entry goes.
        $this->event->sponsorPackages()->whereKey($id)->delete();
    }

    public function render()
    {
        $sponsors = $this->event->sponsors()->orderByDesc('amount_cents')->orderBy('name')->get();
        $packages = $this->event->sponsorPackages()->get();

        $committed = $sponsors->sum('amount_cents');
        $received = $sponsors->sum('paid_cents');
        $target = $this->event->sponsorship_target_cents ?? 0;

        return view('livewire.hub.sponsors-tab', [
            'sponsors' => $sponsors,
            'packages' => $packages,
            'soldByPackage' => $sponsors->groupBy('package')->map->count(),
            'committed' => $committed,
            'received' => $received,
            'target' => $target,
            'progressPct' => $target > 0 ? min(100, round($committed / $target * 100)) : 0,
            'soldCount' => $sponsors->count(),
        ]);
    }
}
