            {{-- ══ 1 · Guests ══ --}}
            <section class="cx-lcard !mb-0">
                <div class="flex flex-wrap items-center gap-2.5 border-b border-line px-3.5 py-2.5" style="background:var(--cx-surface-2)">
                    <div class="min-w-0">
                        <p class="text-[13px] font-bold text-ink">1 · Guests</p>
                        <p class="text-eyebrow text-muted">
                            @if ($unassignedCount)
                                {{ $unassignedCount }} still to place
                            @elseif (array_sum($legCounts) > 0 || $guests->isNotEmpty())
                                Everyone on this leg is on a vehicle
                            @else
                                Import a flight list or pull attendees
                            @endif
                        </p>
                    </div>

                    <div class="cx-seg">
                        @foreach (['arrival' => 'Arrivals', 'departure' => 'Departures'] as $leg => $label)
                            <button type="button" wire:click="setGuestLeg('{{ $leg }}')" aria-pressed="{{ $guestLeg === $leg ? 'true' : 'false' }}">
                                {{ $label }} <span class="opacity-60">{{ $legCounts[$leg] }}</span>
                            </button>
                        @endforeach
                    </div>

                    <label class="flex cursor-pointer items-center gap-1.5 text-eyebrow font-semibold text-muted">
                        <input type="checkbox" wire:model.live="guestsOnlyUnassigned" class="h-3.5 w-3.5 rounded border-line text-navy-900 focus:ring-navy-300">
                        Unassigned only
                    </label>

                    @if ($legCounts[$guestLeg] > 0)
                        <button type="button" wire:click="suggestGrouping"
                                class="ml-auto btn-ghost btn-xs"
                                title="Group whoever is left by pickup place and time">✦ Suggest runs</button>
                    @endif

                    @if (array_sum($legCounts) > 0 || $guests->isNotEmpty())
                        <x-confirm
                            title="Delete all guests?"
                            body="Removes arrivals and departures — assigned and unassigned. Vehicles stay. This cannot be undone."
                            confirm="Delete guests"
                            run="$wire.deleteAllGuests()"
                            @class(['text-eyebrow font-bold uppercase tracking-wide text-red-600 hover:text-red-800', 'ml-auto' => $legCounts[$guestLeg] === 0])>
                            Delete all guests
                        </x-confirm>
                    @endif
                </div>

                @if ($assignTargets->isNotEmpty() && ($guests->isNotEmpty() || $unassignedCount))
                    <div class="flex flex-wrap items-center gap-1.5 border-b border-line px-3.5 py-2" style="background:var(--cx-surface-2)">
                        <span class="me-1 text-eyebrow font-bold uppercase tracking-wide text-muted">Drag onto</span>
                        @foreach ($assignTargets as $t)
                            @php $full = $t->seats() > 0 && $t->manifest->count() >= $t->seats(); @endphp
                            <div data-drop-movement="{{ $t->id }}"
                                 @class([
                                     'flex items-center gap-1.5 rounded-lg border border-dashed px-2 py-1 transition',
                                     'border-emerald-300 bg-emerald-50/50' => ! $full,
                                     'border-line bg-white opacity-50' => $full,
                                 ])
                                 title="Drop guests here to put them on this run">
                                <span class="flex h-6 w-5 shrink-0 items-center justify-center text-eyebrow font-black text-white" style="clip-path: polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%); background: {{ $moduleHex }}">{{ $t->ref_no ?: '–' }}</span>
                                <span class="max-w-[7rem] truncate text-eyebrow font-bold text-ink">{{ $t->depart_at?->format('H:i') ?? 'TBC' }}</span>
                                <span class="text-eyebrow text-muted">{{ $t->manifest->count() }}/{{ $t->seats() ?: '?' }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (count($pickedGuests))
                    <div class="flex flex-wrap items-center gap-2.5 border-b border-amber-200/80 bg-amber-50/50 px-3.5 py-2">
                        <span class="text-xs font-bold text-ink">{{ count($pickedGuests) }} selected</span>
                        <form wire:submit="assignToNumber" class="flex items-center gap-1.5">
                            <label class="text-eyebrow font-bold uppercase tracking-wide text-muted" for="carNo">Car #</label>
                            <input id="carNo" type="text" inputmode="numeric" placeholder="#" wire:model="assignRef"
                                   class="h-8 w-14 rounded-lg border border-line px-2 text-center text-xs font-bold text-ink focus:border-amber-400 focus:outline-none">
                            <button type="submit" class="rounded-lg bg-navy-900 px-2.5 py-1.5 text-eyebrow font-bold text-white hover:bg-navy-800">Go</button>
                        </form>
                        <select wire:change="assignPicked($event.target.value)" class="h-8 w-auto rounded-lg border border-line bg-white px-2 text-xs text-ink focus:border-navy-300 focus:outline-none">
                            <option value="">or pick a run…</option>
                            @foreach ($assignTargets as $t)
                                <option value="{{ $t->id }}">
                                    #{{ $t->ref_no }} · {{ $t->depart_at?->format('D d M · H:i') ?? 'Unscheduled' }} — {{ \Illuminate\Support\Str::limit($t->route, 28) }}
                                    ({{ $t->manifest->count() }}/{{ $t->seats() ?: '?' }})
                                </option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="unassignPicked" class="text-eyebrow font-bold uppercase tracking-wide text-muted hover:text-ink">To pool</button>
                        <x-confirm
                            title="Delete {{ count($pickedGuests) }} {{ \Illuminate\Support\Str::plural('guest', count($pickedGuests)) }}?"
                            body="Removes them from this event. This cannot be undone."
                            confirm="Delete"
                            run="$wire.deletePicked()"
                            class="text-eyebrow font-bold uppercase tracking-wide text-red-600 hover:text-red-800">Delete</x-confirm>
                        <button type="button" wire:click="clearPicked" class="ml-auto text-eyebrow font-bold uppercase tracking-wide text-muted hover:text-ink">Clear</button>
                    </div>
                @endif

                @if ($guests->isEmpty() && ! $unassignedCount && array_sum($legCounts) === 0)
                    {{-- An empty staging pool is not worth 230px at the top of the
                         screen. It used to sit above the movements as a full empty
                         state, pushing the runs — the thing you are actually here
                         to look at — toward the fold. One line, with the two ways
                         to fill it, until there is something in it. --}}
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 px-4 py-2.5 text-[11.5px]">
                        <span class="text-muted">No guests staged yet — import a flight list, or pull registered attendees.</span>
                        <span class="ms-auto flex items-center gap-2">
                            <button type="button" wire:click="$set('showPlanImport', true)" class="font-semibold" style="color: var(--cx-accent-ink)">⇪ Import guests</button>
                            @if ($attendeePull > 0)
                                <x-confirm
                                    title="Pull {{ $attendeePull }} {{ \Illuminate\Support\Str::plural('attendee', $attendeePull) }} into the pool?"
                                    body="Adds registered attendees as arrivals. People already pulled are skipped."
                                    confirm="Pull attendees"
                                    tone="neutral"
                                    run="$wire.pullAttendees()"
                                    class="font-semibold text-muted transition hover:text-ink">⇩ Pull {{ $attendeePull }}</x-confirm>
                            @endif
                        </span>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px]">
                            <thead>
                                <tr class="border-b border-line text-left text-eyebrow font-bold uppercase tracking-wide text-muted">
                                    <th class="w-10 px-3.5 py-2">
                                        <input type="checkbox" @checked($allPicked) wire:click="toggleGuestPage({{ json_encode($guestIds) }})"
                                               class="h-4 w-4 cursor-pointer rounded border-line text-navy-900 focus:ring-navy-300">
                                    </th>
                                    <th class="px-2 py-2">Guest</th>
                                    <th class="hidden px-2 py-2 sm:table-cell">Flight</th>
                                    <th class="px-2 py-2">{{ $guestLeg === 'departure' ? 'Departs' : 'Lands' }}</th>
                                    <th class="hidden px-2 py-2 md:table-cell">Pick-up</th>
                                    <th class="px-2 py-2">On vehicle</th>
                                </tr>
                            </thead>
                            <tbody data-guest-pool>
                                @forelse ($guests as $g)
                                    @php $picked = in_array($g->id, $pickedGuests, true); @endphp
                                    <tr wire:key="g-{{ $g->id }}" data-guest="{{ $g->id }}"
                                        @class(['border-b border-line last:border-0 cursor-grab transition hover:bg-page/50', 'bg-amber-50/40' => $picked])>
                                        <td class="px-3.5 py-2">
                                            <input type="checkbox" @checked($picked) wire:click="toggleGuest({{ $g->id }})"
                                                   class="h-4 w-4 cursor-pointer rounded border-line text-navy-900 focus:ring-navy-300">
                                        </td>
                                        <td class="px-2 py-2">
                                            <span class="flex items-center gap-1.5">
                                                <span class="text-xs font-bold text-ink">{{ $g->name }}</span>
                                                @if ($g->isPriority())
                                                    <span class="rounded px-1 py-0.5 text-eyebrow font-bold uppercase {{ $g->categoryClass() }}"
                                                          title="Priority — promotes the whole vehicle">{{ $g->categoryLabel() }}</span>
                                                @endif
                                            </span>
                                            <span class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                                <select wire:change="updatePassenger({{ $g->id }}, 'category', $event.target.value)"
                                                        class="h-5 w-auto rounded border-0 bg-transparent p-0 pr-4 text-eyebrow font-semibold text-muted hover:text-ink focus:ring-0">
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
                                        <td class="hidden px-2 py-2 sm:table-cell">
                                            @if ($g->flight_no)
                                                <button type="button" wire:click="pickFlight('{{ $g->flight_no }}')"
                                                        class="rounded-md bg-page px-1.5 py-0.5 text-xs font-bold text-ink transition hover:bg-amber-100"
                                                        title="Select everyone on {{ $g->flight_no }}">{{ $g->flight_no }}</button>
                                                @if ($g->airline)<span class="ms-1 text-eyebrow text-muted">{{ $g->airline }}</span>@endif
                                            @else
                                                <span class="text-xs text-muted">{{ $g->airline ?: '—' }}</span>
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-xs text-ink whitespace-nowrap">
                                            {{ $g->arrival_on?->format('j M') ?? '—' }}{{ $g->arrival_time ? ' · '.substr($g->arrival_time, 0, 5) : '' }}
                                        </td>
                                        <td class="hidden px-2 py-2 text-xs font-semibold text-ink whitespace-nowrap md:table-cell">
                                            {{ $g->pickup_time ? substr($g->pickup_time, 0, 5) : '—' }}
                                        </td>
                                        <td class="px-2 py-2">
                                            @if ($assignTargets->isNotEmpty())
                                                <span class="flex items-center gap-1.5">
                                                    <select wire:change="moveGuest({{ $g->id }}, $event.target.value)"
                                                            @class([
                                                                'h-7 w-auto max-w-[10rem] rounded-lg border px-1.5 text-eyebrow font-semibold',
                                                                'border-emerald-200 bg-emerald-50 text-emerald-800' => $g->transport_id,
                                                                'border-amber-200 bg-amber-50 text-amber-700' => ! $g->transport_id,
                                                            ])
                                                            title="Move this guest to another vehicle">
                                                        <option value="">Pool</option>
                                                        @foreach ($assignTargets as $t)
                                                            <option value="{{ $t->id }}" @selected($g->transport_id === $t->id)>
                                                                #{{ $t->ref_no ?: '–' }} · {{ $t->depart_at?->format('d M H:i') ?? 'TBC' }}
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
                                    <tr>
                                        <td colspan="6" class="px-3.5 py-8 text-center text-xs text-muted">
                                            {{ $guestsOnlyUnassigned ? 'Every '.$guestLeg.' guest is on a vehicle.' : 'No '.$guestLeg.' guests yet — import the flight list above.' }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

