@php
    $user = auth()->user();
    $hour = (int) $now->copy()->setTimeFrom(now())->format('H');
    $greeting = match (true) { $hour < 12 => 'Good morning', $hour < 18 => 'Good afternoon', default => 'Good evening' };

    // Mission Radar's numbers — kept, computed once, but no longer the
    // page's hero by default (Phase D). Compact insight strip below;
    // full radar only when a producer actually asks for it.
    $radarPool = $events
        ->filter(fn ($e) => $e->starts_at)
        ->sortBy('starts_at')
        ->take(10)
        ->values();
    $radarN = max($radarPool->count(), 1);
    $radarMissions = $radarPool->map(function ($e, $i) use ($radarN) {
        $tone = match (true) {
            $e->stage === 'live' => 'live',
            in_array($e->stage, ['production', 'planning'], true) => 'warn',
            default => 'ok',
        };
        $angle = -90 + (360 / $radarN) * $i;
        $rad = deg2rad($angle);
        $days = now()->startOfDay()->diffInDays($e->starts_at->copy()->startOfDay(), false);
        $urgency = $e->stage === 'live' ? 0 : max(0, min(1, max(0, $days) / 120));
        $r = $e->stage === 'live' ? 12 : 22 + $urgency * 28;

        return [
            'tone' => $tone,
            'x' => round(50 + $r * cos($rad), 2),
            'y' => round(50 + $r * sin($rad), 2),
            'label' => str($e->name)->limit(22),
            'href' => route('events.hub', $e),
            'featured' => $i === 0,
            'readiness' => (int) ($e->progress ?? 0),
        ];
    })->all();

    $atRiskCount = collect($kpis)->firstWhere('label', 'Operational Risks')['value'] ?? 0;
    $radarStats = [
        ['value' => $events->count(), 'label' => 'Missions'],
        ['value' => $events->where('stage', 'live')->count(), 'label' => 'Live', 'tone' => 'live'],
        ['value' => $atRiskCount, 'label' => 'At risk', 'tone' => 'risk'],
        ['value' => $signalCount, 'label' => 'Signals', 'tone' => $signalCount ? 'warn' : 'ok'],
    ];

    $kpiTone = fn (string $tone) => match ($tone) {
        'red' => 'risk', 'amber' => 'warn', 'green' => 'ok', 'blue' => 'live', default => null,
    };

    $money = fn (int $cents, string $cur = 'JOD') => \App\Support\Money::abbreviated($cents, $cur);
@endphp

<div class="eo-event-atmosphere space-y-6 rounded-[24px]">

    @if ($events->isEmpty())
        <x-eo.empty-state
            title="Nothing on the book yet"
            hint="Once you add a summit, forum, or exhibition, Mission Radar and readiness signals surface here."
            icon="sparkles"
        >
            <x-slot:actions>
                <x-eo.button href="{{ route('events.create') }}">Create your first mission</x-eo.button>
            </x-slot:actions>
        </x-eo.empty-state>
    @endif

    {{-- ══════════ 1 · EXECUTIVE HEADER ══════════ --}}
    <x-eo.page-header
        eyebrow="{{ $now->format('l · j F Y') }}"
        title="{{ $greeting }}, {{ str($user->name)->before(' ') }}"
        subtitle="{{ $headline }}"
    >
        <x-slot:actions>
            <span class="eo-journey-chip">Command Center</span>
            <x-eo.button variant="ghost" size="sm" href="{{ route('ai.index') }}">Open Command Briefing</x-eo.button>
            <x-eo.button size="sm" href="{{ route('ai.index') }}">✦ Command Briefing</x-eo.button>
        </x-slot:actions>
    </x-eo.page-header>

    {{-- ══════════ 2 · KPI STRIP ══════════ --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($kpis as $k)
            <a href="{{ $k['href'] ?? '#' }}" class="block transition hover:-translate-y-0.5">
                <x-eo.metric-pill :label="$k['label']" :value="$k['value']" :hint="$k['note']" :tone="$kpiTone($k['tone'] ?? '')" />
            </a>
        @endforeach
    </div>

    {{-- Mission Radar — a compact strip by default (Phase D: it does not
         dominate), the full hero only behind this toggle. --}}
    <div x-data="{ radarOpen: false }">
        <button type="button" @click="radarOpen = ! radarOpen"
                class="flex w-full flex-wrap items-center gap-4 rounded-2xl border border-eo-line bg-white px-4 py-3 text-left transition hover:border-eo-teal/40 hover:shadow-eo">
            <span class="flex items-center gap-2 text-[12px] font-bold text-eo-text">
                <x-icon name="chart" class="h-4 w-4 text-eo-teal-ink" /> Mission Radar
            </span>
            <span class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11.5px] font-semibold text-eo-muted">
                @foreach ($radarStats as $stat)
                    <span class="flex items-center gap-1.5">
                        <span class="tabular-nums text-eo-text">{{ $stat['value'] }}</span> {{ $stat['label'] }}
                    </span>
                @endforeach
            </span>
            <span class="ms-auto flex items-center gap-1.5 text-[11.5px] font-bold text-eo-teal-ink">
                <span x-text="radarOpen ? 'Hide radar' : 'Open radar'"></span>
                <x-icon name="chevron" class="h-3 w-3 transition" x-bind:class="radarOpen ? '-rotate-90' : 'rotate-90'" />
            </span>
        </button>

        <div x-show="radarOpen" x-cloak x-collapse id="mission-radar" class="mt-4">
            <x-eo.mission-radar
                variant="hero"
                label="Mission Radar"
                story="Distance ≈ days out · colour ≈ health · cluster = missions sharing the same orbit window."
                :missions="$radarMissions ?: null"
                :stats="$radarStats"
            />
        </div>
    </div>

    {{-- ══════════ 3 · MAIN GRID ══════════ --}}
    <div class="grid gap-4 xl:grid-cols-12">

        {{-- LEFT — Today's Command Queue --}}
        <div class="space-y-3 xl:col-span-4">
            <p class="eo-label">Today's Command Queue</p>
            <div class="space-y-3">
                @php
                    $queueGroups = [
                        ['key' => 'approvals', 'label' => 'Approvals', 'icon' => 'identification', 'tone' => 'warn',
                            'empty' => 'Nothing waiting on you'],
                        ['key' => 'overdue', 'label' => 'Overdue Tasks', 'icon' => 'clipboard', 'tone' => 'risk',
                            'empty' => 'Nothing overdue'],
                        ['key' => 'dueToday', 'label' => 'Due Today', 'icon' => 'clock', 'tone' => 'warn',
                            'empty' => 'Nothing due today'],
                        ['key' => 'risks', 'label' => 'Open Risks', 'icon' => 'bell', 'tone' => 'risk',
                            'empty' => 'No open risks'],
                        ['key' => 'paymentIssues', 'label' => 'Payment Issues', 'icon' => 'card', 'tone' => 'warn',
                            'empty' => 'Nothing outstanding'],
                    ];
                @endphp
                @foreach ($queueGroups as $g)
                    @php $items = $queue[$g['key']]; @endphp
                    <x-eo.soft-card class="!p-3.5">
                        <div class="mb-2 flex items-center gap-2">
                            <x-icon :name="$g['icon']" class="h-3.5 w-3.5 text-eo-muted" />
                            <p class="text-[12px] font-bold text-eo-text">{{ $g['label'] }}</p>
                            @if ($items->isNotEmpty())
                                <x-eo.status-pill :tone="$g['tone']" class="ms-auto !text-[10px]">{{ $items->count() }}</x-eo.status-pill>
                            @endif
                        </div>

                        @if ($items->isEmpty())
                            <p class="text-[11.5px] text-eo-muted">{{ $g['empty'] }}</p>
                        @else
                            <div class="space-y-1.5">
                                @foreach ($items->take(3) as $item)
                                    @if ($g['key'] === 'approvals')
                                        <a href="{{ route('events.hub', [$item->event, 'tab' => 'approvals']) }}" class="block truncate text-[11.5px] font-semibold text-eo-text hover:text-eo-teal-ink">
                                            {{ $item->title }} <span class="font-normal text-eo-muted">· {{ $item->event->name }}</span>
                                        </a>
                                    @elseif (in_array($g['key'], ['overdue', 'dueToday'], true))
                                        <a href="{{ route('events.hub', [$item->event, 'tab' => 'tasks']) }}" class="block truncate text-[11.5px] font-semibold text-eo-text hover:text-eo-teal-ink">
                                            {{ $item->title }} <span class="font-normal text-eo-muted">· {{ $item->event->name }}</span>
                                        </a>
                                    @elseif ($g['key'] === 'risks')
                                        <a href="{{ route('events.hub', [$item['event'], 'tab' => 'risks']) }}" class="block truncate text-[11.5px] font-semibold text-eo-text hover:text-eo-teal-ink">
                                            {{ $item['risk']->title }} <span class="font-normal text-eo-muted">· {{ $item['event']->name }}</span>
                                        </a>
                                    @else
                                        <a href="{{ route('events.hub', [$item->event, 'tab' => 'contract']) }}" class="block truncate text-[11.5px] font-semibold text-eo-text hover:text-eo-teal-ink">
                                            {{ $item->label }} <span class="font-normal text-eo-muted">· {{ $item->event->name }}</span>
                                        </a>
                                    @endif
                                @endforeach
                                @if ($items->count() > 3)
                                    <p class="text-[10.5px] text-eo-muted">+{{ $items->count() - 3 }} more</p>
                                @endif
                            </div>
                        @endif
                    </x-eo.soft-card>
                @endforeach
            </div>
        </div>

        {{-- CENTER — Nearest Missions --}}
        <div class="space-y-3 xl:col-span-4">
            <div class="flex items-center justify-between gap-2">
                <p class="eo-label">Nearest missions</p>
                <a href="{{ route('events.index') }}" class="text-[12px] font-semibold text-eo-teal-ink hover:underline">Event Portfolio →</a>
            </div>
            @forelse ($nearestMissions as $i => $mission)
                @if ($i === 0)
                    <x-eo.mission-card :mission="$mission" variant="hero" />
                @else
                    <x-eo.mission-card :mission="$mission" variant="compact" />
                @endif
            @empty
                <x-eo.empty-state title="No dated missions" hint="Add dates in Event Studio to populate the floor." />
            @endforelse
        </div>

        {{-- RIGHT — Executive Intelligence --}}
        <div class="space-y-4 xl:col-span-4" id="live-alerts">
            <x-eo.detail-panel title="Command Briefing" subtitle="Rule-based advisor — what needs a person, worst first">
                <x-slot:header>
                    <x-eo.status-pill tone="{{ $signalCount ? 'warn' : 'ok' }}">{{ $signalCount }}</x-eo.status-pill>
                </x-slot:header>

                <div class="space-y-2">
                    @forelse ($signals as $signal)
                        <a href="{{ $signal['href'] }}" class="block">
                            <x-eo.alert-card
                                :tone="($signal['tone'] ?? '') === 'red' ? 'risk' : (($signal['tone'] ?? '') === 'amber' ? 'warn' : 'info')"
                                :title="$signal['title']"
                            >
                                {{ $signal['where'] }} · {{ $signal['why'] }}
                            </x-eo.alert-card>
                        </a>
                    @empty
                        <p class="text-[13px] text-eo-muted">All clear — no critical signals on the briefing.</p>
                    @endforelse
                </div>

                <x-slot:footer>
                    <x-eo.button variant="ghost" size="sm" href="{{ route('ai.index') }}" class="w-full">Open Command Briefing</x-eo.button>
                </x-slot:footer>
            </x-eo.detail-panel>

            <x-eo.soft-card>
                <p class="eo-label mb-3">Money to collect</p>
                @forelse ($receivables as $r)
                    <a href="{{ route('events.hub', [$r->event, 'tab' => 'contract']) }}" class="flex items-center justify-between gap-2 border-b border-eo-line py-2 text-[12px] last:border-b-0">
                        <span class="min-w-0 truncate font-semibold text-eo-text">{{ $r->event->name }}</span>
                        <span @class(['shrink-0 font-bold tabular-nums', 'text-eo-risk-ink' => $r->status() === 'overdue', 'text-eo-text' => $r->status() !== 'overdue'])>
                            {{ $money($r->outstandingCents(), $r->event->currency ?? 'JOD') }}
                        </span>
                    </a>
                @empty
                    <p class="text-[11.5px] text-eo-muted">Nothing outstanding.</p>
                @endforelse
            </x-eo.soft-card>
        </div>
    </div>

    {{-- ══════════ 4 · WEEK AHEAD ══════════ --}}
    <x-eo.soft-card class="overflow-hidden p-0" :padding="false">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-eo-line px-5 py-4">
            <div>
                <p class="eo-label">The week ahead</p>
                <p class="mt-1 text-[15px] font-semibold text-eo-text">Sessions · movements · deadlines</p>
            </div>
            <span class="eo-journey-chip">7-day strip</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7">
            @foreach ($week as $day)
                <div @class([
                    'border-eo-line px-3 py-4 sm:border-r',
                    'bg-eo-teal-soft/40' => $day['today'],
                ])>
                    <p class="eo-label">{{ $day['date']->format('D') }}</p>
                    <p class="mt-1 text-[20px] font-bold tabular-nums text-eo-text">{{ $day['date']->format('j') }}</p>
                    <p class="mt-2 text-[12px] text-eo-muted">
                        {{ $day['load'] ? $day['load'].' items' : 'Clear' }}
                    </p>
                    @if ($day['starting']->isNotEmpty())
                        <p class="mt-1 truncate text-[11px] font-semibold text-eo-teal-ink">
                            {{ $day['starting']->first()->name }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </x-eo.soft-card>
</div>
