<div>
    {{-- Create Event (top right, above KPIs) --}}
    <div class="mb-4 flex justify-end">
        <a href="{{ route('events.create') }}" class="btn-navy !rounded-2xl px-5 text-sm !text-white">
            <span class="text-gold-400">+</span> Create Event
        </a>
    </div>

    {{-- KPI row --}}
    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
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
            <div class="card px-4 py-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl {{ $toneClass }}"><x-icon :name="$kpi['icon']" class="h-4.5 w-4.5" /></span>
                    <p class="text-[0.65rem] font-semibold text-muted">{{ $kpi['label'] }}</p>
                </div>
                <p class="mt-2.5 text-3xl font-bold text-navy-900">{{ $kpi['value'] }}</p>
                <p class="mt-1 text-[0.62rem] font-semibold {{ $kpi['up'] ? 'text-emerald-600' : 'text-risk' }}">{{ $kpi['trend'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_minmax(24rem,30rem)]">

        {{-- ══ Left: filters + cards ══ --}}
        <div class="min-w-0">
            {{-- Filter tabs --}}
            <div class="mb-5 flex flex-wrap items-center gap-1">
                @foreach (['all' => 'All Events', 'conference' => 'Conference', 'workshop' => 'Workshop', 'exhibition' => 'Exhibition', 'gala' => 'Gala Dinner', 'vip' => 'VIP', 'outdoor' => 'Outdoor'] as $key => $label)
                    <button type="button" wire:click="setTab('{{ $key }}')"
                            @class([
                                'rounded-full px-4 py-2 text-xs font-semibold transition',
                                'bg-navy-900 text-white shadow' => $tab === $key && ! $exactType,
                                'text-navy-600 hover:text-navy-900' => $tab !== $key || $exactType,
                            ])>{{ $label }}</button>
                @endforeach
                <span class="px-2 text-xs text-navy-400" title="More types via search">More ▾</span>
                <input type="search" wire:model.live.debounce.400ms="q" placeholder="Search events, clients, venues…" class="input ml-auto w-52 !py-2 text-xs">
            </div>

            {{-- Grid view --}}
            @if ($view === 'grid')
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($events as $event)
                        <x-event-card :event="$event" :health="$health[$event->id]" :metrics="$metrics[$event->id]"
                                      :selected="$selected && $selected->id === $event->id" wire:key="card-{{ $event->id }}" />
                    @empty
                        <p class="col-span-full py-12 text-center text-sm text-muted">No events match these filters.</p>
                    @endforelse
                </div>
            @else
                {{-- List view --}}
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

        {{-- ══ Right: view switcher + preview ══ --}}
        <div class="min-w-0">
            <div class="mb-5 flex items-center justify-between">
                <span class="inline-flex gap-1 rounded-2xl border border-line bg-white p-1">
                    @foreach ([
                        ['grid', 'grid', 'Grid view'], ['list', 'list', 'List view'],
                        [null, 'calendar', 'Calendar — coming soon'], [null, 'columns', 'Kanban — coming soon'],
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
                <span class="text-navy-300"><x-icon name="dots" class="h-4 w-4" /></span>
            </div>

            @if ($selected)
                <div wire:key="preview-{{ $selected->id }}" class="2xl:sticky 2xl:top-6">
                    <x-event-preview :event="$selected" :health="$selectedHealth" :ai="$ai" />
                </div>
            @endif
        </div>
    </div>
</div>
