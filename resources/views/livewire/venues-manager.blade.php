@php
    $openSupplierIssues = \App\Models\Event::query()
        ->withCount(['suppliers as issues_count' => fn ($q) => $q->where('event_supplier.status', 'issue')])
        ->get()
        ->sum('issues_count');
@endphp

<div class="max-w-5xl">
    <x-cc.header eyebrow="Operations Command" title="Venues & Locations" subtitle="Every hotel, hall and site you run events at — entered once, picked by any event.">
        <x-slot:actions>
            @if (\Illuminate\Support\Facades\Route::has('suppliers.index'))
                <a href="{{ route('suppliers.index') }}" class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:-translate-y-0.5 hover:border-navy-300">Suppliers →</a>
            @endif
            @if (\Illuminate\Support\Facades\Route::has('requirements.index'))
                <a href="{{ route('requirements.index') }}" class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:-translate-y-0.5 hover:border-navy-300">Equipment →</a>
            @endif
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search venues…"
                       class="h-9 w-48 rounded-full border border-line bg-white py-0 pl-9 pr-3 text-[12.5px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
            </div>
            <button type="button" wire:click="newItem" class="rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:-translate-y-0.5 hover:bg-gold-400">＋ Add Venue</button>
        </x-slot:actions>
    </x-cc.header>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-cc.kpi-tile label="Suppliers" :value="number_format(\App\Models\Supplier::count())" hint="Vendor directory" />
        <x-cc.kpi-tile label="Venues" :value="number_format(\App\Models\Venue::count())" hint="Locations on file" tone="live" />
        <x-cc.kpi-tile label="Equipment" :value="number_format(\App\Models\Requirement::count())" hint="Catalog items" />
        <x-cc.kpi-tile label="Open supplier issues" :value="number_format($openSupplierIssues)" hint="Flagged across live events" :tone="$openSupplierIssues > 0 ? 'warn' : 'ok'" />
    </div>

    {{-- Hotels are the half the accommodation and transport modules pick from,
         so they are one click away rather than mixed into the whole list. --}}
    <div class="mt-5 flex flex-wrap items-center gap-1.5">
        @foreach (['all' => 'All venues', 'hotels' => 'Hotels', 'other' => 'Halls & sites'] as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[12px] font-bold transition',
                        'bg-navy-900 text-white' => $filter === $key,
                        'bg-white text-muted ring-1 ring-line hover:text-ink' => $filter !== $key,
                    ])>
                {{ $label }}
                <span @class([
                    'rounded-full px-1.5 text-[10px] tabular-nums',
                    'bg-white/15' => $filter === $key,
                    'bg-page text-muted' => $filter !== $key,
                ])>{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </div>

    {{-- ══ list ══ --}}
    <div class="mt-5">
        @if ($venues->isEmpty())
            <x-eo.empty-state icon="building" title="No venues yet"
                     hint="Add the hotels, conference centres and sites you use. Each becomes a reusable location every event can be assigned to.">
                <x-slot:actions>
                    <button type="button" wire:click="newItem" class="rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add your first venue</button>
                </x-slot:actions>
            </x-eo.empty-state>
        @else
            <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                @foreach ($venues as $venue)
                    <x-operations.venue-card :venue="$venue" />
                @endforeach
            </div>
        @endif
    </div>

    {{-- ══ add / edit modal ══ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit venue' : 'New venue'"
                 subtitle="A reusable location any event can be assigned to."
                 max="2xl" close="$set('showForm', false)">
            <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Venue name</label>
                    <input type="text" wire:model="name" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Fairmont Amman">
                    @error('name')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Type</label>
                    <input type="text" wire:model="type" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Hotel" list="venue-types">
                    <datalist id="venue-types">
                        @foreach (\App\Support\Taxonomy::values('venue_type') as $t)<option value="{{ $t }}"></option>@endforeach
                    </datalist>
                </div>
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Capacity</label>
                    <input type="number" min="0" wire:model="capacity" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="500">
                    @error('capacity')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Address</label>
                    <input type="text" wire:model="address" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="King Hussein Business Park, Building 12">
                </div>

                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">City</label>
                    <input type="text" wire:model="city" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Amman">
                </div>
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Country</label>
                    <input type="text" wire:model="country" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Jordan">
                </div>

                <div class="sm:col-span-2 mt-1 border-t border-line pt-3">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Venue contact</p>
                </div>
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Contact name</label>
                    <input type="text" wire:model="contact_name" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Events Manager">
                </div>
                <div>
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Phone</label>
                    <input type="text" wire:model="contact_phone" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="+962 6 000 0000">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Email</label>
                    <input type="text" wire:model="contact_email" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="events@fairmont.com">
                    @error('contact_email')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Notes</label>
                    <input type="text" wire:model="notes" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Parking, load-in access, preferred halls…">
                </div>

                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('showForm', false)" class="btn-sm rounded-full border border-line font-semibold text-ink transition hover:border-gold-300">Cancel</button>
                    <button type="submit" class="rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">{{ $editingId ? 'Update venue' : 'Add venue' }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
