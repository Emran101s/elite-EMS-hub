@php
    $statusMeta = [
        'planned' => ['Planned', 'bg-navy-100 text-navy-600'],
        'booked' => ['Booked', 'bg-amber-100 text-amber-700'],
        'confirmed' => ['Confirmed', 'bg-emerald-100 text-emerald-700'],
        'completed' => ['Completed', 'bg-sky-100 text-sky-700'],
    ];
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
        <div class="min-w-0 space-y-4">

            {{-- ══ page head ══ --}}
            <x-page-head title="Movements"
                         :subtitle="$total.' '.\Illuminate\Support\Str::plural('movement', $total).' · '.$paxTotal.' passengers · '.$seatsTotal.' seats booked'.($overbooked ? ' · '.$overbooked.' over capacity' : '')">
                <x-slot:actions>
                    @if ($total)
                        <a href="{{ route('events.transport.pdf', $event) }}" target="_blank" class="btn-ghost btn-sm">↧ Manifest PDF</a>
                    @endif
                    <button type="button" wire:click="newItem" class="btn-gold btn-sm">＋ Add Movement</button>
                </x-slot:actions>
            </x-page-head>

            {{-- ══ day filter ══ --}}
            @if ($days->isNotEmpty())
                <div class="card flex flex-wrap items-center gap-2 px-4 py-3">
                    <span class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Day</span>
                    <button type="button" wire:click="setDay('')"
                            class="rounded-full px-3 py-1 text-xs font-semibold transition {{ $filterDay === '' ? 'bg-navy-900 text-white' : 'bg-navy-50 text-navy-600 hover:bg-navy-100' }}">
                        All <span class="opacity-60">{{ $total }}</span>
                    </button>
                    @foreach ($days as $day => $count)
                        <button type="button" wire:click="setDay('{{ $day }}')"
                                class="rounded-full px-3 py-1 text-xs font-semibold transition {{ $filterDay === $day ? 'bg-navy-900 text-white' : 'bg-navy-50 text-navy-600 hover:bg-navy-100' }}">
                            {{ \Carbon\Carbon::parse($day)->format('D j M') }} <span class="opacity-60">{{ $count }}</span>
                        </button>
                    @endforeach
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
                    <div class="flex items-center justify-between border-b border-line bg-page/40 px-5 py-2.5">
                        <p class="text-xs font-bold text-navy-900">
                            {{ $day === 'unscheduled' ? 'Not yet scheduled' : \Carbon\Carbon::parse($day)->format('l, j F') }}
                        </p>
                        <p class="text-eyebrow font-bold uppercase tracking-wide text-muted">
                            {{ $group->count() }} {{ \Illuminate\Support\Str::plural('movement', $group->count()) }}
                            · {{ $group->sum(fn ($x) => $x->paxCount()) }} pax
                        </p>
                    </div>

                    @foreach ($group as $m)
                        @php
                            [$stLabel, $stClass] = $statusMeta[$m->status] ?? $statusMeta['planned'];
                            $cap = $m->vehicleType?->capacity ?? 0;
                            $over = $m->isOverbooked();
                        @endphp
                        @php $open = $expandedId === $m->id; @endphp
                        <div wire:key="mv-{{ $m->id }}" class="border-b border-line last:border-0">
                        <div class="group/mv flex cursor-pointer items-center gap-4 px-5 py-3 hover:bg-page/30"
                             wire:click="toggleExpand({{ $m->id }})">

                            <span class="shrink-0 text-navy-300 transition group-hover/mv:text-navy-600 {{ $open ? 'rotate-90' : '' }}">▸</span>

                            {{-- time --}}
                            <div class="w-14 shrink-0 text-center">
                                <p class="pf text-sm font-bold leading-none text-navy-900">{{ $m->depart_at?->format('H:i') ?? '—' }}</p>
                                <p class="mt-0.5 text-eyebrow uppercase tracking-wide text-muted">{{ $m->depart_at?->format('D') ?? 'TBC' }}</p>
                            </div>

                            <span class="shrink-0 text-lg" title="{{ $m->vehicleType?->name }}">{{ $vehicleIcon($cap) }}</span>

                            {{-- what & where --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-navy-900">{{ $m->serviceType?->name ?? $m->route }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide {{ $stClass }}">{{ $stLabel }}</span>
                                    @if ($over)
                                        <span class="rounded-full bg-risk/10 px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide text-red-700"
                                              title="More passengers than seats booked">Over capacity</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 truncate text-eyebrow text-muted">
                                    @if ($m->pickup_from || $m->drop_to)
                                        {{ $m->pickup_from ?: '—' }} → {{ $m->drop_to ?: '—' }}
                                    @endif
                                    @if ($m->depart_at) · pick-up {{ $m->depart_at->format('D j M · H:i') }}@endif
                                    @if ($m->flight_no) · flight <span class="font-semibold text-navy-700">{{ $m->flight_no }}</span>@endif
                                    @if ($m->arrive_at) · lands {{ $m->arrive_at->format('H:i') }}@endif
                                    @if ($m->provider) · {{ $m->provider }}@endif
                                    @if ($m->driver_contact) · driver <span class="font-semibold text-navy-700">{{ $m->driver_contact }}</span>@endif
                                </p>
                            </div>

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

                            <div class="flex shrink-0 items-center gap-1.5" wire:click.stop>
                                <button type="button" wire:click="duplicate({{ $m->id }})" title="Repeat this run tomorrow"
                                        class="rounded-lg bg-navy-50 px-2 py-1.5 text-eyebrow font-bold text-navy-700 hover:bg-navy-100">⧉</button>
                                <button type="button" wire:click="edit({{ $m->id }})"
                                        class="rounded-lg bg-navy-50 px-2.5 py-1.5 text-eyebrow font-bold text-navy-700 hover:bg-navy-100">✎ Edit</button>
                                <button type="button" wire:click="delete({{ $m->id }})"
                                        wire:confirm="Delete this movement and its {{ $m->manifest->count() }} named {{ \Illuminate\Support\Str::plural('passenger', $m->manifest->count()) }}?"
                                        class="rounded-lg bg-risk/10 px-2.5 py-1.5 text-eyebrow font-bold text-red-700 hover:bg-risk/20">Delete</button>
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
                                                <th class="w-12 px-4 pb-2 text-center">Seat</th>
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
                                                    <td class="px-4 py-1.5 text-center text-eyebrow font-bold text-navy-300">{{ $i + 1 }}</td>
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
                                                        <button type="button" wire:click="deletePassenger({{ $p->id }})"
                                                                class="rounded-lg px-1.5 py-1 text-eyebrow font-bold text-navy-300 opacity-0 transition hover:bg-risk/10 hover:text-red-700 group-hover/px:opacity-100">✕</button>
                                                    </td>
                                                </tr>
                                            @endforeach

                                            @if ($m->seats() === 0 || $m->manifest->count() < $m->seats())
                                                <tr class="bg-white/60">
                                                    <td class="px-4 py-2 text-center text-eyebrow font-bold text-gold-500">{{ $m->manifest->count() + 1 }}</td>
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
                                <div class="border-t border-line px-5 py-2.5 text-eyebrow text-muted">
                                    <span class="font-bold text-navy-900">{{ $m->manifest->count() }}</span> named ·
                                    <span class="font-bold text-navy-900">{{ $m->seatsFree() }}</span> seats free
                                    @if ($m->vehicleType) · {{ $m->vehicleType->name }} holds {{ $m->vehicleType->capacity }} each, {{ $m->vehicleCount() }} booked @endif
                                </div>
                            </div>
                        @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

    </div>

    {{-- ══ controls, docked right ══
         Moved off the page into the dock so the manifest tables get the full
         width. The markup stays inside this component, so wire:click actions
         keep working without routing events through another component. --}}
    <x-dock id="controls" label="Controls" :color="$moduleColor" icon="truck" :order="0"
            title="Transport Controls" subtitle="Summary, fleet and actions for this event">
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
                        <a href="{{ route('events.transport.pdf', $event) }}" target="_blank"
                           class="block rounded-xl border border-line bg-white px-4 py-2.5 text-center text-xs font-bold text-navy-700 transition hover:border-gold-300 hover:text-navy-900">
                            ↧ Export manifest PDF
                        </a>
                    @endif
                    <a href="{{ route('transport-settings.index') }}"
                       class="block rounded-xl px-4 py-2 text-center text-micro font-semibold text-navy-500 hover:bg-navy-50 hover:text-navy-900">
                        Manage vehicles &amp; services →
                    </a>
                </div>
    </x-dock>

    {{-- ══ movement modal ══ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit movement' : 'New movement'"
                 subtitle="A service, in a vehicle, at a time — passengers are named on the manifest afterwards."
                 close="$set('showForm', false)">
            <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
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
</div>
