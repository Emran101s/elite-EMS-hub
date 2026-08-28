@php
    $user = auth()->user();
    $hour = (int) $now->copy()->setTimeFrom(now())->format('H');
    $greeting = match (true) { $hour < 12 => 'Good morning', $hour < 18 => 'Good afternoon', default => 'Good evening' };

    $kpiTone = fn (string $tone) => match ($tone) {
        'red' => 'risk', 'amber' => 'warn', 'green' => 'ok', 'blue' => 'live', default => null,
    };

    // NB: viewData also passes a $money array (PortfolioFinance totals); keep
    // it reachable by naming the formatting helper $fmt rather than shadowing.
    $fmt = fn (int $cents, string $cur = 'JOD') => \App\Support\Money::abbreviated($cents, $cur);
@endphp

<div class="space-y-6">

    @if ($events->isEmpty())
        <x-eo.empty-state
            title="Nothing on the book yet"
            hint="Once you add a summit, forum, or exhibition, readiness signals surface here."
            icon="sparkles"
        >
            <x-slot:actions>
                <x-eo.button href="{{ route('events.create') }}">Create your first mission</x-eo.button>
            </x-slot:actions>
        </x-eo.empty-state>
    @endif

    {{-- ══════════ 1 · EXECUTIVE HEADER ══════════ --}}
    <x-cc.header
        :eyebrow="$now->format('l · j F Y')"
        :title="$greeting.', '.str($user->name)->before(' ')"
        :subtitle="$headline"
    >
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[10.5px] font-bold uppercase tracking-wide text-gold-700">
                <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> Command Center
            </span>
            {{-- One way in, not three. This header carried "Open Command
                 Briefing" and "✦ Command Briefing" side by side — the same
                 destination, near-identical labels — and the Briefing card
                 further down the page repeats the link a third time. --}}
            <a href="{{ route('ai.index') }}" class="rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:-translate-y-0.5 hover:bg-gold-400">✦ Command Briefing</a>
        </x-slot:actions>
    </x-cc.header>

    {{-- ══════════ 2 · KPI STRIP ══════════ --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($kpis as $k)
            <a href="{{ $k['href'] ?? '#' }}" class="block">
                <x-cc.kpi-tile :label="$k['label']" :value="$k['value']" :hint="$k['note']" :tone="$kpiTone($k['tone'] ?? '')" />
            </a>
        @endforeach
    </div>

    {{-- ══════════ 2b · PORTFOLIO PULSE — lifecycle + cashflow at a glance ══════════ --}}
    @if ($events->isNotEmpty())
        <x-cc.portfolio-pulse :stages="$stages" />
    @endif

    {{-- ══════════ 3 · MAIN GRID ══════════ --}}
    <div class="grid gap-4 xl:grid-cols-12">

        {{-- LEFT — Today's Command Queue --}}
        <div class="space-y-3 xl:col-span-4">
            <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Today's Command Queue</p>
            <div class="space-y-3">
                @php
                    // Approvals are not listed here: every pending one is a
                    // Command Briefing signal in the column to the right, with
                    // the waiting time this list never showed. The KPI strip
                    // still counts them. Open Risks below carries only those
                    // scoring under 15 — the louder ones are Briefing signals
                    // too, and were being listed twice.
                    $queueGroups = [
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
                    <x-cc.queue-card :icon="$g['icon']" :label="$g['label']" :tone="$g['tone']" :count="$items->count()" :empty="$g['empty']">
                        @foreach ($items->take(3) as $item)
                            @if ($g['key'] === 'approvals')
                                <a href="{{ route('events.hub', [$item->event, 'tab' => 'approvals']) }}" class="block truncate text-[11.5px] font-semibold text-ink hover:text-gold-700">
                                    {{ $item->title }} <span class="font-normal text-muted">· {{ $item->event->name }}</span>
                                </a>
                            @elseif (in_array($g['key'], ['overdue', 'dueToday'], true))
                                <a href="{{ route('events.hub', [$item->event, 'tab' => 'tasks']) }}" class="block truncate text-[11.5px] font-semibold text-ink hover:text-gold-700">
                                    {{ $item->title }} <span class="font-normal text-muted">· {{ $item->event->name }}</span>
                                </a>
                            @elseif ($g['key'] === 'risks')
                                <a href="{{ route('events.hub', [$item['event'], 'tab' => 'risks']) }}" class="block truncate text-[11.5px] font-semibold text-ink hover:text-gold-700">
                                    {{ $item['risk']->title }} <span class="font-normal text-muted">· {{ $item['event']->name }}</span>
                                </a>
                            @else
                                <a href="{{ route('events.hub', [$item->event, 'tab' => 'contract']) }}" class="block truncate text-[11.5px] font-semibold text-ink hover:text-gold-700">
                                    {{ $item->label }} <span class="font-normal text-muted">· {{ $item->event->name }}</span>
                                </a>
                            @endif
                        @endforeach
                        @if ($items->count() > 3)
                            <p class="text-[10.5px] text-muted">+{{ $items->count() - 3 }} more</p>
                        @endif
                    </x-cc.queue-card>
                @endforeach
            </div>
        </div>

        {{-- CENTER — Nearest Missions --}}
        <div class="space-y-3 xl:col-span-4">
            <div class="flex items-center justify-between gap-2">
                <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Nearest missions</p>
                <a href="{{ route('events.index') }}" class="text-[12px] font-semibold text-gold-700 hover:underline">Event Portfolio →</a>
            </div>
            @forelse ($nearestMissions as $i => $mission)
                @if ($i === 0)
                    <x-cc.mission-card :mission="$mission" variant="hero" />
                @else
                    <x-cc.mission-card :mission="$mission" variant="compact" />
                @endif
            @empty
                <x-eo.empty-state title="No dated missions" hint="Add dates in Event Studio to populate the floor." />
            @endforelse
        </div>

        {{-- RIGHT — Executive Intelligence --}}
        <div class="space-y-4 xl:col-span-4" id="live-alerts">
            <x-cc.briefing-panel title="Command Briefing" subtitle="Rule-based advisor — what needs a person, worst first">
                <x-slot:header>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $signalCount ? 'bg-warning-soft text-warning-ink' : 'bg-success-soft text-success-ink' }}">{{ $signalCount }}</span>
                </x-slot:header>

                <div class="space-y-2">
                    @forelse ($signals as $signal)
                        <a href="{{ $signal['href'] }}" class="block">
                            <x-cc.alert-row
                                :tone="($signal['tone'] ?? '') === 'red' ? 'risk' : (($signal['tone'] ?? '') === 'amber' ? 'warn' : 'info')"
                                :title="$signal['title']"
                            >
                                {{ $signal['where'] }} · {{ $signal['why'] }}
                            </x-cc.alert-row>
                        </a>
                    @empty
                        <p class="text-[13px] text-muted">All clear — no critical signals on the briefing.</p>
                    @endforelse
                </div>

                <x-slot:footer>
                    <a href="{{ route('ai.index') }}" class="block w-full rounded-full border border-line bg-white px-3.5 py-2 text-center text-[12px] font-bold text-ink transition hover:border-navy-300">Open Command Briefing</a>
                </x-slot:footer>
            </x-cc.briefing-panel>

            <div class="rounded-lg border border-line bg-white p-4">
                @php $collectMax = (int) $receivables->max(fn ($r) => $r->outstandingCents()); @endphp
                <div class="mb-3 flex items-baseline justify-between gap-2">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Money to collect</p>
                    @if ($receivables->isNotEmpty())
                        <span class="text-[12px] font-extrabold tabular-nums text-ink">{{ $fmt((int) $receivables->sum(fn ($r) => $r->outstandingCents())) }}</span>
                    @endif
                </div>
                @forelse ($receivables as $r)
                    @php
                        $out = (int) $r->outstandingCents();
                        $overdue = $r->status() === 'overdue';
                    @endphp
                    <a href="{{ route('events.hub', [$r->event, 'tab' => 'contract']) }}" class="group block py-2">
                        <div class="flex items-center justify-between gap-2 text-[12px]">
                            <span class="min-w-0 truncate font-semibold text-ink group-hover:text-gold-700">{{ $r->event->name }}</span>
                            <span @class(['shrink-0 font-bold tabular-nums', 'text-danger-ink' => $overdue, 'text-ink' => ! $overdue])>
                                {{ $fmt($out, $r->event->currency ?? 'JOD') }}
                            </span>
                        </div>
                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-page">
                            <span class="block h-full rounded-full {{ $overdue ? 'bg-danger' : 'bg-gold-500' }}" style="width: {{ $collectMax > 0 ? max(6, round($out / $collectMax * 100)) : 0 }}%"></span>
                        </div>
                        @if ($overdue)
                            <p class="mt-1 text-[10px] font-bold uppercase tracking-wide text-danger-ink">Overdue</p>
                        @endif
                    </a>
                @empty
                    <p class="text-[11.5px] text-muted">Nothing outstanding.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ══════════ 4 · WEEK AHEAD ══════════ --}}
    <x-cc.week-strip :week="$week" />
</div>
