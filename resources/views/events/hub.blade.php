@php $theme = $event->theme(); @endphp

<x-layouts.app :title="$event->name . ' — Event Hub'" :subtitle="'Control room for every operation of ' . $event->name . '.'">

    {{-- ══ Cover ══ --}}
    <div class="card overflow-hidden">
        <div class="flex flex-col gap-5 px-6 py-6 sm:flex-row sm:items-center"
             style="background: linear-gradient(100deg, {{ $theme['primary'] }} 0%, {{ $theme['primary'] }}F0 60%, {{ $theme['accent'] }}40 100%)">
            <span class="rounded-2xl p-1" style="box-shadow: 0 0 0 3px {{ $theme['accent'] }}66">
                <x-event-avatar :event="$event" size="lg" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-xl font-bold text-white">{{ $event->name }}</h2>
                    <x-status-badge :status="$event->stage" />
                    @if ($health['pending_approvals'] > 0)
                        <span class="inline-flex items-center gap-1 rounded-full bg-[#3B82F6]/20 px-2.5 py-0.5 text-xs font-semibold text-blue-100 ring-1 ring-[#3B82F6]/50">
                            {{ $health['pending_approvals'] }} pending {{ str('approval')->plural($health['pending_approvals']) }}
                        </span>
                    @endif
                </div>
                <p class="mt-1.5 text-sm" style="color: {{ $theme['accent'] }}">
                    {{ $event->client?->name ?? 'No client' }} · {{ str($event->type)->replace('_', ' ')->title() }}
                </p>
                <p class="mt-1 text-xs text-white/70">
                    @if ($event->venue) {{ $event->venue->name }} · @endif
                    {{ $event->city }}, {{ $event->country }}
                    · {{ $event->starts_at?->format('M j') }}{{ $event->ends_at ? ' – '.$event->ends_at->format('M j, Y') : '' }}
                    @if ($event->expected_participants) · {{ number_format($event->expected_participants) }} participants @endif
                    @if ($event->projectManager) · PM: {{ $event->projectManager->name }} @endif
                </p>
            </div>
            <div class="flex flex-col items-center">
                <x-health-ring :percent="$health['score']" :group="$health['group']" size="h-20 w-20" class="rounded-full bg-white/95 p-1 shadow-lg" />
                <p class="mt-1.5 text-[0.65rem] font-semibold uppercase tracking-wide text-white/80">Health Score</p>
            </div>
        </div>

        {{-- ══ Tabs ══ --}}
        <nav class="scrollbar-none flex gap-1 overflow-x-auto border-t border-line bg-white px-3 py-2" aria-label="Event hub">
            @foreach ([
                'overview' => 'Overview', 'agenda' => 'Agenda', 'tasks' => 'Tasks', 'budget' => 'Budget',
                'suppliers' => 'Suppliers', 'venue' => 'Venue', 'sponsors' => 'Sponsors', 'attendees' => 'Attendees',
                'files' => 'Files', 'risks' => 'Risks', 'approvals' => 'Approvals', 'reports' => 'Reports',
                'ai' => 'AI Insights', 'settings' => 'Settings',
            ] as $key => $label)
                <a href="{{ route('events.hub', [$event, 'tab' => $key]) }}"
                   @class([
                       'whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-semibold transition',
                       'bg-gold-50 text-gold-700 ring-1 ring-gold-200' => $tab === $key,
                       'text-navy-600 hover:bg-navy-50 hover:text-navy-900' => $tab !== $key,
                   ])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    <div class="mt-6">
        @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai])
    </div>
</x-layouts.app>
