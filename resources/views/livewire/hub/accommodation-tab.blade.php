@php
    $blockStatuses = \App\Models\EventRoomBlock::STATUSES;
@endphp
<div>
    <datalist id="room-categories">
        @foreach (\App\Support\Taxonomy::values('room_category') as $c)<option value="{{ $c }}"></option>@endforeach
    </datalist>
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0 space-y-4">

            {{-- ══ fill rail: the one number that matters ══ --}}
            @if ($blocks->isNotEmpty())
                @php $pct = $roomsHeld > 0 ? (int) round($roomsNamed / $roomsHeld * 100) : 0; @endphp
                <div class="card overflow-hidden">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3 px-5 py-4">
                        <div>
                            <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Rooming list</p>
                            <p class="pf mt-0.5 text-2xl font-bold leading-none text-navy-900">
                                {{ $roomsNamed }}<span class="text-base font-semibold text-navy-300"> / {{ $roomsHeld }}</span>
                            </p>
                            <p class="mt-0.5 text-eyebrow text-muted">rooms named</p>
                        </div>
                        <div class="min-w-[160px] flex-1">
                            <div class="h-2.5 overflow-hidden rounded-full bg-navy-100">
                                <div class="h-full rounded-full bg-gold-400 transition-all" style="width: {{ $pct }}%"></div>
                            </div>
                            <p class="mt-1.5 text-eyebrow text-muted">
                                {{ $pct }}% filled · <span class="font-semibold text-navy-700">{{ max(0, $roomsHeld - $roomsNamed) }}</span> still to name
                            </p>
                        </div>
                        <div class="flex gap-5 border-l border-line pl-5">
                            <div>
                                <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Blocks</p>
                                <p class="pf text-lg font-bold text-navy-900">{{ $blocks->count() }}</p>
                            </div>
                            <div>
                                <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Room-nights</p>
                                <p class="pf text-lg font-bold text-navy-900">{{ $roomNightsTotal }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($blocks->isEmpty())
                <x-empty icon="home" title="No room blocks yet"
                         hint="Start with the deal you struck with the hotel — “50 rooms at the Fairmont, these dates, this rate”. Names go in afterwards, one room at a time, until the block is full.">
                    <x-slot:actions>
                        <button type="button" wire:click="newBlock" class="btn-gold btn-sm">＋ Create the first block</button>
                    </x-slot:actions>
                </x-empty>
            @endif

            {{-- ══ blocks ══ --}}
            @foreach ($blocks as $b)
                @php
                    $open = $expandedId === $b->id;
                    $named = $b->filled();
                    $hex = $b->statusHex();
                @endphp
                <div wire:key="blk-{{ $b->id }}" class="card overflow-hidden">

                    {{-- header --}}
                    <div class="group/blk flex cursor-pointer items-center gap-4 px-5 py-4 hover:bg-page/30"
                         wire:click="toggleExpand({{ $b->id }})">
                        <span class="text-navy-300 transition group-hover/blk:text-navy-600 {{ $open ? 'rotate-90' : '' }}">▸</span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="pf truncate text-base font-bold text-navy-900">{{ $b->hotel }}</p>
                                <span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide"
                                      style="background: color-mix(in srgb, {{ $hex }} 12%, transparent); color: {{ $hex }}">{{ $b->statusLabel() }}</span>
                                @if ($b->isFull())
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide text-emerald-700">Full</span>
                                @endif
                            </div>
                            <p class="mt-0.5 truncate text-eyebrow text-muted">
                                {{ $b->room_type ?: 'Standard' }}@if ($b->occupancy) · {{ \App\Models\EventAccommodation::OCCUPANCIES[$b->occupancy] ?? '' }}@endif
                                @if ($b->check_in) · {{ $b->check_in->format('M j') }} – {{ $b->check_out?->format('M j') ?? '?' }} · {{ $b->nights() }} nights @endif
                                @if ($b->confirmation_number) · #{{ $b->confirmation_number }} @endif
                                @if ($b->cutoff_on) · <span class="font-semibold text-amber-700">cut-off {{ $b->cutoff_on->format('M j') }}</span> @endif
                            </p>
                        </div>

                        {{-- fill meter --}}
                        <div class="hidden w-40 shrink-0 sm:block">
                            <div class="flex items-baseline justify-between">
                                <span class="pf text-sm font-bold text-navy-900">{{ $named }}<span class="text-navy-300"> / {{ $b->rooms_count }}</span></span>
                                <span class="text-eyebrow text-muted">{{ $b->fillPct() }}%</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-navy-100">
                                <div class="h-full rounded-full transition-all {{ $b->isFull() ? 'bg-success' : 'bg-gold-500' }}" style="width: {{ $b->fillPct() }}%"></div>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-1.5" wire:click.stop>
                            <button type="button" wire:click="edit({{ $b->id }})"
                                    class="rounded-lg bg-navy-50 px-2.5 py-1.5 text-eyebrow font-bold text-navy-700 transition hover:bg-navy-100">
                                ✎ Edit block
                            </button>
                            <a href="{{ route('events.rooming.pdf', [$event, $b]) }}" target="_blank"
                               class="rounded-lg bg-navy-50 px-2.5 py-1.5 text-eyebrow font-bold text-navy-700 transition hover:bg-navy-100"
                               title="Rooming list PDF — no rates">PDF</a>
                            <button type="button" wire:click="delete({{ $b->id }})"
                                    wire:confirm="Delete the {{ $b->hotel }} block and its {{ $b->rooms->count() }} named {{ \Illuminate\Support\Str::plural('guest', $b->rooms->count()) }}? This cannot be undone."
                                    class="rounded-lg bg-risk/10 px-2.5 py-1.5 text-eyebrow font-bold text-red-700 transition hover:bg-risk/20">
                                Delete
                            </button>
                        </div>
                    </div>

                    {{-- rooming list --}}
                    @if ($open)
                        <div class="border-t border-line bg-page/20">
                            <div class="overflow-x-auto">
                                <datalist id="attendee-names-{{ $b->id }}">
                                    @foreach ($attendees as $a)<option value="{{ $a->name }}">{{ $a->ticket_type }}</option>@endforeach
                                </datalist>
                                <table class="w-full min-w-[1120px]">
                                    <thead>
                                        {{-- grouped header: the four things a hotel reads off a rooming list --}}
                                        <tr class="border-b border-line/60 text-left text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">
                                            <th class="px-4 pt-2"></th>
                                            <th class="px-3 pt-2" colspan="2">Guest</th>
                                            <th class="border-l border-line px-3 pt-2" colspan="2">Room</th>
                                            <th class="border-l border-line px-3 pt-2" colspan="2">Check-in</th>
                                            <th class="border-l border-line px-3 pt-2" colspan="2">Check-out</th>
                                            <th class="border-l border-line px-3 pt-2">Nights</th>
                                            <th class="px-3 pt-2"></th>
                                        </tr>
                                        <tr class="border-b border-line text-left text-eyebrow font-bold uppercase tracking-wide text-muted">
                                            <th class="w-10 px-4 pb-2 text-center">#</th>
                                            <th class="px-3 pb-2">Name &amp; contact</th>
                                            <th class="px-3 pb-2">Sharing with</th>
                                            <th class="border-l border-line px-3 pb-2">Occupancy</th>
                                            <th class="px-3 pb-2">Category</th>
                                            <th class="border-l border-line px-3 pb-2">Date</th>
                                            <th class="px-3 pb-2">Time</th>
                                            <th class="border-l border-line px-3 pb-2">Date</th>
                                            <th class="px-3 pb-2">Time</th>
                                            <th class="border-l border-line px-3 pb-2 text-center">#</th>
                                            <th class="w-10 px-3 pb-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($b->rooms as $i => $r)
                                            @php
                                                $inp = 'w-full rounded-lg border border-transparent bg-transparent px-2 py-1.5 text-xs text-navy-900 placeholder:text-navy-300 hover:border-line focus:border-gold-400 focus:bg-white focus:outline-none';
                                            @endphp
                                            <tr wire:key="rm-{{ $r->id }}" class="group/rm border-b border-line last:border-0 align-top hover:bg-white">
                                                <td class="px-4 py-2 text-center text-eyebrow font-bold text-navy-300">{{ $i + 1 }}</td>

                                                {{-- name, with contact tucked beneath rather than eating two columns --}}
                                                <td class="min-w-[190px] px-1 py-1">
                                                    <input type="text" value="{{ $r->guest }}" placeholder="Guest name"
                                                           list="attendee-names-{{ $b->id }}"
                                                           wire:change="updateRoom({{ $r->id }}, 'guest', $event.target.value)"
                                                           class="{{ $inp }} !text-xs font-semibold">
                                                    <div class="mt-0.5 flex gap-1">
                                                        <input type="text" value="{{ $r->guest_email }}" placeholder="email"
                                                               wire:change="updateRoom({{ $r->id }}, 'guest_email', $event.target.value)"
                                                               class="{{ $inp }} !py-1 !text-eyebrow !text-muted">
                                                        <input type="text" value="{{ $r->guest_phone }}" placeholder="phone"
                                                               wire:change="updateRoom({{ $r->id }}, 'guest_phone', $event.target.value)"
                                                               class="{{ $inp }} !py-1 !text-eyebrow !text-muted">
                                                    </div>
                                                    @if ($r->attendee_id)
                                                        <p class="px-2 pt-0.5 text-eyebrow text-navy-300" title="Linked to the attendee record">◈ attendee</p>
                                                    @endif
                                                </td>

                                                <td class="px-1 py-1">
                                                    <input type="text" value="{{ $r->sharing_with }}" placeholder="—"
                                                           wire:change="updateRoom({{ $r->id }}, 'sharing_with', $event.target.value)"
                                                           class="{{ $inp }}">
                                                </td>

                                                {{-- room: how many sleep in it, and what grade it is --}}
                                                <td class="border-l border-line px-1 py-1">
                                                    <select wire:change="updateRoom({{ $r->id }}, 'occupancy', $event.target.value)"
                                                            class="{{ $inp }} cursor-pointer">
                                                        <option value="">—</option>
                                                        @foreach (\App\Models\EventAccommodation::OCCUPANCIES as $slug => $label)
                                                            <option value="{{ $slug }}" @selected($r->occupancy === $slug)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="px-1 py-1">
                                                    <input type="text" value="{{ $r->room_type }}" placeholder="Deluxe" list="room-categories"
                                                           wire:change="updateRoom({{ $r->id }}, 'room_type', $event.target.value)"
                                                           class="{{ $inp }}">
                                                </td>

                                                {{-- arrival --}}
                                                @php $offIn = $r->check_in && $b->check_in && ! $r->check_in->isSameDay($b->check_in); @endphp
                                                <td class="border-l border-line px-1 py-1">
                                                    <input type="date" value="{{ $r->check_in?->format('Y-m-d') }}"
                                                           wire:change="updateRoom({{ $r->id }}, 'check_in', $event.target.value)"
                                                           title="{{ $offIn ? 'Differs from the block dates' : '' }}"
                                                           class="{{ $inp }} {{ $offIn ? '!border-gold-300 !bg-gold-50/40 font-semibold !text-gold-800' : '' }}">
                                                </td>
                                                <td class="px-1 py-1">
                                                    <input type="time" value="{{ $r->arrival_time }}"
                                                           wire:change="updateRoom({{ $r->id }}, 'arrival_time', $event.target.value)"
                                                           class="{{ $inp }}">
                                                </td>
                                                {{-- departure --}}
                                                @php $offOut = $r->check_out && $b->check_out && ! $r->check_out->isSameDay($b->check_out); @endphp
                                                <td class="border-l border-line px-1 py-1">
                                                    <input type="date" value="{{ $r->check_out?->format('Y-m-d') }}"
                                                           wire:change="updateRoom({{ $r->id }}, 'check_out', $event.target.value)"
                                                           title="{{ $offOut ? 'Differs from the block dates' : '' }}"
                                                           class="{{ $inp }} {{ $offOut ? '!border-gold-300 !bg-gold-50/40 font-semibold !text-gold-800' : '' }}">
                                                </td>
                                                <td class="px-1 py-1">
                                                    <input type="time" value="{{ $r->departure_time }}"
                                                           wire:change="updateRoom({{ $r->id }}, 'departure_time', $event.target.value)"
                                                           class="{{ $inp }}">
                                                </td>
                                                {{-- nights for THIS guest, from their own check-in/out --}}
                                                <td class="border-l border-line px-2 py-2 text-center">
                                                    <span class="inline-block rounded-md bg-navy-50 px-2 py-1 text-xs font-bold text-navy-700" title="Nights for this guest — from their own check-in and check-out">{{ $r->nights() ?: '·' }}</span>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <button type="button" wire:click="deleteRoom({{ $r->id }})"
                                                            class="rounded-lg px-1.5 py-1 text-eyebrow font-bold text-navy-300 opacity-0 transition hover:bg-risk/10 hover:text-red-700 group-hover/rm:opacity-100">✕</button>
                                                </td>
                                            </tr>
                                        @endforeach

                                        {{-- the next empty room --}}
                                        @if ($b->rooms->count() < $b->rooms_count)
                                            <tr class="bg-white/60">
                                                <td class="px-4 py-2 text-center text-eyebrow font-bold text-gold-700">{{ $b->rooms->count() + 1 }}</td>
                                                <td class="px-1 py-2" colspan="9">
                                                    <input type="text" wire:model="newGuest.{{ $b->id }}"
                                                           wire:keydown.enter="addRoom({{ $b->id }})"
                                                           list="attendee-names-{{ $b->id }}"
                                                           placeholder="{{ $attendees->isEmpty()
                                                                ? 'Type a guest name and press Enter — they will be added to the attendee list too…'
                                                                : 'Start typing to pick from the '.$attendees->count().' attendees, or write a new name…' }}"
                                                           class="w-full rounded-lg border border-dashed border-navy-200 bg-transparent px-2 py-1.5 text-xs text-navy-900 placeholder:text-navy-300 focus:border-gold-400 focus:bg-white focus:outline-none">
                                                    @error('newGuest.'.$b->id)<p class="mt-1 px-2 text-xs text-risk">{{ $message }}</p>@enderror
                                                </td>
                                                <td class="px-3 py-2">
                                                    <button type="button" wire:click="addRoom({{ $b->id }})"
                                                            class="rounded-lg bg-gold-100 px-1.5 py-1 text-eyebrow font-bold text-gold-700 hover:bg-gold-200">＋</button>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>

                            {{-- import an Excel / CSV rooming list into this block --}}
                            @if ($importBlockId === $b->id)
                                <div class="border-t border-line bg-gold-50/40 px-5 py-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-bold text-navy-900">Import guests into {{ $b->hotel }}</p>
                                            <p class="mt-0.5 text-eyebrow leading-relaxed text-muted">
                                                Excel (.xlsx) or CSV. First row = headers. Recognised columns:
                                                <span class="font-semibold text-navy-700">Name</span> (required), Email, Phone, Room Type, Occupancy, Sharing With, Check In, Check Out, Arrival Time, Departure Time, Confirmation #, Notes.
                                                Anything left blank uses the block's own dates, room type &amp; rate.
                                            </p>
                                        </div>
                                        <button type="button" wire:click="closeImport" class="shrink-0 rounded-lg px-2 py-1 text-eyebrow font-bold text-navy-400 hover:text-navy-700">✕</button>
                                    </div>
                                    <div class="mt-3 flex flex-wrap items-center gap-2.5">
                                        <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv,text/csv"
                                               class="text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                                        <button type="button" wire:click="importRooms" wire:loading.attr="disabled" wire:target="importRooms,importFile"
                                                class="rounded-lg bg-gold-500 px-3.5 py-1.5 text-xs font-bold text-navy-950 transition hover:brightness-105 disabled:opacity-50">
                                            <span wire:loading.remove wire:target="importRooms">Import list</span>
                                            <span wire:loading wire:target="importRooms">Importing…</span>
                                        </button>
                                        <span wire:loading wire:target="importFile" class="text-eyebrow font-semibold text-gold-700">Uploading…</span>
                                        <a href="{{ route('events.rooming.template', [$event, $b]) }}"
                                           class="ml-1 border-l border-gold-300/70 pl-3 text-eyebrow font-bold uppercase tracking-wide text-navy-500 hover:text-navy-900"
                                           title="Download a ready-to-fill Excel template with these columns">↧ Download template</a>
                                    </div>
                                    @error('importFile')<p class="mt-1.5 text-xs text-risk">{{ $message }}</p>@enderror
                                </div>
                            @endif

                            @if ($importMsg && $expandedId === $b->id)
                                <div class="border-t border-line bg-emerald-50/60 px-5 py-2 text-xs font-semibold text-emerald-800">{{ $importMsg }}</div>
                            @endif

                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-2.5">
                                <p class="text-eyebrow text-muted">
                                    <span class="font-bold text-navy-900">{{ $named }}</span> named ·
                                    <span class="font-bold text-navy-900">{{ $b->remaining() }}</span> still open
                                    <span class="ml-2 border-l border-line pl-2">
                                        @if ($attendees->isEmpty())
                                            names typed here are added to <a href="?tab=attendees" class="font-semibold text-navy-600 underline">Attendees</a>
                                        @else
                                            names autocomplete from the {{ $attendees->count() }} attendees · flights live in Transportation
                                        @endif
                                    </span>
                                    @if ($b->rate_cents)
                                        <span class="ml-2 border-l border-line pl-2">
                                            internal: {{ $event->money($b->rate_cents) }}/night · block {{ $event->money($b->totalCents()) }}
                                        </span>
                                    @endif
                                </p>
                                <div class="flex items-center gap-3">
                                    <button type="button" wire:click="openImport({{ $b->id }})"
                                            class="text-eyebrow font-bold uppercase tracking-wide text-navy-500 hover:text-navy-900">⇪ Import Excel</button>
                                    <a href="{{ route('events.rooming.pdf', [$event, $b]) }}" target="_blank"
                                       class="border-l border-line pl-3 text-eyebrow font-bold uppercase tracking-wide text-navy-500 hover:text-navy-900">Send to hotel →</a>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- ══ pre-block bookings, if any survived ══ --}}
            @if ($loose->isNotEmpty())
                <div class="card overflow-hidden">
                    <div class="border-b border-line px-5 py-2.5">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Not in a block</p>
                        <p class="mt-0.5 text-eyebrow text-muted">Bookings made before blocks existed. Convert one to start naming its guests.</p>
                    </div>
                    @foreach ($loose as $l)
                        <div wire:key="loose-{{ $l->id }}" class="group/l flex items-center gap-3 border-b border-line px-5 py-2.5 last:border-0">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold text-navy-900">{{ $l->hotel }}</p>
                                <p class="truncate text-eyebrow text-muted">{{ $l->guest ?: '—' }} · {{ $l->rooms }} room(s)</p>
                            </div>
                            <button type="button" wire:click="convertToBlock({{ $l->id }})"
                                    class="shrink-0 rounded-lg bg-gold-100 px-2.5 py-1 text-eyebrow font-bold text-gold-700 transition hover:bg-gold-200">
                                Convert to block →
                            </button>
                            <button type="button" wire:click="deleteRoom({{ $l->id }})"
                                    class="rounded-lg px-1.5 py-1 text-eyebrow font-bold text-navy-300 opacity-0 transition hover:bg-risk/10 hover:text-red-700 group-hover/l:opacity-100">✕</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ══ control rail ══ --}}
        <div class="xl:sticky xl:top-12 xl:h-fit">
            <div class="cc-panel">
                <div class="cc-head">
                    <x-icon name="sparkles" class="relative h-4 w-4 text-gold-600" />
                    <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-navy-900">Accommodation Control Center</span>
                </div>
                <div class="border-b border-line p-4">
                    <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-navy-300"></span> Summary</p>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Blocks</span><span class="font-bold text-navy-900">{{ $blocks->count() }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Rooms held</span><span class="font-bold text-navy-900">{{ $roomsHeld }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Rooms named</span><span class="font-bold text-navy-900">{{ $roomsNamed }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Room-nights</span><span class="font-bold text-navy-900">{{ $roomNightsTotal }}</span></div>
                        <div class="flex justify-between border-t border-line pt-1.5"><span class="text-muted">Estimated cost</span><span class="font-bold text-navy-900">{{ $event->money($costTotal) }}</span></div>

                        {{-- Which section of the budget this module's spend
                             lands under. Asked here because this is where
                             somebody is looking when the question comes up. --}}
                        <x-budget-routing :routing="$this->budgetRouting()" />
                    </div>
                    <p class="mt-2 text-eyebrow leading-relaxed text-muted">Rates are internal — the rooming list PDF never shows them.</p>
                </div>
                <div class="p-4">
                    <button type="button" wire:click="newBlock" class="btn-gold h-10 w-full text-xs">＋ New Room Block</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ block modal ══ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit room block' : 'New room block'"
                 subtitle="The deal with the hotel. Guest names come after."
                 close="$set('showForm', false)">
            <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    {{-- Pick the hotel from the directory. Typing one still works
                         for a hotel that is not in it yet — a name is only
                         required when nothing was picked. --}}
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow" for="ab-venue">Hotel</label>
                        <select id="ab-venue" wire:model.live="venue_id" class="input h-10 text-sm">
                            <option value="">— type a name below —</option>
                            @foreach ($venues as $v)
                                <option value="{{ $v->id }}">{{ $v->name }}@if ($v->city) · {{ $v->city }}@endif</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-eyebrow text-muted">
                            Not there yet?
                            <a href="{{ route('venues.index') }}" class="font-semibold text-gold-700 hover:underline">Add it to Venues</a>
                            so every event can reuse it.
                        </p>
                        @error('venue_id')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>

                    @unless ($venue_id)
                        <div class="sm:col-span-2">
                            <label class="field-label !mb-1 !text-eyebrow" for="ab-hotel">…or type the name</label>
                            <input id="ab-hotel" type="text" wire:model="hotel" class="input h-10 text-sm" placeholder="Fairmont Amman">
                            @error('hotel')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                        </div>
                    @endunless
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Supplier</label>
                        <select wire:model="supplier_id" class="input h-10 text-sm">
                            <option value="">— none —</option>
                            @foreach ($hotels as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Room category</label>
                        <input type="text" wire:model="room_type" class="input h-10 text-sm" placeholder="Deluxe" list="room-categories">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Occupancy</label>
                        <select wire:model="occupancy" class="input h-10 text-sm">
                            <option value="">—</option>
                            @foreach (\App\Models\EventAccommodation::OCCUPANCIES as $slug => $label)
                                <option value="{{ $slug }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-eyebrow text-muted">Every room in the block starts here; change any row later.</p>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">How many rooms</label>
                        <input type="number" min="1" wire:model="rooms_count" class="input h-10 text-sm">
                        @error('rooms_count')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Rate / night ({{ $event->currency }}) <span class="normal-case text-muted">— internal</span></label>
                        <input type="number" step="0.01" min="0" wire:model="rate" class="input h-10 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Check-in</label>
                        <input type="date" wire:model="check_in" class="input h-10 text-sm">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Check-out</label>
                        <input type="date" wire:model="check_out" class="input h-10 text-sm">
                        @error('check_out')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Status</label>
                        <select wire:model="status" class="input h-10 text-sm">
                            @foreach ($blockStatuses as $slug => [$label, $hex])<option value="{{ $slug }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Release / cut-off date</label>
                        <input type="date" wire:model="cutoff_on" class="input h-10 text-sm">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Confirmation #</label>
                        <input type="text" wire:model="confirmation_number" class="input h-10 text-sm" placeholder="—">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">Notes</label>
                        <input type="text" wire:model="notes" class="input h-10 text-sm" placeholder="Breakfast included, 2 complimentary upgrades…">
                    </div>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('showForm', false)" class="btn-ghost btn-sm">Cancel</button>
                    <button type="submit" class="btn-navy btn-sm">{{ $editingId ? 'Update block' : 'Create block' }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
