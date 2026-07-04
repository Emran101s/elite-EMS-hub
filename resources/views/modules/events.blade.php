<x-layouts.app title="Events" subtitle="Manage all events, projects, venues, suppliers, budgets, and live operations.">

    {{-- KPI row --}}
    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach ($kpis as $label => $value)
            <div class="card px-4 py-3.5">
                <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-muted">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold {{ $label === 'At Risk' && $value > 0 ? 'text-risk' : 'text-navy-900' }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- Toolbar: search, filters, view switcher, actions --}}
    <form method="GET" class="mb-5 flex flex-wrap items-center gap-3">
        <input type="hidden" name="view" value="{{ $view }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search events…" class="input w-56">
        <select name="type" class="input w-44" onchange="this.form.submit()">
            <option value="">All types</option>
            @foreach (\App\Models\Event::TYPES as $type)
                <option value="{{ $type }}" @selected(request('type') === $type)>{{ str($type)->replace('_', ' ')->title() }}</option>
            @endforeach
        </select>
        <select name="stage" class="input w-44" onchange="this.form.submit()">
            <option value="">All stages</option>
            @foreach (\App\Models\Event::STAGES as $stage)
                <option value="{{ $stage }}" @selected(request('stage') === $stage)>{{ str($stage)->replace('_', ' ')->title() }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-navy px-4 py-2.5 text-xs">Filter</button>

        <span class="ml-auto inline-flex overflow-hidden rounded-xl border border-line bg-white">
            @foreach (['grid' => 'Grid', 'list' => 'List'] as $key => $label)
                <a href="{{ request()->fullUrlWithQuery(['view' => $key]) }}"
                   @class([
                       'px-3.5 py-2 text-xs font-semibold transition',
                       'bg-navy-900 text-gold-400' => $view === $key,
                       'text-navy-600 hover:text-navy-900' => $view !== $key,
                   ])>{{ $label }}</a>
            @endforeach
            <span class="px-3.5 py-2 text-xs text-navy-300" title="Coming soon">Calendar</span>
            <span class="px-3.5 py-2 text-xs text-navy-300" title="Coming soon">Kanban</span>
        </span>

        <a href="{{ route('events.avatars') }}" class="rounded-xl border border-line bg-white px-4 py-2.5 text-xs font-semibold text-navy-700 transition hover:border-gold-300">Avatar Library</a>
        <a href="{{ route('events.create') }}" class="btn-gold text-xs">+ New Event</a>
    </form>

    @if ($view === 'grid')
        <div class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
            @forelse ($events as $event)
                @php $theme = $event->theme(); @endphp
                <a href="{{ route('events.hub', $event) }}" class="card group block overflow-hidden transition hover:shadow-lg">
                    <div class="h-1.5" style="background: linear-gradient(90deg, {{ $theme['primary'] }}, {{ $theme['accent'] }})"></div>
                    <div class="p-5">
                        <div class="flex items-start gap-4">
                            <x-event-avatar :event="$event" size="md" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-navy-900 group-hover:text-gold-700">{{ $event->name }}</p>
                                <p class="mt-0.5 truncate text-xs text-muted">{{ $event->client?->name ?? '—' }}</p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-navy-50 px-2 py-0.5 text-[0.6rem] font-semibold uppercase tracking-wide text-navy-600">
                                        {{ str($event->type)->replace('_', ' ')->title() }}
                                    </span>
                                    <x-status-badge :status="$event->stage" class="!text-[0.6rem]" />
                                </div>
                            </div>
                        </div>

                        <dl class="mt-4 grid grid-cols-3 gap-2 border-t border-line pt-3 text-center">
                            <div>
                                <dt class="text-[0.6rem] uppercase tracking-wide text-muted">Dates</dt>
                                <dd class="mt-0.5 text-xs font-semibold text-navy-900">{{ $event->starts_at?->format('M j') }}{{ $event->ends_at && ! $event->ends_at->isSameDay($event->starts_at) ? '–'.$event->ends_at->format('j') : '' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[0.6rem] uppercase tracking-wide text-muted">Budget</dt>
                                <dd class="mt-0.5 text-xs font-semibold text-navy-900">${{ \Illuminate\Support\Number::abbreviate($event->budget_cents / 100) }}</dd>
                            </div>
                            <div>
                                <dt class="text-[0.6rem] uppercase tracking-wide text-muted">PM</dt>
                                <dd class="mt-0.5 truncate text-xs font-semibold text-navy-900">{{ $event->projectManager?->name ? str($event->projectManager->name)->before(' ') : '—' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-3 flex items-center justify-between text-xs">
                            <span class="text-muted">{{ $event->city }}, {{ $event->country }}</span>
                            <span class="font-semibold text-gold-600 opacity-0 transition group-hover:opacity-100">Open Hub →</span>
                        </div>
                    </div>
                </a>
            @empty
                <p class="col-span-full py-12 text-center text-sm text-muted">No events match these filters.</p>
            @endforelse
        </div>
    @else
        <div class="card divide-y divide-line">
            @forelse ($events as $event)
                <a href="{{ route('events.hub', $event) }}" class="flex flex-col gap-4 px-6 py-5 transition hover:bg-page/60 sm:flex-row sm:items-center">
                    <x-event-avatar :event="$event" size="md" class="hidden sm:inline-block" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-3">
                            <p class="truncate text-sm font-bold text-navy-900">{{ $event->name }}</p>
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
                            <p class="text-xs text-muted">Budget</p>
                            <p class="text-sm font-semibold text-navy-900">${{ \Illuminate\Support\Number::abbreviate($event->budget_cents / 100, 2) }}</p>
                        </div>
                        <div class="hidden text-right md:block">
                            <p class="text-xs text-muted">Tasks</p>
                            <p class="text-sm font-semibold text-navy-900">{{ $event->tasks_count }}</p>
                        </div>
                        <x-health-ring :percent="$event->progress" :group="$event->healthGroup()" />
                    </div>
                </a>
            @empty
                <p class="px-6 py-12 text-center text-sm text-muted">No events match these filters.</p>
            @endforelse
        </div>
    @endif
</x-layouts.app>
