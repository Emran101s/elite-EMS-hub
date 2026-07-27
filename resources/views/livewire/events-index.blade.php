@php
    // KPI tone → soft/ink token pair, so the strip stays light with tinted chips.
    $tone = [
        'blue'  => ['var(--color-info-soft)', 'var(--color-info-ink)'],
        'green' => ['var(--color-success-soft)', 'var(--color-success-ink)'],
        'gold'  => ['var(--color-gold-100)', 'var(--color-gold-700)'],
        'red'   => ['var(--color-danger-soft)', 'var(--color-danger-ink)'],
    ];
    $typeTabs = ['all' => 'All', 'conference' => 'Conferences', 'workshop' => 'Workshops',
        'exhibition' => 'Exhibitions', 'gala' => 'Galas', 'vip' => 'VIP', 'outdoor' => 'Outdoor'];
@endphp

<div class="space-y-5">

    {{-- ══════════ Command strip — light, tinted KPI tiles ══════════ --}}
    <div class="card p-1.5">
        <div class="grid grid-cols-2 gap-1 sm:grid-cols-3 xl:grid-cols-6">
            @foreach ($kpis as $k)
                @php [$soft, $ink] = $tone[$k['tone']] ?? $tone['blue']; @endphp
                <div class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition hover:bg-page/60">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" style="background: {{ $soft }}; color: {{ $ink }}">
                        <x-icon :name="$k['icon']" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-h2 font-black leading-none text-navy-900">{{ $k['value'] }}</p>
                        <p class="mt-0.5 truncate text-eyebrow font-bold uppercase tracking-wider text-muted">{{ $k['label'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ══════════ Toolbar ══════════ --}}
    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        {{-- type filter --}}
        <div class="scrollbar-none -mx-1 flex min-w-0 items-center gap-1 overflow-x-auto px-1 pb-1">
            @foreach ($typeTabs as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')"
                        @class([
                            'shrink-0 rounded-full px-3.5 py-1.5 text-xs font-bold transition',
                            'bg-navy-900 text-white shadow-raise' => $tab === $key && ! $starred,
                            'text-navy-500 hover:bg-navy-50 hover:text-navy-900' => ! ($tab === $key && ! $starred),
                        ])>{{ $label }}</button>
            @endforeach
            <span class="mx-1 h-5 w-px shrink-0 bg-line"></span>
            <button type="button" wire:click="toggleStarred"
                    @class([
                        'flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition',
                        'bg-gold-400 text-navy-950' => $starred,
                        'text-navy-500 hover:bg-gold-50 hover:text-gold-700' => ! $starred,
                    ])>
                <x-icon name="star" class="h-3.5 w-3.5 {{ $starred ? 'fill-current' : '' }}" /> Starred
            </button>
        </div>

        {{-- search · sort · view · create --}}
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Search events, clients, venues…"
                       class="input h-9 w-44 !rounded-xl !py-0 !ps-9 text-xs sm:w-56">
            </div>
            <select wire:model.live="sort" class="input h-9 w-auto !rounded-xl !py-0 text-xs">
                <option value="date">Sort: Date</option>
                <option value="health">Sort: Health</option>
                <option value="budget">Sort: Budget used</option>
            </select>

            {{-- view toggle: Cards | List | Calendar --}}
            <div class="flex h-9 shrink-0 items-center gap-0.5 rounded-xl border border-line bg-white p-0.5">
                @foreach (['cards' => 'grid', 'list' => 'list', 'calendar' => 'calendar'] as $mode => $icon)
                    <button type="button" wire:click="$set('view', '{{ $mode }}')"
                            @class([
                                'flex h-full items-center gap-1.5 rounded-lg px-3 text-xs font-bold capitalize transition',
                                'bg-navy-900 text-white' => $view === $mode,
                                'text-navy-500 hover:text-navy-900' => $view !== $mode,
                            ])>
                        <x-icon :name="$icon" class="h-3.5 w-3.5" />{{ $mode }}
                    </button>
                @endforeach
            </div>

            <a href="{{ route('events.create') }}" class="btn-gold btn-sm shrink-0">＋ Create Event</a>
        </div>
    </div>

    {{-- ══════════ LIST VIEW ══════════ --}}
    @if ($view === 'list')
        @php $pageIds = $events->pluck('id')->all(); $allOnPage = $pageIds && ! array_diff($pageIds, $selectedIds); @endphp

        {{-- bulk bar — only once something is ticked --}}
        @if (count($selectedIds))
            <div class="mb-3 flex flex-wrap items-center gap-3 rounded-2xl border border-gold-300 bg-gold-50/60 px-4 py-2.5">
                <span class="text-xs font-bold text-navy-900">{{ count($selectedIds) }} selected</span>
                @if ($events->total() > count($selectedIds))
                    <button type="button" wire:click="selectAllMatching"
                            class="text-eyebrow font-bold uppercase tracking-wide text-navy-600 hover:text-navy-900">Select all {{ $events->total() }} matching</button>
                @endif
                <button type="button" wire:click="clearSelection"
                        class="text-eyebrow font-bold uppercase tracking-wide text-navy-500 hover:text-navy-900">Clear</button>
                <button type="button" wire:click="deleteSelected"
                        wire:confirm="Permanently delete {{ count($selectedIds) }} {{ \Illuminate\Support\Str::plural('event', count($selectedIds)) }}? Everything inside them goes too. This cannot be undone."
                        class="ml-auto rounded-lg bg-risk px-3.5 py-1.5 text-xs font-bold text-white transition hover:brightness-110">
                    Delete selected ({{ count($selectedIds) }})
                </button>
            </div>
        @endif

        <div class="card overflow-x-auto">
            <table class="w-full min-w-[940px]">
                <thead>
                    <tr class="border-b border-line text-left text-eyebrow font-bold uppercase tracking-wide text-muted">
                        <th class="w-10 px-4 py-3">
                            <input type="checkbox" @checked($allOnPage)
                                   wire:click="toggleSelectPage({{ json_encode($pageIds) }})"
                                   title="Select everything on this page"
                                   class="h-4 w-4 cursor-pointer rounded border-line text-gold-500 focus:ring-gold-400">
                        </th>
                        <th class="px-3 py-3">Event</th>
                        <th class="px-3 py-3">Client</th>
                        <th class="px-3 py-3">Dates</th>
                        <th class="px-3 py-3">Stage</th>
                        <th class="px-3 py-3">Health</th>
                        <th class="px-3 py-3 text-right">Pax</th>
                        <th class="w-24 px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        @php
                            $h = $health[$event->id] ?? null;
                            $m = $metrics[$event->id] ?? [];
                            $ticked = in_array($event->id, $selectedIds, true);
                        @endphp
                        <tr wire:key="row-{{ $event->id }}"
                            @class(['group border-b border-line last:border-0 transition hover:bg-page/50', 'bg-gold-50/40' => $ticked])>
                            <td class="px-4 py-3">
                                <input type="checkbox" @checked($ticked) wire:click="toggleSelect({{ $event->id }})"
                                       class="h-4 w-4 cursor-pointer rounded border-line text-gold-500 focus:ring-gold-400">
                            </td>
                            <td class="px-3 py-3">
                                <a href="{{ route('events.hub', $event) }}" class="block">
                                    <span class="block truncate text-sm font-bold text-navy-900 group-hover:text-gold-700">{{ $event->name }}</span>
                                    <span class="mt-0.5 block text-eyebrow text-muted">
                                        {{ str($event->type)->replace('_', ' ')->title() }} · {{ $event->city }}{{ $event->country ? ', '.$event->country : '' }}
                                    </span>
                                </a>
                            </td>
                            <td class="px-3 py-3 text-xs text-navy-700">{{ $event->client?->name ?? '—' }}</td>
                            <td class="px-3 py-3 text-xs text-navy-700 whitespace-nowrap">
                                {{ $event->starts_at?->format('d M Y') ?? '—' }}
                                @if ($event->ends_at && $event->starts_at && ! $event->ends_at->isSameDay($event->starts_at))
                                    <span class="text-muted">– {{ $event->ends_at->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-block rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide text-white"
                                      style="background: {{ \App\Models\Event::stageColor($event->stage) }}">
                                    {{ str($event->stage)->replace('_', ' ')->title() }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                @if ($h)
                                    <span class="flex items-center gap-1.5 text-xs font-bold text-navy-900">
                                        <x-health-ring :percent="$h['score']" :group="$h['group']" size="h-6 w-6" />
                                        {{ $h['score'] !== null ? $h['score'].'%' : 'Not started' }}
                                    </span>
                                @else
                                    <span class="text-xs text-muted">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right text-xs font-semibold text-navy-900">{{ $m['participants'] ?? '—' }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-end gap-1 opacity-0 transition group-hover:opacity-100">
                                    <a href="{{ route('events.hub', $event) }}"
                                       class="rounded-lg bg-navy-50 px-2 py-1 text-eyebrow font-bold text-navy-700 hover:bg-navy-100">Open</a>
                                    <button type="button" wire:click="deleteEvent({{ $event->id }})"
                                            wire:confirm="Permanently delete “{{ $event->name }}”? Everything inside it goes too. This cannot be undone."
                                            class="rounded-lg bg-risk/10 px-2 py-1 text-eyebrow font-bold text-red-700 hover:bg-risk/20">✕</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-16 text-center text-sm text-muted">No events match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($events->hasPages())
            <div class="mt-4">{{ $events->links() }}</div>
        @endif
    @endif

    {{-- ══════════ CARDS VIEW ══════════ --}}
    @if ($view === 'cards')

        {{-- Next Up — cinematic hero for whatever is live or nearest --}}
        @if ($nextUp && $events->currentPage() === 1)
            @php
                $nuStage = \App\Models\Event::stageColor($nextUp->stage);
                $nuStart = $nextUp->starts_at?->copy()->startOfDay();
                $nuDays = $nuStart ? (int) round(now()->startOfDay()->diffInDays($nuStart, false)) : null;
            @endphp
            <div class="strip-dark p-0.5">
                <div class="relative flex flex-col gap-5 overflow-hidden rounded-[1.25rem] p-6 md:flex-row md:items-center">
                    {{-- ambient glow in the stage colour --}}
                    <div class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full opacity-30 blur-3xl" style="background: {{ $nuStage }}"></div>

                    {{-- crest --}}
                    <a href="{{ route('events.hub', $nextUp) }}" class="relative z-10 h-24 w-32 shrink-0 overflow-hidden rounded-xl ring-1 ring-white/15">
                        @if ($nextUp->cover_path)
                            <x-event-avatar :event="$nextUp" :ring="false" size="lg" class="h-full w-full [&>span]:h-full [&>span]:w-full [&>span]:rounded-none [&>span]:!bg-transparent [&>span]:ring-0" />
                        @else
                            <x-event-crest :event="$nextUp" class="h-full w-full" />
                        @endif
                    </a>

                    {{-- identity --}}
                    <div class="relative z-10 min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="eyebrow-gold">{{ $nextUpLive ? 'Happening now' : 'Next up' }}</span>
                            @if ($nextUpLive)<span class="flex items-center gap-1 rounded-md bg-gold-400 px-1.5 py-0.5 text-eyebrow font-black uppercase tracking-wider text-navy-950"><span class="h-1.5 w-1.5 animate-pulse rounded-full bg-navy-950"></span>Live</span>@endif
                        </div>
                        <a href="{{ route('events.hub', $nextUp) }}" class="pf mt-1 block truncate text-2xl font-black text-white hover:text-gold-200">{{ $nextUp->name }}</a>
                        <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-micro text-white/55">
                            <span>{{ $nextUp->client?->name ?? str($nextUp->type)->replace('_', ' ')->title() }}</span>
                            @if ($nextUp->city)<span class="flex items-center gap-1"><x-icon name="pin" class="h-3 w-3" />{{ $nextUp->city }}</span>@endif
                            @if ($nextUp->starts_at)<span class="flex items-center gap-1"><x-icon name="calendar" class="h-3 w-3" />{{ $nextUp->starts_at->format('j M Y') }}</span>@endif
                            @if ($nextUp->venue)<span class="truncate">{{ $nextUp->venue->name }}</span>@endif
                        </p>
                    </div>

                    {{-- stats --}}
                    <div class="relative z-10 flex flex-wrap items-center gap-x-6 gap-y-3">
                        @foreach ([
                            ['Guests', $nextUpMetrics['participants'] ? number_format($nextUpMetrics['participants']) : '—'],
                            ['Sponsors', $nextUpMetrics['sponsors'] ?: '—'],
                            ['Budget', $nextUpMetrics['budget_used'] !== null ? $nextUpMetrics['budget_used'].'%' : '—'],
                        ] as [$l, $v])
                            <div class="text-center">
                                <p class="pf text-2xl font-black leading-none text-white">{{ $v }}</p>
                                <p class="mt-1 text-eyebrow font-bold uppercase tracking-wider text-white/45">{{ $l }}</p>
                            </div>
                        @endforeach
                        <div class="hidden h-12 w-px bg-white/10 sm:block"></div>
                        <div class="hidden items-center gap-3 sm:flex">
                            @if ($nuDays !== null)
                                <div class="text-center">
                                    <p class="pf text-2xl font-black leading-none {{ $nextUpLive ? 'text-gold-300' : 'text-white' }}">{{ $nextUpLive ? 'LIVE' : ($nuDays > 0 ? $nuDays : abs($nuDays)) }}</p>
                                    <p class="mt-1 text-eyebrow font-bold uppercase tracking-wider text-white/45">{{ $nextUpLive ? 'now' : ($nuDays > 0 ? 'days to go' : 'days ago') }}</p>
                                </div>
                            @endif
                            <x-health-ring :percent="$nextUpHealth['score']" :group="$nextUpHealth['group']" size="h-14 w-14" :dark="true" />
                        </div>
                        <a href="{{ route('events.hub', $nextUp) }}" class="btn-gold btn-sm shrink-0">Open hub →</a>
                    </div>
                </div>
            </div>
        @endif

        {{-- the deck --}}
        <div>
            <div class="mb-3 flex items-baseline gap-2">
                <h2 class="pf text-h1 font-bold text-navy-900">Portfolio</h2>
                <span class="text-xs text-muted">{{ $events->total() }} {{ str('event')->plural($events->total()) }} · click a card for full detail</span>
            </div>

            @if ($events->isEmpty())
                <x-empty icon="calendar" title="No events match these filters"
                         hint="Clear the search or filters, or create a new event to get started.">
                    <x-slot:actions>
                        <a href="{{ route('events.create') }}" class="btn-gold btn-sm">＋ Create Event</a>
                    </x-slot:actions>
                </x-empty>
            @else
                <div class="grid items-start gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    @foreach ($events as $event)
                        @include('livewire.partials.events.card', ['event' => $event])
                    @endforeach
                </div>
            @endif

            @if ($events->hasPages())
                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 text-xs text-muted">
                    <span>Showing {{ $events->firstItem() ?? 0 }}–{{ $events->lastItem() ?? 0 }} of {{ $events->total() }}</span>
                    <span class="flex items-center gap-1.5">
                        <button type="button" wire:click="previousPage" @disabled($events->onFirstPage()) class="rounded-lg border border-line bg-white px-2.5 py-1.5 font-semibold text-navy-600 transition enabled:hover:border-gold-300 disabled:opacity-40">‹</button>
                        @foreach (range(1, $events->lastPage()) as $page)
                            <button type="button" wire:click="gotoPage({{ $page }})"
                                    @class(['rounded-lg px-3 py-1.5 font-semibold transition', 'bg-gold-50 text-gold-700 ring-1 ring-gold-300' => $events->currentPage() === $page, 'border border-line bg-white text-navy-600 hover:border-gold-300' => $events->currentPage() !== $page])>{{ $page }}</button>
                        @endforeach
                        <button type="button" wire:click="nextPage" @disabled(! $events->hasMorePages()) class="rounded-lg border border-line bg-white px-2.5 py-1.5 font-semibold text-navy-600 transition enabled:hover:border-gold-300 disabled:opacity-40">›</button>
                    </span>
                </div>
            @endif
        </div>
    @endif

    {{-- ══════════ CALENDAR VIEW ══════════ --}}
    @if ($view === 'calendar' && $calendar)
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                <h2 class="pf text-h1 font-bold text-navy-900">{{ $calendar['label'] }}</h2>
                <div class="flex items-center gap-1">
                    <button type="button" wire:click="prevMonth" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-navy-500 transition hover:border-gold-300 hover:text-navy-900">‹</button>
                    <button type="button" wire:click="nextMonth" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-navy-500 transition hover:border-gold-300 hover:text-navy-900">›</button>
                </div>
            </div>

            {{-- weekday header --}}
            <div class="grid grid-cols-7 border-b border-line bg-page/40">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d)
                    <div class="px-3 py-2 text-eyebrow font-bold uppercase tracking-wider text-muted">{{ $d }}</div>
                @endforeach
            </div>

            {{-- weeks --}}
            <div class="grid grid-cols-7">
                @foreach ($calendar['weeks'] as $week)
                    @foreach ($week as $day)
                        @php $isToday = $day['date']->isToday(); @endphp
                        <div @class([
                            'min-h-[104px] border-b border-r border-line p-1.5 last:border-r-0',
                            'bg-white' => $day['inMonth'],
                            'bg-page/40' => ! $day['inMonth'],
                        ])>
                            <div class="mb-1 flex justify-end">
                                <span @class([
                                    'flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold',
                                    'bg-gold-400 text-navy-950' => $isToday,
                                    'text-navy-700' => ! $isToday && $day['inMonth'],
                                    'text-navy-300' => ! $day['inMonth'],
                                ])>{{ $day['date']->day }}</span>
                            </div>
                            <div class="space-y-1">
                                @foreach ($day['events']->take(3) as $ev)
                                    @php $evHex = \App\Models\Event::stageColor($ev->stage); @endphp
                                    <a href="{{ route('events.hub', $ev) }}"
                                       class="flex items-center gap-1.5 truncate rounded-md px-1.5 py-1 text-eyebrow font-bold text-navy-700 transition hover:bg-page"
                                       style="background: color-mix(in srgb, {{ $evHex }} 12%, transparent)"
                                       title="{{ $ev->name }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background: {{ $evHex }}"></span>
                                        <span class="truncate">{{ $ev->name }}</span>
                                    </a>
                                @endforeach
                                @if ($day['events']->count() > 3)
                                    <p class="px-1.5 text-eyebrow font-bold text-muted">+{{ $day['events']->count() - 3 }} more</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif
</div>
