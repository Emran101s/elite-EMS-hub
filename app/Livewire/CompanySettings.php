<?php

namespace App\Livewire;

use App\Models\CompanyProfile;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app', ['title' => 'Company Profile'])]
class CompanySettings extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $default_currency = 'USD';

    public string $default_timezone = 'Asia/Amman';

    public string $country = '';

    public string $city = '';

    public string $email = '';

    public string $phone = '';

    public string $website = '';

    public string $address = '';

    public $logo = null;

    public function mount(): void
    {
        $p = CompanyProfile::current();
        $this->name = $p->name;
        $this->default_currency = $p->default_currency;
        $this->default_timezone = $p->default_timezone;
        $this->country = $p->country ?? '';
        $this->city = $p->city ?? '';
        $this->email = $p->email ?? '';
        $this->phone = $p->phone ?? '';
        $this->website = $p->website ?? '';
        $this->address = $p->address ?? '';
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'default_currency' => ['required', Rule::in(CompanyProfile::CURRENCIES)],
            'default_timezone' => ['required', Rule::in(CompanyProfile::TIMEZONES)],
            'country' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'website' => ['nullable', 'string', 'max:190'],
            'address' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'max:4096'],
        ]);

        $data = [
            'name' => trim($this->name),
            'default_currency' => $this->default_currency,
            'default_timezone' => $this->default_timezone,
            'country' => trim($this->country) ?: null,
            'city' => trim($this->city) ?: null,
            'email' => trim($this->email) ?: null,
            'phone' => trim($this->phone) ?: null,
            'website' => trim($this->website) ?: null,
            'address' => trim($this->address) ?: null,
        ];

        if ($this->logo) {
            $data['logo_path'] = 'storage/'.$this->logo->store('company', 'public');
        }

        CompanyProfile::current()->update($data);
        $this->logo = null;
        session()->flash('status', 'Company profile saved.');
    }

    public function render()
    {
        return view('livewire.company-settings', [
            'profile' => CompanyProfile::current(),
            'currencies' => CompanyProfile::CURRENCIES,
            'timezones' => CompanyProfile::TIMEZONES,
        ]);
    }
}
