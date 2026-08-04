@php
    // The module's colour comes from the one map, never a literal.
    $moduleColor = \App\Models\Event::moduleColor('transportation');

    // Capacity, not brand, decides the icon — it's what you're actually choosing between.
    $vehicleIcon = fn ($cap) => $cap <= 2 ? '🚗' : ($cap <= 8 ? '🚐' : ($cap <= 20 ? '🚌' : '🚍'));
@endphp
<div>
    <datalist id="airlines">
        @foreach (['Royal Jordanian', 'Emirates', 'Qatar Airways', 'Turkish Airlines', 'EgyptAir',
                   'Saudia', 'Etihad', 'Lufthansa', 'British Airways', 'Air France', 'Pegasus', 'flydubai'] as $al)
            <option value="{{ $al }}"></option>
        @endforeach
    </datalist>
    <div>
        <div class="min-w-0 space-y-3">

            {{-- Lead strip — Transport used a standalone page title; hub modules lead with numbers. --}}
            <x-stat-strip :stats="[
                ['Movements', $total, 'truck', null, null, $shown < $total ? $shown.' shown' : null],
                ['Passengers', $paxTotal, 'users', null, null, $unassignedCount ? $unassignedCount.' still to place' : 'All placed'],
                ['Seats booked', $seatsTotal, 'identification', $seatsTotal > 0 ? min(100, (int) round($paxTotal / max(1, $seatsTotal) * 100)) : null, $overbooked ? 'bg-risk' : 'bg-gold-400', $overbooked ? $overbooked.' over capacity' : null, $overbooked ? 'text-red-700' : 'text-navy-900'],
                ['Unassigned', $unassignedCount, 'flag', null, null, 'In the guest pool', $unassignedCount ? 'text-amber-700' : 'text-emerald-600'],
            ]" />

            {{-- Compact action bar — same weight as Attendees / Approvals toolbars --}}
            <div class="flex flex-wrap items-center gap-2">
                @if ($total)
                    <span class="inline-flex items-center rounded-xl border border-line bg-white p-0.5">
                        <span class="rounded-lg bg-navy-950 px-2.5 py-1.5 text-eyebrow font-bold text-white">List</span>
                        <a href="{{ route('events.transport.dispatch', $event) }}"
                           class="rounded-lg px-2.5 py-1.5 text-eyebrow font-bold text-navy-500 transition hover:text-navy-900"
                           title="Lanes against a time axis — plan and catch clashes">Dispatch</a>
                        <a href="{{ route('events.transport.live', $event) }}"
                           class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-eyebrow font-bold text-navy-500 transition hover:text-navy-900"
                           title="Event-day operations — designed for a phone">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>Live
                        </a>
                    </span>

                    <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                        <button type="button" @click="open = !open" class="btn-ghost btn-sm">
                            <span>↧ Export</span>
                            @if ($filterLeg !== '' || $filterDay !== '')
                                <span class="ml-1 opacity-60">({{ $shown }})</span>
                            @endif
                        </button>

                        <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                             class="absolute left-0 z-30 mt-1 w-72 overflow-hidden rounded-xl border border-line bg-white shadow-overlay">
                            <p class="border-b border-line bg-page/50 px-4 py-2 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">
                                {{ $filterLeg || $filterDay ? $shown.' of '.$total.' movements' : 'All '.$total.' movements' }}
                            </p>
                            <a href="{{ route('events.transport.daily-schedule.pdf', [$event, ...$this->exportFilters()]) }}" target="_blank"
                               class="block border-b border-line px-4 py-2 transition hover:bg-page/60">
                                <span class="block text-xs font-bold text-navy-900">Daily Schedule</span>
                                <span class="block text-eyebrow text-muted">Ops team — one day per page</span>
                            </a>
                            <a href="{{ route('events.transport.pdf', [$event, ...$this->exportFilters()]) }}" target="_blank"
                               class="block border-b border-line px-4 py-2 transition hover:bg-page/60">
                                <span class="block text-xs font-bold text-navy-900">Vehicle Manifest</span>
                                <span class="block text-eyebrow text-muted">Who rides in which vehicle</span>
                            </a>
                            <a href="{{ route('events.transport.trip-sheet.pdf', [$event, ...array_filter(['day' => $filterDay])]) }}" target="_blank"
                               class="block border-b border-line px-4 py-2 transition hover:bg-page/60">
                                <span class="block text-xs font-bold text-navy-900">Driver Trip Sheets</span>
                                <span class="block text-eyebrow text-muted">One page per driver, per day</span>
                            </a>
                            <a href="{{ route('events.transport.vip-sheet.pdf', $event) }}" target="_blank"
                               class="block px-4 py-2 transition hover:bg-page/60">
                                <span class="block text-xs font-bold text-navy-900">VIP Transfer Sheets</span>
                                <span class="block text-eyebrow text-muted">One page per VIP or speaker</span>
                            </a>
                            <p class="border-y border-line bg-page/50 px-4 py-2 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Whole event</p>
                            <a href="{{ route('events.transport.master-plan.pdf', $event) }}" target="_blank"
                               class="block border-b border-line px-4 py-2 transition hover:bg-page/60">
                                <span class="block text-xs font-bold text-navy-900">Master Plan</span>
                                <span class="block text-eyebrow text-muted">Client-facing cover &amp; approval</span>
                            </a>
                            <a href="{{ route('events.transport.supplier-order.pdf', $event) }}" target="_blank"
                               class="block px-4 py-2 transition hover:bg-page/60">
                                <span class="block text-xs font-bold text-navy-900">Supplier Order</span>
                                <span class="block text-eyebrow text-muted">Request per vendor to quote</span>
                            </a>
                        </div>
                    </div>
                @endif

                @if ($attendeePull > 0)
                    <button type="button" wire:click="pullAttendees"
                            wire:confirm="Add {{ $attendeePull }} registered {{ \Illuminate\Support\Str::plural('attendee', $attendeePull) }} to the transport guest pool as arrivals? People already pulled are skipped."
                            class="btn-ghost btn-sm"
                            title="Bring registered attendees into the guest pool — no retyping">⇩ Pull {{ $attendeePull }}</button>
                @endif
                <button type="button" wire:click="$toggle('showPlanImport')" class="btn-ghost btn-sm">⇪ Import</button>

                <div class="ms-auto flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="newItem" class="btn-gold btn-sm">＋ Add Movement</button>
                    @if ($total)
                        <details class="relative" data-menu>
                            <summary class="grid h-8 w-8 cursor-pointer list-none place-items-center rounded-xl text-[15px] leading-none text-navy-300 transition hover:bg-navy-50 hover:text-navy-700 [&::-webkit-details-marker]:hidden">⋮</summary>
                            <div class="absolute end-0 z-30 mt-1 w-64 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-xl">
                                <button type="button" wire:click="deleteAllMovements"
                                        wire:confirm="Delete all {{ $total }} {{ \Illuminate\Support\Str::plural('movement', $total) }}?&#10;&#10;The guests are kept — they go back to the pool so you can plan again. The movements themselves cannot be recovered."
                                        class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-red-700 transition hover:bg-red-50">
                                    Clear the vehicle plan
                                    <span class="block text-[10.5px] font-normal text-muted">Deletes {{ $total }} {{ \Illuminate\Support\Str::plural('movement', $total) }}; guests return to the pool.</span>
                                </button>
                            </div>
                        </details>
                    @endif
                </div>
            </div>

            {{-- ══ import a whole transport plan: one sheet, every movement ══ --}}
            @if ($showPlanImport)
                <div class="card border-gold-300 bg-gold-50/40 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold text-navy-900">Import the guest list</p>
                            <p class="mt-0.5 text-eyebrow leading-relaxed text-muted">
                                Excel (.xlsx) or CSV — <span class="font-semibold text-navy-700">one row per traveller</span>:
                                <span class="font-semibold text-navy-700">Name</span>, Direction (Arrival / Departure), Airline, Flight #, Date,
                                <span class="font-semibold text-navy-700">Flight Time</span>, <span class="font-semibold text-navy-700">Pickup Time</span>, From, To, Phone, Notes.
                                On arrivals leave Pickup Time blank and the landing is used; on departures it's when the car leaves the hotel.
                                Guests land in the pool below — you book the vehicles and place them.
                                Re-importing a corrected sheet updates guests instead of duplicating them.
                            </p>
                        </div>
                        <button type="button" wire:click="$set('showPlanImport', false)" class="shrink-0 rounded-lg px-2 py-1 text-eyebrow font-bold text-navy-400 hover:text-navy-700">✕</button>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2.5">
                        <input type="file" wire:model="planFile" accept=".xlsx,.xls,.csv,text/csv"
                               class="text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                        <button type="button" wire:click="importPlan" wire:loading.attr="disabled" wire:target="importPlan,planFile"
                                class="rounded-lg bg-gold-500 px-3.5 py-1.5 text-xs font-bold text-navy-950 transition hover:brightness-105 disabled:opacity-50">
                            <span wire:loading.remove wire:target="importPlan">Import plan</span>
                            <span wire:loading wire:target="importPlan">Importing…</span>
                        </button>
                        <span wire:loading wire:target="planFile" class="text-eyebrow font-semibold text-gold-700">Uploading…</span>
                        <a href="{{ route('events.transport.plan-template', $event) }}"
                           class="ml-1 border-l border-gold-300/70 pl-3 text-eyebrow font-bold uppercase tracking-wide text-navy-500 hover:text-navy-900"
                           title="Download a ready-to-fill Excel template">↧ Download template</a>
                    </div>
                    @error('planFile')<p class="mt-1.5 text-xs text-risk">{{ $message }}</p>@enderror
                </div>
            @endif

            @if ($planMsg)
                <div class="card border-emerald-200 bg-emerald-50/60 px-4 py-2.5 text-xs font-semibold text-emerald-800">{{ $planMsg }}</div>
            @endif

            {{-- ══ Guest pool — imported flights waiting for a vehicle ══ --}}
            @if ($guests->isNotEmpty() || $unassignedCount)
                @php $guestIds = $guests->pluck('id')->all(); $allPicked = $guestIds && ! array_diff($guestIds, $pickedGuests); @endphp
                <div class="card overflow-hidden">
                    <div class="flex flex-wrap items-center gap-2.5 border-b border-line px-3.5 py-2">
                        <div>
                            <p class="text-[13px] font-bold text-navy-900">Guests</p>
                            <p class="text-eyebrow text-muted">{{ $unassignedCount }} still to place</p>
                        </div>

                        {{-- the leg you're working --}}
                        <div class="flex rounded-xl border border-line bg-white p-0.5">
                            @foreach (['arrival' => 'Arrivals', 'departure' => 'Departures'] as $leg => $label)
                                <button type="button" wire:click="setGuestLeg('{{ $leg }}')"
                                        @class([
                                            'rounded-lg px-3 py-1.5 text-micro font-bold transition',
                                            'bg-navy-900 text-white' => $guestLeg === $leg,
                                            'text-navy-500 hover:text-navy-900' => $guestLeg !== $leg,
                                        ])>
                                    {{ $label }}
                                    <span class="ml-1 rounded-full {{ $guestLeg === $leg ? 'bg-white/20' : 'bg-navy-50' }} px-1.5 text-eyebrow">{{ $legCounts[$leg] }}</span>
                                </button>
                            @endforeach
                        </div>

                        <label class="flex cursor-pointer items-center gap-1.5 text-eyebrow font-semibold text-navy-600">
                            <input type="checkbox" wire:model.live="guestsOnlyUnassigned" class="h-3.5 w-3.5 rounded border-line text-gold-700 focus:ring-gold-400">
                            Unassigned only
                        </label>

                        @if ($legCounts[$guestLeg] > 0)
                            <button type="button" wire:click="suggestGrouping"
                                    class="ml-auto btn-ghost btn-xs"
                                    title="Group whoever is left by pickup place and time — a starting point you can adjust">✦ Suggest runs</button>
                        @endif

                        @if (array_sum($legCounts) > 0)
                            <button type="button" wire:click="deleteAllGuests"
                                    wire:confirm="Delete all {{ array_sum($legCounts) }} guests — arrivals and departures, assigned and unassigned? The vehicles stay. This cannot be undone."
                                    @class(['text-eyebrow font-bold uppercase tracking-wide text-red-600 hover:text-red-800', 'ml-auto' => $legCounts[$guestLeg] === 0])
                                    title="Empty the guest list so you can import a corrected sheet">Delete all guests</button>
                        @endif
                    </div>

                    {{-- ══ drop rail: drag guests onto a vehicle ══ --}}
                    @if ($assignTargets->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-2 border-b border-line bg-page/40 px-4 py-2.5">
                            <span class="text-eyebrow font-bold uppercase tracking-wide text-muted">Drag onto</span>
                            @foreach ($assignTargets as $t)
                                @php $full = $t->seats() > 0 && $t->manifest->count() >= $t->seats(); @endphp
                                <div data-drop-movement="{{ $t->id }}"
                                     @class([
                                         'flex min-w-[7rem] items-center gap-2 rounded-xl border border-dashed px-2.5 py-1.5 transition',
                                         'border-emerald-300 bg-emerald-50/60' => ! $full,
                                         'border-line bg-white opacity-60' => $full,
                                     ])
                                     title="Drop guests here to put them on this run">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-navy-900 text-eyebrow font-black text-white">{{ $t->ref_no }}</span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-eyebrow font-bold text-navy-900">{{ \Illuminate\Support\Str::limit($t->route, 22) }}</span>
                                        <span class="block text-eyebrow text-muted">
                                            {{ $t->depart_at?->format('d M · H:i') ?? 'Unscheduled' }} · {{ $t->manifest->count() }}/{{ $t->seats() ?: '?' }}
                                        </span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- bulk bar --}}
                    @if (count($pickedGuests))
                        <div class="flex flex-wrap items-center gap-3 border-b border-gold-200 bg-gold-50/60 px-4 py-2.5">
                            <span class="text-xs font-bold text-navy-900">{{ count($pickedGuests) }} selected</span>

                            {{-- Type the car number; faster than hunting a route once the plan is long. --}}
                            <form wire:submit="assignToNumber" class="flex items-center gap-1.5">
                                <label class="text-eyebrow font-bold uppercase tracking-wide text-navy-600" for="carNo">Assign to car</label>
                                <input id="carNo" type="text" inputmode="numeric" placeholder="#" wire:model="assignRef"
                                       class="h-8 w-14 rounded-lg border border-line px-2 text-center text-xs font-bold text-navy-900 focus:border-gold-400 focus:outline-none">
                                <button type="submit" class="rounded-lg bg-navy-900 px-2.5 py-1.5 text-eyebrow font-bold text-white hover:bg-navy-800">Go</button>
                            </form>

                            <select wire:change="assignPicked($event.target.value)" class="input h-8 w-auto !rounded-lg !py-0 text-xs">
                                <option value="">or pick from the list…</option>
                                @foreach ($assignTargets as $t)
                                    <option value="{{ $t->id }}">
                                        #{{ $t->ref_no }} · {{ $t->depart_at?->format('D d M · H:i') ?? 'Unscheduled' }} — {{ \Illuminate\Support\Str::limit($t->route, 30) }}
                                        ({{ $t->manifest->count() }}/{{ $t->seats() ?: '?' }})
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="unassignPicked" class="text-eyebrow font-bold uppercase tracking-wide text-navy-500 hover:text-navy-900">Send back to pool</button>
                            <button type="button" wire:click="deletePicked"
                                    wire:confirm="Delete {{ count($pickedGuests) }} {{ \Illuminate\Support\Str::plural('guest', count($pickedGuests)) }} from this event? This cannot be undone."
                                    class="text-eyebrow font-bold uppercase tracking-wide text-red-600 hover:text-red-800">Delete</button>
                            <button type="button" wire:click="clearPicked" class="ml-auto text-eyebrow font-bold uppercase tracking-wide text-navy-400 hover:text-navy-700">Clear</button>
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[860px]">
                            <thead>
                                <tr class="border-b border-line text-left text-eyebrow font-bold uppercase tracking-wide text-muted">
                                    <th class="w-10 px-4 py-2">
                                        <input type="checkbox" @checked($allPicked) wire:click="toggleGuestPage({{ json_encode($guestIds) }})"
                                               class="h-4 w-4 cursor-pointer rounded border-line text-gold-700 focus:ring-gold-400">
                                    </th>
                                    <th class="px-3 py-2">Guest</th>
                                    <th class="px-3 py-2">Airline</th>
                                    <th class="px-3 py-2">Flight</th>
                                    <th class="px-3 py-2">{{ $guestLeg === 'departure' ? 'Departs' : 'Lands' }}</th>
                                    <th class="px-3 py-2">Pick-up</th>
                                    <th class="px-3 py-2">Route</th>
                                    <th class="px-3 py-2">On vehicle</th>
                                </tr>
                            </thead>
                            <tbody data-guest-pool>
                                @forelse ($guests as $g)
                                    @php $picked = in_array($g->id, $pickedGuests, true); @endphp
                                    <tr wire:key="g-{{ $g->id }}" data-guest="{{ $g->id }}"
                                        @class(['border-b border-line last:border-0 cursor-grab transition hover:bg-page/50', 'bg-gold-50/40' => $picked])>
                                        <td class="px-4 py-2">
                                            <input type="checkbox" @checked($picked) wire:click="toggleGuest({{ $g->id }})"
                                                   class="h-4 w-4 cursor-pointer rounded border-line text-gold-700 focus:ring-gold-400">
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="flex items-center gap-1.5">
                                                <span class="text-xs font-bold text-navy-900">{{ $g->name }}</span>
                                                @if ($g->isPriority())
                                                    <span class="rounded px-1 py-0.5 text-eyebrow font-bold uppercase {{ $g->categoryClass() }}"
                                                          title="Priority — promotes the whole vehicle">{{ $g->categoryLabel() }}</span>
                                                @endif
                                            </span>
                                            <span class="mt-0.5 flex items-center gap-1.5">
                                                {{-- Category is what turns one list into the VIP, speaker and shuttle sheets. --}}
                                                <select wire:change="updatePassenger({{ $g->id }}, 'category', $event.target.value)"
                                                        class="h-5 w-auto rounded border-0 bg-transparent p-0 pr-4 text-eyebrow font-semibold text-muted hover:text-navy-800 focus:ring-0">
                                                    @foreach (\App\Support\Taxonomy::options('passenger_category') as $key => $label)
                                                        <option value="{{ $key }}" @selected($g->category === $key)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                @if ($g->phone)<span class="text-eyebrow text-muted">{{ $g->phone }}</span>@endif
                                                @if ($wa = \App\Support\WhatsApp::toGuest($g))
                                                    <a href="{{ $wa }}" target="_blank" rel="noopener"
                                                       class="text-eyebrow font-bold text-emerald-600 hover:text-emerald-800"
                                                       title="Send {{ $g->name }} their pickup details">WA</a>
                                                @endif
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-navy-700">{{ $g->airline ?: '—' }}</td>
                                        <td class="px-3 py-2">
                                            @if ($g->flight_no)
                                                <button type="button" wire:click="pickFlight('{{ $g->flight_no }}')"
                                                        class="rounded-md bg-navy-50 px-1.5 py-0.5 text-xs font-bold text-navy-800 transition hover:bg-gold-100"
                                                        title="Select everyone on {{ $g->flight_no }}">{{ $g->flight_no }}</button>
                                            @else — @endif
                                        </td>
                                        <td class="px-3 py-2 text-xs text-navy-700 whitespace-nowrap">
                                            {{ $g->arrival_on?->format('j M') ?? '—' }}{{ $g->arrival_time ? ' · '.substr($g->arrival_time, 0, 5) : '' }}
                                        </td>
                                        <td class="px-3 py-2 text-xs font-semibold text-navy-900 whitespace-nowrap">{{ $g->pickup_time ? substr($g->pickup_time, 0, 5) : '—' }}</td>
                                        <td class="px-3 py-2 text-eyebrow text-muted">{{ \Illuminate\Support\Str::limit(($g->pickup_point ?: '—').' → '.($g->drop_point ?: '—'), 34) }}</td>
                                        <td class="px-3 py-2">
                                            @if ($assignTargets->isNotEmpty())
                                                <span class="flex items-center gap-1.5">
                                                    <select wire:change="moveGuest({{ $g->id }}, $event.target.value)"
                                                            @class([
                                                                'h-7 w-auto max-w-[11rem] rounded-lg border px-1.5 text-eyebrow font-semibold',
                                                                'border-emerald-200 bg-emerald-50 text-emerald-800' => $g->transport_id,
                                                                'border-amber-200 bg-amber-50 text-amber-700' => ! $g->transport_id,
                                                            ])
                                                            title="Move this guest to another vehicle">
                                                        <option value="">Unassigned — in the pool</option>
                                                        @foreach ($assignTargets as $t)
                                                            <option value="{{ $t->id }}" @selected($g->transport_id === $t->id)>
                                                                {{ $t->depart_at?->format('d M H:i') ?? 'Unscheduled' }} · {{ \Illuminate\Support\Str::limit($t->route, 22) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @if ($g->vehicle_no)
                                                        <span class="text-eyebrow font-bold text-muted whitespace-nowrap">Van {{ $g->vehicle_no }}</span>
                                                    @endif
                                                </span>
                                            @elseif ($g->transport_id)
                                                <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-eyebrow font-bold text-emerald-700">Assigned</span>
                                            @else
                                                <span class="text-eyebrow font-semibold text-amber-600">Unassigned</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="px-4 py-10 text-center text-xs text-muted">
                                        {{ $guestsOnlyUnassigned ? 'Every '.$guestLeg.' guest is on a vehicle.' : 'No '.$guestLeg.' guests yet — import the flight list above.' }}
                                    </td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- ══ filters — leg + day in one strip (was two full cards of chrome) ══ --}}
            @if ($total || $days->isNotEmpty())
                <div class="card flex flex-wrap items-center gap-x-3 gap-y-2 px-3.5 py-2">
                    @if ($total)
                        <div class="flex flex-wrap items-center gap-1">
                            <button type="button" wire:click="setLeg('')"
                                    class="rounded-full px-2.5 py-1 text-eyebrow font-bold transition {{ $filterLeg === '' ? 'bg-navy-900 text-white' : 'bg-navy-50 text-navy-600 hover:bg-navy-100' }}">
                                All <span class="opacity-60">{{ $total }}</span>
                            </button>
                            @foreach ($legTabs as $tab)
                                <button type="button" wire:click="setLeg('{{ $tab['key'] }}')"
                                        @disabled($tab['runs'] === 0)
                                        class="rounded-full px-2.5 py-1 text-eyebrow font-bold transition {{ $filterLeg === $tab['key'] ? 'bg-navy-900 text-white' : ($tab['runs'] ? 'bg-navy-50 text-navy-600 hover:bg-navy-100' : 'bg-white text-navy-300') }}"
                                        title="{{ $tab['hint'] }}">
                                    {{ $tab['label'] }} <span class="opacity-60">{{ $tab['runs'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @if ($days->isNotEmpty())
                        @if ($total)<span class="hidden h-5 w-px bg-line sm:block" aria-hidden="true"></span>@endif
                        <div class="flex flex-wrap items-center gap-1">
                            <span class="me-0.5 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Day</span>
                            <button type="button" wire:click="setDay('')"
                                    class="rounded-full px-2.5 py-1 text-eyebrow font-bold transition {{ $filterDay === '' ? 'bg-navy-900 text-white' : 'bg-navy-50 text-navy-600 hover:bg-navy-100' }}">
                                All
                            </button>
                            @foreach ($days as $day => $count)
                                <button type="button" wire:click="setDay('{{ $day }}')"
                                        class="rounded-full px-2.5 py-1 text-eyebrow font-bold transition {{ $filterDay === $day ? 'bg-navy-900 text-white' : 'bg-navy-50 text-navy-600 hover:bg-navy-100' }}">
                                    {{ \Carbon\Carbon::parse($day)->format('D j M') }} <span class="opacity-60">{{ $count }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @if ($total)
                        <span class="ms-auto text-eyebrow text-muted">{{ $shown }} of {{ $total }}</span>
                    @endif
                </div>
            @endif

            @if ($movements->isEmpty())
                <x-empty icon="truck"
                         :title="$filterDay ? 'Nothing moving on that day' : 'No transport planned yet'"
                         hint="A movement is a service — pickup & drop-off, airport transfer, a full day at disposal — in a vehicle sized to the group.">
                    <x-slot:actions>
                        <button type="button" wire:click="newItem" class="btn-gold btn-sm">＋ Add a movement</button>
                        <a href="{{ route('transport-settings.index') }}" class="btn-ghost btn-sm">Manage vehicles &amp; services →</a>
                    </x-slot:actions>
                </x-empty>
            @endif

            {{-- ══ movements, grouped by day ══ --}}
            @foreach ($movements as $day => $group)
                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-line bg-page/40 px-3.5 py-2">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-700">
                            {{ $day === 'unscheduled' ? 'Not yet scheduled' : \Carbon\Carbon::parse($day)->format('l, j F') }}
                        </p>
                        <p class="text-eyebrow font-bold uppercase tracking-wide text-muted">
                            {{ $group->count() }} {{ \Illuminate\Support\Str::plural('movement', $group->count()) }}
                            · {{ $group->sum(fn ($x) => $x->paxCount()) }} pax
                        </p>
                    </div>

                    @foreach ($group as $m)
                        @php
                            $stLabel = $m->statusLabel(); $stClass = $m->statusClass();
                            $cap = $m->vehicleType?->capacity ?? 0;
                            $over = $m->isOverbooked();
                            $ready = $m->readiness();
                            $priority = $m->isPriority();
                            $overloaded = $m->driver && $m->depart_at
                                && $m->driver->isOverloadedOn($m->depart_at->toDateString());
                        @endphp
                        @php $open = $expandedId === $m->id; @endphp
                        <div wire:key="mv-{{ $m->id }}" class="border-b border-line last:border-0">
                        <div class="group/mv flex cursor-pointer items-center gap-3 px-3.5 py-2 hover:bg-page/30"
                             wire:click="toggleExpand({{ $m->id }})">

                            <span class="shrink-0 text-navy-300 transition group-hover/mv:text-navy-600 {{ $open ? 'rotate-90' : '' }}">▸</span>

            {{-- The number you say out loud: "put them on car 3".
                 A movement with no number rendered as a filled black circle with
                 nothing in it — the loudest thing in the row, saying nothing. --}}
            @if ($m->ref_no)
                <span @class([
                          'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-black',
                          'bg-gold-500 text-navy-950' => $priority,
                          'bg-navy-900 text-white' => ! $priority,
                      ])
                      title="Car {{ $m->refLabel() }} — assign by this number">{{ $m->ref_no }}</span>
            @else
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-dashed border-line text-xs font-black text-navy-300"
                      title="No car number yet — Edit to give it one">–</span>
            @endif

                            {{-- time --}}
                            <div class="w-14 shrink-0 text-center">
                                <p class="pf text-sm font-bold leading-none text-navy-900">{{ $m->depart_at?->format('H:i') ?? '—' }}</p>
                                <p class="mt-0.5 text-eyebrow uppercase tracking-wide text-muted">{{ $m->depart_at?->format('D') ?? 'TBC' }}</p>
                            </div>

                            <span class="shrink-0 text-lg" title="{{ $m->vehicleType?->name }}">{{ $vehicleIcon($cap) }}</span>

                            {{-- what & where --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    @php $legClass = ['arrival' => 'bg-emerald-100 text-emerald-700', 'departure' => 'bg-sky-100 text-sky-700'][$m->leg] ?? 'bg-navy-100 text-navy-600'; @endphp
                                    {{-- Arrival and departure earn a colour; "other" is
                                         the absence of one, and it was taking the first
                                         slot on every row to say so. --}}
                                    @if (in_array($m->leg, ['arrival', 'departure'], true))
                                        <span class="shrink-0 rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide {{ $legClass }}"
                                              title="{{ \App\Models\EventTransport::LEG_HINTS[$m->leg] ?? '' }}">{{ $m->legLabel() }}</span>
                                    @endif
                                    <p class="truncate text-sm font-semibold text-navy-900">{{ $m->serviceType?->name ?? $m->route }}</p>

                                    {{-- Planned is the absence of news. On every row it
                                         taught the eye to skip the one place a status
                                         actually says something. --}}
                                    @if ($m->status !== 'planned')
                                        <span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide {{ $stClass }}">{{ $stLabel }}</span>
                                    @endif
                                    @if ($priority)
                                        <span class="rounded-full bg-gold-100 px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide text-gold-800"
                                              title="{{ $m->priorityReason() }}">★ Priority</span>
                                    @endif
                                    @if ($over)
                                        <span class="rounded-full bg-risk/10 px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide text-red-700"
                                              title="More passengers than seats booked">Over capacity</span>
                                    @endif
                                    @if ($overloaded)
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide text-amber-800"
                                              title="{{ $m->driver->name }} is on duty {{ \App\Models\TransportDriver::readableMinutes($m->driver->dutyMinutes($m->depart_at->toDateString())) }} that day">Driver overloaded</span>
                                    @endif
                                </div>
                                {{-- The route is what the movement IS, so it is not
                                     the third thing on the row behind an ellipsis.
                                     The pick-up date and time are already in the
                                     column to the left and the day heading above;
                                     printing them a third time cost the route its
                                     room. --}}
                                @if ($m->pickup_from || $m->drop_to)
                                    <p class="mt-0.5 truncate text-xs font-semibold text-navy-700">
                                        {{ $m->pickup_from ?: '—' }} <span class="text-navy-300">→</span> {{ $m->drop_to ?: '—' }}
                                    </p>
                                @endif

                                @php
                                    $facts = collect([
                                        $m->flight_no ? 'flight '.$m->flight_no : null,
                                        $m->arrive_at ? 'lands '.$m->arrive_at->format('H:i') : null,
                                        $m->supplierName(),
                                        $m->vehicle?->label(),
                                    ])->filter();
                                @endphp
                                @if ($facts->isNotEmpty())
                                    <p class="mt-0.5 truncate text-eyebrow text-muted">{{ $facts->join(' · ') }}</p>
                                @endif

                                {{-- Driver contact stays one muted line so the route keeps the scan. --}}
                                @if ($m->driver)
                                    <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-eyebrow text-muted">
                                        <span class="font-semibold text-navy-700">{{ $m->driver->name }}</span>
                                        @if ($m->contactNumber())
                                            <a href="tel:{{ $m->contactNumber() }}" wire:click.stop
                                               class="font-semibold text-navy-600 hover:text-navy-900">{{ $m->contactNumber() }}</a>
                                        @endif
                                        @if ($wa = \App\Support\WhatsApp::toDriver($m))
                                            <a href="{{ $wa }}" target="_blank" rel="noopener" wire:click.stop
                                               class="font-bold text-emerald-600 hover:text-emerald-800"
                                               title="Send this run's details to {{ $m->driver->name }}">WA</a>
                                        @endif
                                    </p>
                                @endif
                            </div>

            {{-- Readiness, once, in words. Three dots needed a tooltip to
                 decode and nobody scanning forty rows reads dots. --}}
            @php $chip = $m->readinessChip(); @endphp
            <span @class([
                      'hidden shrink-0 rounded-full px-2.5 py-1 text-eyebrow font-bold lg:block',
                      'bg-emerald-50 text-emerald-700' => $chip['ready'],
                      'bg-amber-50 text-amber-700' => ! $chip['ready'],
                  ])
                  title="{{ $chip['detail'] }}">{{ $chip['label'] }}</span>

                            {{-- vehicle & load --}}
                            <div class="hidden w-44 shrink-0 sm:block">
                                <p class="truncate text-xs font-semibold text-navy-900">
                                    {{ $m->vehicleType?->name ?? '—' }}@if ($m->vehicles > 1) <span class="text-muted">×{{ $m->vehicles }}</span>@endif
                                </p>
                                @if ($m->seats() > 0)
                                    @php $load = min(100, (int) round($m->paxCount() / max(1, $m->seats()) * 100)); @endphp
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-navy-100">
                                            <div class="h-full rounded-full {{ $over ? 'bg-danger' : 'bg-gold-500' }}" style="width: {{ $load }}%"></div>
                                        </div>
                                        <span class="shrink-0 text-eyebrow font-bold {{ $over ? 'text-red-700' : 'text-muted' }}">{{ $m->paxCount() }}/{{ $m->seats() }}</span>
                                    </div>
                                @else
                                    <p class="mt-0.5 text-eyebrow text-muted">{{ $m->paxCount() }} pax</p>
                                @endif
                            </div>

                            <p class="hidden w-20 shrink-0 text-right text-xs font-semibold text-navy-900 md:block">
                                {{ $m->cost_cents ? $event->money($m->cost_cents) : '—' }}
                            </p>

            {{-- Edit is the everyday one and stays a button. Repeating a run
                 and deleting it are not everyday, and a permanent delete does
                 not belong beside Edit at the same size and shape. --}}
            <div class="flex shrink-0 items-center gap-1.5" wire:click.stop>
                <button type="button" wire:click="edit({{ $m->id }})"
                        class="rounded-lg bg-navy-50 px-2.5 py-1.5 text-eyebrow font-bold text-navy-700 hover:bg-navy-100">✎ Edit</button>

                <details class="relative" data-menu>
                    <summary class="grid h-7 w-7 cursor-pointer list-none place-items-center rounded-lg text-[15px] leading-none text-navy-300 transition hover:bg-navy-50 hover:text-navy-700 [&::-webkit-details-marker]:hidden">⋮</summary>
                    <div class="absolute end-0 z-30 mt-1 w-52 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-xl">
                        <button type="button" wire:click="duplicate({{ $m->id }})"
                                class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-navy-700 transition hover:bg-page">
                            Repeat this run tomorrow
                        </button>
                        <button type="button" wire:click="delete({{ $m->id }})"
                                wire:confirm="Delete {{ $m->refLabel() !== '—' ? 'car '.$m->refLabel().', ' : '' }}{{ $m->pickup_from ?: 'this movement' }}{{ $m->drop_to ? ' → '.$m->drop_to : '' }}?&#10;&#10;{{ $m->manifest->count() }} named {{ \Illuminate\Support\Str::plural('passenger', $m->manifest->count()) }} {{ $m->manifest->count() === 1 ? 'goes' : 'go' }} with it. This cannot be undone."
                                class="block w-full border-t border-line px-3 py-2 text-start text-[11.5px] font-semibold text-red-700 transition hover:bg-red-50">
                            Delete this movement
                        </button>
                    </div>
                </details>
            </div>
                        </div>

                        {{-- ══ manifest: who is actually in the vehicle ══ --}}
                        @if ($open)
                            <div class="border-t border-line bg-page/20">
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[1180px]">
                                        <thead>
                                            <tr class="border-b border-line/60 text-left text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">
                                                <th class="px-4 pt-2"></th>
                                                <th class="px-3 pt-2">Passenger</th>
                                                <th class="border-l border-line px-3 pt-2" colspan="4">Flight</th>
                                                <th class="border-l border-line px-3 pt-2" colspan="2">Pick-up</th>
                                                <th class="px-3 pt-2"></th>
                                            </tr>
                                            <tr class="border-b border-line text-left text-eyebrow font-bold uppercase tracking-wide text-muted">
                                                <th class="w-14 px-4 pb-2 text-center">{{ $m->vehicles > 1 ? 'Van' : 'Seat' }}</th>
                                                <th class="px-3 pb-2">Name</th>
                                                <th class="border-l border-line px-3 pb-2">Airline</th>
                                                <th class="px-3 pb-2">Flight no.</th>
                                                <th class="px-3 pb-2">Arrival date</th>
                                                <th class="px-3 pb-2">Time</th>
                                                <th class="border-l border-line px-3 pb-2">Point</th>
                                                <th class="px-3 pb-2">Phone</th>
                                                <th class="w-10 px-3 pb-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($m->manifest as $i => $p)
                                                <tr wire:key="pax-{{ $p->id }}" class="group/px border-b border-line last:border-0 hover:bg-white">
                                                    <td class="px-2 py-1.5 text-center">
                                                        @if ($m->vehicles > 1)
                                                            <select wire:change="updatePassenger({{ $p->id }}, 'vehicle_no', $event.target.value)"
                                                                    class="w-full cursor-pointer rounded-md border border-line bg-white px-1 py-1 text-eyebrow font-bold text-navy-700"
                                                                    title="Which vehicle of this run">
                                                                @for ($v = 1; $v <= $m->vehicles; $v++)
                                                                    <option value="{{ $v }}" @selected((int) ($p->vehicle_no ?: 1) === $v)>{{ $v }}</option>
                                                                @endfor
                                                            </select>
                                                        @else
                                                            <span class="text-eyebrow font-bold text-navy-300">{{ $i + 1 }}</span>
                                                        @endif
                                                    </td>
                                                    @php
                                                        $pin = 'w-full rounded-lg border border-transparent bg-transparent px-2 py-1.5 text-xs text-navy-900 placeholder:text-navy-300 hover:border-line focus:border-gold-400 focus:bg-white focus:outline-none';
                                                    @endphp
                                                    <td class="min-w-[150px] px-1 py-1">
                                                        <input type="text" value="{{ $p->name }}" placeholder="Passenger name"
                                                               wire:change="updatePassenger({{ $p->id }}, 'name', $event.target.value)"
                                                               class="{{ $pin }} font-semibold">
                                                    </td>
                                                    <td class="border-l border-line px-1 py-1">
                                                        <input type="text" value="{{ $p->airline }}" placeholder="Royal Jordanian" list="airlines"
                                                               wire:change="updatePassenger({{ $p->id }}, 'airline', $event.target.value)"
                                                               class="{{ $pin }}">
                                                    </td>
                                                    <td class="px-1 py-1">
                                                        <input type="text" value="{{ $p->flight_no }}" placeholder="RJ 512"
                                                               wire:change="updatePassenger({{ $p->id }}, 'flight_no', $event.target.value)"
                                                               class="{{ $pin }}">
                                                    </td>
                                                    <td class="px-1 py-1">
                                                        <input type="date" value="{{ $p->arrival_on?->format('Y-m-d') }}"
                                                               wire:change="updatePassenger({{ $p->id }}, 'arrival_on', $event.target.value)"
                                                               class="{{ $pin }}">
                                                    </td>
                                                    <td class="px-1 py-1">
                                                        <input type="time" value="{{ $p->arrival_time }}"
                                                               wire:change="updatePassenger({{ $p->id }}, 'arrival_time', $event.target.value)"
                                                               class="{{ $pin }}">
                                                    </td>
                                                    <td class="border-l border-line px-1 py-1">
                                                        <input type="text" value="{{ $p->pickup_point }}" placeholder="—"
                                                               wire:change="updatePassenger({{ $p->id }}, 'pickup_point', $event.target.value)"
                                                               class="{{ $pin }}">
                                                    </td>
                                                    <td class="px-1 py-1">
                                                        <input type="text" value="{{ $p->phone }}" placeholder="—"
                                                               wire:change="updatePassenger({{ $p->id }}, 'phone', $event.target.value)"
                                                               class="{{ $pin }}">
                                                    </td>
                                                    <td class="px-3 py-1.5">
                                                        {{-- Takes them off this vehicle, back to the pool — they still
                                                             need a transfer. Deleting for good lives in the Guests panel. --}}
                                                        <button type="button" wire:click="unassignGuest({{ $p->id }})"
                                                                title="Take {{ $p->name }} off this vehicle — back to the guest pool"
                                                                class="rounded-lg px-1.5 py-1 text-eyebrow font-bold text-navy-300 opacity-0 transition hover:bg-navy-50 hover:text-navy-800 group-hover/px:opacity-100">✕</button>
                                                    </td>
                                                </tr>
                                            @endforeach

                                            @if ($m->seats() === 0 || $m->manifest->count() < $m->seats())
                                                <tr class="bg-white/60">
                                                    <td class="px-4 py-2 text-center text-eyebrow font-bold text-gold-700">{{ $m->manifest->count() + 1 }}</td>
                                                    <td class="px-1 py-2" colspan="7">
                                                        <input type="text" wire:model="newPax.{{ $m->id }}"
                                                               wire:keydown.enter="addPassenger({{ $m->id }})"
                                                               placeholder="Type a passenger name and press Enter to take the next seat…"
                                                               class="w-full rounded-lg border border-dashed border-navy-200 bg-transparent px-2 py-1.5 text-xs text-navy-900 placeholder:text-navy-300 focus:border-gold-400 focus:bg-white focus:outline-none">
                                                        @error('newPax.'.$m->id)<p class="mt-1 px-2 text-xs text-risk">{{ $message }}</p>@enderror
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <button type="button" wire:click="addPassenger({{ $m->id }})"
                                                                class="rounded-lg bg-gold-100 px-1.5 py-1 text-eyebrow font-bold text-gold-700 hover:bg-gold-200">＋</button>
                                                    </td>
                                                </tr>
                                            @else
                                                <tr><td colspan="9" class="px-5 py-2.5 text-center text-eyebrow font-semibold text-emerald-700">
                                                    Every seat is taken — {{ $m->seats() }}/{{ $m->seats() }} full.
                                                </td></tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>

                                {{-- import an Excel / CSV manifest into this movement --}}
                                @if ($importMoveId === $m->id)
                                    <div class="border-t border-line bg-gold-50/40 px-5 py-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-xs font-bold text-navy-900">Import passengers into this run</p>
                                                <p class="mt-0.5 text-eyebrow leading-relaxed text-muted">
                                                    Excel (.xlsx) or CSV. First row = headers. Recognised columns:
                                                    <span class="font-semibold text-navy-700">Name</span> (required), Airline, Flight #, Arrival Date, Arrival Time, Phone, Email, Pickup Point, Notes.
                                                    Blank cells fall back to this run's flight &amp; pickup.
                                                </p>
                                            </div>
                                            <button type="button" wire:click="closeImport" class="shrink-0 rounded-lg px-2 py-1 text-eyebrow font-bold text-navy-400 hover:text-navy-700">✕</button>
                                        </div>
                                        <div class="mt-3 flex flex-wrap items-center gap-2.5">
                                            <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv,text/csv"
                                                   class="text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                                            <button type="button" wire:click="importPassengers" wire:loading.attr="disabled" wire:target="importPassengers,importFile"
                                                    class="rounded-lg bg-gold-500 px-3.5 py-1.5 text-xs font-bold text-navy-950 transition hover:brightness-105 disabled:opacity-50">
                                                <span wire:loading.remove wire:target="importPassengers">Import manifest</span>
                                                <span wire:loading wire:target="importPassengers">Importing…</span>
                                            </button>
                                            <span wire:loading wire:target="importFile" class="text-eyebrow font-semibold text-gold-700">Uploading…</span>
                                            <a href="{{ route('events.transport.template', [$event, $m]) }}"
                                               class="ml-1 border-l border-gold-300/70 pl-3 text-eyebrow font-bold uppercase tracking-wide text-navy-500 hover:text-navy-900"
                                               title="Download a ready-to-fill Excel template">↧ Download template</a>
                                        </div>
                                        @error('importFile')<p class="mt-1.5 text-xs text-risk">{{ $message }}</p>@enderror
                                    </div>
                                @endif

                                @if ($importMsg && $expandedId === $m->id)
                                    <div class="border-t border-line bg-emerald-50/60 px-5 py-2 text-xs font-semibold text-emerald-800">{{ $importMsg }}</div>
                                @endif

                                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-2.5 text-eyebrow text-muted">
                                    <span>
                                        <span class="font-bold text-navy-900">{{ $m->manifest->count() }}</span> named ·
                                        <span class="font-bold text-navy-900">{{ $m->seatsFree() }}</span> seats free
                                        @if ($m->vehicleType) · {{ $m->vehicleType->name }} holds {{ $m->vehicleType->capacity }} each, {{ $m->vehicleCount() }} booked @endif
                                        {{-- who is in which vehicle, at a glance --}}
                                        @if ($m->vehicles > 1 && $m->manifest->isNotEmpty())
                                            <span class="ml-2 border-l border-line pl-2">
                                                @foreach ($m->manifestByVehicle() as $no => $pax)
                                                    <span class="mr-1.5 rounded bg-navy-50 px-1.5 py-0.5 font-bold text-navy-700">Van {{ $no }}: {{ $pax->count() }}</span>
                                                @endforeach
                                            </span>
                                        @endif
                                    </span>
                                    <span class="flex items-center gap-3">
                                        @if ($m->manifest->isNotEmpty())
                                            <button type="button" wire:click="autoAssign({{ $m->id }})"
                                                    class="font-bold uppercase tracking-wide text-navy-500 hover:text-navy-900"
                                                    title="Fill each vehicle to capacity in order">⇄ Auto-assign vehicles</button>
                                        @endif
                                        <button type="button" wire:click="openImport({{ $m->id }})"
                                                class="font-bold uppercase tracking-wide text-navy-500 hover:text-navy-900">⇪ Import Excel</button>
                                    </span>
                                </div>
                            </div>
                        @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

    </div>

    {{-- ══ Transport Control Center — inline box (no more hanging dock) ══ --}}
    <div class="cc-panel mt-6 w-full max-w-sm">
        <div class="cc-head">
            <span class="relative flex h-7 w-7 items-center justify-center rounded-lg text-white shadow-sm" style="background: {{ $moduleColor }}">
                <x-icon name="truck" class="h-3.5 w-3.5" />
            </span>
            <span class="relative text-2xs font-bold uppercase tracking-[0.18em] text-navy-900">Transport Control Center</span>
        </div>
                <div class="border-b border-line p-4">
                    <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-navy-300"></span> Summary</p>
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between"><span class="text-muted">Movements</span><span class="font-bold text-navy-900">{{ $total }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Seats booked</span><span class="font-bold text-navy-900">{{ $seatsTotal }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Passengers</span><span class="font-bold text-navy-900">{{ $paxTotal }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Named on manifests</span><span class="font-bold text-navy-900">{{ $namedTotal }}</span></div>
                        @if ($overbooked)
                            <div class="flex justify-between"><span class="text-muted">Over capacity</span><span class="font-bold text-red-700">{{ $overbooked }}</span></div>
                        @endif
                        <div class="flex justify-between border-t border-line pt-1.5"><span class="text-muted">Transport cost</span><span class="font-bold text-navy-900">{{ $event->money($costTotal) }}</span></div>

                        {{-- Which section of the budget this module's spend
                             lands under. Asked here because this is where
                             somebody is looking when the question comes up. --}}
                        <x-budget-routing :routing="$this->budgetRouting()" />
                    </div>
                </div>
                @if ($fleet->isNotEmpty())
                    <div class="border-b border-line p-4">
                        <p class="field-label !mb-2 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-gold-400"></span> Vehicles required</p>
                        <div class="space-y-1.5">
                            @foreach ($fleet as $f)
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="min-w-0 truncate text-xs text-navy-700">{{ $f['name'] }}<span class="text-muted"> · max {{ $f['capacity'] }}</span></span>
                                    <span class="shrink-0 text-xs font-bold text-navy-900">×{{ $f['vehicles'] }}</span>
                                </div>
                                <div class="text-eyebrow text-muted">{{ $f['runs'] }} {{ \Illuminate\Support\Str::plural('run', $f['runs']) }} · {{ $f['pax'] }} pax</div>
                            @endforeach
                        </div>
                        <p class="mt-2 text-eyebrow leading-relaxed text-muted">What to order from the supplier.</p>
                    </div>
                @endif
                <div class="space-y-2 p-4">
                    <button type="button" wire:click="newItem"
                            class="h-10 w-full rounded-xl text-xs font-bold text-white transition hover:opacity-90"
                            style="background: {{ $moduleColor }}">＋ Add Movement</button>
                    @if ($total)
                        <a href="{{ route('events.transport.pdf', [$event, ...$this->exportFilters()]) }}" target="_blank"
                           class="block rounded-xl border border-line bg-white px-4 py-2.5 text-center text-xs font-bold text-navy-700 transition hover:border-gold-300 hover:text-navy-900">
                            ↧ Export manifest PDF
                        </a>
                    @endif
                    <a href="{{ route('transport-settings.index') }}"
                       class="block rounded-xl px-4 py-2 text-center text-micro font-semibold text-navy-500 hover:bg-navy-50 hover:text-navy-900">
                        Manage vehicles &amp; services →
                    </a>
                </div>
    </div>

    {{-- ══ movement modal ══ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit movement' : 'New movement'"
                 subtitle="A service, in a vehicle, at a time — passengers are named on the manifest afterwards."
                 close="$set('showForm', false)">
            <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                    {{-- The first decision, and the one everything else filters by. --}}
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">Movement type</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (\App\Models\EventTransport::LEGS as $key => $label)
                                <button type="button" wire:click="$set('leg', '{{ $key }}')"
                                        @class([
                                            'rounded-xl border px-3 py-2 text-left transition',
                                            'border-navy-900 bg-navy-900 text-white' => $leg === $key,
                                            'border-line bg-white hover:border-navy-300' => $leg !== $key,
                                        ])>
                                    <span class="block text-xs font-bold">{{ $label }}</span>
                                    <span class="block text-eyebrow {{ $leg === $key ? 'text-white/60' : 'text-muted' }}">
                                        {{ \App\Models\EventTransport::LEG_HINTS[$key] }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                        @error('leg')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    {{-- Who runs it. A named driver is what makes trip sheets and
                         overload warnings possible, so it sits up front. --}}
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Driver</label>
                        <select wire:model="driver_id" class="input h-10 text-sm">
                            <option value="">— unassigned —</option>
                            @foreach ($drivers as $d)<option value="{{ $d->id }}">{{ $d->label() }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Vehicle (specific car)</label>
                        <select wire:model="vehicle_id" class="input h-10 text-sm">
                            <option value="">— any of this type —</option>
                            @foreach ($fleetVehicles as $v)<option value="{{ $v->id }}">{{ $v->label() }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Supplier</label>
                        <select wire:model="supplier_id" class="input h-10 text-sm">
                            <option value="">— none —</option>
                            @foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="flex h-10 w-full cursor-pointer items-center gap-2.5 rounded-xl border border-line px-3 transition hover:border-gold-300">
                            <input type="checkbox" wire:model="is_vip" class="h-4 w-4 rounded border-line text-gold-700 focus:ring-gold-400">
                            <span class="text-xs font-semibold text-navy-900">Priority / VIP run</span>
                        </label>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Service</label>
                        <select wire:model="service_type_id" class="input h-10 text-sm">
                            <option value="">— none —</option>
                            @foreach ($serviceTypes as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Vehicle</label>
                        <select wire:model="vehicle_type_id" class="input h-10 text-sm">
                            <option value="">— none —</option>
                            @foreach ($vehicleTypes as $v)<option value="{{ $v->id }}">{{ $v->label() }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">How many vehicles</label>
                        <input type="number" min="1" wire:model="vehicles" class="input h-10 text-sm">
                        @error('vehicles')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Passengers <span class="normal-case text-muted">— estimate</span></label>
                        <input type="number" min="0" wire:model="passengers" class="input h-10 text-sm" placeholder="0">
                        <p class="mt-1 text-eyebrow text-muted">Named passengers on the manifest override this.</p>
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Pick up from</label>
                        <input type="text" wire:model="pickup_from" class="input h-10 text-sm" placeholder="Queen Alia Airport">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Drop off at</label>
                        <input type="text" wire:model="drop_to" class="input h-10 text-sm" placeholder="Fairmont Amman">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Pick-up date &amp; time</label>
                        <input type="datetime-local" wire:model="depart_at" class="input h-10 text-sm">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Flight number</label>
                        <input type="text" wire:model="flight_no" class="input h-10 text-sm" placeholder="RJ 512">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Flight lands / arrives</label>
                        <input type="datetime-local" wire:model="arrive_at" class="input h-10 text-sm">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Provider</label>
                        <input type="text" wire:model="provider" class="input h-10 text-sm" placeholder="Petra Limo">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Driver contact</label>
                        <input type="text" wire:model="driver_contact" class="input h-10 text-sm" placeholder="+962 79 555 0100">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Cost ({{ $event->currency }})</label>
                        <input type="number" step="0.01" min="0" wire:model="cost" class="input h-10 text-sm" placeholder="0">
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Status</label>
                        <select wire:model="status" class="input h-10 text-sm">
                            @foreach (\App\Models\EventTransport::STATUSES as $st)<option value="{{ $st }}">{{ ucfirst($st) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="field-label !mb-1 !text-eyebrow">Notes</label>
                        <input type="text" wire:model="notes" class="input h-10 text-sm" placeholder="Meet & greet at arrivals, name board…">
                    </div>
                    <p class="text-eyebrow text-muted sm:col-span-2">
                        Only the vehicles and services switched on in
                        <a href="{{ route('transport-settings.index') }}" class="font-semibold text-navy-600 underline hover:text-navy-900">Settings → Transport</a>
                        appear here.
                    </p>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('showForm', false)" class="btn-ghost btn-sm">Cancel</button>
                    <button type="submit" class="btn-navy btn-sm">{{ $editingId ? 'Update' : 'Add movement' }}</button>
                </div>
            </form>
        </x-modal>
    @endif

    @script
    <script>
        // Drag a guest from the pool onto a vehicle in the drop rail.
        // Dragging one of several selected guests moves the whole selection.
        (() => {
            const wire = $wire;

            const wireUp = () => {
                const pool = document.querySelector('[data-guest-pool]');
                if (!pool || pool.dataset.sortableBound) return;
                pool.dataset.sortableBound = '1';

                // The pool only gives rows away; it never receives them.
                new window.Sortable(pool, {
                    group: { name: 'transferGuests', pull: 'clone', put: false },
                    sort: false,
                    animation: 140,
                    draggable: '[data-guest]',
                    filter: 'input,button,select,a',
                    preventOnFilter: false,
                    ghostClass: 'opacity-40',
                });

                document.querySelectorAll('[data-drop-movement]').forEach((zone) => {
                    if (zone.dataset.sortableBound) return;
                    zone.dataset.sortableBound = '1';

                    new window.Sortable(zone, {
                        group: { name: 'transferGuests', pull: false, put: true },
                        sort: false,
                        animation: 140,
                        onAdd(evt) {
                            const guestId = Number(evt.item?.dataset?.guest);
                            const movementId = Number(zone.dataset.dropMovement);
                            evt.item.remove();            // Livewire re-renders the truth
                            if (guestId && movementId) {
                                wire.dropGuest(guestId, movementId);
                            }
                        },
                        onDragOver() { zone.classList.add('ring-2', 'ring-gold-400'); },
                    });

                    zone.addEventListener('dragleave', () => zone.classList.remove('ring-2', 'ring-gold-400'));
                    zone.addEventListener('drop', () => zone.classList.remove('ring-2', 'ring-gold-400'));
                });
            };

            wireUp();
            // Re-bind after every Livewire re-render, since the rows are replaced.
            Livewire.hook('morphed', wireUp);
        })();
    </script>
    @endscript
</div>
