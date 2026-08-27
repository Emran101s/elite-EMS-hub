            {{-- ═══ LEFT RAIL ═══ --}}
            <aside class="rounded-lg border border-line bg-white flex flex-col gap-4 self-start !p-3.5">

                {{-- Days --}}
                <div>
                    <div class="mb-2 flex items-center justify-between px-0.5">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Days</p>
                        <button type="button" wire:click="addDay" class="text-[11px] font-bold text-gold-700 hover:underline">＋ Add</button>
                    </div>
                    <div class="scrollbar-none max-h-[260px] space-y-1.5 overflow-y-auto">
                        @foreach ($dayCards as $card)
                            @php $d = $card['model']; $on = $day && $day->id === $d->id; $r = 2 * M_PI * 11; @endphp
                            <button type="button" wire:click="selectDay({{ $d->id }})"
                                    @class([
                                        'flex w-full items-center gap-2.5 rounded-xl border px-2.5 py-2 text-left transition',
                                        'border-navy-900 bg-navy-900 text-white' => $on,
                                        'border-line bg-white text-ink hover:border-gold-300' => ! $on,
                                    ])>
                                <span class="relative grid h-7 w-7 shrink-0 place-items-center">
                                    <svg class="h-7 w-7 -rotate-90" viewBox="0 0 26 26" aria-hidden="true">
                                        <circle cx="13" cy="13" r="11" fill="none" stroke="{{ $on ? 'rgba(255,255,255,.16)' : 'var(--color-page)' }}" stroke-width="2.5" />
                                        <circle cx="13" cy="13" r="11" fill="none" stroke="var(--color-gold-500)" stroke-width="2.5" stroke-linecap="round"
                                                stroke-dasharray="{{ $r }}" stroke-dashoffset="{{ $r - ($r * $card['pct'] / 100) }}" />
                                    </svg>
                                    <span class="absolute text-[7.5px] font-black">{{ $card['pct'] }}</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[12px] font-bold">{{ $d->date?->format('D, j M') ?? $d->label }}</span>
                                    <span class="block text-[10px] {{ $on ? 'text-white/55' : 'text-muted' }}">{{ $card['sessions'] }} {{ str('session')->plural($card['sessions']) }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Rooms --}}
                <div class="border-t border-line pt-3.5">
                    <p class="mb-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Rooms</p>
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
                <div class="border-t border-line pt-3.5">
                    <p class="mb-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Tracks</p>
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
                <div class="border-t border-line pt-3.5">
                    <p class="mb-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Filters</p>
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
                <button type="button" wire:click="newSession"
                        class="mt-auto flex h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-400 to-gold-600 text-[12.5px] font-black text-navy-900 shadow-[0_10px_22px_-14px_rgba(212,175,55,0.7)] transition hover:brightness-105">
                    ＋ Add Session
                </button>
            </aside>

