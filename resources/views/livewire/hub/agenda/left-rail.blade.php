            {{-- ═══ LEFT RAIL ═══ --}}
            <aside class="cx-panel flex flex-col gap-0 self-start !p-3.5">

                {{-- Days --}}
                <div class="cx-railsec">
                    <div class="cx-raillbl">
                        <span>Days</span>
                        <button type="button" wire:click="addDay" class="text-[11px] font-bold normal-case tracking-normal text-gold-700 hover:underline">＋ Add</button>
                    </div>
                    <div class="scrollbar-none max-h-[260px] space-y-1.5 overflow-y-auto">
                        @foreach ($dayCards as $card)
                            @php $d = $card['model']; $on = $day && $day->id === $d->id; @endphp
                            <button type="button" wire:click="selectDay({{ $d->id }})" class="cx-daycell {{ $on ? 'is-on' : '' }}">
                                <span class="cx-dhex">{{ $card['pct'] }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate cx-dname">{{ $d->date?->format('D, j M') ?? $d->label }}</span>
                                    <span class="block cx-dsub">{{ $card['sessions'] }} {{ str('session')->plural($card['sessions']) }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Rooms --}}
                <div class="cx-railsec">
                    <div class="cx-raillbl"><span>Rooms</span></div>
                    <div class="relative mb-2">
                        <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3 w-3 -translate-y-1/2 text-muted" />
                        <input type="search" wire:model.live.debounce.250ms="venueSearch" placeholder="Search rooms…"
                               aria-label="Search rooms"
                               class="h-8 w-full rounded-lg border border-line bg-white py-0 ps-7 pe-2 text-[11.5px] text-ink focus:border-navy-300 focus:outline-none">
                    </div>
                    <div class="scrollbar-none max-h-[220px] space-y-1.5 overflow-y-auto">
                        @forelse ($venues as $v)
                            @php
                                [$dot, $chip, $note] = match ($v['state']) {
                                    'conflict' => ['bg-danger', 'bg-danger-soft text-danger-ink', $v['conflicts'].' '.str('conflict')->plural($v['conflicts'])],
                                    'warning' => ['bg-warning', 'bg-warning-soft text-amber-800', $v['over'].' warning'],
                                    default => ['bg-success', 'bg-success-soft text-emerald-800', 'No issues'],
                                };
                            @endphp
                            <div class="rounded-xl px-1.5 py-1.5 hover:bg-page">
                                <p class="truncate text-[11.5px] font-bold text-ink">{{ $v['room']->name }}</p>
                                <p class="mt-0.5 text-[10px] text-muted">
                                    @if ($v['room']->capacity) Cap. {{ number_format($v['room']->capacity) }} · @endif{{ $v['sessions'] }} {{ str('session')->plural($v['sessions']) }}
                                </p>
                                <span class="mt-1 inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[9px] font-bold {{ $chip }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>{{ $note }}
                                </span>
                            </div>
                        @empty
                            <p class="px-1 py-3 text-center text-[11px] text-muted">
                                {{ trim($venueSearch) === '' ? 'No rooms yet.' : 'No room matches “'.$venueSearch.'”.' }}
                            </p>
                        @endforelse
                    </div>
                    <a href="{{ route('events.hub', [$event, 'tab' => 'venue']) }}"
                       class="mt-1.5 block text-center text-[11px] font-semibold text-muted transition hover:text-gold-700">＋ Add Room</a>
                </div>

                {{-- Tracks — read-only for now; a full Tracks view is staged for later. --}}
                <div class="cx-railsec">
                    <div class="cx-raillbl"><span>Tracks</span></div>
                    <div class="scrollbar-none max-h-[140px] space-y-1 overflow-y-auto">
                        @forelse ($trackSummary as $t)
                            <div class="flex items-center justify-between gap-2 rounded-lg px-1.5 py-1 text-[11.5px]">
                                <span class="min-w-0 truncate text-ink">{{ $t['name'] }}</span>
                                <span class="shrink-0 rounded-full bg-page px-1.5 py-0.5 text-[9.5px] font-bold text-muted">{{ $t['count'] }}</span>
                            </div>
                        @empty
                            <p class="px-1 py-2 text-[11px] text-muted">No sessions on this day yet.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Filters --}}
                <div class="cx-railsec">
                    <div class="cx-raillbl"><span>Filters</span></div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (\App\Models\EventAgendaSession::STATUS_META as $key => [$label, $settled2, $hex])
                            @php $on = in_array($key, $statusFilter, true); @endphp
                            <button type="button" wire:click="toggleStatusFilter('{{ $key }}')"
                                    @class([
                                        'rounded-full border px-2.5 py-1 text-[10.5px] font-bold transition',
                                        'border-transparent text-white' => $on,
                                        'border-line bg-white text-muted hover:border-gold-300' => ! $on,
                                    ])
                                    style="{{ $on ? 'background:'.$hex : '' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    @if ($statusFilter)
                        <p class="mt-1.5 text-[10px] text-muted">Other statuses are dimmed on the board.</p>
                    @endif
                </div>

                {{-- The one action worth a full-width button. --}}
                <button type="button" wire:click="newSession" class="cx-btn cx-btn-accent mt-4 w-full justify-center" style="height:40px">
                    ＋ Add Session
                </button>
            </aside>

