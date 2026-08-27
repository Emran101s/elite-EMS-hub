<?php

namespace App\Livewire;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app', ['title' => 'Company Profile', 'hideTitleRow' => true])]
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

    /**
     * Where clients send money. A list because there are two accounts today,
     * one per currency, and a third is a business decision rather than a
     * schema change.
     *
     * @var list<array<string,string>>
     */
    public array $bank_accounts = [];

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
        $this->bank_accounts = $p->bank_accounts ?: [];
    }

    public function addBankAccount(): void
    {
        $this->bank_accounts[] = array_fill_keys(array_keys(CompanyProfile::BANK_FIELDS), '');
    }

    public function removeBankAccount(int $i): void
    {
        unset($this->bank_accounts[$i]);
        $this->bank_accounts = array_values($this->bank_accounts);
    }

    public function save(): void
    {
        Gate::authorize('write');
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
            'bank_accounts.*.label' => ['nullable', 'string', 'max:60'],
            'bank_accounts.*.account_name' => ['nullable', 'string', 'max:190'],
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:190'],
            'bank_accounts.*.account_no' => ['nullable', 'string', 'max:60'],
            'bank_accounts.*.iban' => ['nullable', 'string', 'max:60'],
            'bank_accounts.*.swift' => ['nullable', 'string', 'max:20'],
            'bank_accounts.*.currency' => ['nullable', 'string', 'max:60'],
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
            // A row with neither an account number nor an IBAN is an empty box
            // somebody opened and never filled; it must not reach a document.
            'bank_accounts' => collect($this->bank_accounts)
                ->map(fn (array $row) => array_map(fn ($v) => trim((string) $v), $row))
                ->filter(fn (array $row) => ($row['account_no'] ?? '') !== '' || ($row['iban'] ?? '') !== '')
                ->values()->all(),
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
            'bankFields' => CompanyProfile::BANK_FIELDS,
            'profile' => CompanyProfile::current(),
            'currencies' => CompanyProfile::CURRENCIES,
            'timezones' => CompanyProfile::TIMEZONES,
        ]);
    }
}
