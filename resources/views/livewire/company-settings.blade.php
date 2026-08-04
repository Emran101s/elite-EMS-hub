<div class="max-w-3xl">
    <div class="mb-5">
        <a href="{{ route('settings.index') }}" class="text-xs font-semibold text-muted hover:text-navy-900">← Settings</a>
        <h1 class="mt-1 text-lg font-bold text-navy-900">Company Profile</h1>
        <p class="text-xs text-muted">Your brand and the defaults every new event inherits.</p>
    </div>

    <form wire:submit="save" class="grid gap-5">
        {{-- brand --}}
        <div class="card p-5">
            <p class="mb-3 text-eyebrow font-bold uppercase tracking-wide text-muted">Brand</p>
            <div class="flex items-start gap-4">
                <span class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-navy-900 text-lg font-bold text-gold-400 ring-1 ring-line">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" class="h-full w-full object-contain" alt="preview">
                    @elseif ($profile->logo_path)
                        <img src="{{ asset($profile->logo_path) }}" class="h-full w-full object-contain" alt="logo">
                    @else
                        {{ $profile->initials() }}
                    @endif
                </span>
                <div class="flex-1">
                    <label class="field-label !mb-1 !text-eyebrow">Company name</label>
                    <input type="text" wire:model="name" class="input h-10 text-sm" placeholder="Elite Business Hub">
                    @error('name')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    <div class="mt-2">
                        <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                               class="block w-full text-xs text-navy-600 file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-navy-800">
                        <div wire:loading wire:target="logo" class="mt-1 text-eyebrow font-semibold text-gold-700">Uploading…</div>
                        @error('logo')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        <p class="mt-1 text-eyebrow text-muted">Logo — PNG, JPG, WebP or SVG.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- event defaults --}}
        <div class="card p-5">
            <p class="mb-3 text-eyebrow font-bold uppercase tracking-wide text-muted">Event defaults <span class="font-normal normal-case tracking-normal text-navy-300">— pre-fill every new event</span></p>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="field-label !mb-1 !text-eyebrow">Default currency</label>
                    <select wire:model="default_currency" class="input h-10 text-sm">
                        @foreach ($currencies as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                    </select>
                    @error('default_currency')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="field-label !mb-1 !text-eyebrow">Default timezone</label>
                    <select wire:model="default_timezone" class="input h-10 text-sm">
                        @foreach ($timezones as $tz)<option value="{{ $tz }}">{{ $tz }}</option>@endforeach
                    </select>
                    @error('default_timezone')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow">Country</label>
                    <input type="text" wire:model="country" class="input h-10 text-sm" placeholder="Jordan">
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow">City / HQ</label>
                    <input type="text" wire:model="city" class="input h-10 text-sm" placeholder="Amman">
                </div>
            </div>
        </div>

        {{-- contact --}}
        <div class="card p-5">
            <p class="mb-3 text-eyebrow font-bold uppercase tracking-wide text-muted">Contact</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label !mb-1 !text-eyebrow">Email</label>
                    <input type="email" wire:model="email" class="input h-10 text-sm" placeholder="hello@company.com">
                    @error('email')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow">Phone</label>
                    <input type="text" wire:model="phone" class="input h-10 text-sm" placeholder="+962 …">
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow">Website</label>
                    <input type="text" wire:model="website" class="input h-10 text-sm" placeholder="elitebhub.com">
                </div>
                <div>
                    <label class="field-label !mb-1 !text-eyebrow">Address</label>
                    <input type="text" wire:model="address" class="input h-10 text-sm" placeholder="Street, area…">
                </div>
            </div>
        </div>

        {{-- ══ Payment details ══

             What the foot of every invoice prints. Kept here rather than typed
             onto each document: a bank changes once, and every invoice raised
             afterwards should be right — including the ones nobody re-reads. --}}
        <div class="card p-6">
            <div class="mb-4 flex flex-wrap items-baseline gap-2 border-b border-line pb-2">
                <h3 class="pf text-base font-bold text-navy-900">Payment details</h3>
                <p class="text-[11.5px] text-muted">Printed at the foot of every invoice. One account per currency.</p>
                <button type="button" wire:click="addBankAccount"
                        class="ms-auto rounded-xl border border-line bg-white px-3 py-1.5 text-[11.5px] font-semibold text-navy-700 transition hover:border-gold-300">
                    ＋ Add an account
                </button>
            </div>

            @forelse ($bank_accounts as $i => $account)
                <div wire:key="bank-{{ $i }}" class="mb-3 rounded-2xl border border-line bg-page/40 p-4 last:mb-0">
                    <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($bankFields as $field => $label)
                            <label class="block {{ $field === 'account_name' ? 'sm:col-span-2' : '' }}">
                                <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">{{ $label }}</span>
                                <input type="text" wire:model="bank_accounts.{{ $i }}.{{ $field }}"
                                       @class(['input h-9 w-full text-xs', 'font-mono' => in_array($field, ['account_no', 'iban', 'swift'], true)])
                                       placeholder="{{ ['label' => 'USD Account', 'account_name' => 'The name on the account', 'bank_name' => 'Bank Al Etihad — Jordan', 'account_no' => '0390…', 'iban' => 'JO00 UBSI …', 'swift' => 'UBSIJOAX', 'currency' => 'USD — United States Dollar'][$field] ?? '' }}">
                                @error('bank_accounts.'.$i.'.'.$field) <p class="mt-1 text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
                            </label>
                        @endforeach
                    </div>

                    <button type="button" wire:click="removeBankAccount({{ $i }})"
                            class="mt-2.5 text-[11.5px] font-semibold text-navy-400 transition hover:text-red-600">Remove this account</button>
                </div>
            @empty
                <p class="py-6 text-center text-[12px] italic text-navy-300">
                    No account yet — invoices go out without saying where to send the money.
                </p>
            @endforelse
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save,logo" class="btn-navy h-10 px-6 text-xs">Save profile</button>
        </div>
    </form>
</div>
