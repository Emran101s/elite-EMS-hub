@php
    $moduleHex = \App\Models\Event::moduleColor('transportation');
    // Capacity tiers share the truck glyph; wash + title carry the size signal.
    $vehicleIcon = fn (?int $cap) => 'truck';
    $guestIds = $guests->pluck('id')->all();
    $allPicked = $guestIds && ! array_diff($guestIds, $pickedGuests);
    // Cost carries a third decimal now (cost_cents is decimal(15,1)) — the
    // shared $event->money() helper is deliberately whole-currency-unit
    // only everywhere else, so cost figures here format locally instead.
    $money3 = fn ($c) => $event->currencySymbol().(strlen($event->currencySymbol()) > 1 ? ' ' : '').number_format(($c ?? 0) / 100, 3);
@endphp
<div class="cx-canvas">
    <datalist id="airlines">
        @foreach (['Royal Jordanian', 'Emirates', 'Qatar Airways', 'Turkish Airlines', 'EgyptAir',
                   'Saudia', 'Etihad', 'Lufthansa', 'British Airways', 'Air France', 'Pegasus', 'flydubai'] as $al)
            <option value="{{ $al }}"></option>
        @endforeach
    </datalist>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        {{-- ══════════ MAIN · guests → plan → manifests ══════════ --}}
        <div class="min-w-0 space-y-4">

            {{-- Dense toolbar --}}
            <div class="flex flex-wrap items-center gap-2">
                <span class="cx-seg">
                    <span aria-pressed="true">List</span>
                    <a href="{{ route('events.transport.dispatch', $event) }}"
                       title="Lanes against a time axis — plan and catch clashes">Dispatch</a>
                    <a href="{{ route('events.transport.live', $event) }}" style="display:inline-flex;align-items:center;gap:6px"
                       title="Event-day operations — designed for a phone">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>Live
                    </a>
                </span>

                @if ($total)
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
                                <span class="block text-xs font-bold text-ink">Daily Schedule</span>
                                <span class="block text-eyebrow text-muted">Ops team — one day per page</span>
                            </a>
                            <a href="{{ route('events.transport.pdf', [$event, ...$this->exportFilters()]) }}" target="_blank"
                               class="block border-b border-line px-4 py-2 transition hover:bg-page/60">
                                <span class="block text-xs font-bold text-ink">Vehicle Manifest</span>
                                <span class="block text-eyebrow text-muted">Who rides in which vehicle</span>
                            </a>
                            <a href="{{ route('events.transport.trip-sheet.pdf', [$event, ...array_filter(['day' => $filterDay])]) }}" target="_blank"
                               class="block border-b border-line px-4 py-2 transition hover:bg-page/60">
                                <span class="block text-xs font-bold text-ink">Driver Trip Sheets</span>
                                <span class="block text-eyebrow text-muted">One page per driver, per day</span>
                            </a>
                            <a href="{{ route('events.transport.vip-sheet.pdf', $event) }}" target="_blank"
                               class="block px-4 py-2 transition hover:bg-page/60">
                                <span class="block text-xs font-bold text-ink">VIP Transfer Sheets</span>
                                <span class="block text-eyebrow text-muted">One page per VIP or speaker</span>
                            </a>
                            <p class="border-y border-line bg-page/50 px-4 py-2 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Whole event</p>
                            <a href="{{ route('events.transport.master-plan.pdf', $event) }}" target="_blank"
                               class="block border-b border-line px-4 py-2 transition hover:bg-page/60">
                                <span class="block text-xs font-bold text-ink">Master Plan</span>
                                <span class="block text-eyebrow text-muted">Client-facing cover &amp; approval</span>
                            </a>
                            <a href="{{ route('events.transport.supplier-order.pdf', $event) }}" target="_blank"
                               class="block px-4 py-2 transition hover:bg-page/60">
                                <span class="block text-xs font-bold text-ink">Supplier Order</span>
                                <span class="block text-eyebrow text-muted">Request per vendor to quote</span>
                            </a>
                        </div>
                    </div>
                @endif

                @if ($attendeePull > 0)
                    <x-confirm
                        title="Pull {{ $attendeePull }} {{ \Illuminate\Support\Str::plural('attendee', $attendeePull) }} into the pool?"
                        body="Adds registered attendees as arrivals. People already pulled are skipped."
                        confirm="Pull attendees"
                        tone="neutral"
                        run="$wire.pullAttendees()"
                        class="btn-ghost btn-sm">⇩ Pull {{ $attendeePull }}</x-confirm>
                @endif

                <button type="button" wire:click="$toggle('showPlanImport')" class="btn-ghost btn-sm">⇪ Import</button>

                <div class="ms-auto flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="newItem" class="btn-gold btn-sm">＋ Add Movement</button>
                    @if ($total)
                        <details class="relative" data-menu>
                            <summary class="grid h-8 w-8 cursor-pointer list-none place-items-center rounded-xl text-[15px] leading-none text-muted transition hover:bg-page hover:text-ink [&::-webkit-details-marker]:hidden">⋮</summary>
                            <div class="absolute end-0 z-30 mt-1 w-64 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-xl">
                                <x-confirm
                                    title="Clear the vehicle plan?"
                                    body="Deletes all {{ $total }} {{ \Illuminate\Support\Str::plural('movement', $total) }}. Guests return to the pool so you can plan again. The movements cannot be recovered."
                                    confirm="Clear plan"
                                    run="$wire.deleteAllMovements()"
                                    class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-red-700 transition hover:bg-red-50">
                                    Clear the vehicle plan
                                    <span class="block text-[10.5px] font-normal text-muted">Guests stay in the pool.</span>
                                </x-confirm>
                            </div>
                        </details>
                    @endif
                </div>
            </div>

            {{-- Plan import — slim inset --}}
            @if ($showPlanImport)
                <div class="rounded-xl border border-line bg-page/40 px-3.5 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold text-ink">Import the guest list</p>
                            <p class="mt-0.5 text-eyebrow leading-relaxed text-muted">
                                Excel or CSV — one row per traveller (Name, Direction, Airline, Flight #, Date, Flight Time, Pickup Time, From, To, Phone, Notes).
                                Guests land in the pool; you book vehicles and place them. Re-import updates instead of duplicating.
                            </p>
                        </div>
                        <button type="button" wire:click="$set('showPlanImport', false)" class="shrink-0 rounded-lg px-2 py-1 text-eyebrow font-bold text-muted hover:text-ink">✕</button>
                    </div>
                    <div class="mt-2.5 flex flex-wrap items-center gap-2.5">
                        <input type="file" wire:model="planFile" accept=".xlsx,.xls,.csv,text/csv"
                               class="text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                        <button type="button" wire:click="importPlan" wire:loading.attr="disabled" wire:target="importPlan,planFile"
                                class="btn-gold btn-sm disabled:opacity-50">
                            <span wire:loading.remove wire:target="importPlan">Import plan</span>
                            <span wire:loading wire:target="importPlan">Importing…</span>
                        </button>
                        <span wire:loading wire:target="planFile" class="text-eyebrow font-semibold text-amber-700">Uploading…</span>
                        <a href="{{ route('events.transport.plan-template', $event) }}"
                           class="border-l border-line pl-3 text-eyebrow font-bold uppercase tracking-wide text-muted hover:text-ink"
                           title="Download a ready-to-fill Excel template">↧ Template</a>
                    </div>
                    @error('planFile')<p class="mt-1.5 text-xs text-danger-ink">{{ $message }}</p>@enderror
                </div>
            @endif

            @if ($planMsg)
                <x-alert tone="ok">{{ $planMsg }}</x-alert>
            @endif

            @include('livewire.hub.transport.guests')
            {{-- ══ 2 · Vehicle plan ══ --}}
            <section>
                <div class="mb-2 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <p class="text-[13px] font-bold text-ink">2 · Vehicle plan</p>
                        <p class="text-eyebrow text-muted">Book runs, then open a row to name who rides</p>
                    </div>
                    @if ($total)
                        <span class="text-eyebrow text-muted">{{ $shown }} of {{ $total }}</span>
                    @endif
                </div>

                @if ($total || $days->isNotEmpty())
                    <div class="mb-2.5 flex flex-wrap items-center gap-x-3 gap-y-2">
                        @if ($total)
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button type="button" wire:click="setLeg('')" class="cx-chip {{ $filterLeg === '' ? 'is-on' : '' }}">
                                    All <span class="opacity-60">{{ $total }}</span>
                                </button>
                                @foreach ($legTabs as $tab)
                                    <button type="button" wire:click="setLeg('{{ $tab['key'] }}')"
                                            @disabled($tab['runs'] === 0)
                                            class="cx-chip {{ $filterLeg === $tab['key'] ? 'is-on' : '' }}"
                                            title="{{ $tab['hint'] }}">
                                        {{ $tab['label'] }} <span class="opacity-60">{{ $tab['runs'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @if ($days->isNotEmpty())
                            @if ($total)<span class="hidden h-5 w-px bg-line sm:block" aria-hidden="true"></span>@endif
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="me-0.5 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Day</span>
                                <button type="button" wire:click="setDay('')" class="cx-chip {{ $filterDay === '' ? 'is-on' : '' }}">
                                    All
                                </button>
                                @foreach ($days as $day => $count)
                                    <button type="button" wire:click="setDay('{{ $day }}')" class="cx-chip {{ $filterDay === $day ? 'is-on' : '' }}">
                                        {{ \Carbon\Carbon::parse($day)->format('D j M') }} <span class="opacity-60">{{ $count }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                @if ($movements->isEmpty())
                    <x-empty icon="truck"
                             :title="$filterDay ? 'Nothing moving on that day' : 'No transport planned yet'"
                             hint="A movement is a service — airport transfer, hotel shuttle, a day at disposal — in a vehicle sized to the group.">
                        <x-slot:actions>
                            <button type="button" wire:click="newItem" class="btn-gold btn-sm">＋ Add a movement</button>
                            <a href="{{ route('transport-settings.index') }}" class="btn-ghost btn-sm">Manage vehicles &amp; services →</a>
                        </x-slot:actions>
                    </x-empty>
                @else
                    <div class="space-y-3">
                        @foreach ($movements as $day => $group)
                            <div>
                                <div class="mb-1.5 flex items-center justify-between px-0.5">
                                    <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-ink">
                                        {{ $day === 'unscheduled' ? 'Not yet scheduled' : \Carbon\Carbon::parse($day)->format('l, j F') }}
                                    </p>
                                    <p class="text-eyebrow font-bold uppercase tracking-wide text-muted">
                                        {{ $group->count() }} {{ \Illuminate\Support\Str::plural('movement', $group->count()) }}
                                        · {{ $group->sum(fn ($x) => $x->paxCount()) }} pax
                                    </p>
                                </div>

                                <div class="cx-lcard !mb-0">
                                    @foreach ($group as $m)
                                        @php
                                            $stLabel = $m->statusLabel(); $stClass = $m->statusClass();
                                            $cap = $m->vehicleType?->capacity ?? 0;
                                            $over = $m->isOverbooked();
                                            $priority = $m->isPriority();
                                            $overloaded = $m->driver && $m->depart_at
                                                && $m->driver->isOverloadedOn($m->depart_at->toDateString());
                                            $open = $expandedId === $m->id;
                                            $chip = $m->readinessChip();
                                            $legClass = ['arrival' => 'bg-emerald-100 text-emerald-700', 'departure' => 'bg-sky-100 text-sky-700'][$m->leg] ?? 'bg-page text-muted';
                                        @endphp
                                        <div wire:key="mv-{{ $m->id }}" class="border-b border-line last:border-0">
                                            <div class="group/mv flex flex-wrap cursor-pointer items-center gap-x-2.5 gap-y-1.5 border-l-[3px] px-3 py-2.5 transition hover:bg-page/30 {{ $open ? 'bg-page/20' : '' }}"
                                                 style="border-left-color: {{ $priority ? '#D4AF37' : $moduleHex }}"
                                                 wire:click="toggleExpand({{ $m->id }})">

                                                <span class="shrink-0 text-muted transition group-hover/mv:text-muted {{ $open ? 'rotate-90' : '' }}">▸</span>

                                                @if ($m->ref_no)
                                                    <span @class([
                                                              'flex h-9 w-8 shrink-0 items-center justify-center text-xs font-black',
                                                              'text-ink' => $priority,
                                                              'text-white' => ! $priority,
                                                          ])
                                                          style="clip-path: polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%); background: {{ $priority ? 'var(--cx-accent)' : $moduleHex }}"
                                                          title="Car {{ $m->refLabel() }}">{{ $m->ref_no }}</span>
                                                @else
                                                    <span class="flex h-9 w-8 shrink-0 items-center justify-center text-xs font-black text-muted" style="clip-path: polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%); box-shadow: inset 0 0 0 1.5px var(--cx-line)"
                                                          title="No car number yet">–</span>
                                                @endif

                                                <div class="w-12 shrink-0 text-center">
                                                    <p class="text-sm font-bold leading-none text-ink">{{ $m->depart_at?->format('H:i') ?? '—' }}</p>
                                                    <p class="mt-0.5 text-eyebrow uppercase tracking-wide text-muted">{{ $m->depart_at?->format('D') ?? 'TBC' }}</p>
                                                </div>

                                                <span class="flex h-9 w-8 shrink-0 items-center justify-center"
                                                      style="clip-path: polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%); color: {{ $moduleHex }}; background: {{ $moduleHex }}18"
                                                      title="{{ $m->vehicleType?->name ?? 'Vehicle' }}{{ $cap ? ' · max '.$cap : '' }}">
                                                    <x-icon :name="$vehicleIcon($cap)" class="h-4 w-4" />
                                                </span>

                                                <div class="order-last w-full min-w-0 sm:order-none sm:w-auto sm:flex-1">
                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                        @if (in_array($m->leg, ['arrival', 'departure'], true))
                                                            <span class="shrink-0 rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide {{ $legClass }}"
                                                                  title="{{ \App\Models\EventTransport::LEG_HINTS[$m->leg] ?? '' }}">{{ $m->legLabel() }}</span>
                                                        @endif
                                                        <p class="truncate text-sm font-semibold text-ink">{{ $m->serviceType?->name ?? $m->route }}</p>
                                                        <span class="rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide {{ $stClass }}"
                                                              title="{{ \App\Models\EventTransport::STATUS_META[$m->status]['hint'] ?? '' }}">{{ $stLabel }}</span>
                                                        @if ($priority)
                                                            <span class="rounded-full bg-gold-100 px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide text-gold-800"
                                                                  title="{{ $m->priorityReason() }}">★ Priority</span>
                                                        @endif
                                                        @if ($over)
                                                            <span class="rounded-full bg-danger-soft px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide text-red-700">Over capacity</span>
                                                        @endif
                                                        @if ($overloaded)
                                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide text-amber-800"
                                                                  title="{{ $m->driver->name }} is on duty {{ \App\Models\TransportDriver::readableMinutes($m->driver->dutyMinutes($m->depart_at->toDateString())) }} that day">Driver overloaded</span>
                                                        @endif
                                                    </div>
                                                    @if ($m->pickup_from || $m->drop_to)
                                                        <p class="mt-0.5 truncate text-xs font-semibold text-ink">
                                                            {{ $m->pickup_from ?: '—' }} <span class="text-muted">→</span> {{ $m->drop_to ?: '—' }}
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
                                                    @if ($m->driver)
                                                        <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-eyebrow text-muted">
                                                            <span class="font-semibold text-ink">{{ $m->driver->name }}</span>
                                                            @if ($m->contactNumber())
                                                                <a href="tel:{{ $m->contactNumber() }}" wire:click.stop
                                                                   class="font-semibold text-muted hover:text-ink">{{ $m->contactNumber() }}</a>
                                                            @endif
                                                            @if ($wa = \App\Support\WhatsApp::toDriver($m))
                                                                <a href="{{ $wa }}" target="_blank" rel="noopener" wire:click.stop
                                                                   class="font-bold text-emerald-600 hover:text-emerald-800"
                                                                   title="Send this run's details to {{ $m->driver->name }}">WA</a>
                                                            @endif
                                                        </p>
                                                    @endif
                                                </div>

                                                <span @class([
                                                          'hidden shrink-0 rounded-full px-2.5 py-1 text-eyebrow font-bold sm:block',
                                                          'bg-emerald-50 text-emerald-700' => $chip['ready'],
                                                          'bg-amber-50 text-amber-700' => ! $chip['ready'],
                                                      ])
                                                      title="{{ $chip['detail'] }}">{{ $chip['label'] }}</span>

                                                <div class="hidden w-40 shrink-0 sm:block">
                                                    <p class="truncate text-xs font-semibold text-ink">
                                                        {{ $m->vehicleType?->name ?? '—' }}@if ($m->vehicles > 1) <span class="text-muted">×{{ $m->vehicles }}</span>@endif
                                                    </p>
                                                    @if ($m->seats() > 0)
                                                        @php $load = min(100, (int) round($m->paxCount() / max(1, $m->seats()) * 100)); @endphp
                                                        <div class="mt-1 flex items-center gap-1.5">
                                                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-page">
                                                                <div class="h-full rounded-full {{ $over ? 'bg-danger' : '' }}"
                                                                     style="width: {{ $load }}%; {{ $over ? '' : 'background: '.$moduleHex }}"></div>
                                                            </div>
                                                            <span class="shrink-0 text-eyebrow font-bold {{ $over ? 'text-red-700' : 'text-muted' }}">{{ $m->paxCount() }}/{{ $m->seats() }}</span>
                                                        </div>
                                                    @else
                                                        <p class="mt-0.5 text-eyebrow text-muted">{{ $m->paxCount() }} pax</p>
                                                    @endif
                                                </div>

                                                <p class="hidden w-20 shrink-0 text-right text-xs font-semibold text-ink md:block">
                                                    {{ $m->cost_cents ? $money3($m->cost_cents) : '—' }}
                                                </p>

                                                <div class="flex shrink-0 items-center gap-1" wire:click.stop>
                                                    <button type="button" wire:click="edit({{ $m->id }})"
                                                            class="rounded-lg bg-page px-2 py-1.5 text-eyebrow font-bold text-ink hover:bg-page">✎</button>
                                                    <details class="relative" data-menu>
                                                        <summary class="grid h-7 w-7 cursor-pointer list-none place-items-center rounded-lg text-[15px] leading-none text-muted transition hover:bg-page hover:text-ink [&::-webkit-details-marker]:hidden">⋮</summary>
                                                        <div class="absolute end-0 z-30 mt-1 w-56 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-xl">
                                                            @if ($next = $m->nextPlanningStatus())
                                                                <button type="button" wire:click="advanceStatus({{ $m->id }})"
                                                                        class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-ink transition hover:bg-page">
                                                                    → Mark {{ \App\Models\EventTransport::STATUS_META[$next]['label'] }}
                                                                    <span class="block text-eyebrow font-medium text-muted">{{ \App\Models\EventTransport::STATUS_META[$next]['hint'] }}</span>
                                                                </button>
                                                            @endif
                                                            <button type="button" wire:click="duplicate({{ $m->id }})"
                                                                    class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-ink transition hover:bg-page">
                                                                Repeat this run tomorrow
                                                            </button>
                                                            <x-confirm
                                                                title="Delete {{ $m->refLabel() !== '—' ? 'car '.$m->refLabel() : 'this movement' }}?"
                                                                body="{{ $m->manifest->count() }} named {{ \Illuminate\Support\Str::plural('passenger', $m->manifest->count()) }} return to the guest pool. The movement itself cannot be recovered."
                                                                confirm="Delete movement"
                                                                run="$wire.delete({{ $m->id }})"
                                                                class="block w-full border-t border-line px-3 py-2 text-start text-[11.5px] font-semibold text-red-700 transition hover:bg-red-50">
                                                                Delete this movement
                                                            </x-confirm>
                                                        </div>
                                                    </details>
                                                </div>
                                            </div>

                                            {{-- ══ 3 · Manifest ══ --}}
                                            @if ($open)
                                                <div class="border-t border-line bg-page/20">
                                                    <div class="flex items-center justify-between gap-2 border-b border-line/60 px-3.5 py-1.5">
                                                        <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">3 · Manifest</p>
                                                        <span class="text-eyebrow text-muted">
                                                            <span class="font-bold text-ink">{{ $m->manifest->count() }}</span> named ·
                                                            <span class="font-bold text-ink">{{ $m->seatsFree() }}</span> free
                                                        </span>
                                                    </div>
                                                    <div class="overflow-x-auto">
                                                        <table class="w-full min-w-[640px]">
                                                            <thead>
                                                                <tr class="border-b border-line text-left text-eyebrow font-bold uppercase tracking-wide text-muted">
                                                                    <th class="w-12 px-3 py-2 text-center">{{ $m->vehicles > 1 ? 'Van' : '#' }}</th>
                                                                    <th class="px-2 py-2">Passenger</th>
                                                                    <th class="px-2 py-2">Flight</th>
                                                                    <th class="hidden px-2 py-2 lg:table-cell">Lands</th>
                                                                    <th class="hidden px-2 py-2 md:table-cell">Pick-up</th>
                                                                    <th class="hidden px-2 py-2 sm:table-cell">Phone</th>
                                                                    <th class="w-10 px-2 py-2"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($m->manifest as $i => $p)
                                                                    @php $pin = 'w-full rounded-lg border border-transparent bg-transparent px-1.5 py-1 text-xs text-ink placeholder:text-muted hover:border-line focus:border-amber-400 focus:bg-white focus:outline-none'; @endphp
                                                                    <tr wire:key="pax-{{ $p->id }}" class="group/px border-b border-line last:border-0 hover:bg-white">
                                                                        <td class="px-2 py-1.5 text-center">
                                                                            @if ($m->vehicles > 1)
                                                                                <select wire:change="updatePassenger({{ $p->id }}, 'vehicle_no', $event.target.value)"
                                                                                        class="w-full cursor-pointer rounded-md border border-line bg-white px-1 py-1 text-eyebrow font-bold text-ink"
                                                                                        title="Which vehicle of this run">
                                                                                    @for ($v = 1; $v <= $m->vehicles; $v++)
                                                                                        <option value="{{ $v }}" @selected((int) ($p->vehicle_no ?: 1) === $v)>{{ $v }}</option>
                                                                                    @endfor
                                                                                </select>
                                                                            @else
                                                                                <span class="text-eyebrow font-bold text-muted">{{ $i + 1 }}</span>
                                                                            @endif
                                                                        </td>
                                                                        <td class="min-w-[120px] px-1 py-1">
                                                                            <input type="text" value="{{ $p->name }}" placeholder="Name"
                                                                                   wire:change="updatePassenger({{ $p->id }}, 'name', $event.target.value)"
                                                                                   class="{{ $pin }} font-semibold">
                                                                        </td>
                                                                        <td class="px-1 py-1">
                                                                            <div class="flex flex-col gap-0.5 sm:flex-row sm:items-center">
                                                                                <input type="text" value="{{ $p->flight_no }}" placeholder="RJ 512"
                                                                                       wire:change="updatePassenger({{ $p->id }}, 'flight_no', $event.target.value)"
                                                                                       class="{{ $pin }} max-w-[5.5rem]">
                                                                                <input type="text" value="{{ $p->airline }}" placeholder="Airline" list="airlines"
                                                                                       wire:change="updatePassenger({{ $p->id }}, 'airline', $event.target.value)"
                                                                                       class="{{ $pin }} hidden max-w-[7rem] lg:block">
                                                                            </div>
                                                                        </td>
                                                                        <td class="hidden px-1 py-1 lg:table-cell">
                                                                            <div class="flex items-center gap-0.5">
                                                                                <input type="date" value="{{ $p->arrival_on?->format('Y-m-d') }}"
                                                                                       wire:change="updatePassenger({{ $p->id }}, 'arrival_on', $event.target.value)"
                                                                                       class="{{ $pin }}">
                                                                                <input type="time" value="{{ $p->arrival_time }}"
                                                                                       wire:change="updatePassenger({{ $p->id }}, 'arrival_time', $event.target.value)"
                                                                                       class="{{ $pin }} max-w-[5.5rem]">
                                                                            </div>
                                                                        </td>
                                                                        <td class="hidden px-1 py-1 md:table-cell">
                                                                            <input type="text" value="{{ $p->pickup_point }}" placeholder="—"
                                                                                   wire:change="updatePassenger({{ $p->id }}, 'pickup_point', $event.target.value)"
                                                                                   class="{{ $pin }}">
                                                                        </td>
                                                                        <td class="hidden px-1 py-1 sm:table-cell">
                                                                            <input type="text" value="{{ $p->phone }}" placeholder="—"
                                                                                   wire:change="updatePassenger({{ $p->id }}, 'phone', $event.target.value)"
                                                                                   class="{{ $pin }}">
                                                                        </td>
                                                                        <td class="px-2 py-1.5">
                                                                            <button type="button" wire:click="unassignGuest({{ $p->id }})"
                                                                                    title="Take {{ $p->name }} off this vehicle — back to the pool"
                                                                                    class="rounded-lg px-1.5 py-1 text-eyebrow font-bold text-muted opacity-100 transition sm:opacity-0 hover:bg-page hover:text-ink sm:group-hover/px:opacity-100">✕</button>
                                                                        </td>
                                                                    </tr>
                                                                @endforeach

                                                                @if ($m->seats() === 0 || $m->manifest->count() < $m->seats())
                                                                    <tr class="bg-white/60">
                                                                        <td class="px-3 py-2 text-center text-eyebrow font-bold" style="color: {{ $moduleHex }}">{{ $m->manifest->count() + 1 }}</td>
                                                                        <td class="px-1 py-2" colspan="5">
                                                                            <input type="text" wire:model="newPax.{{ $m->id }}"
                                                                                   wire:keydown.enter="addPassenger({{ $m->id }})"
                                                                                   placeholder="Type a name and press Enter…"
                                                                                   class="w-full rounded-lg border border-dashed border-line bg-transparent px-2 py-1.5 text-xs text-ink placeholder:text-muted focus:border-amber-400 focus:bg-white focus:outline-none">
                                                                            @error('newPax.'.$m->id)<p class="mt-1 px-2 text-xs text-danger-ink">{{ $message }}</p>@enderror
                                                                        </td>
                                                                        <td class="px-2 py-2">
                                                                            <button type="button" wire:click="addPassenger({{ $m->id }})"
                                                                                    class="rounded-lg bg-amber-100 px-1.5 py-1 text-eyebrow font-bold text-amber-800 hover:bg-amber-200">＋</button>
                                                                        </td>
                                                                    </tr>
                                                                @else
                                                                    <tr>
                                                                        <td colspan="7" class="px-4 py-2 text-center text-eyebrow font-semibold text-emerald-700">
                                                                            Every seat is taken — {{ $m->seats() }}/{{ $m->seats() }} full.
                                                                        </td>
                                                                    </tr>
                                                                @endif
                                                            </tbody>
                                                        </table>
                                                    </div>

                                                    @if ($importMoveId === $m->id)
                                                        <div class="border-t border-line bg-page/40 px-3.5 py-3">
                                                            <div class="flex items-start justify-between gap-3">
                                                                <div>
                                                                    <p class="text-xs font-bold text-ink">Import into this run</p>
                                                                    <p class="mt-0.5 text-eyebrow leading-relaxed text-muted">
                                                                        Excel or CSV. Columns: Name (required), Airline, Flight #, Arrival Date, Arrival Time, Phone, Email, Pickup Point, Notes.
                                                                    </p>
                                                                </div>
                                                                <button type="button" wire:click="closeImport" class="shrink-0 rounded-lg px-2 py-1 text-eyebrow font-bold text-muted hover:text-ink">✕</button>
                                                            </div>
                                                            <div class="mt-2.5 flex flex-wrap items-center gap-2.5">
                                                                <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv,text/csv"
                                                                       class="text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                                                                <button type="button" wire:click="importPassengers" wire:loading.attr="disabled" wire:target="importPassengers,importFile"
                                                                        class="btn-gold btn-sm disabled:opacity-50">
                                                                    <span wire:loading.remove wire:target="importPassengers">Import</span>
                                                                    <span wire:loading wire:target="importPassengers">Importing…</span>
                                                                </button>
                                                                <a href="{{ route('events.transport.template', [$event, $m]) }}"
                                                                   class="border-l border-line pl-3 text-eyebrow font-bold uppercase tracking-wide text-muted hover:text-ink">↧ Template</a>
                                                            </div>
                                                            @error('importFile')<p class="mt-1.5 text-xs text-danger-ink">{{ $message }}</p>@enderror
                                                        </div>
                                                    @endif

                                                    @if ($importMsg && $expandedId === $m->id)
                                                        <div class="border-t border-line px-3.5 py-2">
                                                            <x-alert tone="ok" class="!py-2 !text-xs">{{ $importMsg }}</x-alert>
                                                        </div>
                                                    @endif

                                                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-3.5 py-2 text-eyebrow text-muted">
                                                        <span>
                                                            @if ($m->vehicleType)
                                                                {{ $m->vehicleType->name }} holds {{ $m->vehicleType->capacity }} · {{ $m->vehicleCount() }} booked
                                                            @endif
                                                            @if ($m->vehicles > 1 && $m->manifest->isNotEmpty())
                                                                <span class="ms-1.5 border-l border-line ps-1.5">
                                                                    @foreach ($m->manifestByVehicle() as $no => $pax)
                                                                        <span class="me-1 rounded bg-page px-1.5 py-0.5 font-bold text-ink">Van {{ $no }}: {{ $pax->count() }}</span>
                                                                    @endforeach
                                                                </span>
                                                            @endif
                                                        </span>
                                                        <span class="flex items-center gap-3">
                                                            @if ($m->manifest->isNotEmpty())
                                                                <button type="button" wire:click="autoAssign({{ $m->id }})"
                                                                        class="font-bold uppercase tracking-wide text-muted hover:text-ink"
                                                                        title="Fill each vehicle to capacity in order">⇄ Auto-assign</button>
                                                            @endif
                                                            <button type="button" wire:click="openImport({{ $m->id }})"
                                                                    class="font-bold uppercase tracking-wide text-muted hover:text-ink">⇪ Import Excel</button>
                                                        </span>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- ══════════ RIGHT · control rail ══════════ --}}
        @include('livewire.hub.transport.control-rail')
    </div>

    {{-- Movement modal --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit movement' : 'New movement'"
                 subtitle="A service, in a vehicle, at a time — passengers are named on the manifest afterwards."
                 close="$set('showForm', false)">
            <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Movement type</label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (\App\Models\EventTransport::LEGS as $key => $label)
                            <button type="button" wire:click="$set('leg', '{{ $key }}')"
                                    @class([
                                        'rounded-xl border px-3 py-2 text-left transition',
                                        'border-navy-900 bg-navy-900 text-white' => $leg === $key,
                                        'border-line bg-white hover:border-line' => $leg !== $key,
                                    ])>
                                <span class="block text-xs font-bold">{{ $label }}</span>
                                <span class="block text-eyebrow {{ $leg === $key ? 'text-white/60' : 'text-muted' }}">
                                    {{ \App\Models\EventTransport::LEG_HINTS[$key] }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                    @error('leg')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Driver</label>
                    <select wire:model="driver_id" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                        <option value="">— unassigned —</option>
                        @foreach ($drivers as $d)<option value="{{ $d->id }}">{{ $d->label() }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Vehicle (specific car)</label>
                    <select wire:model="vehicle_id" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                        <option value="">— any of this type —</option>
                        @foreach ($fleetVehicles as $v)<option value="{{ $v->id }}">{{ $v->label() }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Supplier</label>
                    <select wire:model="supplier_id" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                        <option value="">— none —</option>
                        @foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="flex h-10 w-full cursor-pointer items-center gap-2.5 rounded-xl border border-line px-3 transition hover:border-amber-300">
                        <input type="checkbox" wire:model="is_vip" class="h-4 w-4 rounded border-line text-gold-600 focus:ring-gold-400">
                        <span class="text-xs font-semibold text-ink">Priority / VIP run</span>
                    </label>
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Service</label>
                    <select wire:model="service_type_id" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                        <option value="">— none —</option>
                        @foreach ($serviceTypes as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Vehicle</label>
                    <select wire:model="vehicle_type_id" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                        <option value="">— none —</option>
                        @foreach ($vehicleTypes as $v)<option value="{{ $v->id }}">{{ $v->label() }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">How many vehicles</label>
                    <input type="number" min="1" wire:model="vehicles" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                    @error('vehicles')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Passengers <span class="normal-case text-muted">— estimate</span></label>
                    <input type="number" min="0" wire:model="passengers" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="0">
                    <p class="mt-1 text-eyebrow text-muted">Named passengers on the manifest override this.</p>
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Pick up from</label>
                    <input type="text" wire:model="pickup_from" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Queen Alia Airport">
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Drop off at</label>
                    <input type="text" wire:model="drop_to" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Fairmont Amman">
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Pick-up date &amp; time</label>
                    <input type="datetime-local" wire:model="depart_at" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Flight number</label>
                    <input type="text" wire:model="flight_no" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="RJ 512">
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Flight lands / arrives</label>
                    <input type="datetime-local" wire:model="arrive_at" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Provider</label>
                    <input type="text" wire:model="provider" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Petra Limo">
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Driver contact</label>
                    <input type="text" wire:model="driver_contact" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="+962 79 555 0100">
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Cost ({{ $event->currency }})</label>
                    <input type="number" step="0.001" min="0" wire:model="cost" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="0">
                </div>
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Status</label>
                    <select wire:model="status" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm">
                        @foreach (\App\Models\EventTransport::STATUSES as $st)<option value="{{ $st }}">{{ ucfirst($st) }}</option>@endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Notes</label>
                    <input type="text" wire:model="notes" class="w-full rounded-lg border border-line bg-white px-3 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Meet & greet at arrivals, name board…">
                </div>
                <p class="text-eyebrow text-muted sm:col-span-2">
                    Only the vehicles and services switched on in
                    <a href="{{ route('transport-settings.index') }}" class="font-semibold text-muted underline hover:text-ink">Settings → Transport</a>
                    appear here.
                </p>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('showForm', false)" class="btn-ghost btn-sm">Cancel</button>
                    <button type="submit" class="btn-sm rounded-full bg-gold-500 font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">{{ $editingId ? 'Update' : 'Add movement' }}</button>
                </div>
            </form>
        </x-modal>
    @endif

    @script
    <script>
        (() => {
            const wire = $wire;

            const wireUp = () => {
                const pool = document.querySelector('[data-guest-pool]');
                if (!pool || pool.dataset.sortableBound) return;
                pool.dataset.sortableBound = '1';

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
                            evt.item.remove();
                            if (guestId && movementId) {
                                wire.dropGuest(guestId, movementId);
                            }
                        },
                    });

                    zone.addEventListener('dragenter', () => zone.classList.add('ring-2', 'ring-amber-400'));
                    zone.addEventListener('dragleave', () => zone.classList.remove('ring-2', 'ring-amber-400'));
                    zone.addEventListener('drop', () => zone.classList.remove('ring-2', 'ring-amber-400'));
                });
            };

            wireUp();
            Livewire.hook('morphed', wireUp);
        })();
    </script>
    @endscript
</div>
