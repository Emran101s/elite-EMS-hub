@props(['event'])

@php
    use Illuminate\Support\Carbon;

    // Severity mapping mirrors EventMission's own risk-word thresholds
    // (severity is probability × impact, 1–25) — the same numbers already
    // used everywhere else a risk gets a word instead of a score.
    $riskTier = fn (int $severity) => match (true) {
        $severity >= 20 => 'Critical',
        $severity >= 12 => 'High',
        $severity > 0 => 'Medium',
        default => 'Low',
    };

    $today = Carbon::today();
    $openRisks = $event->risks->filter->isOpen()
        ->sortByDesc(fn ($r) => $r->severity())
        ->map(fn ($r) => [
            'title' => $r->title,
            'meta' => str($r->category ?: 'Risk register')->replace('_', ' ')->title()->toString(),
            'tier' => $riskTier($r->severity()),
            'href' => route('events.hub', [$event, 'tab' => 'risks']),
        ]);

    // Approval urgency by age — extending PortfolioAdvisor's own 7-day
    // "critical" threshold into four tiers rather than its two.
    $pendingApprovals = $event->approvals->where('status', 'pending')
        ->sortBy('created_at')
        ->map(function ($a) use ($today, $event) {
            $waiting = (int) $a->created_at->diffInDays($today);

            return [
                'title' => $a->title,
                'meta' => str($a->type)->replace('_', ' ')->title().' · waiting '.$waiting.' '.str('day')->plural($waiting),
                'tier' => match (true) {
                    $waiting >= 14 => 'Critical',
                    $waiting >= 7 => 'High',
                    $waiting >= 3 => 'Medium',
                    default => 'Low',
                },
                'href' => route('events.hub', [$event, 'tab' => 'approvals']),
            ];
        });

    // Escalations: operational problems that have surfaced, distinct from a
    // formal risk or a decision waiting on approval. Same predicates
    // PortfolioAdvisor already applies portfolio-wide (docs/…"blocked" /
    // "late" / supplier and transport rules) — re-read here for one event
    // rather than calling that shared service, so its return shape for
    // Command Center's Command Briefing stays exactly as it is.
    $overdueTasks = $event->tasks->filter(fn ($t) => $t->isOpen() && $t->due_on?->isPast());
    $supplierIssues = $event->suppliers->filter(fn ($s) => $s->pivot->status === 'issue');
    $transportUnready = $event->transport
        ->reject(fn ($m) => in_array($m->status, ['completed', 'cancelled'], true))
        ->reject->isReady();

    $escalations = collect();
    if ($overdueTasks->isNotEmpty()) {
        $worst = $overdueTasks->sortBy('due_on')->first();
        $escalations->push([
            'title' => $overdueTasks->count().' overdue '.str('task')->plural($overdueTasks->count()),
            'meta' => 'Oldest: "'.str($worst->title)->limit(40).'", due '.$worst->due_on->format('j M'),
            'tier' => 'High',
            'href' => route('events.hub', [$event, 'tab' => 'tasks']),
        ]);
    }
    foreach ($supplierIssues as $supplier) {
        $escalations->push([
            'title' => 'Supplier issue: '.$supplier->name,
            'meta' => 'Flagged on the supplier board',
            'tier' => 'Medium',
            'href' => route('events.hub', [$event, 'tab' => 'suppliers']),
        ]);
    }
    if ($transportUnready->isNotEmpty()) {
        $escalations->push([
            'title' => $transportUnready->count().' '.str('movement')->plural($transportUnready->count()).' without a driver or vehicle',
            'meta' => 'Cannot run as booked',
            'tier' => 'High',
            'href' => route('events.hub', [$event, 'tab' => 'transportation']),
        ]);
    }

    $tierTone = fn (string $tier) => match ($tier) {
        'Critical' => 'risk', 'High' => 'warn', 'Medium' => 'warn', default => 'ok',
    };

    $groups = [
        ['label' => 'Open Risks', 'icon' => 'bell', 'items' => $openRisks, 'empty' => 'No open risks'],
        ['label' => 'Pending Approvals', 'icon' => 'identification', 'items' => $pendingApprovals, 'empty' => 'Nothing waiting on a decision'],
        ['label' => 'Event Escalations', 'icon' => 'chat', 'items' => $escalations, 'empty' => 'Nothing escalated'],
    ];

    $totalCount = $openRisks->count() + $pendingApprovals->count() + $escalations->count();
@endphp

{{-- Priority Area — Phase E. What needs a person on this one event,
     scoped down from Command Center's Today's Command Queue, same pattern. --}}
<div {{ $attributes->class(['eo-domain-card eo-priority-area']) }}>
    <div class="mb-3 flex items-center justify-between gap-2">
        <p class="eo-label">Priority Area</p>
        @if ($totalCount)
            <x-eo.status-pill tone="warn" class="!text-[10px]">{{ $totalCount }}</x-eo.status-pill>
        @else
            <x-eo.status-pill tone="ok" class="!text-[10px]">Clear</x-eo.status-pill>
        @endif
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        @foreach ($groups as $g)
            <div class="rounded-2xl bg-eo-workspace px-3.5 py-3">
                <div class="mb-2 flex items-center gap-1.5">
                    <x-icon :name="$g['icon']" class="h-3.5 w-3.5 text-eo-muted" />
                    <p class="text-[11.5px] font-bold text-eo-text">{{ $g['label'] }}</p>
                    @if ($g['items']->isNotEmpty())
                        <span class="ms-auto rounded-full bg-white px-1.5 py-0.5 text-[10px] font-bold text-eo-muted">{{ $g['items']->count() }}</span>
                    @endif
                </div>

                @if ($g['items']->isEmpty())
                    <p class="text-[11px] text-eo-muted">{{ $g['empty'] }}</p>
                @else
                    <div class="space-y-1.5">
                        @foreach ($g['items']->take(3) as $item)
                            <a href="{{ $item['href'] }}" class="block rounded-lg bg-white px-2 py-1.5 transition hover:-translate-y-px hover:shadow-sm">
                                <span class="flex items-start justify-between gap-2">
                                    <span class="min-w-0 truncate text-[11px] font-semibold text-eo-text">{{ $item['title'] }}</span>
                                    <x-eo.status-pill :tone="$tierTone($item['tier'])" class="shrink-0 !text-[8.5px]">{{ $item['tier'] }}</x-eo.status-pill>
                                </span>
                                <span class="mt-0.5 block truncate text-[10px] text-eo-muted">{{ $item['meta'] }}</span>
                            </a>
                        @endforeach
                        @if ($g['items']->count() > 3)
                            <p class="px-2 text-[10px] text-eo-muted">+{{ $g['items']->count() - 3 }} more</p>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
