@php
    $h = $health[$event->id];
    $m = $metrics[$event->id];
    $open = $expandedId === $event->id;

    $stageHex = \App\Models\Event::stageColor($event->stage);

    $today = now()->startOfDay();
    $start = $event->starts_at?->copy()->startOfDay();
    $end = ($event->ends_at ?? $event->starts_at)?->copy()->endOfDay();
    $isLive = $start && $end && $start->lte($today) && $end->gte($today);
    $days = $start ? (int) round($today->diffInDays($start, false)) : null;
    $money = fn ($c) => \App\Models\Event::moneyIn((int) $c, $event->currency ?? 'USD');
@endphp

<div wire:key="panel-{{ $event->id }}"
     @class([
         'overflow-hidden rounded-2xl border bg-white transition duration-200',
         'border-gold-400 shadow-[0_18px_44px_-18px_rgba(212,175,55,0.45)]' => $open,
         'border-line shadow-[0_10px_30px_-18px_rgba(15,23,42,0.35)] hover:border-navy-200' => ! $open,
     ])>

    {{-- ═══ HEADER ROW (always visible) ═══ --}}
    <div class="flex items-stretch">
        <span class="w-1.5 shrink-0" style="background: {{ $stageHex }}"></span>

        <button type="button" wire:click="toggleExpand({{ $event->id }})" class="flex min-w-0 flex-1 items-center gap-4 p-4 text-left">
            {{-- crest --}}
            <span class="relative hidden h-14 w-20 shrink-0 overflow-hidden rounded-xl bg-gradient-to-br from-navy-800 to-[var(--color-navy-950)] sm:block">
                @if ($event->cover_path)
                    <x-event-avatar :event="$event" :ring="false" size="md" class="h-full w-full [&>span]:h-full [&>span]:w-full [&>span]:rounded-none [&>span]:!bg-transparent [&>span]:ring-0" />
                @else
                    <x-event-crest :event="$event" class="h-full w-full" />
                @endif
            </span>

            {{-- identity --}}
            <span class="min-w-0 flex-1">
                <span class="flex flex-wrap items-center gap-2">
                    <span class="truncate text-base font-bold text-navy-900">{{ $event->name }}</span>
                    <span class="shrink-0 rounded-md px-2 py-0.5 text-eyebrow font-black uppercase tracking-wider text-white" style="background: {{ $stageHex }}">{{ str($event->stage)->replace('_', ' ')->title() }}</span>
                    @if ($isLive)
                        <span class="flex shrink-0 items-center gap-1 rounded-md bg-gold-400 px-2 py-0.5 text-eyebrow font-black uppercase tracking-wider text-navy-950"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-navy-950"></span>Live</span>
                    @endif
                </span>
                <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-micro text-muted">
                    <span class="truncate">{{ $event->client?->name ?? str($event->type)->replace('_', ' ')->title() }}</span>
                    <span class="flex items-center gap-1"><x-icon name="pin" class="h-3 w-3 text-navy-300" />{{ $event->city }}</span>
                    <span class="flex items-center gap-1"><x-icon name="calendar" class="h-3 w-3 text-navy-300" />{{ $event->starts_at?->format('j M Y') }}</span>
                    @if ($event->venue)<span class="hidden truncate lg:inline">{{ $event->venue->name }}</span>@endif
                </span>
            </span>

            {{-- at-a-glance numbers --}}
            <span class="hidden shrink-0 items-center gap-5 lg:flex">
                @foreach ([
                    ['Tasks', $event->tasks->count()],
                    ['Team', $event->teamMembers->count()],
                    ['Budget', $m['budget_used'] !== null ? $m['budget_used'].'%' : '—'],
                ] as [$l, $v])
                    <span class="text-center">
                        <span class="block text-sm font-black leading-none text-navy-900">{{ $v }}</span>
                        <span class="mt-1 block text-eyebrow font-bold uppercase tracking-wider text-muted">{{ $l }}</span>
                    </span>
                @endforeach
            </span>

            {{-- countdown + health --}}
            <span class="flex shrink-0 items-center gap-3">
                @if ($days !== null)
                    <span class="hidden text-right sm:block">
                        <span class="block text-sm font-black leading-none {{ $isLive ? 'text-gold-600' : 'text-navy-900' }}">{{ $isLive ? 'LIVE' : ($days > 0 ? $days.'d' : 'done') }}</span>
                        <span class="mt-1 block text-eyebrow font-bold uppercase tracking-wider text-muted">{{ $isLive ? 'now' : ($days > 0 ? 'to go' : 'past') }}</span>
                    </span>
                @endif
                <x-health-ring :percent="$h['score']" :group="$h['group']" size="h-11 w-11" textSize="text-[11px]" class="shrink-0" />
                <svg class="h-4 w-4 shrink-0 text-navy-300 transition-transform duration-200 {{ $open ? 'rotate-180' : '' }}" viewBox="0 0 20 20" fill="currentColor"><path d="M5 7l5 6 5-6H5z"/></svg>
            </span>
        </button>

        {{-- actions --}}
        <div class="flex shrink-0 items-center gap-1 pr-3">
            <button type="button" wire:click="toggleFavorite({{ $event->id }})"
                    class="flex h-8 w-8 items-center justify-center rounded-lg transition {{ in_array($event->id, $favoriteIds) ? 'text-gold-700' : 'text-navy-300 hover:text-gold-700' }}"
                    aria-label="Star event"><x-icon name="star" class="h-4 w-4 {{ in_array($event->id, $favoriteIds) ? 'fill-current' : '' }}" /></button>

            <details class="relative" wire:key="menu-{{ $event->id }}">
                <summary class="flex h-8 w-8 cursor-pointer list-none items-center justify-center rounded-lg text-navy-300 transition hover:text-navy-700 [&::-webkit-details-marker]:hidden" aria-label="Actions">
                    <span class="rotate-90"><x-icon name="dots" class="h-4 w-4" /></span>
                </summary>
                <div class="absolute right-0 top-9 z-30 w-40 overflow-hidden rounded-xl border border-line bg-white shadow-lg">
                    <a href="{{ route('events.hub', $event) }}" class="flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-semibold text-navy-700 transition hover:bg-gold-50/60"><x-icon name="home" class="h-3.5 w-3.5 text-navy-500" /> Open hub</a>
                    <a href="{{ route('events.hub', [$event, 'tab' => 'settings']) }}" class="flex items-center gap-2.5 px-3.5 py-2.5 text-xs font-semibold text-navy-700 transition hover:bg-gold-50/60"><x-icon name="cog" class="h-3.5 w-3.5 text-navy-500" /> Edit</a>
                    <button type="button" wire:click="duplicate({{ $event->id }})" class="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left text-xs font-semibold text-navy-700 transition hover:bg-gold-50/60"><x-icon name="archive" class="h-3.5 w-3.5 text-navy-500" /> Duplicate</button>
                    <x-confirm title="Archive “{{ $event->name }}”?" confirm="Archive" tone="warn" run="$wire.archive({{ $event->id }})" class="flex w-full items-center gap-2.5 border-t border-line px-3.5 py-2.5 text-left text-xs font-semibold text-navy-700 transition hover:bg-navy-50"><x-icon name="logout" class="h-3.5 w-3.5 text-navy-500" /> Archive</x-confirm>
                    @can('manage-events')
                        <x-confirm title="Permanently delete “{{ $event->name }}”?"
                                   body="This erases the event and everything in it. It cannot be undone."
                                   confirm="Delete" run="$wire.deleteEvent({{ $event->id }})"
                                   class="flex w-full items-center gap-2.5 border-t border-line px-3.5 py-2.5 text-left text-xs font-semibold text-risk transition hover:bg-risk/5"><x-icon name="dots" class="h-3.5 w-3.5" /> Delete forever</x-confirm>
                    @endcan
                </div>
            </details>
        </div>
    </div>

    {{-- ═══ EXPANDED DETAIL ═══ --}}
    @if ($open && $expanded)
        <div class="border-t border-line bg-page/40 p-5">
            {{-- AI read --}}
            <div class="mb-4 rounded-xl border border-gold-200 bg-gold-50/70 p-3.5">
                <p class="text-eyebrow font-black uppercase tracking-[0.2em] text-gold-700">AI Recommendation</p>
                <p class="pf mt-1 text-sm font-bold text-navy-900">{{ $expanded['ai']['headline'] }}</p>
                @if (! empty($expanded['ai']['attention']))
                    <ul class="mt-1.5 space-y-0.5">
                        @foreach (array_slice($expanded['ai']['attention'], 0, 3) as $line)
                            <li class="flex gap-1.5 text-micro text-navy-600"><span class="text-gold-600">•</span><span>{{ $line }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                {{-- health breakdown --}}
                <div class="rounded-xl border border-line bg-white p-3.5">
                    <p class="text-eyebrow font-black uppercase tracking-[0.18em] text-navy-400">Health breakdown</p>
                    <div class="mt-2.5 space-y-2">
                        @forelse ($expanded['components'] as $label => $score)
                            @if ($score !== null)
                                @php $c = $score >= 70 ? 'var(--color-success)' : ($score >= 45 ? 'var(--color-warning)' : 'var(--color-danger)'); @endphp
                                <div>
                                    <div class="flex items-baseline justify-between text-eyebrow">
                                        <span class="capitalize text-navy-600">{{ str($label)->replace('_', ' ') }}</span>
                                        <span class="font-black" style="color: {{ $c }}">{{ $score }}%</span>
                                    </div>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full" style="width: {{ $score }}%; background: {{ $c }}"></div></div>
                                </div>
                            @endif
                        @empty
                            <p class="text-micro italic text-navy-300">No components scored yet.</p>
                        @endforelse
                    </div>
                </div>

                {{-- delivery phases --}}
                <div class="rounded-xl border border-line bg-white p-3.5">
                    <p class="text-eyebrow font-black uppercase tracking-[0.18em] text-navy-400">Delivery phases</p>
                    <div class="mt-2.5 space-y-1.5">
                        @forelse ($expanded['tracks'] as $t)
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $t['color'] }}"></span>
                                <span class="min-w-0 flex-1 truncate text-micro text-navy-600">{{ \Illuminate\Support\Str::limit($t['name'], 20) }}</span>
                                <span class="h-1.5 w-12 shrink-0 overflow-hidden rounded-full bg-navy-50"><span class="block h-full rounded-full" style="width: {{ $t['pct'] }}%; background: {{ $t['color'] }}"></span></span>
                                <span class="w-9 shrink-0 text-right text-eyebrow font-black text-navy-400">{{ $t['done'] }}/{{ $t['total'] }}</span>
                            </div>
                        @empty
                            <p class="text-micro italic text-navy-300">No plan phases yet.</p>
                        @endforelse
                    </div>
                </div>

                {{-- money + ops --}}
                <div class="rounded-xl border border-line bg-white p-3.5">
                    <p class="text-eyebrow font-black uppercase tracking-[0.18em] text-navy-400">Money &amp; operations</p>
                    @if ($expanded['spentPct'] !== null)
                        @php $bc = $expanded['spentPct'] > 90 ? 'var(--color-danger)' : ($expanded['spentPct'] > 70 ? 'var(--color-warning)' : 'var(--color-success)'); @endphp
                        <div class="mt-2.5">
                            <div class="flex items-baseline justify-between text-eyebrow">
                                <span class="text-navy-600">{{ $money($expanded['spent']) }} of {{ $money($expanded['budget']) }}</span>
                                <span class="font-black" style="color: {{ $bc }}">{{ $expanded['spentPct'] }}%</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full" style="width: {{ $expanded['spentPct'] }}%; background: {{ $bc }}"></div></div>
                        </div>
                    @endif
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        @foreach ([
                            ['Overdue', $expanded['overdue'], 'var(--color-danger)'], ['Unassigned', $expanded['unassigned'], 'var(--color-warning)'],
                            ['Open risks', $expanded['risks'], 'var(--color-danger-on-dark)'], ['Approvals', $expanded['approvals'], \App\Models\Event::moduleColor('approvals')],
                            ['Sessions', $expanded['sessions'], \App\Models\Event::moduleColor('agenda')], ['Suppliers', $expanded['suppliers'], 'var(--color-success)'],
                        ] as [$l, $v, $c])
                            <div class="rounded-lg bg-page/70 px-2 py-1.5">
                                <p class="text-sm font-black leading-none" style="color: {{ $v > 0 ? $c : 'var(--color-neutral)' }}">{{ $v }}</p>
                                <p class="mt-0.5 text-eyebrow font-bold uppercase tracking-wider text-muted">{{ $l }}</p>
                            </div>
                        @endforeach
                    </div>
                    @if ($expanded['outstanding'] > 0)
                        <p class="mt-2 rounded-lg bg-risk/5 px-2 py-1.5 text-eyebrow font-semibold text-risk">{{ $money($expanded['outstanding']) }} outstanding on the contract</p>
                    @endif
                </div>
            </div>

            {{-- next deadlines --}}
            @if ($expanded['deadlines']->isNotEmpty())
                <div class="mt-4 rounded-xl border border-line bg-white p-3.5">
                    <p class="mb-2 text-eyebrow font-black uppercase tracking-[0.18em] text-navy-400">Next deadlines</p>
                    <div class="grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($expanded['deadlines'] as $d)
                            @php $late = $d['due']->copy()->startOfDay()->lt(now()->startOfDay()); @endphp
                            <div class="flex items-center gap-2 rounded-lg bg-page/60 px-2.5 py-1.5">
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $d['hex'] }}"></span>
                                <span class="min-w-0 flex-1 truncate text-micro font-semibold text-navy-700">{{ $d['title'] }}</span>
                                <span class="shrink-0 text-eyebrow font-bold {{ $late ? 'text-risk' : 'text-navy-400' }}">{{ $d['due']->format('j M') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- control room --}}
            <div class="mt-4">
                <p class="mb-2 text-eyebrow font-black uppercase tracking-[0.18em] text-navy-400">Event Control Room</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ([['overview', 'Overview', 'home'], ['planning', 'Plan', 'list'], ['tasks', 'Tasks', 'clipboard'], ['budget', 'Budget', 'currency'], ['agenda', 'Agenda', 'calendar'], ['suppliers', 'Suppliers', 'truck'], ['risks', 'Risks', 'bell'], ['approvals', 'Approvals', 'identification']] as [$tab, $label, $icon])
                        <a href="{{ route('events.hub', [$event, 'tab' => $tab]) }}" class="flex items-center gap-1.5 rounded-lg border border-line bg-white px-2.5 py-1.5 text-micro font-bold text-navy-600 transition hover:border-gold-300 hover:text-gold-700">
                            <x-icon :name="$icon" class="h-3.5 w-3.5 text-navy-400" />{{ $label }}
                        </a>
                    @endforeach
                    <a href="{{ route('events.hub', $event) }}" class="btn-gold ml-auto h-8 !rounded-lg px-4 text-micro">Open hub →</a>
                </div>
            </div>
        </div>
    @endif
</div>
