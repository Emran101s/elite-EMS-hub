<div>
    {{-- Create Event (top right, above KPIs) --}}
    <div class="mb-5 flex justify-end">
        <a href="{{ route('events.create') }}" class="btn-navy !rounded-2xl px-5 text-sm !text-white">
            <span class="text-gold-400">+</span> Create Event
        </a>
    </div>

    {{-- KPI row: 155×90 cards --}}
    <div class="mb-5 flex flex-wrap gap-3">
        @foreach ($kpis as $kpi)
            @php
                $toneClass = match ($kpi['tone']) {
                    'blue' => 'bg-[#3B82F6]/10 text-[#3B82F6]',
                    'green' => 'bg-track/10 text-emerald-600',
                    'gold' => 'bg-gold-50 text-gold-600',
                    'red' => 'bg-risk/10 text-risk',
                    default => 'bg-navy-50 text-navy-600',
                };
            @endphp
            <div class="flex h-[90px] w-[155px] flex-col justify-between rounded-[18px] border border-line bg-white px-3 py-2.5 shadow-[0_10px_30px_rgba(15,23,42,0.05)]">
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $toneClass }}"><x-icon :name="$kpi['icon']" class="h-4.5 w-4.5" /></span>
                    <p class="text-[11px] font-semibold leading-tight text-muted">{{ $kpi['label'] }}</p>
                </div>
                <p class="text-[22px] font-bold leading-none text-navy-900">{{ $kpi['value'] }}</p>
                <p class="truncate text-[10px] font-semibold {{ $kpi['up'] ? 'text-emerald-600' : 'text-risk' }}">{{ $kpi['trend'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ══ Toolbar: filters + view switcher + search (always visible) ══ --}}
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <div class="flex flex-wrap items-center gap-1">
            @foreach (['all' => 'All Events', 'conference' => 'Conference', 'workshop' => 'Workshop', 'exhibition' => 'Exhibition', 'gala' => 'Gala Dinner', 'vip' => 'VIP', 'outdoor' => 'Outdoor'] as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')"
                        @class([
                            'h-9 rounded-xl px-[18px] text-[13px] font-semibold transition',
                            'bg-navy-900 text-white shadow' => $tab === $key && ! $exactType,
                            'text-navy-600 hover:text-navy-900' => $tab !== $key || $exactType,
                        ])>{{ $label }}</button>
            @endforeach
            <button type="button" wire:click="toggleStarred"
                    @class([
                        'ml-1 flex h-9 items-center gap-1.5 rounded-xl px-3.5 text-[13px] font-semibold transition',
                        'bg-gold-50 text-gold-700 ring-1 ring-gold-300' => $starred,
                        'text-navy-600 hover:text-navy-900' => ! $starred,
                    ])>
                <x-icon name="star" class="h-3.5 w-3.5 {{ $starred ? 'fill-current' : '' }}" /> Starred
            </button>
        </div>

        <div class="ml-auto flex items-center gap-3">
            <input type="search" wire:model.live.debounce.400ms="q" placeholder="Search events, clients, venues…" class="input h-9 w-52 !rounded-xl text-xs">
            <select wire:model.live="sort" class="input h-9 w-36 !rounded-xl text-xs" title="Sort events">
                <option value="date">Sort: Date</option>
                <option value="health">Sort: Health Score</option>
                <option value="budget">Sort: Budget Used</option>
            </select>
            <span class="inline-flex shrink-0 gap-1 rounded-2xl border border-line bg-white p-1">
                @foreach ([
                    ['grid', 'grid', 'Grid view'], ['list', 'list', 'List view'],
                    ['calendar', 'calendar', 'Calendar view'], ['kanban', 'columns', 'Pipeline (Kanban)'],
                    [null, 'share', 'Hub view — coming soon'], [null, 'pin', 'Map — coming soon'],
                ] as [$mode, $icon, $title])
                    @if ($mode)
                        <button type="button" wire:click="$set('view', '{{ $mode }}')" title="{{ $title }}"
                                @class([
                                    'rounded-xl p-2 transition',
                                    'bg-gold-50 text-gold-600 ring-1 ring-gold-200' => $view === $mode,
                                    'text-navy-500 hover:text-navy-900' => $view !== $mode,
                                ])><x-icon :name="$icon" class="h-4.5 w-4.5" /></button>
                    @else
                        <span class="cursor-not-allowed rounded-xl p-2 text-navy-200" title="{{ $title }}"><x-icon :name="$icon" class="h-4.5 w-4.5" /></span>
                    @endif
                @endforeach
            </span>
        </div>
    </div>

    {{-- ══════════ Pipeline (Kanban) ══════════ --}}
    @if ($view === 'kanban' && $pipeline)
        @php
            $accentBtn = [
                'navy' => 'bg-navy-50 text-navy-600 hover:bg-navy-100',
                'gold' => 'bg-gold-50 text-gold-700 hover:bg-gold-100',
                'track' => 'bg-track/10 text-emerald-700 hover:bg-track/20',
                'blue' => 'bg-[#3B82F6]/10 text-[#3B82F6] hover:bg-[#3B82F6]/20',
                'ink' => 'bg-navy-900/5 text-navy-700 hover:bg-navy-900/10',
            ];
        @endphp
        <div class="mb-1">
            <h2 class="text-lg font-bold text-navy-900">Pipeline</h2>
            <p class="text-xs text-muted">Drag events across stages as deals progress</p>
        </div>
        <div class="flex gap-4 overflow-x-auto pb-3 pt-3">
            @foreach ($pipeline as $bucket => $col)
                <div class="w-[290px] shrink-0" wire:key="col-{{ $bucket }}">
                    <div class="mb-3 flex items-center gap-2 px-1">
                        <span class="h-2.5 w-2.5 rounded-full {{ $col['dot'] }}"></span>
                        <span class="text-sm font-bold text-navy-900">{{ $col['label'] }}</span>
                        <span class="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-navy-50 px-1.5 text-[0.65rem] font-bold text-navy-600">{{ $col['events']->count() }}</span>
                    </div>

                    <div data-kanban-col="{{ $bucket }}" class="min-h-[120px] space-y-3 rounded-2xl">
                        @forelse ($col['events'] as $event)
                            <div data-kanban-card="{{ $event->id }}" wire:key="pcard-{{ $event->id }}"
                                 class="cursor-grab rounded-2xl border border-line bg-white p-4 shadow-[0_4px_16px_rgba(15,23,42,0.05)] transition hover:shadow-[0_10px_28px_rgba(15,23,42,0.10)] active:cursor-grabbing">
                                <p class="text-[0.62rem] font-bold uppercase tracking-wide text-navy-300">EVT-{{ str_pad($event->id, 3, '0', STR_PAD_LEFT) }}</p>
                                <a href="{{ route('events.hub', $event) }}" class="mt-0.5 block text-sm font-bold leading-snug text-navy-900 hover:text-gold-700">{{ $event->name }}</a>

                                <div class="mt-2.5 flex items-center gap-2">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-navy-900 text-[0.6rem] font-bold text-gold-400">
                                        {{ $event->client ? str($event->client->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') : '—' }}
                                    </span>
                                    <span class="truncate text-xs text-muted">{{ $event->client?->name ?? 'No client' }}</span>
                                </div>

                                <div class="mt-3 flex items-center justify-between">
                                    <span class="text-[0.7rem] text-muted">{{ $event->starts_at?->format('d M Y') }}</span>
                                    @if ($event->starts_at)
                                        <span class="text-[0.7rem] font-semibold {{ $event->starts_at->isPast() ? 'text-muted' : ($event->starts_at->diffInDays() <= 7 ? 'text-gold-600' : 'text-navy-500') }}">
                                            {{ $event->starts_at->isPast() ? $event->starts_at->diffForHumans(short: true) : 'in '.(int) now()->diffInDays($event->starts_at).'d' }}
                                        </span>
                                    @endif
                                </div>

                                @if ($event->budget_cents > 0)
                                    <p class="mt-2 text-sm font-bold text-navy-900">{{ number_format($event->budget_cents / 100) }} <span class="text-[0.65rem] font-semibold text-muted">JOD</span></p>
                                @endif

                                @if ($col['next'])
                                    <button type="button" wire:click="moveStage({{ $event->id }}, '{{ $col['next'] }}')"
                                            class="mt-3 w-full rounded-xl py-2 text-xs font-bold transition {{ $accentBtn[$col['accent']] }}">
                                        → Move to {{ $col['nextLabel'] }}
                                    </button>
                                @endif
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-line py-8 text-center text-[0.7rem] text-navy-300">Drop events here</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

        @script
        <script>
            const initPipeline = () => {
                document.querySelectorAll('[data-kanban-col]').forEach((col) => {
                    if (col._sortable) return;
                    col._sortable = window.Sortable.create(col, {
                        group: 'pipeline',
                        animation: 160,
                        ghostClass: 'opacity-40',
                        onEnd: (evt) => {
                            if (evt.from === evt.to) return;
                            const id = parseInt(evt.item.dataset.kanbanCard);
                            const bucket = evt.to.dataset.kanbanCol;
                            $wire.moveStage(id, bucket);
                        },
                    });
                });
            };
            initPipeline();
            Livewire.hook('morph.updated', () => initPipeline());
        </script>
        @endscript

    {{-- ══════════ Calendar ══════════ --}}
    @elseif ($view === 'calendar' && $calendar)
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                <h3 class="text-sm font-bold text-navy-900">{{ $calendar['label'] }}</h3>
                <span class="flex gap-1.5">
                    <button type="button" wire:click="prevMonth" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-navy-600 transition hover:border-gold-300">‹</button>
                    <button type="button" wire:click="nextMonth" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-navy-600 transition hover:border-gold-300">›</button>
                </span>
            </div>
            <div class="grid grid-cols-7 border-b border-line bg-page/60">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                    <div class="px-2 py-2 text-center text-[0.62rem] font-bold uppercase tracking-wide text-muted">{{ $dayName }}</div>
                @endforeach
            </div>
            @foreach ($calendar['weeks'] as $week)
                <div class="grid grid-cols-7 divide-x divide-line border-b border-line last:border-b-0">
                    @foreach ($week as $day)
                        <div @class(['min-h-28 p-1.5', 'bg-page/50' => ! $day['inMonth']])>
                            <p @class([
                                'mb-1 px-1 text-[0.65rem] font-semibold',
                                'text-navy-900' => $day['inMonth'],
                                'text-navy-300' => ! $day['inMonth'],
                                '!text-gold-600' => $day['date']->isToday(),
                            ])>{{ $day['date']->day }}</p>
                            @foreach ($day['events']->take(3) as $calEvent)
                                <a href="{{ route('events.hub', $calEvent) }}"
                                   class="mb-1 flex w-full items-center gap-1.5 rounded-lg border border-line bg-white px-1.5 py-1 text-left transition hover:border-gold-300">
                                    <x-event-avatar :event="$calEvent" :ring="false" size="sm" class="[&>span]:h-5 [&>span]:w-7 [&>span]:rounded" />
                                    <span class="truncate text-[0.6rem] font-semibold text-navy-800">{{ $calEvent->name }}</span>
                                </a>
                            @endforeach
                            @if ($day['events']->count() > 3)
                                <p class="px-1 text-[0.55rem] font-semibold text-muted">+ {{ $day['events']->count() - 3 }} more</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

    {{-- ══════════ Grid / List + preview ══════════ --}}
    @else
        <div class="flex flex-col gap-5 xl:flex-row">
            <div class="min-w-0 flex-1">
                @if ($view === 'grid')
                    <div class="grid gap-4 [grid-template-columns:repeat(auto-fill,minmax(232px,1fr))]">
                        @forelse ($events as $event)
                            <x-event-card :event="$event" :health="$health[$event->id]" :metrics="$metrics[$event->id]"
                                          :selected="$selected && $selected->id === $event->id"
                                          :favorite="in_array($event->id, $favoriteIds)" wire:key="card-{{ $event->id }}" />
                        @empty
                            <p class="col-span-full py-12 text-center text-sm text-muted">No events match these filters.</p>
                        @endforelse
                    </div>
                @else
                    <div class="card divide-y divide-line">
                        @forelse ($events as $event)
                            <div wire:key="row-{{ $event->id }}" wire:click="select({{ $event->id }})"
                                 @class([
                                     'flex cursor-pointer flex-col gap-4 px-6 py-5 transition sm:flex-row sm:items-center',
                                     'bg-gold-50/60' => $selected && $selected->id === $event->id,
                                     'hover:bg-page/60' => ! ($selected && $selected->id === $event->id),
                                 ])>
                                <x-event-avatar :event="$event" size="md" class="hidden sm:inline-block" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('events.hub', $event) }}" wire:click.stop class="truncate text-sm font-bold text-navy-900 hover:text-gold-700">{{ $event->name }}</a>
                                        <x-status-badge :status="$event->stage" />
                                    </div>
                                    <p class="mt-1 text-xs text-muted">
                                        {{ $event->client?->name ?? 'No client' }} · {{ $event->city }}, {{ $event->country }}
                                        @if ($event->venue) · {{ $event->venue->name }} @endif
                                        · {{ $event->starts_at?->format('M j, Y') }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-6">
                                    <div class="hidden text-right md:block">
                                        <p class="text-xs text-muted">Budget used</p>
                                        <p class="text-sm font-semibold text-navy-900">{{ $metrics[$event->id]['budget_used'] !== null ? $metrics[$event->id]['budget_used'].'%' : '—' }}</p>
                                    </div>
                                    <div class="hidden text-right md:block">
                                        <p class="text-xs text-muted">Tasks</p>
                                        <p class="text-sm font-semibold text-navy-900">{{ $event->tasks->count() }}</p>
                                    </div>
                                    <x-health-ring :percent="$health[$event->id]['score']" :group="$health[$event->id]['group']" />
                                </div>
                            </div>
                        @empty
                            <p class="px-6 py-12 text-center text-sm text-muted">No events match these filters.</p>
                        @endforelse
                    </div>
                @endif

                {{-- Pagination footer --}}
                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 text-xs text-muted">
                    <span>Showing {{ $events->firstItem() ?? 0 }} to {{ $events->lastItem() ?? 0 }} of {{ $events->total() }} events</span>
                    @if ($events->hasPages())
                        <span class="flex items-center gap-1.5">
                            <button type="button" wire:click="previousPage" @disabled($events->onFirstPage())
                                    class="rounded-lg border border-line bg-white px-2.5 py-1.5 font-semibold text-navy-600 transition enabled:hover:border-gold-300 disabled:opacity-40">‹</button>
                            @foreach (range(1, $events->lastPage()) as $page)
                                <button type="button" wire:click="gotoPage({{ $page }})"
                                        @class([
                                            'rounded-lg px-3 py-1.5 font-semibold transition',
                                            'bg-gold-50 text-gold-700 ring-1 ring-gold-300' => $events->currentPage() === $page,
                                            'border border-line bg-white text-navy-600 hover:border-gold-300' => $events->currentPage() !== $page,
                                        ])>{{ $page }}</button>
                            @endforeach
                            <button type="button" wire:click="nextPage" @disabled(! $events->hasMorePages())
                                    class="rounded-lg border border-line bg-white px-2.5 py-1.5 font-semibold text-navy-600 transition enabled:hover:border-gold-300 disabled:opacity-40">›</button>
                        </span>
                    @endif
                    <span>Show {{ \App\Livewire\EventsIndex::PER_PAGE }} per page</span>
                </div>
            </div>

            {{-- Preview panel --}}
            <div class="min-w-0 xl:w-[400px] xl:shrink-0 2xl:w-[440px]">
                @if ($selected)
                    <div wire:key="preview-{{ $selected->id }}" class="xl:sticky xl:top-6">
                        <x-event-preview :event="$selected" :health="$selectedHealth" :ai="$ai" />
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
