<?php

namespace App\Livewire;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'Sponsorship Packages', 'hideTitleRow' => true])]
class SponsorPackagesSettings extends Component
{
    /** @var array<int,array{name:string,price:string,slots:string,benefits:string}> */
    public array $packages = [];

    public function mount(): void
    {
        $this->packages = collect(CompanyProfile::current()->sponsorPackages())->map(fn ($p) => [
            'name' => $p['name'],
            'price' => $p['price_cents'] ? rtrim(rtrim(number_format($p['price_cents'] / 100, 2, '.', ''), '0'), '.') : '',
            'slots' => $p['slots'] !== null ? (string) $p['slots'] : '',
            'benefits' => implode("\n", $p['benefits'] ?? []),
        ])->all();
    }

    public function addPackage(): void
    {
        $this->packages[] = ['name' => '', 'price' => '', 'slots' => '', 'benefits' => ''];
    }

    public function removePackage(int $i): void
    {
        unset($this->packages[$i]);
        $this->packages = array_values($this->packages);
    }

    public function move(int $i, int $dir): void
    {
        $j = $i + $dir;
        if ($j < 0 || $j >= count($this->packages)) {
            return;
        }
        [$this->packages[$i], $this->packages[$j]] = [$this->packages[$j], $this->packages[$i]];
    }

    public function resetPackages(): void
    {
        Gate::authorize('write');
        CompanyProfile::current()->update(['default_sponsor_packages' => null]);
        $this->mount();
        session()->flash('status', 'Reset to the standard package list.');
    }

    public function save(): void
    {
        Gate::authorize('write');
        $clean = collect($this->packages)
            ->filter(fn ($p) => trim((string) ($p['name'] ?? '')) !== '')
            ->map(fn ($p) => [
                'name' => trim($p['name']),
                'price_cents' => $p['price'] !== '' ? (int) round((float) $p['price'] * 100) : 0,
                'slots' => $p['slots'] !== '' ? max(0, (int) $p['slots']) : null,
                'benefits' => collect(preg_split('/\r?\n/', (string) ($p['benefits'] ?? '')))
                    ->map(fn ($b) => trim($b))->filter()->values()->all(),
            ])->values()->all();

        CompanyProfile::current()->update(['default_sponsor_packages' => $clean]);
        session()->flash('status', 'Sponsorship packages saved — new events will seed from them.');
    }

    public function render()
    {
        return view('livewire.sponsor-packages-settings', [
            'currency' => CompanyProfile::current()->default_currency,
        ]);
    }
}
