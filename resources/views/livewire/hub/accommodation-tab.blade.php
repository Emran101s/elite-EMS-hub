@php
    $blockStatuses = \App\Models\EventRoomBlock::STATUSES;
    $moduleHex = \App\Models\Event::moduleColor('accommodation');
@endphp
<div class="cx-canvas">
    <datalist id="room-categories">
        @foreach (\App\Support\Taxonomy::values('room_category') as $c)<option value="{{ $c }}"></option>@endforeach
    </datalist>

    {{-- The old Rooming list headline strip (ring + named/blocks/room-nights
         figures, in a dark x-module-head) is retired here — the Event Hub's
         new Universal Module Header shows the equivalent Blocks/Rooms named/
         Still to name/Room-nights numbers above this component now, so
         showing both was two headers for one module. Its own "＋ New block"
         action stays reachable from the control rail's own button further
         down this page. --}}

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0 space-y-3">

            @if ($blocks->isEmpty())
                <div class="cx-empty">
                    <h3>No room blocks yet</h3>
                    <p>Start with the deal you struck with the hotel — “50 rooms at the Fairmont, these dates, this rate”. Names go in afterwards, one room at a time, until the block is full.</p>
                    <button type="button" wire:click="newBlock" class="cx-btn cx-btn-accent" style="display:inline-flex">＋ Create the first block</button>
                </div>
            @endif

            {{-- ══ WHAT THIS MODULE IS ACTUALLY FOR ══
                 Accommodation is a race: fill the block before the hotel takes
                 the rooms back. Both halves of that were silent. The naming gap
                 lived as a figure in the module header, and the release date —
                 the deadline the whole job runs against — appeared only as a
                 small amber word inside a collapsed row, and only if somebody
                 had set it. A held block with no release date recorded is the
                 real risk here, and the tool never said so.

                 Both are now stated before the blocks, because they are the
                 reason to open them. --}}
            @php
                $heldBlocks = $blocks->reject(fn ($b) => in_array($b->status, ['cancelled', 'released'], true));
                $roomsHeld = $heldBlocks->sum('rooms_count');
                $roomsNamed = $heldBlocks->sum(fn ($b) => $b->filled());
                $toName = max(0, $roomsHeld - $roomsNamed);
                $noCutoff = $heldBlocks->filter(fn ($b) => ! $b->cutoff_on);
                $soonest = $heldBlocks->filter(fn ($b) => $b->cutoff_on)->sortBy('cutoff_on')->first();
                $daysToCutoff = $soonest?->cutoff_on?->diffInDays(now()->startOfDay(), false);
            @endphp
            @if ($heldBlocks->isNotEmpty() && ($toName > 0 || $noCutoff->isNotEmpty()))
                @php $tone = $noCutoff->isNotEmpty() ? 'warn' : 'accent'; @endphp
                <div class="cx-lcard mb-2 flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-2.5"
                     style="border-color: var(--cx-{{ $tone }}); background: var(--cx-{{ $tone === 'warn' ? 'warn-wash' : 'accent-wash' }})">
                    @if ($toName > 0)
                        <span class="flex items-center gap-2 text-[12px] font-bold" style="color: var(--cx-warn-ink)">
                            <span class="cx-hexdot" style="background: var(--cx-{{ $tone }})"></span>
                            {{ number_format($toName) }} of {{ number_format($roomsHeld) }} {{ str('room')->plural($roomsHeld) }} still to name
                        </span>
                    @endif

                    @if ($noCutoff->isNotEmpty())
                        <span class="text-[11.5px]" style="color: var(--cx-warn-ink)">
                            {{ $noCutoff->count() }} {{ str('block')->plural($noCutoff->count()) }} with no release date —
                            the hotel can take those rooms back without warning.
                        </span>
                    @elseif ($soonest && $daysToCutoff !== null)
                        <span class="text-[11.5px]" style="color: var(--cx-warn-ink)">
                            @if ($daysToCutoff > 0)
                                {{ $soonest->hotel }} releases in {{ (int) $daysToCutoff }} {{ str('day')->plural((int) $daysToCutoff) }}
                            @else
                                {{ $soonest->hotel }} passed its release date
                            @endif
                        </span>
                    @endif

                    <span class="ms-auto text-[10.5px]" style="color: var(--cx-warn-ink); opacity:.75">Open a block to name its rooms.</span>
                </div>
            @endif

            {{-- ══ blocks ══ --}}
            @foreach ($blocks as $b)
                @php
                    $open = $expandedId === $b->id;
                    $named = $b->filled();
                    $hex = $b->statusHex();
                @endphp
                <div wire:key="blk-{{ $b->id }}" class="cx-lcard">

                    {{-- header --}}
                    <div class="group/blk flex flex-wrap cursor-pointer items-center gap-x-4 gap-y-2 px-5 py-4 transition hover:bg-[var(--cx-surface-2)]"
                         wire:click="toggleExpand({{ $b->id }})">
                        <span class="text-muted transition group-hover/blk:text-muted {{ $open ? 'rotate-90' : '' }}">▸</span>

                        {{-- hex block badge — the honeycomb signature, in the
                             block's own status colour --}}
                        <span class="cx-cathex flex shrink-0 items-center justify-center text-white"
                              style="width:30px;height:33px;background:{{ $hex }}">
                            <x-icon name="{{ \App\Models\Event::moduleIcon('accommodation') }}" class="h-3.5 w-3.5" />
                        </span>

                        <div class="order-last w-full min-w-0 sm:order-none sm:w-auto sm:flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate text-base font-bold text-ink">{{ $b->hotel }}</p>
                                <span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide"
                                      style="background: color-mix(in srgb, {{ $hex }} 12%, transparent); color: {{ $hex }}">{{ $b->statusLabel() }}</span>
                                @if ($b->isFull())
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide text-emerald-700">Full</span>
                                @endif
                                {{-- On the title row, not in the subtitle: that line is
                                     truncated, and this warning was being cut to "no…". --}}
                                @if (! $b->cutoff_on && ! in_array($b->status, ['cancelled', 'released'], true))
                                    <span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide"
                                          style="background: var(--cx-warn-wash); color: var(--cx-warn-ink)">No release date</span>
                                @endif
                            </div>
                            <p class="mt-0.5 truncate text-eyebrow text-muted">
                                {{ $b->room_type ?: 'Standard' }}@if ($b->occupancy) · {{ \App\Models\EventAccommodation::OCCUPANCIES[$b->occupancy] ?? '' }}@endif
                                @if ($b->check_in) · {{ $b->check_in->format('j M') }} – {{ $b->check_out?->format('j M') ?? '?' }} · {{ $b->nights() }} nights @endif
                                @if ($b->supplier) · booked via {{ $b->supplier->name }} @endif
                                @if ($b->confirmation_number) · #{{ $b->confirmation_number }} @endif
                                @if ($b->cutoff_on) · <span class="font-semibold text-amber-700">releases {{ $b->cutoff_on->format('j M') }}</span> @endif
                            </p>
                        </div>

                        {{-- fill meter --}}
                        <div class="hidden w-40 shrink-0 sm:block">
                            <div class="flex items-baseline justify-between">
                                <span class="text-sm font-bold text-ink">{{ $named }}<span class="text-muted"> / {{ $b->rooms_count }}</span></span>
                                <span class="text-eyebrow text-muted">{{ $b->fillPct() }}%</span>
                            </div>
                            <div class="cx-bar mt-1">
                                <span class="{{ $b->isFull() ? 'tone-ok' : '' }}" style="width: {{ $b->fillPct() }}%; {{ $b->isFull() ? '' : 'background:var(--cx-accent)' }}"></span>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-1 sm:gap-1.5" wire:click.stop>
                            <button type="button" wire:click="edit({{ $b->id }})" title="Edit block"
                                    class="rounded-lg bg-page px-2 py-1.5 text-eyebrow font-bold text-ink transition hover:bg-page sm:px-2.5">
                                ✎<span class="hidden sm:inline"> Edit block</span>
                            </button>
                            <a href="{{ route('events.rooming.pdf', [$event, $b]) }}" target="_blank"
                               class="rounded-lg bg-page px-2 py-1.5 text-eyebrow font-bold text-ink transition hover:bg-page sm:px-2.5"
                               title="Rooming list PDF — no rates">PDF</a>
                            <x-confirm title="Delete the {{ $b->hotel }} block and its {{ $b->rooms->count() }} named {{ \Illuminate\Support\Str::plural('guest', $b->rooms->count()) }}?"
                                    body="This cannot be undone."
                                    confirm="Delete"
                                    run="$wire.delete({{ $b->id }})"
                                    class="rounded-lg bg-danger-soft px-2 py-1.5 text-eyebrow font-bold text-danger-ink transition hover:bg-red-100 sm:px-2.5">
                                ✕<span class="hidden sm:inline"> Delete</span>
                            </x-confirm>
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
                                        <tr class="border-b border-line/60 text-left text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">
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
                                                $inp = 'w-full rounded-lg border border-transparent bg-transparent px-2 py-1.5 text-xs text-ink placeholder:text-muted hover:border-line focus:border-navy-300 focus:bg-white focus:outline-none';
                                            @endphp
                                            <tr wire:key="rm-{{ $r->id }}" class="group/rm border-b border-line last:border-0 align-top hover:bg-white">
                                                <td class="px-4 py-2 text-center text-eyebrow font-bold text-muted">{{ $i + 1 }}</td>

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
                                                        <p class="px-2 pt-0.5 text-eyebrow text-muted" title="Linked to the attendee record">◈ attendee</p>
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
                                                           class="{{ $inp }} {{ $offIn ? '!border-warning/50 !bg-warning-soft font-semibold !text-warning-ink' : '' }}">
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
                                                           class="{{ $inp }} {{ $offOut ? '!border-warning/50 !bg-warning-soft font-semibold !text-warning-ink' : '' }}">
                                                </td>
                                                <td class="px-1 py-1">
                                                    <input type="time" value="{{ $r->departure_time }}"
                                                           wire:change="updateRoom({{ $r->id }}, 'departure_time', $event.target.value)"
                                                           class="{{ $inp }}">
                                                </td>
                                                {{-- nights for THIS guest, from their own check-in/out --}}
                                                <td class="border-l border-line px-2 py-2 text-center">
                                                    <span class="inline-block rounded-md bg-page px-2 py-1 text-xs font-bold text-ink" title="Nights for this guest — from their own check-in and check-out">{{ $r->nights() ?: '·' }}</span>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <button type="button" wire:click="deleteRoom({{ $r->id }})"
                                                            class="rounded-lg px-1.5 py-1 text-eyebrow font-bold text-muted opacity-100 transition sm:opacity-0 hover:bg-danger-soft hover:text-danger-ink sm:group-hover/rm:opacity-100">✕</button>
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
                                                           class="w-full rounded-lg border border-dashed border-line bg-transparent px-2 py-1.5 text-xs text-ink placeholder:text-muted focus:border-navy-300 focus:bg-white focus:outline-none">
                                                    @error('newGuest.'.$b->id)<p class="mt-1 px-2 text-xs text-danger-ink">{{ $message }}</p>@enderror
                                                </td>
                                                <td class="px-3 py-2">
                                                    <button type="button" wire:click="addRoom({{ $b->id }})"
                                                            class="rounded-lg bg-gold-50 px-1.5 py-1 text-eyebrow font-bold text-gold-700 hover:bg-gold-100">＋</button>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>

                            {{-- import an Excel / CSV rooming list into this block --}}
                            @if ($importBlockId === $b->id)
                                <div class="border-t border-line bg-gold-50 px-5 py-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-bold text-ink">Import guests into {{ $b->hotel }}</p>
                                            <p class="mt-0.5 text-eyebrow leading-relaxed text-muted">
                                                Excel (.xlsx) or CSV. First row = headers. Recognised columns:
                                                <span class="font-semibold text-ink">Name</span> (required), Email, Phone, Room Type, Occupancy, Sharing With, Check In, Check Out, Arrival Time, Departure Time, Confirmation #, Notes.
                                                Anything left blank uses the block's own dates, room type &amp; rate.
                                            </p>
                                        </div>
                                        <button type="button" wire:click="closeImport" class="shrink-0 rounded-lg px-2 py-1 text-eyebrow font-bold text-muted hover:text-ink">✕</button>
                                    </div>
                                    <div class="mt-3 flex flex-wrap items-center gap-2.5">
                                        <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv,text/csv"
                                               class="text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                                        <button type="button" wire:click="importRooms" wire:loading.attr="disabled" wire:target="importRooms,importFile"
                                                class="rounded-lg bg-gold-500 px-3.5 py-1.5 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400 disabled:opacity-50">
                                            <span wire:loading.remove wire:target="importRooms">Import list</span>
                                            <span wire:loading wire:target="importRooms">Importing…</span>
                                        </button>
                                        <span wire:loading wire:target="importFile" class="text-eyebrow font-semibold text-gold-700">Uploading…</span>
                                        <a href="{{ route('events.rooming.template', [$event, $b]) }}"
                                           class="ml-1 border-l border-line pl-3 text-eyebrow font-bold uppercase tracking-wide text-muted hover:text-ink"
                                           title="Download a ready-to-fill Excel template with these columns">↧ Download template</a>
                                    </div>
                                    @error('importFile')<p class="mt-1.5 text-xs text-danger-ink">{{ $message }}</p>@enderror
                                </div>
                            @endif

                            @if ($importMsg && $expandedId === $b->id)
                                <div class="border-t border-line bg-emerald-50/60 px-5 py-2 text-xs font-semibold text-emerald-800">{{ $importMsg }}</div>
                            @endif

                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-2.5">
                                <p class="text-eyebrow text-muted">
                                    <span class="font-bold text-ink">{{ $named }}</span> named ·
                                    <span class="font-bold text-ink">{{ $b->remaining() }}</span> still open
                                    <span class="ml-2 border-l border-line pl-2">
                                        @if ($attendees->isEmpty())
                                            names typed here are added to <a href="?tab=attendees" class="font-semibold text-muted underline">Attendees</a>
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
                                            class="text-eyebrow font-bold uppercase tracking-wide text-muted hover:text-ink">⇪ Import Excel</button>
                                    <a href="{{ route('events.rooming.pdf', [$event, $b]) }}" target="_blank"
                                       class="border-l border-line pl-3 text-eyebrow font-bold uppercase tracking-wide text-muted hover:text-ink">Send to hotel →</a>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- ══ pre-block bookings, if any survived ══ --}}
            @if ($loose->isNotEmpty())
                <div class="cx-lcard">
                    <div class="border-b border-line px-5 py-2.5" style="background:var(--cx-surface-2)">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Not in a block</p>
                        <p class="mt-0.5 text-eyebrow text-muted">Bookings made before blocks existed. Convert one to start naming its guests.</p>
                    </div>
                    @foreach ($loose as $l)
                        <div wire:key="loose-{{ $l->id }}" class="group/l flex items-center gap-3 border-b border-line px-5 py-2.5 last:border-0">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold text-ink">{{ $l->hotel }}</p>
                                <p class="truncate text-eyebrow text-muted">{{ $l->guest ?: '—' }} · {{ $l->rooms }} room(s)</p>
                            </div>
                            <button type="button" wire:click="convertToBlock({{ $l->id }})"
                                    class="shrink-0 rounded-lg bg-gold-50 px-2.5 py-1 text-eyebrow font-bold text-gold-700 transition hover:bg-gold-100">
                                Convert to block →
                            </button>
                            <button type="button" wire:click="deleteRoom({{ $l->id }})"
                                    class="rounded-lg px-1.5 py-1 text-eyebrow font-bold text-muted opacity-100 transition hover:bg-danger-soft hover:text-danger-ink sm:opacity-0 sm:group-hover/l:opacity-100">✕</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ══ control rail ══ --}}
        <div class="xl:sticky xl:top-12 xl:h-fit">
            <div class="cx-panel">
                <div class="cx-lcard-head" style="background: var(--cx-espresso-1); border-bottom-color: transparent;">
                    <span class="flex items-center gap-2 text-[10.5px] font-bold uppercase tracking-[0.14em]" style="color:#F0E7D5">
                        <span class="cx-cathex" style="width:22px;height:24px;background:{{ $moduleHex }}">
                            <x-icon name="{{ \App\Models\Event::moduleIcon('accommodation') }}" class="h-3 w-3" />
                        </span>
                        Stay Control
                    </span>
                </div>

                <div class="cx-panel-sec">
                    {{-- Blocks / Rooms named / Room-nights are the same figures the
                         Universal Module Header already shows above this component;
                         only what the header doesn't carry stays here. --}}
                    <p class="cx-panel-k"><span class="cx-hexdot"></span> Summary</p>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Estimated cost</span><span class="font-bold text-ink">{{ $event->money($costTotal) }}</span></div>

                        {{-- Which section of the budget this module's spend
                             lands under. Asked here because this is where
                             somebody is looking when the question comes up. --}}
                        <x-budget-routing :routing="$this->budgetRouting()" />
                    </div>
                    <p class="mt-2 text-eyebrow leading-relaxed text-muted">Rates are internal — the rooming list PDF never shows them.</p>
                </div>

                <div class="cx-panel-sec">
                    <button type="button" wire:click="newBlock" class="cx-btn cx-btn-accent w-full justify-center" style="height:40px">＋ New Room Block</button>
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
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="ab-venue">Hotel</label>
                        <select id="ab-venue" wire:model.live="venue_id" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
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
                        @error('venue_id')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                    </div>

                    @unless ($venue_id)
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="ab-hotel">…or type the name</label>
                            <input id="ab-hotel" type="text" wire:model="hotel" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Fairmont Amman">
                            @error('hotel')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                        </div>
                    @endunless
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Supplier</label>
                        <select wire:model="supplier_id" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                            <option value="">— none —</option>
                            @foreach ($hotels as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Room category</label>
                        <input type="text" wire:model="room_type" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Deluxe" list="room-categories">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Occupancy</label>
                        <select wire:model="occupancy" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                            <option value="">—</option>
                            @foreach (\App\Models\EventAccommodation::OCCUPANCIES as $slug => $label)
                                <option value="{{ $slug }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-eyebrow text-muted">Every room in the block starts here; change any row later.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">How many rooms</label>
                        <input type="number" min="1" wire:model="rooms_count" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                        @error('rooms_count')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Rate / night ({{ $event->currency }}) <span class="normal-case text-muted">— internal</span></label>
                        <input type="number" step="0.01" min="0" wire:model="rate" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Check-in</label>
                        <input type="date" wire:model="check_in" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Check-out</label>
                        <input type="date" wire:model="check_out" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                        @error('check_out')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Status</label>
                        <select wire:model="status" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                            @foreach ($blockStatuses as $slug => [$label, $hex])<option value="{{ $slug }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Release / cut-off date</label>
                        <input type="date" wire:model="cutoff_on" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Confirmation #</label>
                        <input type="text" wire:model="confirmation_number" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="—">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Notes</label>
                        <input type="text" wire:model="notes" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Breakfast included, 2 complimentary upgrades…">
                    </div>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('showForm', false)" class="btn-sm rounded-full font-semibold text-muted transition hover:text-ink">Cancel</button>
                    <button type="submit" class="btn-sm rounded-full bg-gold-500 font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">{{ $editingId ? 'Update block' : 'Create block' }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
