@props(['event', 'health', 'ai'])

@php
    $theme = $event->theme();
    $budgetLabel = match (true) {
        $health['components']['budget'] === null => '—',
        $health['components']['budget'] >= 81 => 'Healthy',
        $health['components']['budget'] >= 61 => 'Watch',
        default => 'Over budget',
    };
    $openRisks = $event->risks->filter->isOpen();
    $riskWord = $openRisks->isEmpty() ? null : ($openRisks->max(fn ($r) => $r->severity()) >= 15 ? 'High' : 'Medium');
@endphp

<div class="space-y-4">

    {{-- ── Dark hero ── --}}
    <div class="relative overflow-hidden rounded-3xl shadow-[0_18px_45px_rgba(11,31,58,0.35)]"
         style="background: linear-gradient(120deg, {{ $theme['primary'] }} 0%, #101c33 55%, #1b2c4c 100%)">
        <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <div class="p-5">
                <div class="flex items-center gap-2.5">
                    <span class="truncate text-lg font-bold text-white">{{ $event->name }}</span>
                    <x-status-badge :status="$health['status']" />
                </div>
                <p class="mt-1 text-sm text-white/85">{{ $event->avatar?->name ?? str($event->type)->replace('_', ' ')->title() }}</p>
                <ul class="mt-3.5 space-y-2 text-xs text-white/80">
                    <li class="flex items-center gap-2"><x-icon name="building" class="h-3.5 w-3.5 shrink-0 text-white/50" /> {{ $event->city }}, {{ $event->country }}</li>
                    <li class="flex items-center gap-2"><x-icon name="calendar" class="h-3.5 w-3.5 shrink-0 text-white/50" /> {{ $event->starts_at?->format('M j') }} – {{ $event->ends_at?->format('j, Y') ?? $event->starts_at?->format('Y') }}</li>
                    @if ($event->venue)<li class="flex items-center gap-2"><x-icon name="home" class="h-3.5 w-3.5 shrink-0 text-white/50" /> Venue: {{ $event->venue->name }}</li>@endif
                    @if ($event->expected_participants)<li class="flex items-center gap-2"><x-icon name="users" class="h-3.5 w-3.5 shrink-0 text-white/50" /> Participants: {{ number_format($event->expected_participants) }}</li>@endif
                    @if ($event->projectManager)<li class="flex items-center gap-2"><x-icon name="identification" class="h-3.5 w-3.5 shrink-0 text-white/50" /> Project Manager: {{ $event->projectManager->name }}</li>@endif
                </ul>
            </div>

            <div class="relative">
                <x-event-avatar :event="$event" :ring="false" size="xl"
                                class="block h-full w-full [&>span]:h-full [&>span]:min-h-56 [&>span]:w-full [&>span]:rounded-none [&>span]:bg-transparent [&>span]:ring-0" />
                <div class="pointer-events-none absolute inset-0" style="background: linear-gradient(90deg, {{ $theme['primary'] }} 0%, transparent 30%)"></div>
                <span class="absolute bottom-3 right-3 flex flex-col items-center rounded-2xl bg-navy-950/70 px-2 py-2 backdrop-blur">
                    <x-health-ring :percent="$health['score']" :group="$health['group']" size="h-16 w-16" class="[&_span]:!text-white [&_circle:first-child]:!stroke-white/20" />
                    <span class="mt-0.5 text-[0.55rem] font-semibold text-white/85">Health Score</span>
                </span>
            </div>
        </div>

        <span class="absolute right-3 top-3 flex gap-1.5">
            <button type="button" class="rounded-full bg-white/10 p-1.5 text-gold-400 backdrop-blur transition hover:bg-white/20" aria-label="Favorite"><x-icon name="star" class="h-4 w-4" /></button>
            <button type="button" class="rounded-full bg-white/10 p-1.5 text-white/70 backdrop-blur transition hover:bg-white/20" aria-label="Actions"><x-icon name="dots" class="h-4 w-4" /></button>
        </span>
    </div>

    {{-- ── Quick metric chips ── --}}
    <div class="grid grid-cols-2 gap-2.5 xl:grid-cols-4">
        @foreach ([
            ['icon' => 'currency', 'label' => 'Budget Health', 'value' => $budgetLabel, 'good' => $budgetLabel === 'Healthy'],
            ['icon' => 'clipboard', 'label' => 'Tasks Completed', 'value' => $health['components']['tasks'] !== null ? $health['components']['tasks'].'%' : '—', 'good' => ($health['components']['tasks'] ?? 0) >= 61],
            ['icon' => 'users', 'label' => 'Supplier Readiness', 'value' => $health['components']['suppliers'] !== null ? $health['components']['suppliers'].'%' : '—', 'good' => ($health['components']['suppliers'] ?? 0) >= 61],
            ['icon' => 'bell', 'label' => 'Risks', 'value' => $openRisks->isEmpty() ? 'None' : $openRisks->count(), 'suffix' => $riskWord, 'good' => $openRisks->isEmpty(), 'warn' => ! $openRisks->isEmpty()],
        ] as $chip)
            <div class="card flex items-center gap-2.5 px-3 py-3">
                <span @class([
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
                        'bg-track/10 text-emerald-600' => $chip['good'],
                        'bg-risk/10 text-risk' => ($chip['warn'] ?? false),
                        'bg-navy-50 text-navy-600' => ! $chip['good'] && ! ($chip['warn'] ?? false),
                    ])><x-icon :name="$chip['icon']" class="h-4 w-4" /></span>
                <span class="min-w-0">
                    <span class="block truncate text-[0.6rem] font-semibold text-muted">{{ $chip['label'] }}</span>
                    <span class="block text-sm font-bold {{ $chip['good'] ? 'text-emerald-600' : 'text-navy-900' }}">
                        {{ $chip['value'] }}
                        @if ($chip['suffix'] ?? false)<span class="text-[0.65rem] font-semibold text-amber-600">{{ $chip['suffix'] }}</span>@endif
                    </span>
                </span>
            </div>
        @endforeach
    </div>

    {{-- ── Control Room shortcuts ── --}}
    <div>
        <p class="mb-2.5 text-xs font-bold uppercase tracking-wide text-navy-900">Event Control Room</p>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5">
            @foreach ([
                'overview' => ['Overview', 'home'], 'agenda' => ['Agenda', 'calendar'], 'tasks' => ['Tasks', 'clipboard'],
                'budget' => ['Budget', 'currency'], 'suppliers' => ['Suppliers', 'truck'], 'venue' => ['Venue', 'building'],
                'sponsors' => ['Sponsors', 'star'], 'attendees' => ['Attendees', 'users'], 'files' => ['Files', 'archive'],
                'risks' => ['Risks', 'bell'], 'approvals' => ['Approvals', 'identification'], 'reports' => ['Reports', 'chart'],
                'ai' => ['AI Insights', 'sparkles'], 'team' => ['Team', 'users'], 'settings' => ['Settings', 'cog'],
            ] as $tabKey => [$label, $icon])
                <a href="{{ route('events.hub', [$event, 'tab' => $tabKey === 'team' ? 'overview' : $tabKey]) }}"
                   @class([
                       'flex items-center gap-2 rounded-2xl border px-3 py-2.5 transition',
                       'border-gold-300 bg-gold-50 text-gold-700' => $tabKey === 'overview',
                       'border-line bg-white text-navy-700 hover:border-gold-300 hover:bg-gold-50/40' => $tabKey !== 'overview',
                   ])>
                    <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
                    <span class="truncate text-[0.68rem] font-semibold">{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- ── AI recommendation ── --}}
    <div class="card p-5">
        <p class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-navy-900">
            <span class="text-gold-500">✦</span> AI Recommendation
        </p>
        <ul class="space-y-2 text-xs text-navy-800">
            @forelse (array_slice($ai['attention'], 0, 3) as $point)
                <li class="flex gap-2"><span class="text-navy-300">•</span> {{ $point }}</li>
            @empty
                <li class="flex gap-2"><span class="text-navy-300">•</span> No blockers detected — keep executing the plan.</li>
            @endforelse
        </ul>
        <div class="mt-4 flex justify-end">
            <a href="{{ route('events.hub', [$event, 'tab' => 'ai']) }}" class="btn-navy text-xs !text-gold-400">View All Insights →</a>
        </div>
    </div>
</div>
