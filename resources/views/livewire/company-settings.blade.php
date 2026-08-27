<div class="max-w-3xl">
    <div class="mb-5">
        <a href="{{ route('settings.index') }}" class="text-xs font-semibold text-muted hover:text-ink">← Settings</a>
        <h1 class="mt-1 text-lg font-bold text-ink">Company Profile</h1>
        <p class="text-xs text-muted">Your brand and the defaults every new event inherits.</p>
    </div>

    <form wire:submit="save" class="grid gap-5">
        {{-- brand --}}
        <div class="rounded-lg border border-line bg-white shadow-raise p-5">
            <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-3">Brand</p>
            <div class="flex items-start gap-4">
                <span class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-navy-900 text-lg font-bold text-gold-600 ring-1 ring-line">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" class="h-full w-full object-contain" alt="preview">
                    @elseif ($profile->logo_path)
                        <img src="{{ asset($profile->logo_path) }}" class="h-full w-full object-contain" alt="logo">
                    @else
                        {{ $profile->initials() }}
                    @endif
                </span>
                <div class="flex-1">
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Company name</label>
                    <input type="text" wire:model="name" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Elite Business Hub">
                    @error('name')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                    <div class="mt-2">
                        <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                               class="block w-full text-xs text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-navy-950">
                        <div wire:loading wire:target="logo" class="mt-1 text-[11px] font-semibold text-gold-700">Uploading…</div>
                        @error('logo')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                        <p class="mt-1 text-[11px] text-muted">Logo — PNG, JPG, WebP or SVG.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- event defaults --}}
        <div class="rounded-lg border border-line bg-white shadow-raise p-5">
            <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-3">Event defaults <span class="font-normal normal-case tracking-normal text-muted">— pre-fill every new event</span></p>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Default currency</label>
                    <select wire:model="default_currency" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none h-10 text-sm">
                        @foreach ($currencies as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                    </select>
                    @error('default_currency')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Default timezone</label>
                    <select wire:model="default_timezone" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none h-10 text-sm">
                        @foreach ($timezones as $tz)<option value="{{ $tz }}">{{ $tz }}</option>@endforeach
                    </select>
                    @error('default_timezone')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Country</label>
                    <input type="text" wire:model="country" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Jordan">
                </div>
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">City / HQ</label>
                    <input type="text" wire:model="city" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Amman">
                </div>
            </div>
        </div>

        {{-- contact --}}
        <div class="rounded-lg border border-line bg-white shadow-raise p-5">
            <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-3">Contact</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Email</label>
                    <input type="email" wire:model="email" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="hello@company.com">
                    @error('email')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Phone</label>
                    <input type="text" wire:model="phone" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="+962 …">
                </div>
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Website</label>
                    <input type="text" wire:model="website" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="elitebhub.com">
                </div>
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Address</label>
                    <input type="text" wire:model="address" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Street, area…">
                </div>
            </div>
        </div>

        {{-- ══ Payment details ══

             What the foot of every invoice prints. Kept here rather than typed
             onto each document: a bank changes once, and every invoice raised
             afterwards should be right — including the ones nobody re-reads. --}}
        <div class="rounded-lg border border-line bg-white shadow-raise p-6">
            <div class="mb-4 flex flex-wrap items-baseline gap-2 border-b border-line pb-2">
                <h3 class="text-base font-bold text-ink">Payment details</h3>
                <p class="text-[11.5px] text-muted">Printed at the foot of every invoice. One account per currency.</p>
                <button type="button" wire:click="addBankAccount" class="rounded-full border border-line px-4 py-2 text-xs font-semibold text-ink transition hover:border-gold-300 ms-auto">
                    ＋ Add an account
                </button>
            </div>

            @forelse ($bank_accounts as $i => $account)
                <div wire:key="bank-{{ $i }}" class="mb-3 rounded-2xl border border-line bg-page p-4 last:mb-0">
                    <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($bankFields as $field => $label)
                            <label class="block {{ $field === 'account_name' ? 'sm:col-span-2' : '' }}">
                                <span class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1 block">{{ $label }}</span>
                                <input type="text" wire:model="bank_accounts.{{ $i }}.{{ $field }}"
                                       @class(['w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-xs', 'font-mono' => in_array($field, ['account_no', 'iban', 'swift'], true)])
                                       placeholder="{{ ['label' => 'USD Account', 'account_name' => 'The name on the account', 'bank_name' => 'Bank Al Etihad — Jordan', 'account_no' => '0390…', 'iban' => 'JO00 UBSI …', 'swift' => 'UBSIJOAX', 'currency' => 'USD — United States Dollar'][$field] ?? '' }}">
                                @error('bank_accounts.'.$i.'.'.$field) <p class="mt-1 text-[11px] font-semibold text-danger-ink">{{ $message }}</p> @enderror
                            </label>
                        @endforeach
                    </div>

                    <button type="button" wire:click="removeBankAccount({{ $i }})"
                            class="mt-2.5 text-[11.5px] font-semibold text-muted transition hover:text-danger-ink">Remove this account</button>
                </div>
            @empty
                <p class="py-6 text-center text-[12px] italic text-muted">
                    No account yet — invoices go out without saying where to send the money.
                </p>
            @endforelse
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save,logo" class="rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400 disabled:opacity-60">Save profile</button>
        </div>
    </form>
</div>
