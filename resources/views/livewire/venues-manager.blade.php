<div class="max-w-5xl">
    <div class="mb-5">
        <a href="{{ route('settings.index') }}" class="text-xs font-semibold text-muted hover:text-navy-900">← Settings</a>
    </div>

    <x-page-head title="Venues & Locations"
                 subtitle="Every hotel, hall and site you run events at — entered once, picked by any event.">
        <x-slot:actions>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search venues…"
                       class="input h-9 w-48 !rounded-xl !py-0 !ps-9 text-xs">
            </div>
            <button type="button" wire:click="newItem" class="btn-gold btn-sm">＋ Add Venue</button>
        </x-slot:actions>
    </x-page-head>

    {{-- Hotels are the half the accommodation and transport modules pick from,
         so they are one click away rather than mixed into the whole list. --}}
    <div class="mt-4 flex flex-wrap items-center gap-1.5">
        @foreach (['all' => 'All venues', 'hotels' => 'Hotels', 'other' => 'Halls & sites'] as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold transition',
                        'bg-navy-900 text-white' => $filter === $key,
                        'bg-white text-navy-500 ring-1 ring-line hover:text-navy-900' => $filter !== $key,
                    ])>
                {{ $label }}
                <span @class([
                    'rounded-full px-1.5 text-[10px] tabular-nums',
                    'bg-white/15' => $filter === $key,
                    'bg-navy-50 text-navy-400' => $filter !== $key,
                ])>{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </div>

    {{-- ══ list ══ --}}
    <div class="mt-5">
        @if ($venues->isEmpty())
            <x-empty icon="building" title="No venues yet"
                     hint="Add the hotels, conference centres and sites you use. Each becomes a reusable location every event can be assigned to.">
                <x-slot:actions>
                    <button type="button" wire:click="newItem" class="btn-gold btn-sm">＋ Add your first venue</button>
                </x-slot:actions>
            </x-empty>
        @else
            <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                @foreach ($venues as $venue)
                    <div wire:key="venue-{{ $venue->id }}" class="group op-card">
                        <div class="flex flex-1 flex-col p-4">
                            <div class="flex items-start gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-navy-50 text-navy-500">
                                    <x-icon name="building" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="pf truncate text-sm font-bold text-navy-900">{{ $venue->name }}</p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-micro text-muted">
                                        @if ($venue->type)<span class="pill bg-navy-50 text-navy-500">{{ $venue->type }}</span>@endif
                                        <span>{{ $venue->locationLine() ?: 'No location set' }}</span>
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition group-hover:opacity-100">
                                    <button type="button" wire:click="edit({{ $venue->id }})" class="rounded-lg bg-navy-50 px-1.5 py-1 text-eyebrow font-bold text-navy-600 hover:bg-navy-100">✎</button>
                                    <button type="button" wire:click="delete({{ $venue->id }})"
                                            wire:confirm="Delete “{{ $venue->name }}”? Events using it keep working — they just lose the venue link."
                                            class="rounded-lg bg-risk/10 px-1.5 py-1 text-eyebrow font-bold text-red-700 hover:bg-risk/20">✕</button>
                                </div>
                            </div>

                            @if ($venue->address)
                                <p class="mt-2.5 flex items-start gap-1.5 text-micro text-navy-600"><x-icon name="pin" class="mt-0.5 h-3 w-3 shrink-0 text-navy-300" />{{ $venue->address }}</p>
                            @endif

                            @if ($venue->contact_name || $venue->contact_phone || $venue->contact_email)
                                <div class="mt-2.5 rounded-lg bg-page/60 px-2.5 py-1.5 text-micro text-navy-600">
                                    @if ($venue->contact_name)<span class="font-semibold text-navy-800">{{ $venue->contact_name }}</span>@endif
                                    @if ($venue->contact_phone) · {{ $venue->contact_phone }}@endif
                                    @if ($venue->contact_email) · {{ $venue->contact_email }}@endif
                                </div>
                            @endif
                        </div>

                        {{-- dark navy footer: capacity + events --}}
                        <div class="op-card-foot">
                            <x-icon name="building" class="h-3 w-3 shrink-0 text-gold-600" />
                            <span class="text-eyebrow font-semibold text-navy-700">{{ $venue->capacity ? number_format($venue->capacity) : '—' }} capacity</span>
                            <span class="ml-auto shrink-0 text-eyebrow font-bold uppercase tracking-wide text-navy-400">{{ $venue->events_count }} {{ str('event')->plural($venue->events_count) }}</span>
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
                <x-field label="Venue name" :error="$errors->first('name')" class="sm:col-span-2">
                    <input type="text" wire:model="name" class="input h-10 text-sm" placeholder="Fairmont Amman">
                </x-field>

                <x-field label="Type">
                    <input type="text" wire:model="type" class="input h-10 text-sm" placeholder="Hotel" list="venue-types">
                    <datalist id="venue-types">
                        @foreach (\App\Support\Taxonomy::values('venue_type') as $t)<option value="{{ $t }}"></option>@endforeach
                    </datalist>
                </x-field>
                <x-field label="Capacity" :error="$errors->first('capacity')">
                    <input type="number" min="0" wire:model="capacity" class="input h-10 text-sm" placeholder="500">
                </x-field>

                <x-field label="Address" class="sm:col-span-2">
                    <input type="text" wire:model="address" class="input h-10 text-sm" placeholder="King Hussein Business Park, Building 12">
                </x-field>

                <x-field label="City">
                    <input type="text" wire:model="city" class="input h-10 text-sm" placeholder="Amman">
                </x-field>
                <x-field label="Country">
                    <input type="text" wire:model="country" class="input h-10 text-sm" placeholder="Jordan">
                </x-field>

                <div class="sm:col-span-2 mt-1 border-t border-line pt-3">
                    <p class="eyebrow">Venue contact</p>
                </div>
                <x-field label="Contact name">
                    <input type="text" wire:model="contact_name" class="input h-10 text-sm" placeholder="Events Manager">
                </x-field>
                <x-field label="Phone">
                    <input type="text" wire:model="contact_phone" class="input h-10 text-sm" placeholder="+962 6 000 0000">
                </x-field>
                <x-field label="Email" :error="$errors->first('contact_email')" class="sm:col-span-2">
                    <input type="text" wire:model="contact_email" class="input h-10 text-sm" placeholder="events@fairmont.com">
                </x-field>

                <x-field label="Notes" class="sm:col-span-2">
                    <input type="text" wire:model="notes" class="input h-10 text-sm" placeholder="Parking, load-in access, preferred halls…">
                </x-field>

                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('showForm', false)" class="btn-ghost btn-sm">Cancel</button>
                    <button type="submit" class="btn-navy btn-sm">{{ $editingId ? 'Update venue' : 'Add venue' }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
