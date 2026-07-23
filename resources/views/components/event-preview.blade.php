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

<div class="rounded-[28px] border border-line p-4 shadow-[0_12px_35px_rgba(15,23,42,0.08)]"
     style="background: linear-gradient(180deg, #0B1F3A 0%, #122645 210px, #DDE5F0 330px, #F8FAFC 430px, #FFFFFF 100%)">

    {{-- ── Hero: one dark-navy gradient, avatar blended into it ── --}}
    <div class="relative h-[250px] overflow-hidden rounded-[22px] ring-1 ring-white/10"
         style="background: linear-gradient(115deg, #0B1F3A 0%, #0E1E36 45%, #16294A 78%, #1C3357 100%)">

        {{-- Avatar layer: multiply-blend melts white/light backgrounds into the navy --}}
        <div class="absolute inset-y-0 right-0 w-[64%] [mask-image:linear-gradient(90deg,transparent_0%,black_38%)]">
            <x-event-avatar :event="$event" :ring="false" size="xl"
                            class="block h-full w-full [&>span]:h-full [&>span]:w-full [&>span]:rounded-none [&>span]:!bg-transparent [&>span]:ring-0 [&_img]:object-right" />
        </div>

        {{-- Soft gold ambience + bottom vignette --}}
        <div class="pointer-events-none absolute -right-12 -top-16 h-52 w-52 rounded-full bg-gold-500/15 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-20 left-1/3 h-44 w-44 rounded-full bg-gold-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-16" style="background: linear-gradient(180deg, transparent, rgba(11,31,58,0.55))"></div>

        {{-- Content --}}
        <div class="relative z-10 flex h-full max-w-[58%] flex-col p-5">
            <div class="flex items-center gap-2.5">
                <span class="truncate text-xl font-bold tracking-tight text-white">{{ $event->name }}</span>
                <x-status-badge :status="$health['status']" />
            </div>
            <p class="mt-0.5 flex items-center gap-2 text-[13px] font-medium" style="color: {{ $theme['accent'] }}">
                {{ str($event->type)->replace('_', ' ')->title() }}
                <span class="h-px w-8" style="background: {{ $theme['accent'] }}66"></span>
            </p>
            <ul class="mt-3 space-y-1.5 text-xs text-white/80">
                <li class="flex items-center gap-2"><x-icon name="pin" class="h-3.5 w-3.5 shrink-0 text-white/50" /> {{ $event->city }}, {{ $event->country }}</li>
                <li class="flex items-center gap-2"><x-icon name="calendar" class="h-3.5 w-3.5 shrink-0 text-white/50" /> {{ $event->starts_at?->format('M j') }} – {{ $event->ends_at?->format('j, Y') ?? $event->starts_at?->format('Y') }}</li>
                @if ($event->venue)<li class="flex items-center gap-2"><x-icon name="home" class="h-3.5 w-3.5 shrink-0 text-white/50" /> Venue: {{ $event->venue->name }}</li>@endif
                @if ($event->expected_participants)<li class="flex items-center gap-2"><x-icon name="users" class="h-3.5 w-3.5 shrink-0 text-white/50" /> Participants: {{ number_format($event->expected_participants) }}</li>@endif
                @if ($event->projectManager)<li class="flex items-center gap-2"><x-icon name="identification" class="h-3.5 w-3.5 shrink-0 text-white/50" /> Project Manager: {{ $event->projectManager->name }}</li>@endif
            </ul>

            {{-- Primary action --}}
            <a href="{{ route('events.hub', $event) }}"
               class="mt-auto inline-flex h-9 w-fit items-center gap-2 rounded-xl bg-gold-500 px-4 text-xs font-bold text-navy-900 shadow-[0_6px_18px_rgba(212,175,55,0.35)] transition hover:bg-gold-400">
                Open Event Hub →
            </a>
        </div>

        {{-- Health ring --}}
        <span class="absolute bottom-3 right-3 z-10 flex flex-col items-center rounded-2xl bg-navy-950/60 px-2.5 py-2 backdrop-blur-sm">
            <x-health-ring :percent="$health['score']" :group="$health['group']" size="h-[88px] w-[88px]" textSize="text-[20px]"
                           class="[&_span]:!text-white [&_circle:first-child]:!stroke-white/20" />
            <span class="mt-0.5 text-[10px] font-semibold text-white/85">Health Score</span>
        </span>

        <span class="absolute right-3 top-3 z-10 flex gap-1.5">
            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-gold-400 backdrop-blur transition hover:bg-white/20" aria-label="Favorite"><x-icon name="star" class="h-4 w-4" /></button>
            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-white/70 backdrop-blur transition hover:bg-white/20" aria-label="Actions"><x-icon name="dots" class="h-4 w-4" /></button>
        </span>
    </div>

    {{-- ── Quick metric cards: 68px ── --}}
    <div class="mt-4 grid grid-cols-2 gap-2.5 xl:grid-cols-4">
        @foreach ([
            ['icon' => 'currency', 'label' => 'Budget Health', 'value' => $budgetLabel, 'good' => $budgetLabel === 'Healthy'],
            ['icon' => 'clipboard', 'label' => 'Tasks Completed', 'value' => $health['components']['tasks'] !== null ? $health['components']['tasks'].'%' : '—', 'good' => ($health['components']['tasks'] ?? 0) >= 61],
            ['icon' => 'users', 'label' => 'Supplier Readiness', 'value' => $health['components']['suppliers'] !== null ? $health['components']['suppliers'].'%' : '—', 'good' => ($health['components']['suppliers'] ?? 0) >= 61],
            ['icon' => 'bell', 'label' => 'Risks', 'value' => $openRisks->isEmpty() ? 'None' : $openRisks->count(), 'suffix' => $riskWord, 'good' => $openRisks->isEmpty(), 'warn' => ! $openRisks->isEmpty()],
        ] as $chip)
            <div class="flex h-[68px] items-center gap-2.5 rounded-2xl border border-line bg-white px-3 shadow-[0_4px_14px_rgba(15,23,42,0.04)]">
                <span @class([
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
                        'bg-track/10 text-emerald-600' => $chip['good'],
                        'bg-risk/10 text-risk' => ($chip['warn'] ?? false),
                        'bg-navy-50 text-navy-600' => ! $chip['good'] && ! ($chip['warn'] ?? false),
                    ])><x-icon :name="$chip['icon']" class="h-4 w-4" /></span>
                <span class="min-w-0">
                    <span class="block truncate text-[10px] font-semibold text-muted">{{ $chip['label'] }}</span>
                    <span class="block text-sm font-bold {{ $chip['good'] ? 'text-emerald-600' : 'text-navy-900' }}">
                        {{ $chip['value'] }}
                        @if ($chip['suffix'] ?? false)<span class="text-[11px] font-semibold text-amber-600">{{ $chip['suffix'] }}</span>@endif
                    </span>
                </span>
            </div>
        @endforeach
    </div>

    {{-- ── Control Room shortcuts: 96×60 ── --}}
    <div class="mt-4">
        <p class="mb-2.5 text-xs font-bold uppercase tracking-wide text-navy-800">Event Control Room</p>
        <div class="grid grid-cols-3 gap-2.5 sm:grid-cols-5">
            @foreach ([
                'overview' => ['Overview', 'home'], 'agenda' => ['Agenda', 'calendar'], 'tasks' => ['Tasks', 'clipboard'],
                'budget' => ['Budget', 'currency'], 'suppliers' => ['Suppliers', 'truck'], 'venue' => ['Venue', 'building'],
                'sponsors' => ['Sponsors', 'star'], 'attendees' => ['Attendees', 'users'], 'files' => ['Files', 'archive'],
                'risks' => ['Risks', 'bell'], 'approvals' => ['Approvals', 'identification'], 'reports' => ['Reports', 'chart'],
                'ai' => ['AI Insights', 'sparkles'], 'team' => ['Team', 'users'], 'settings' => ['Settings', 'cog'],
            ] as $tabKey => [$label, $icon])
                <a href="{{ route('events.hub', [$event, 'tab' => $tabKey === 'team' ? 'overview' : $tabKey]) }}"
                   @class([
                       'flex h-[60px] flex-col items-center justify-center gap-1 rounded-2xl border transition',
                       'border-gold-300 bg-gold-50 text-gold-700' => $tabKey === 'overview',
                       'border-line bg-white text-navy-700 hover:border-gold-300 hover:bg-gold-50/40' => $tabKey !== 'overview',
                   ])>
                    <x-icon :name="$icon" class="h-[18px] w-[18px] shrink-0" />
                    <span class="truncate text-[11px] font-semibold">{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- ── AI recommendation: radius 22, padding 18 ── --}}
    <div class="mt-4 rounded-[22px] border border-line bg-white p-[18px] shadow-[0_4px_14px_rgba(15,23,42,0.04)]">
        <p class="mb-2.5 flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-navy-900">
            <span class="text-gold-500">✦</span> AI Recommendation
        </p>
        <ul class="space-y-1.5 text-[13px] text-navy-800">
            @forelse (array_slice($ai['attention'], 0, 3) as $point)
                <li class="flex gap-2"><span class="text-navy-300">•</span> {{ $point }}</li>
            @empty
                <li class="flex gap-2"><span class="text-navy-300">•</span> No blockers detected — keep executing the plan.</li>
            @endforelse
        </ul>
        <div class="mt-3 flex justify-end">
            <a href="{{ route('events.hub', [$event, 'tab' => 'ai']) }}" class="btn-navy h-10 text-xs !text-gold-400">View All Insights →</a>
        </div>
    </div>
</div>
