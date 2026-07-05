<div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_24rem]">

    {{-- ══ Main column ══ --}}
    <div class="min-w-0">

        {{-- KPI row --}}
        <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach ($kpis as $kpi)
                <div class="card px-4 py-3.5">
                    <div class="flex items-center gap-2.5">
                        <span @class([
                                'flex h-8 w-8 items-center justify-center rounded-lg',
                                'bg-risk/10 text-risk' => $kpi['risk'] ?? false,
                                'bg-navy-50 text-navy-600' => ! ($kpi['risk'] ?? false),
                            ])><x-icon :name="$kpi['icon']" class="h-4 w-4" /></span>
                        <p class="text-[0.6rem] font-semibold uppercase tracking-wide text-muted">{{ $kpi['label'] }}</p>
                    </div>
                    <p class="mt-2 text-2xl font-bold {{ ($kpi['risk'] ?? false) && $kpi['value'] > 0 ? 'text-risk' : 'text-navy-900' }}">{{ $kpi['value'] }}</p>
                    <p class="mt-0.5 text-[0.6rem] text-muted">{{ $kpi['hint'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Toolbar --}}
        <div class="mb-5 flex flex-wrap items-center gap-3">
            <div class="flex flex-wrap gap-1.5">
                @foreach (['all' => 'All Events', 'conference' => 'Conference', 'workshop' => 'Workshop', 'exhibition' => 'Exhibition', 'gala' => 'Gala Dinner', 'vip' => 'VIP', 'outdoor' => 'Outdoor'] as $key => $label)
                    <button type="button" wire:click="setTab('{{ $key }}')"
                            @class([
                                'rounded-full px-3.5 py-1.5 text-xs font-semibold transition',
                                'bg-navy-900 text-white' => $tab === $key && ! $exactType,
                                'bg-white text-navy-600 ring-1 ring-line hover:text-navy-900' => $tab !== $key || $exactType,
                            ])>{{ $label }}</button>
                @endforeach
            </div>

            <div class="ml-auto flex items-center gap-3">
                <input type="search" wire:model.live.debounce.400ms="q" placeholder="Search events, clients, venues…" class="input w-56">
                <span class="inline-flex overflow-hidden rounded-xl border border-line bg-white">
                    @foreach (['grid' => 'Grid', 'list' => 'List'] as $key => $label)
                        <button type="button" wire:click="$set('view', '{{ $key }}')"
                                @class([
                                    'px-3.5 py-2 text-xs font-semibold transition',
                                    'bg-navy-900 text-gold-400' => $view === $key,
                                    'text-navy-600 hover:text-navy-900' => $view !== $key,
                                ])>{{ $label }}</button>
                    @endforeach
                    <span class="px-3 py-2 text-xs text-navy-300" title="Coming soon">Calendar</span>
                    <span class="px-3 py-2 text-xs text-navy-300" title="Coming soon">Kanban</span>
                </span>
                <a href="{{ route('events.create') }}" class="btn-navy whitespace-nowrap text-xs">+ Create Event</a>
            </div>
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

        <p class="mt-4 text-xs text-muted">Showing {{ $events->count() }} {{ str('event')->plural($events->count()) }} · <a href="{{ route('events.avatars') }}" class="font-semibold text-gold-600 hover:text-gold-700">Avatar Library</a></p>
    </div>

    {{-- ══ Preview panel ══ --}}
    <div>
        @if ($selected)
            <div wire:key="preview-{{ $selected->id }}" class="2xl:sticky 2xl:top-6">
                <x-event-preview :event="$selected" :health="$selectedHealth" :ai="$ai" />
            </div>
        @endif
    </div>
</div>
