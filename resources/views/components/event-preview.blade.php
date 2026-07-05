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
    $riskLabel = $openRisks->isEmpty() ? 'None'
        : $openRisks->count().' '.($openRisks->max(fn ($r) => $r->severity()) >= 15 ? 'High' : 'Medium');
@endphp

<div class="card overflow-hidden">
    {{-- Hero --}}
    <div class="p-5" style="background: linear-gradient(160deg, {{ $theme['primary'] }} 0%, #142842 80%, {{ $theme['accent'] }}30 100%)">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-2">
                <span class="text-lg font-bold text-white">{{ $event->name }}</span>
                <x-status-badge :status="$health['status']" />
            </div>
            <span class="flex gap-1.5">
                <span class="rounded-full bg-white/15 p-1.5 text-gold-300"><x-icon name="star" class="h-3.5 w-3.5" /></span>
                <span class="rounded-full bg-white/15 p-1.5 text-white/70"><x-icon name="dots" class="h-3.5 w-3.5" /></span>
            </span>
        </div>
        <p class="mt-0.5 text-xs" style="color: {{ $theme['accent'] }}">{{ $event->avatar?->name ?? str($event->type)->replace('_', ' ')->title() }}</p>

        <div class="relative mt-3 overflow-hidden rounded-2xl ring-2" style="--tw-ring-color: {{ $theme['accent'] }}66">
            <x-event-avatar :event="$event" :ring="false" size="xl" class="block w-full [&>span]:h-36 [&>span]:w-full [&>span]:rounded-none [&>span]:ring-0" />
            <span class="absolute bottom-2 right-2 rounded-full bg-white p-1 shadow-lg">
                <x-health-ring :percent="$health['score']" :group="$health['group']" size="h-14 w-14" />
            </span>
        </div>

        <ul class="mt-4 space-y-1 text-xs text-white/85">
            <li>📍 {{ $event->city }}, {{ $event->country }}</li>
            <li>📅 {{ $event->starts_at?->format('M j') }} – {{ $event->ends_at?->format('M j, Y') ?? $event->starts_at?->format('Y') }}</li>
            @if ($event->venue)<li>🏛 Venue: {{ $event->venue->name }}</li>@endif
            @if ($event->expected_participants)<li>👥 Participants: {{ number_format($event->expected_participants) }}</li>@endif
            @if ($event->projectManager)<li>👤 Project Manager: {{ $event->projectManager->name }}</li>@endif
        </ul>
    </div>

    {{-- Quick metrics --}}
    <div class="grid grid-cols-2 gap-2 border-b border-line p-4">
        @foreach ([
            ['label' => 'Budget Health', 'value' => $budgetLabel, 'good' => $budgetLabel === 'Healthy'],
            ['label' => 'Tasks Completed', 'value' => $health['components']['tasks'] !== null ? $health['components']['tasks'].'%' : '—', 'good' => ($health['components']['tasks'] ?? 0) >= 61],
            ['label' => 'Supplier Readiness', 'value' => $health['components']['suppliers'] !== null ? $health['components']['suppliers'].'%' : '—', 'good' => ($health['components']['suppliers'] ?? 0) >= 61],
            ['label' => 'Risks', 'value' => $riskLabel, 'good' => $openRisks->isEmpty()],
        ] as $metric)
            <div class="rounded-xl border border-line bg-page/60 px-3 py-2.5">
                <p class="text-[0.6rem] font-semibold uppercase tracking-wide text-muted">{{ $metric['label'] }}</p>
                <p class="mt-0.5 text-sm font-bold {{ $metric['good'] ? 'text-emerald-600' : 'text-navy-900' }}">{{ $metric['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Control Room shortcuts --}}
    <div class="p-4">
        <p class="mb-3 text-[0.65rem] font-bold uppercase tracking-wide text-navy-900">Event Control Room</p>
        <div class="grid grid-cols-3 gap-2">
            @foreach ([
                'overview' => ['Overview', 'home'], 'agenda' => ['Agenda', 'calendar'], 'tasks' => ['Tasks', 'clipboard'],
                'budget' => ['Budget', 'currency'], 'suppliers' => ['Suppliers', 'truck'], 'venue' => ['Venue', 'building'],
                'sponsors' => ['Sponsors', 'star'], 'attendees' => ['Attendees', 'users'], 'files' => ['Files', 'archive'],
                'risks' => ['Risks', 'bell'], 'approvals' => ['Approvals', 'identification'], 'reports' => ['Reports', 'chart'],
                'ai' => ['AI Insights', 'sparkles'], 'overview#team' => ['Team', 'users'], 'settings' => ['Settings', 'cog'],
            ] as $tabKey => [$label, $icon])
                <a href="{{ route('events.hub', [$event, 'tab' => str($tabKey)->before('#')->toString()]) }}"
                   class="flex flex-col items-center gap-1.5 rounded-xl border border-line bg-white px-2 py-3 text-center transition hover:border-gold-300 hover:bg-gold-50/40">
                    <span class="text-navy-600"><x-icon :name="$icon" class="h-4 w-4" /></span>
                    <span class="text-[0.6rem] font-semibold text-navy-800">{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- AI recommendation --}}
    <div class="border-t border-line bg-gold-50/50 p-4">
        <p class="mb-2 text-[0.65rem] font-bold uppercase tracking-wide text-gold-700">✦ AI Recommendation</p>
        <ul class="space-y-1.5 text-xs text-navy-800">
            @forelse (array_slice($ai['attention'], 0, 3) as $point)
                <li class="flex gap-1.5"><span class="text-gold-600">•</span> {{ $point }}</li>
            @empty
                <li>No blockers detected — keep executing the plan.</li>
            @endforelse
        </ul>
        <a href="{{ route('events.hub', [$event, 'tab' => 'ai']) }}" class="btn-gold mt-3 w-full text-xs">View All Insights →</a>
    </div>
</div>
