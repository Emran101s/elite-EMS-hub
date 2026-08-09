@php
    $user = auth()->user();
    $hour = (int) $now->copy()->setTimeFrom(now())->format('H');
    $greeting = match (true) { $hour < 12 => 'Good morning', $hour < 18 => 'Good afternoon', default => 'Good evening' };

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

    $radarStats = [
        ['value' => $events->count(), 'label' => 'Missions'],
        ['value' => $events->where('stage', 'live')->count(), 'label' => 'Live', 'tone' => 'live'],
        ['value' => $figures[3]['value'] ?? 0, 'label' => 'At risk', 'tone' => 'risk'],
        ['value' => $signalCount, 'label' => 'Signals', 'tone' => $signalCount ? 'warn' : 'ok'],
    ];

    $nearest = $events
        ->filter(fn ($e) => $e->starts_at)
        ->sortBy('starts_at')
        ->take(4);

    $dnaTypes = ['Conferences', 'Summits', 'Exhibitions', 'Forums', 'VIP', 'Delegate journeys'];
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

    <x-eo.page-header
        eyebrow="{{ $now->format('l · j F Y') }}"
        title="{{ $greeting }}, {{ str($user->name)->before(' ') }}"
        subtitle="{{ $headline }}"
    >
        <x-slot:actions>
            <span class="eo-journey-chip">Command Center</span>
            <x-eo.button variant="ghost" size="sm" href="{{ route('ai.index') }}">Open briefing</x-eo.button>
            <x-eo.button size="sm" href="{{ route('events.create') }}">New mission</x-eo.button>
        </x-slot:actions>
    </x-eo.page-header>

    <div class="eo-dna-strip px-1">
        @foreach ($dnaTypes as $dna)
            <span class="eo-dna-pill">{{ $dna }}</span>
        @endforeach
    </div>

    {{-- Hero Mission Radar — Orbit identity --}}
    <div id="mission-radar" class="grid gap-4 xl:grid-cols-12">
        <div class="xl:col-span-7">
            <x-eo.mission-radar
                variant="hero"
                label="Mission Radar"
                story="Distance ≈ days out · colour ≈ health · cluster = missions sharing the same orbit window."
                :missions="$radarMissions ?: null"
                :stats="$radarStats"
            />
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:col-span-5 xl:grid-cols-1">
            @foreach ($figures as $f)
                @php
                    $tone = match ($f['tone'] ?? '') {
                        'green' => 'ok',
                        'red' => 'risk',
                        'gold', 'amber' => 'warn',
                        'blue' => 'live',
                        default => null,
                    };
                @endphp
                <a href="{{ $f['href'] ?? '#' }}" class="block transition hover:-translate-y-0.5">
                    <x-eo.metric-pill
                        :label="$f['label']"
                        :value="$f['value']"
                        :hint="$f['note']"
                        :tone="$tone"
                    />
                </a>
            @endforeach

            <x-eo.operations-card
                title="Today across the book"
                subtitle="{{ $now->format('l, j F') }}"
                :open="$today['tasks']"
                :due="$today['sessions']"
                :blocked="$today['movements']"
            />
        </div>
    </div>

    {{-- Week strip --}}
    <x-eo.soft-card class="overflow-hidden p-0" :padding="false">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-eo-line px-5 py-4">
            <div>
                <p class="eo-label">The week ahead</p>
                <p class="mt-1 text-[15px] font-semibold text-eo-text">Sessions · movements · deadlines</p>
            </div>
            <span class="eo-journey-chip">Event DNA</span>
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

    <div class="grid gap-4 xl:grid-cols-12">
        {{-- Nearest missions --}}
        <div class="space-y-3 xl:col-span-7">
            <div class="flex items-center justify-between gap-2">
                <p class="eo-label">Nearest missions</p>
                <a href="{{ route('events.index') }}" class="text-[12px] font-semibold text-eo-teal-ink hover:underline">Event Portfolio →</a>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                @forelse ($nearest as $event)
                    <x-eo.mission-card
                        :title="$event->name"
                        :type="str($event->type ?? 'Event')->replace('_', ' ')->title()"
                        :stage="\App\Support\Workflow::label('event_stage', $event->stage)"
                        :venue="$event->city"
                        :dates="$event->starts_at?->format('j M Y')"
                        :readiness="(int) ($event->progress ?? 0)"
                        :health="$event->stage === 'live' ? 'live' : (in_array($event->stage, ['production', 'planning'], true) ? 'warn' : 'ok')"
                        :href="route('events.hub', $event)"
                    />
                @empty
                    <x-eo.empty-state title="No dated missions" hint="Add dates in Event Studio to populate the floor." />
                @endforelse
            </div>
        </div>

        {{-- Signals --}}
        <div class="xl:col-span-5" id="live-alerts">
            <x-eo.detail-panel title="Signals" subtitle="What needs a person across the book">
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
                    <x-eo.button variant="ghost" size="sm" href="{{ route('ai.index') }}" class="w-full">Open full briefing</x-eo.button>
                </x-slot:footer>
            </x-eo.detail-panel>
        </div>
    </div>

    {{-- Book by stage + commercial snapshot --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <x-eo.soft-card>
            <p class="eo-label">The book · by stage</p>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($stages as $stage)
                    @if ($stage['count'] > 0)
                        <a href="{{ route('events.index') }}" class="eo-soft-card flex items-center gap-2 px-3 py-2 shadow-none ring-1 ring-eo-line">
                            <span class="h-2 w-2 rounded-full" style="background: {{ $stage['hex'] }}"></span>
                            <span class="text-[13px] font-semibold text-eo-text">{{ $stage['label'] }}</span>
                            <span class="text-[12px] font-bold text-eo-muted">{{ $stage['count'] }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </x-eo.soft-card>

        @php
            $moneySym = \App\Models\Event::CURRENCIES[$money['currency'] ?? 'JOD'][0] ?? '';
            $fmtK = fn (int $cents) => $moneySym.number_format($cents / 100000, 0).'K';
        @endphp
        <x-eo.commercial-card
            title="Portfolio commercial"
            subtitle="Income across the book{{ ! empty($money['mixed']) ? ' · mixed currencies' : '' }}"
            :value="$fmtK((int) ($money['income'] ?? 0))"
            :meta="'Cost '.$fmtK((int) ($money['cost'] ?? 0)).' · Margin '.(($money['margin'] ?? null) !== null ? $money['margin'].'%' : '—')"
        />
    </div>
</div>
