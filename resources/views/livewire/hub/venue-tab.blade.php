@php
    $roomTypeLabels = \App\Support\Taxonomy::options('venue_room_type');
    // Built-in labels + any custom room types already used — suggestions for the datalist.
    $roomTypeOptions = collect(\App\Support\Taxonomy::labels('venue_room_type'))
        ->merge($rooms->pluck('type')->map(fn ($t) => str($t)->replace('_', ' ')->title()))
        ->unique()->sort()->values();
    $typeMeta = [
        'main_hall' => ['building', 'bg-page', 'text-ink'],
        'breakout' => ['users', 'bg-info-soft', 'text-info-ink'],
        'exhibition' => ['grid', 'bg-page', 'text-muted'],
        'registration' => ['identification', 'bg-gold-50', 'text-gold-700'],
        'vip' => ['star', 'bg-warning-soft', 'text-warning-ink'],
        'catering' => ['home', 'bg-success-soft', 'text-success-ink'],
    ];
    $evReqs = $event->event_requirements ?? [];
    $evTotal = $event->eventRequirementsTotalCents();
    $moduleHex = \App\Models\Event::moduleColor('venue');
@endphp

<div>
    {{-- Venues / Capacity / On the agenda / Venue cost already live in the
         Universal Module Header above this — the lead strip that used to
         repeat them here is gone rather than kept as a second copy. --}}

<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
    {{-- ══════════ MAIN · venues ══════════ --}}
    <div class="min-w-0">
        <x-bulk-bar :count="$this->selectedCount()" noun="venue" />

        {{-- add / edit form --}}
        @if ($showRoomForm)
            <form wire:submit="saveRoom" class="mb-4 rounded-lg border border-line bg-white p-4">
                <h3 class="mb-2.5 text-sm font-bold text-ink">{{ $editingRoomId ? 'Edit venue' : 'New venue' }}</h3>
                <div class="grid gap-2.5 sm:grid-cols-3">
                    <div class="sm:col-span-3">
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="room-name">Venue name</label>
                        <input id="room-name" type="text" wire:model="room_name" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none" placeholder="e.g. Main Summit Hall">
                        @error('room_name') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="room-type">Type</label>
                        <input id="room-type" type="text" list="room-types" wire:model="room_type" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none"
                               autocomplete="off" placeholder="Breakout, Stage, Green Room…">
                        <datalist id="room-types">
                            @foreach ($roomTypeOptions as $label)<option value="{{ $label }}"></option>@endforeach
                        </datalist>
                        @error('room_type') <p class="mt-1 text-eyebrow text-danger-ink">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="room-capacity">Capacity</label>
                        <input id="room-capacity" type="number" min="0" wire:model="room_capacity" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none" placeholder="—">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="room-cost">Hire, per day ({{ $event->currency }})</label>
                        <input id="room-cost" type="number" min="0" step="0.01" wire:model="room_cost" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none" placeholder="0">
                        @error('room_cost') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($venueSpaces->isNotEmpty())
                    <div class="mt-2.5">
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="room-venue-space">Venue's own space</label>
                        <select id="room-venue-space" wire:model="room_venue_space_id" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                            <option value="">— Not linked —</option>
                            @foreach ($venueSpaces as $space)<option value="{{ $space->id }}">{{ $space->name }}</option>@endforeach
                        </select>
                        <p class="mt-1 text-eyebrow text-muted">Optional — links this booking back to {{ $event->venue->name }}'s own space in Venue Studio. This room's own layout and price stay independent either way.</p>
                    </div>
                @endif

                {{-- How long it is held. Left blank, the agenda answers. --}}
                @php
                    $editing = $editingRoomId ? $event->rooms()->find($editingRoomId) : null;
                    $counted = $editing?->daysOnTheAgenda() ?? 0;
                @endphp
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="room-days">Days held</label>
                        <input id="room-days" type="number" min="1" max="365" wire:model="room_days" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none"
                               placeholder="{{ $counted > 0 ? $counted.' — from the agenda' : 'From the agenda' }}">
                        @error('room_days') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="room-setup">Setup &amp; teardown days</label>
                        <input id="room-setup" type="number" min="0" max="60" wire:model="room_setup_days" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none" placeholder="0">
                        @error('room_setup_days') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
                    </div>
                    <div class="rounded-lg bg-page px-3 py-2">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Hire total</p>
                        @php
                            $rate = $room_cost !== '' ? (int) round((float) $room_cost * 100) : 0;
                            $d = max(1, $room_days !== '' ? (int) $room_days : ($counted ?: 1)) + (int) ($room_setup_days ?: 0);
                        @endphp
                        <p class="text-[17px] font-black leading-tight text-ink">{{ $event->money($rate * $d) }}</p>
                        <p class="text-eyebrow text-muted">{{ $event->money($rate) }} × {{ $d }} {{ str('day')->plural($d) }}</p>
                    </div>
                </div>

                <p class="mt-2 text-eyebrow text-muted">
                    Leave <b>days held</b> blank and it is counted from the agenda — a session moved re-prices the hire on its own.
                    @if ($counted > 0) This venue is used on <b>{{ $counted }}</b> {{ str('day')->plural($counted) }} of the programme. @endif
                    Hire syncs into the Budget; equipment &amp; requirements are priced inside the venue.
                </p>
                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showRoomForm', false)" class="h-9 rounded-full px-4 text-xs font-semibold text-muted hover:text-ink">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveRoom" class="h-9 rounded-full bg-navy-900 px-5 text-xs font-bold text-white transition hover:bg-navy-800">
                        <span wire:loading.remove wire:target="saveRoom">{{ $editingRoomId ? 'Update venue' : 'Add venue' }}</span>
                        <span wire:loading wire:target="saveRoom">Saving…</span>
                    </button>
                </div>
            </form>
        @endif

        {{-- List scans many spaces; cards earn their keep when there are few
             (≤3 default) or when someone chooses them. Preference sticks. --}}
        @php $spacesDefault = $rooms->isNotEmpty() && $rooms->count() <= 3 ? 'cards' : 'list'; @endphp
        <div x-data="{
                 mode: (() => {
                     const saved = localStorage.getItem('elitehub.venue.spacesMode');
                     return saved === 'list' || saved === 'cards' ? saved : @js($spacesDefault);
                 })(),
                 setMode(m) {
                     this.mode = m;
                     localStorage.setItem('elitehub.venue.spacesMode', m);
                 }
             }">
            <div class="mb-2.5 flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0">
                    <h3 class="flex items-center gap-2 text-sm font-bold text-ink">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg" style="color: {{ $moduleHex }}; background: {{ $moduleHex }}15">
                            <x-icon name="building" class="h-3.5 w-3.5" />
                        </span>
                        Spaces
                    </h3>
                    <p class="ms-9 text-eyebrow text-muted">Open a space for layout, equipment &amp; requirements</p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($rooms->isNotEmpty())
                        <span role="group" aria-label="Room layout" class="inline-flex items-center rounded-full border border-line bg-white p-0.5">
                            <button type="button" @click="setMode('list')" :aria-pressed="mode === 'list'"
                                    :class="mode === 'list' ? 'bg-navy-900 text-white' : 'text-muted hover:text-ink'"
                                    class="rounded-full px-2.5 py-1.5 text-eyebrow font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 focus-visible:ring-offset-1">List</button>
                            <button type="button" @click="setMode('cards')" :aria-pressed="mode === 'cards'"
                                    :class="mode === 'cards' ? 'bg-navy-900 text-white' : 'text-muted hover:text-ink'"
                                    class="rounded-full px-2.5 py-1.5 text-eyebrow font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 focus-visible:ring-offset-1">Cards</button>
                        </span>
                    @endif
                    <button type="button" wire:click="newRoom" class="h-8 rounded-full bg-gold-500 px-3 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add Venue</button>
                </div>
            </div>

            @if ($rooms->isEmpty())
                <div class="rounded-lg border border-line bg-white">
                    <x-empty icon="building" title="No venues yet" class="!border-0 !shadow-none"
                             hint="Add the halls, rooms and areas inside your location — each becomes a schedulable space with its own layout, equipment and requirements.">
                        <x-slot:actions>
                            <button type="button" wire:click="newRoom" class="h-9 rounded-full bg-gold-500 px-3.5 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add the first venue</button>
                        </x-slot:actions>
                    </x-empty>
                </div>
            @else
                <div x-show="mode === 'list'" x-cloak class="overflow-hidden rounded-lg border border-line bg-white">
                    <ul class="divide-y divide-line">
                        @foreach ($rooms as $room)
                            @php
                                [$tIcon, $tBg, $tText] = $typeMeta[$room->type] ?? ['building', 'bg-page', 'text-muted'];
                                $total = $room->totalCents();
                                $eqCount = count($room->requirements ?? []);
                            @endphp
                            <li class="group flex items-center gap-2.5 px-3.5 py-2 transition hover:bg-page {{ $this->isSelected($room->id) ? 'bg-page' : '' }}" wire:key="room-list-{{ $room->id }}">
                                <button type="button" wire:click="toggleSelect({{ $room->id }})" class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($room->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-line text-transparent hover:border-muted' }}" title="Select">✓</button>
                                <a href="{{ route('events.room-layout', [$event, $room]) }}" class="flex min-w-0 flex-1 items-center gap-2.5">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $tBg }} {{ $tText }}"><x-icon :name="$tIcon" class="h-3.5 w-3.5" /></span>
                                    <div class="min-w-0">
                                        <p class="truncate text-[13px] font-bold text-ink group-hover:text-gold-700">{{ $room->name }}</p>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-eyebrow text-muted">
                                            <span class="rounded px-1.5 py-px text-eyebrow font-bold uppercase tracking-wide {{ $tBg }} {{ $tText }}">{{ $roomTypeLabels[$room->type] ?? str($room->type)->replace('_', ' ')->title() }}</span>
                                            @if ($room->capacity)<span>{{ number_format($room->capacity) }} pax</span>@endif
                                            @if ($room->sessions_count)<span>{{ $room->sessions_count }} {{ str('session')->plural($room->sessions_count) }}</span>@endif
                                            @if ($eqCount)<span>{{ $eqCount }} eq</span>@endif
                                            <span title="{{ $room->daysAreCounted() ? 'Counted from the agenda' : 'Set by hand' }}">
                                                {{ $room->chargedDays() }}d{{ $room->daysAreCounted() ? '' : ' fixed' }}
                                            </span>
                                        </p>
                                    </div>
                                </a>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    @if ($total > 0)<span class="hidden rounded-md bg-page px-1.5 py-0.5 text-eyebrow font-bold tabular-nums text-ink sm:inline">{{ $event->money($total) }}</span>@endif
                                    <a href="{{ route('events.room-layout', [$event, $room]) }}" class="rounded-md border border-line bg-white px-2 py-0.5 text-eyebrow font-bold text-ink transition hover:border-navy-300 group-hover:border-navy-300">Open →</a>
                                    <span class="flex gap-0.5 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100">
                                        <a href="{{ route('events.room-layout.pdf', [$event, $room]) }}" target="_blank"
                                           class="rounded-md bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line"
                                           title="Layout PDF — the floor drawing">↧ Layout</a>
                                        <a href="{{ route('events.room-equipment.pdf', [$event, $room]) }}" target="_blank"
                                           class="rounded-md bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line"
                                           title="AV & equipment prep sheet">↧ Equip</a>
                                        <button type="button" wire:click="editRoom({{ $room->id }})" class="rounded-md bg-page px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-line" title="Edit">✎</button>
                                        <x-confirm title="Delete “{{ $room->name }}”?" body="Sessions here become room-less." confirm="Delete" run="$wire.deleteRoom({{ $room->id }})"
                                                   class="rounded-md bg-danger-soft px-1.5 py-0.5 text-eyebrow font-bold text-danger-ink hover:bg-danger-soft/70" title="Delete">✕</x-confirm>
                                    </span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div x-show="mode === 'cards'" x-cloak class="grid gap-3 sm:grid-cols-2">
                    @foreach ($rooms as $room)
                        @php
                            [$tIcon, $tBg, $tText] = $typeMeta[$room->type] ?? ['building', 'bg-page', 'text-muted'];
                            $total = $room->totalCents();
                            $eqCount = count($room->requirements ?? []);
                            $typeLabel = $roomTypeLabels[$room->type] ?? str($room->type)->replace('_', ' ')->title();
                        @endphp
                        <div wire:key="room-card-{{ $room->id }}"
                             class="group/space flex flex-col overflow-hidden rounded-lg border border-line bg-white {{ $this->isSelected($room->id) ? '!border-navy-900 ring-2 ring-navy-900' : '' }}">
                            <div class="flex flex-1 flex-col p-3.5">
                                <div class="flex items-start gap-2.5">
                                    <button type="button" wire:click="toggleSelect({{ $room->id }})"
                                            class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($room->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-line text-transparent hover:border-muted' }}"
                                            title="Select">✓</button>
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $tBg }} {{ $tText }}">
                                        <x-icon :name="$tIcon" class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('events.room-layout', [$event, $room]) }}"
                                           class="block truncate text-sm font-bold text-ink transition group-hover/space:text-gold-700">{{ $room->name }}</a>
                                        <span class="mt-1 inline-flex rounded px-1.5 py-px text-eyebrow font-bold uppercase tracking-wide {{ $tBg }} {{ $tText }}">{{ $typeLabel }}</span>
                                    </div>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-eyebrow">
                                    <div>
                                        <p class="font-bold uppercase tracking-[0.12em] text-muted">Capacity</p>
                                        <p class="mt-0.5 font-semibold text-ink">{{ $room->capacity ? number_format($room->capacity).' pax' : '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase tracking-[0.12em] text-muted">Agenda</p>
                                        <p class="mt-0.5 font-semibold text-ink">{{ $room->sessions_count ? $room->sessions_count.' '.str('session')->plural($room->sessions_count) : '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase tracking-[0.12em] text-muted">Held</p>
                                        <p class="mt-0.5 font-semibold text-ink" title="{{ $room->daysAreCounted() ? 'Counted from the agenda' : 'Set by hand' }}">
                                            {{ $room->chargedDays() }} {{ str('day')->plural($room->chargedDays()) }}{{ $room->daysAreCounted() ? '' : ' · fixed' }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="font-bold uppercase tracking-[0.12em] text-muted">Equipment</p>
                                        <p class="mt-0.5 font-semibold text-ink">{{ $eqCount ? $eqCount.' '.str('item')->plural($eqCount) : '—' }}</p>
                                    </div>
                                </div>
                                <p class="mt-3 text-[18px] font-black leading-none text-ink">
                                    {{ $total > 0 ? $event->money($total) : '—' }}
                                </p>
                            </div>
                            <div class="mt-auto flex items-center gap-2 border-t border-line bg-page px-3.5 py-2">
                                <a href="{{ route('events.room-layout', [$event, $room]) }}"
                                   class="text-eyebrow font-bold text-ink transition hover:text-gold-700">Open layout →</a>
                                <div class="ms-auto flex items-center gap-1 opacity-100 sm:opacity-0 sm:transition sm:group-hover/space:opacity-100">
                                    <a href="{{ route('events.room-layout.pdf', [$event, $room]) }}" target="_blank"
                                       class="rounded-md bg-white px-1.5 py-0.5 text-eyebrow font-bold text-muted ring-1 ring-line hover:ring-navy-300"
                                       title="Layout PDF">↧ Layout</a>
                                    <a href="{{ route('events.room-equipment.pdf', [$event, $room]) }}" target="_blank"
                                       class="rounded-md bg-white px-1.5 py-0.5 text-eyebrow font-bold text-muted ring-1 ring-line hover:ring-navy-300"
                                       title="AV & equipment prep sheet">↧ Equip</a>
                                    <button type="button" wire:click="editRoom({{ $room->id }})"
                                            class="rounded-md bg-white px-1.5 py-0.5 text-eyebrow font-bold text-muted ring-1 ring-line hover:ring-navy-300" title="Edit">✎</button>
                                    <x-confirm title="Delete “{{ $room->name }}”?" body="Sessions here become room-less." confirm="Delete" run="$wire.deleteRoom({{ $room->id }})"
                                               class="rounded-md bg-danger-soft px-1.5 py-0.5 text-eyebrow font-bold text-danger-ink hover:bg-danger-soft/70" title="Delete">✕</x-confirm>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ══════════ RIGHT · control rail ══════════ --}}
    <div class="space-y-3 xl:sticky xl:top-12 xl:h-fit">
        <div class="cc-panel">
            <div class="cc-head">
                <x-icon name="building" class="relative h-4 w-4" style="color: {{ $moduleHex }}" />
                <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-ink">Venue Control Center</span>
            </div>

            <div class="border-b border-line p-3">
                <button type="button" wire:click="newRoom" class="h-9 w-full rounded-full bg-gold-500 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Add Venue</button>
            </div>

            {{-- location — assign the venue and jump straight to its own Venue
                 Studio here, rather than hunting for one field in Settings.
                 This is the two-way link that connects a booking to the place
                 it happens (Venue Studio carries the reverse link back). --}}
            <div class="border-b border-line p-3">
                <p class="mb-1.5 flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Event location</p>
                <div class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gold-400" style="background: linear-gradient(135deg, {{ $event->theme()['primary'] }}, var(--color-navy-800));"><x-icon name="building" class="h-4 w-4" /></span>
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-bold text-ink">{{ $event->venue?->name ?? ($event->city ?: 'Not set') }}</p>
                        <p class="truncate text-eyebrow text-muted">{{ $event->city }}@if ($event->city && $event->country), {{ $event->country }}@endif</p>
                    </div>
                </div>

                {{-- assign / change the venue in place --}}
                <select wire:change="setVenue($event.target.value)" aria-label="Event venue"
                        class="mt-2 h-8 w-full rounded-lg border border-line bg-white px-2.5 text-xs text-ink focus:border-navy-300 focus:outline-none">
                    <option value="">— No venue linked —</option>
                    @foreach ($venues as $v)
                        <option value="{{ $v->id }}" @selected($event->venue_id === $v->id)>{{ $v->name }}@if ($v->city) · {{ $v->city }}@endif</option>
                    @endforeach
                </select>

                @if ($event->venue)
                    <a href="{{ route('venues.show', $event->venue) }}"
                       class="mt-2 flex items-center justify-between gap-2 rounded-lg border border-gold-300 bg-gold-50 px-2.5 py-1.5 text-eyebrow font-bold text-gold-700 transition hover:bg-gold-50/70">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="building" class="h-3.5 w-3.5" /> Open in Venue Studio</span>
                        <span aria-hidden="true">→</span>
                    </a>
                    @if ($venueSpaces->isNotEmpty())
                        <p class="mt-1.5 text-eyebrow leading-snug text-muted">{{ $event->venue->name }} has {{ $venueSpaces->count() }} {{ str('space')->plural($venueSpaces->count()) }} in its catalog — link a room to one when you add it, so its floor plan starts from the real place.</p>
                    @endif
                @else
                    <p class="mt-1.5 text-eyebrow leading-snug text-muted">Link a venue to reach its own spaces, documents and contacts in Venue Studio.</p>
                @endif

                <a href="{{ route('events.hub', [$event, 'tab' => 'settings']) }}" class="mt-1.5 block text-eyebrow font-semibold text-muted hover:text-ink">City, dates &amp; full details in Settings →</a>
            </div>
        </div>

        {{-- event-wide requirements --}}
        <div class="rounded-lg border border-line bg-white p-3">
            <div class="mb-1.5 flex items-center justify-between">
                <p class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Event Requirements</p>
                <span class="text-xs font-bold text-ink">{{ $evTotal ? $event->money($evTotal) : '—' }}</span>
            </div>
            <p class="mb-2 text-eyebrow leading-snug text-muted">Not tied to a venue — syncs to Budget.</p>

            <ul class="mb-2.5 divide-y divide-line">
                @forelse ($evReqs as $req)
                    <li wire:key="evreq-{{ $req['id'] }}" class="flex items-center justify-between gap-2 py-1.5 text-xs">
                        <span class="min-w-0 flex-1 truncate text-ink">{{ $req['name'] }}</span>
                        <span class="flex shrink-0 items-center gap-1.5">
                            <span class="font-semibold text-ink">{{ $event->money($req['cost_cents'] ?? 0) }}</span>
                            <button type="button" wire:click="removeEventRequirement('{{ $req['id'] }}')" class="rounded bg-danger-soft px-1 py-0.5 text-eyebrow font-bold text-danger-ink hover:bg-danger-soft/70">✕</button>
                        </span>
                    </li>
                @empty
                    <li class="py-1.5 text-eyebrow text-muted">None yet.</li>
                @endforelse
            </ul>

            @if ($catalog->isNotEmpty())
                <select wire:change="pickEvReq($event.target.value)" class="mb-1.5 h-8 w-full rounded-lg border border-line bg-white px-2.5 text-xs text-ink focus:border-navy-300 focus:outline-none">
                    <option value="">— Pick from catalog —</option>
                    @foreach ($catalog as $ci)<option value="{{ $ci->id }}">{{ $ci->name }}@if ($ci->unit_price_cents) · {{ number_format($ci->unit_price_cents / 100) }}@endif</option>@endforeach
                </select>
            @endif
            <input type="text" wire:model="evReqName" wire:keydown.enter="addEventRequirement" maxlength="120" placeholder="Requirement name…" class="mb-1.5 h-8 w-full rounded-lg border border-line bg-white px-2.5 text-xs text-ink focus:border-navy-300 focus:outline-none">
            <div class="flex items-center gap-1.5">
                <span class="text-eyebrow font-semibold text-muted">{{ $event->currencySymbol() }}</span>
                <input type="number" min="0" step="0.01" wire:model="evReqCost" wire:keydown.enter="addEventRequirement" placeholder="Cost" class="h-8 flex-1 rounded-lg border border-line bg-white px-2.5 text-xs text-ink focus:border-navy-300 focus:outline-none">
                <button type="button" wire:click="addEventRequirement" class="rounded-lg border border-gold-300 bg-gold-50 px-2.5 py-1 text-eyebrow font-bold text-gold-700 hover:bg-gold-50/70">Add</button>
            </div>
            @error('evReqName') <p class="mt-1 text-eyebrow font-semibold text-danger-ink">{{ $message }}</p> @enderror
            <div class="mt-2 flex items-center gap-2">
                <a href="{{ route('requirements.index') }}" class="flex-1 text-center text-eyebrow font-semibold text-gold-700 hover:text-gold-600">Manage Catalog →</a>
                <a href="{{ route('requirements.pdf') }}" target="_blank"
                   class="rounded-lg border border-line bg-white px-2 py-1 text-eyebrow font-bold text-ink transition hover:border-navy-300"
                   title="Equipment catalogue PDF">↧ PDF</a>
            </div>
        </div>
    </div>
</div>
</div>
