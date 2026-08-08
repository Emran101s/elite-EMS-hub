@php
    // On the platform's light canvas, matching every other screen. The "we are
    // live" signal comes from the navy header strip and the pulsing pill — not
    // from a dark page, which reads as a different product.
    $tap = 'flex-1 rounded-xl px-3 py-3 text-sm font-bold transition active:scale-[0.98]';
    $moduleHex = \App\Models\Event::moduleColor('transportation');
@endphp

<div wire:poll.30s>

    {{-- ══ live header strip: the one navy band, carrying the identity.
         Uses the approved .strip-dark tone — near-black was rejected before. ══ --}}
    <div class="strip-dark -mx-4 -mt-4 mb-4 !rounded-none px-4 py-4 text-white sm:-mx-6 sm:-mt-6 sm:px-6">
        <div class="mb-3 h-0.5 w-12 rounded-full" style="background: {{ $moduleHex }}" aria-hidden="true"></div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('events.hub', ['event' => $event, 'tab' => 'transportation']) }}"
               class="text-xs font-semibold text-white/50 hover:text-white">← Transportation</a>
            <span class="ml-auto flex items-center gap-2 rounded-full bg-emerald-500/20 px-3 py-1">
                <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-400"></span>
                <span class="text-eyebrow font-bold uppercase tracking-[0.14em] text-emerald-300">Live</span>
            </span>
            <span class="pf text-lg font-black tabular-nums">{{ now()->format('H:i') }}</span>
        </div>
        <h1 class="mt-2 flex items-center gap-2.5 text-2xl font-black leading-tight">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg text-white" style="background: {{ $moduleHex }}">
                <x-icon name="truck" class="h-4 w-4" />
            </span>
            Transport · Live
        </h1>
        <p class="text-xs text-white/55">Event-day operations · {{ $event->name }}</p>
    </div>

    {{-- ══ the day ══ --}}
    @if ($days->count() > 1)
        <div class="mb-3 flex flex-wrap gap-1.5">
            @foreach ($days as $d)
                <button type="button" wire:click="setDay('{{ $d }}')"
                        @class([
                            'rounded-full px-3 py-1.5 text-xs font-bold transition',
                            'bg-navy-900 text-white' => $day === $d,
                            'bg-navy-50 text-navy-600 hover:bg-navy-100' => $day !== $d,
                        ])>
                    {{ \Carbon\Carbon::parse($d)->format('D j M') }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- ══ what needs attention ══ --}}
    @if ($unstaffed || $noShows)
        <div class="mb-4 flex flex-wrap gap-2">
            @if ($unstaffed)
                <span class="rounded-xl bg-amber-100 px-3 py-2 text-xs font-bold text-amber-800">
                    ⚠ {{ $unstaffed }} {{ \Illuminate\Support\Str::plural('run', $unstaffed) }} with no driver
                </span>
            @endif
            @if ($noShows)
                <span class="rounded-xl bg-navy-50 px-3 py-2 text-xs font-bold text-navy-600">
                    {{ $noShows }} no-{{ \Illuminate\Support\Str::plural('show', $noShows) }}
                </span>
            @endif
        </div>
    @endif

    @if ($flash)
        <x-alert tone="ok" class="mb-4">{{ $flash }}</x-alert>
    @endif

    {{-- ══ the board ══ --}}
    @php
        $sections = [
            ['issues', 'Needs attention', 'text-red-600', 'border-red-200 bg-red-50/50'],
            ['now', 'Now', 'text-sky-600', 'border-sky-200 bg-sky-50/40'],
            ['next', 'Next · within 2 hours', 'text-gold-700', 'border-line'],
            ['later', 'Later today', 'text-muted', 'border-line'],
            ['done', 'Done', 'text-navy-300', 'border-line'],
        ];
    @endphp

    @foreach ($sections as [$key, $label, $tone, $cardClass])
        @continue ($board[$key]->isEmpty())

        <p class="mb-2 mt-6 text-eyebrow font-black uppercase tracking-[0.2em] {{ $tone }}">
            {{ $label }} <span class="opacity-50">{{ $board[$key]->count() }}</span>
        </p>

        <div class="space-y-3">
            @foreach ($board[$key] as $m)
                @php
                    $open = $openId === $m->id;
                    $settled = $m->isSettled();
                    $late = $m->delayed_to !== null;
                @endphp

                <div wire:key="live-{{ $m->id }}"
                     @class(['card !p-4', $cardClass, 'opacity-60' => $settled])>

                    {{-- line one: car, time, route --}}
                    <div class="flex items-start gap-3">
                        <span @class([
                            'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-base font-black',
                            'bg-gold-500 text-navy-950' => $m->isPriority(),
                            'bg-navy-900 text-white' => ! $m->isPriority(),
                        ])>{{ $m->ref_no }}</span>

                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-baseline gap-2">
                                <span class="pf text-2xl font-black leading-none tabular-nums text-navy-900">
                                    {{ $m->effectiveDeparture()?->format('H:i') ?? 'TBC' }}
                                </span>
                                @if ($late)
                                    <span class="text-xs font-bold text-amber-700 line-through">{{ $m->depart_at?->format('H:i') }}</span>
                                @endif
                                @if ($m->isPriority())
                                    <span class="rounded bg-gold-100 px-1.5 py-0.5 text-eyebrow font-black uppercase text-gold-800">★ Priority</span>
                                @endif
                            </p>
                            <p class="mt-1 truncate text-sm font-semibold text-navy-900">
                                {{ $m->pickup_from ?: '—' }} → {{ $m->drop_to ?: '—' }}
                            </p>
                            <p class="mt-0.5 text-eyebrow text-muted">
                                {{ $m->legLabel() }}
                                @if ($m->flight_no) · {{ $m->flight_no }}@endif
                                · {{ $m->paxCount() }} {{ \Illuminate\Support\Str::plural('passenger', $m->paxCount()) }}
                                @if ($m->vehicle) · {{ $m->vehicle->label() }}@endif
                            </p>
                        </div>
                    </div>

                    @if ($m->issue_note)
                        <p class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm font-semibold text-red-800">{{ $m->issue_note }}</p>
                    @endif

                    {{-- the driver, and how to reach them --}}
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($m->driver)
                            <span class="text-sm font-bold text-navy-900">{{ $m->driver->name }}</span>
                            @if ($m->contactNumber())
                                <a href="tel:{{ $m->contactNumber() }}"
                                   class="rounded-lg bg-navy-50 px-3 py-2 text-xs font-bold text-navy-700 hover:bg-navy-100">📞 Call</a>
                            @endif
                            @if ($wa = \App\Support\WhatsApp::toDriver($m))
                                <a href="{{ $wa }}" target="_blank" rel="noopener"
                                   class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-100">WhatsApp</a>
                            @endif
                        @else
                            <span class="rounded-lg bg-amber-100 px-3 py-2 text-xs font-bold text-amber-800">No driver assigned</span>
                        @endif

                        @if ($m->manifest->isNotEmpty())
                            <button type="button" wire:click="toggleOpen({{ $m->id }})"
                                    class="ml-auto rounded-lg bg-navy-50 px-3 py-2 text-xs font-bold text-navy-700 hover:bg-navy-100">
                                {{ $open ? 'Hide' : 'Passengers' }} ({{ $m->manifest->count() }})
                            </button>
                        @endif
                    </div>

                    {{-- passengers, with the no-show tap --}}
                    @if ($open)
                        <div class="mt-3 space-y-1.5 border-t border-line pt-3">
                            @foreach ($m->manifest as $p)
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="toggleNoShow({{ $p->id }})"
                                            title="{{ $p->isNoShow() ? 'Un-mark' : 'Mark as a no-show' }}"
                                            @class([
                                                'h-7 w-7 shrink-0 rounded-lg text-xs font-black transition',
                                                'bg-red-100 text-red-700' => $p->isNoShow(),
                                                'bg-navy-50 text-navy-400 hover:bg-navy-100' => ! $p->isNoShow(),
                                            ])>{{ $p->isNoShow() ? '✕' : '○' }}</button>

                                    <span @class(['min-w-0 flex-1 truncate text-sm text-navy-900', 'line-through opacity-50' => $p->isNoShow()])>
                                        {{ $p->name }}
                                        @if ($p->isPriority())
                                            <span class="ml-1 rounded bg-gold-100 px-1 text-eyebrow font-black uppercase text-gold-800">{{ $p->categoryLabel() }}</span>
                                        @endif
                                    </span>

                                    @if ($p->phone)
                                        <a href="tel:{{ $p->phone }}" class="shrink-0 rounded-lg bg-navy-50 px-2.5 py-1.5 text-eyebrow font-bold text-navy-700">📞</a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- ══ the two taps ══ --}}
                    @if (! $settled)
                        <div class="mt-3 flex gap-2">
                            @if ($m->status === 'in_progress')
                                <button type="button" wire:click="arrive({{ $m->id }})"
                                        class="{{ $tap }} bg-emerald-600 text-white hover:bg-emerald-700">✓ Arrived</button>
                            @elseif ($m->status === 'issue')
                                <button type="button" wire:click="resolveIssue({{ $m->id }})"
                                        class="{{ $tap }} bg-navy-900 text-white hover:bg-navy-800">Resolved</button>
                            @else
                                <button type="button" wire:click="start({{ $m->id }})"
                                        class="{{ $tap }} bg-sky-600 text-white hover:bg-sky-700">▸ On the way</button>
                            @endif

                            @if ($m->status !== 'issue')
                                <button type="button" wire:click="openIssue({{ $m->id }})"
                                        class="{{ $tap }} max-w-[7rem] bg-red-50 text-red-700 hover:bg-red-100">⚠ Issue</button>
                            @endif
                        </div>

                        {{-- relative delays: nobody types a timestamp at a barrier --}}
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <span class="text-eyebrow font-bold uppercase tracking-wide text-muted">Delay</span>
                            @foreach ([15, 30, 60] as $mins)
                                <button type="button" wire:click="delay({{ $m->id }}, {{ $mins }})"
                                        class="rounded-lg bg-navy-50 px-2.5 py-1.5 text-eyebrow font-bold text-navy-600 hover:bg-navy-100">
                                    +{{ $mins }}m
                                </button>
                            @endforeach
                            @if ($late)
                                <button type="button" wire:click="clearDelay({{ $m->id }})"
                                        class="rounded-lg px-2 py-1.5 text-eyebrow font-bold text-navy-400 hover:text-navy-900">clear</button>
                            @endif
                        </div>
                    @else
                        <button type="button" wire:click="undo({{ $m->id }})"
                                class="mt-3 text-eyebrow font-bold uppercase tracking-wide text-navy-400 hover:text-navy-900">↺ Undo</button>
                    @endif

                    {{-- issue note box --}}
                    @if ($issueFor === $m->id)
                        <div class="mt-3 border-t border-line pt-3">
                            <textarea wire:model="issueNote" rows="2" autofocus
                                      placeholder="What happened? e.g. vehicle broke down at the airport"
                                      class="input w-full text-sm"></textarea>
                            @error('issueNote')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                            <div class="mt-2 flex gap-2">
                                <button type="button" wire:click="flagIssue({{ $m->id }})"
                                        class="{{ $tap }} bg-red-600 text-white hover:bg-red-700">Flag it</button>
                                <button type="button" wire:click="openIssue({{ $m->id }})"
                                        class="{{ $tap }} max-w-[6rem] bg-navy-50 text-navy-700 hover:bg-navy-100">Cancel</button>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach

    @if (collect($board)->every(fn ($b) => $b->isEmpty()))
        <x-empty icon="truck" title="Nothing moving on this day"
                 hint="Movements scheduled for {{ \Carbon\Carbon::parse($day)->format('D j M') }} appear here once they have a departure time. Plan them on the List, then come back when the day starts.">
            <x-slot:actions>
                <a href="{{ route('events.hub', ['event' => $event, 'tab' => 'transportation']) }}" class="btn-gold btn-sm">← Open the List</a>
                <a href="{{ route('events.transport.dispatch', $event) }}" class="btn-ghost btn-sm">Check Dispatch</a>
            </x-slot:actions>
        </x-empty>
    @endif

    <p class="mt-10 text-center text-eyebrow text-navy-300">
        Refreshes every 30 seconds · {{ now()->format('D j M · H:i') }}
    </p>
</div>
