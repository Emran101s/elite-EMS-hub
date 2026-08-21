<div class="max-w-5xl">
    <x-eo.operations-header active="venues" title="Venues & Locations"
                 subtitle="Every hotel, hall and site you run events at — entered once, picked by any event.">
        <x-slot:actions>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-eo-muted" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search venues…"
                       class="eo-input h-9 w-48 !rounded-xl !py-0 !ps-9 text-xs">
            </div>
            <x-eo.button size="sm" wire:click="newItem">＋ Add Venue</x-eo.button>
        </x-slot:actions>
    </x-eo.operations-header>

    {{-- Hotels are the half the accommodation and transport modules pick from,
         so they are one click away rather than mixed into the whole list. --}}
    <div class="mt-4 flex flex-wrap items-center gap-1.5">
        @foreach (['all' => 'All venues', 'hotels' => 'Hotels', 'other' => 'Halls & sites'] as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold transition',
                        'bg-eo-navy text-white' => $filter === $key,
                        'bg-white text-eo-muted ring-1 ring-eo-line hover:text-eo-text' => $filter !== $key,
                    ])>
                {{ $label }}
                <span @class([
                    'rounded-full px-1.5 text-[10px] tabular-nums',
                    'bg-white/15' => $filter === $key,
                    'bg-eo-bg text-eo-muted' => $filter !== $key,
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
                    <x-eo.button size="sm" wire:click="newItem">＋ Add your first venue</x-eo.button>
                </x-slot:actions>
            </x-eo.empty-state>
        @else
            <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                @foreach ($venues as $venue)
                    <div wire:key="venue-{{ $venue->id }}" class="group eo-soft-card flex flex-col overflow-hidden transition hover:-translate-y-0.5">
                        <div class="flex flex-1 flex-col p-4">
                            <div class="flex items-start gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-eo-bg text-eo-muted">
                                    <x-icon name="building" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold text-eo-text">{{ $venue->name }}</p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-micro text-eo-muted">
                                        @if ($venue->type)<span class="eo-pill bg-eo-bg text-eo-muted">{{ $venue->type }}</span>@endif
                                        <span>{{ $venue->locationLine() ?: 'No location set' }}</span>
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition group-hover:opacity-100">
                                    <button type="button" wire:click="edit({{ $venue->id }})" class="rounded-lg bg-eo-bg px-1.5 py-1 text-[10px] font-bold text-eo-muted hover:bg-eo-line">✎</button>
                                    <x-confirm title="Delete “{{ $venue->name }}”?"
                                               body="Events using it keep working — they just lose the venue link."
                                               confirm="Delete" run="$wire.delete({{ $venue->id }})"
                                               class="rounded-lg bg-eo-risk/10 px-1.5 py-1 text-[10px] font-bold text-eo-risk hover:bg-eo-risk/20">✕</x-confirm>
                                </div>
                            </div>

                            @if ($venue->address)
                                <p class="mt-2.5 flex items-start gap-1.5 text-micro text-eo-muted"><x-icon name="pin" class="mt-0.5 h-3 w-3 shrink-0 text-eo-muted" />{{ $venue->address }}</p>
                            @endif

                            @if ($venue->contact_name || $venue->contact_phone || $venue->contact_email)
                                <div class="mt-2.5 rounded-lg bg-eo-workspace px-2.5 py-1.5 text-micro text-eo-muted">
                                    @if ($venue->contact_name)<span class="font-semibold text-eo-text">{{ $venue->contact_name }}</span>@endif
                                    @if ($venue->contact_phone) · {{ $venue->contact_phone }}@endif
                                    @if ($venue->contact_email) · {{ $venue->contact_email }}@endif
                                </div>
                            @endif
                        </div>

                        {{-- light footer: capacity + events --}}
                        <div class="mt-auto flex items-center gap-2 border-t border-eo-line bg-eo-workspace px-3.5 py-2 text-eo-text">
                            <x-icon name="building" class="h-3 w-3 shrink-0 text-eo-gold-ink" />
                            <span class="text-[11px] font-semibold text-eo-muted">{{ $venue->capacity ? number_format($venue->capacity) : '—' }} capacity</span>
                            <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-eo-muted">{{ $venue->events_count }} {{ str('event')->plural($venue->events_count) }}</span>
                            <a href="{{ route('venues.show', $venue) }}" wire:navigate class="ml-auto shrink-0 text-[10.5px] font-bold text-eo-teal-ink hover:underline">Open Studio →</a>
                        </div>
                    </div>
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
                    <label class="eo-label mb-1">Venue name</label>
                    <input type="text" wire:model="name" class="eo-input h-10 text-sm" placeholder="Fairmont Amman">
                    @error('name')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="eo-label mb-1">Type</label>
                    <input type="text" wire:model="type" class="eo-input h-10 text-sm" placeholder="Hotel" list="venue-types">
                    <datalist id="venue-types">
                        @foreach (\App\Support\Taxonomy::values('venue_type') as $t)<option value="{{ $t }}"></option>@endforeach
                    </datalist>
                </div>
                <div>
                    <label class="eo-label mb-1">Capacity</label>
                    <input type="number" min="0" wire:model="capacity" class="eo-input h-10 text-sm" placeholder="500">
                    @error('capacity')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="eo-label mb-1">Address</label>
                    <input type="text" wire:model="address" class="eo-input h-10 text-sm" placeholder="King Hussein Business Park, Building 12">
                </div>

                <div>
                    <label class="eo-label mb-1">City</label>
                    <input type="text" wire:model="city" class="eo-input h-10 text-sm" placeholder="Amman">
                </div>
                <div>
                    <label class="eo-label mb-1">Country</label>
                    <input type="text" wire:model="country" class="eo-input h-10 text-sm" placeholder="Jordan">
                </div>

                <div class="sm:col-span-2 mt-1 border-t border-eo-line pt-3">
                    <p class="eo-label">Venue contact</p>
                </div>
                <div>
                    <label class="eo-label mb-1">Contact name</label>
                    <input type="text" wire:model="contact_name" class="eo-input h-10 text-sm" placeholder="Events Manager">
                </div>
                <div>
                    <label class="eo-label mb-1">Phone</label>
                    <input type="text" wire:model="contact_phone" class="eo-input h-10 text-sm" placeholder="+962 6 000 0000">
                </div>
                <div class="sm:col-span-2">
                    <label class="eo-label mb-1">Email</label>
                    <input type="text" wire:model="contact_email" class="eo-input h-10 text-sm" placeholder="events@fairmont.com">
                    @error('contact_email')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="eo-label mb-1">Notes</label>
                    <input type="text" wire:model="notes" class="eo-input h-10 text-sm" placeholder="Parking, load-in access, preferred halls…">
                </div>

                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('showForm', false)" class="eo-btn-ghost eo-btn-sm">Cancel</button>
                    <x-eo.button type="submit" size="sm">{{ $editingId ? 'Update venue' : 'Add venue' }}</x-eo.button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
