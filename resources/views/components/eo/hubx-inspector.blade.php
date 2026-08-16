@props(['event', 'header', 'tab'])

{{--
    Universal Module Inspector — one shell, per-module data. Every module
    that reaches this component gets the same structure (icon, status,
    readiness, metrics, ownership, next action, recent activity, quick
    links); only the values inside change per $tab. Modules without a rich
    metric set fall back honestly rather than inventing figures.

    "Ownership" reads the event's own project manager — there is no
    per-module owner field in the data model, so this is labelled "Event
    Owner" rather than claiming a role (e.g. "Agenda Manager") the schema
    doesn't have.
--}}

@php
    $modules = \App\Models\Event::HUB_TABS;
    // Overview has no metrics of its own to inspect — Agenda is the
    // default landing module here, same as the reference mockup shows.
    $inspectTab = $tab === 'overview' ? 'agenda' : $tab;
    [$moduleLabel,, $moduleIcon] = $modules[$inspectTab] ?? [ucfirst($inspectTab), '', 'archive'];
    $moduleColor = \App\Models\Event::moduleColor($inspectTab);

    $metersByKey = collect($header['meters'] ?? [])->keyBy('key');
    $meterAlias = ['transportation' => 'logistics', 'venue' => 'logistics', 'suppliers' => 'logistics'];
    $pct = $metersByKey->get($meterAlias[$inspectTab] ?? $inspectTab)['pct'] ?? null;

    $statusWord = fn (?int $p) => match (true) {
        $p === null || $p === 0 => 'Not started',
        $p >= 60 => 'On Track',
        default => 'At Risk',
    };
    $statusColor = fn (?int $p) => match (true) {
        $p === null || $p === 0 => '#94A3B8',
        $p >= 60 => 'var(--color-eo-ok)',
        default => 'var(--color-eo-risk)',
    };

    $critical = $header['critical'];
    // A module's own Next Action, when EventCommandHeader's single
    // event-wide critical() item happens to belong to this tab — never a
    // fabricated per-module action.
    $moduleNextAction = ($critical && ($critical['tab'] ?? null) === $inspectTab) ? $critical : null;

    // Metrics + quick links, per module — real relations already on
    // $event, no new queries beyond what each block needs.
    [$metrics, $quickLinks, $auditTypes] = match ($inspectTab) {
        'agenda' => [
            [
                ['icon' => 'clipboard', 'value' => $event->agendaSessions->count(), 'label' => 'Sessions'],
                ['icon' => 'bell', 'value' => $event->agendaSessions->reject->isSettled()->count(), 'label' => 'Not settled'],
                ['icon' => 'users', 'value' => $event->attendees->count(), 'label' => 'Attendees'],
                ['icon' => 'sparkles', 'value' => $event->speakers->where('status', 'confirmed')->count().'/'.$event->speakers->count(), 'label' => 'Speakers confirmed'],
            ],
            [['label' => 'Sessions', 'tab' => 'agenda', 'icon' => 'calendar'], ['label' => 'Speakers', 'tab' => 'speakers', 'icon' => 'sparkles'], ['label' => 'Documents', 'tab' => 'files', 'icon' => 'archive']],
            [\App\Models\EventAgendaSession::class, \App\Models\EventAgendaDay::class],
        ],
        'budget' => [
            (function () use ($event) {
                $cost = $event->costForecast();
                $committed = (int) $event->budgetItems->sum('actual_cents');

                return [
                    ['icon' => 'currency', 'value' => $event->money($cost['forecast']), 'label' => 'Forecast'],
                    ['icon' => 'currency', 'value' => $event->money($committed), 'label' => 'Committed'],
                    ['icon' => 'chart', 'value' => $cost['cap'] > 0 ? $cost['pct'].'%' : '—', 'label' => 'Of cap'],
                    ['icon' => 'currency', 'value' => $cost['over'] > 0 ? $event->money($cost['over']) : $event->money(0), 'label' => 'Over cap'],
                ];
            })(),
            [['label' => 'Budget', 'tab' => 'budget', 'icon' => 'currency'], ['label' => 'Pricing', 'tab' => 'pricing', 'icon' => 'archive'], ['label' => 'Reports', 'tab' => 'reports', 'icon' => 'chart']],
            [\App\Models\EventBudgetItem::class, \App\Models\EventBudgetVersion::class],
        ],
        'transportation' => [
            [
                ['icon' => 'truck', 'value' => $event->transport->count(), 'label' => 'Movements'],
                ['icon' => 'bell', 'value' => $event->transport->reject(fn ($m) => in_array($m->status, ['completed', 'cancelled'], true))->reject->isReady()->count(), 'label' => 'Not ready'],
                ['icon' => 'users', 'value' => $event->transferGuests->count(), 'label' => 'Guests in pool'],
                ['icon' => 'clipboard', 'value' => $event->transferGuests->whereNull('transport_id')->count(), 'label' => 'Unassigned'],
            ],
            [['label' => 'Transport', 'tab' => 'transportation', 'icon' => 'truck'], ['label' => 'Suppliers', 'tab' => 'suppliers', 'icon' => 'archive']],
            [\App\Models\EventTransport::class],
        ],
        'approvals' => [
            (function () use ($event) {
                $pending = $event->approvals->where('status', 'pending');
                $oldest = $pending->sortBy('created_at')->first();

                return [
                    ['icon' => 'identification', 'value' => $pending->count(), 'label' => 'Pending'],
                    ['icon' => 'clipboard', 'value' => $event->approvals->where('status', 'approved')->count(), 'label' => 'Approved'],
                    ['icon' => 'bell', 'value' => $event->approvals->where('status', 'rejected')->count(), 'label' => 'Rejected'],
                    ['icon' => 'clock', 'value' => $oldest ? (int) $oldest->created_at->diffInDays(now()).'d' : '—', 'label' => 'Oldest waiting'],
                ];
            })(),
            [['label' => 'Approvals', 'tab' => 'approvals', 'icon' => 'identification'], ['label' => 'Contract', 'tab' => 'contract', 'icon' => 'archive']],
            [\App\Models\EventApproval::class],
        ],
        default => [null, [['label' => $moduleLabel, 'tab' => $inspectTab, 'icon' => $moduleIcon]], []],
    };

    $owner = $event->projectManager;

    $recent = collect();
    if ($auditTypes !== []) {
        $recent = \App\Models\AuditLog::where('event_id', $event->id)
            ->whereIn('auditable_type', $auditTypes)
            ->with('user')->latest()->take(3)->get();
    }
@endphp

<div class="hubx-panel">
    <div class="hubx-panel-head">
        <span class="hubx-panel-icon" style="background: color-mix(in srgb, {{ $moduleColor }} 16%, transparent); color: {{ $moduleColor }}">
            <x-icon :name="$moduleIcon" class="h-4 w-4" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="eo-label !text-[9.5px]">Inspector</p>
            <p class="text-[14px] font-extrabold text-eo-text">{{ $moduleLabel }}</p>
        </div>
        <span class="hubx-pill" style="background: color-mix(in srgb, {{ $statusColor($pct) }} 16%, transparent); color: {{ $statusColor($pct) }}">
            {{ $statusWord($pct) }}
        </span>
    </div>

    @if ($pct !== null)
        <div class="hubx-panel-ring-wrap" style="--pr-pct: {{ $pct }}">
            <div class="hubx-panel-ring-inner">
                <span class="text-[20px] font-extrabold text-eo-text">{{ $pct }}%</span>
                <span class="text-[9px] font-bold uppercase tracking-wide text-eo-muted">Ready</span>
            </div>
        </div>
    @endif

    @if ($metrics)
        <div class="mt-2">
            @foreach ($metrics as $m)
                <div class="hubx-panel-metric-row">
                    <x-icon :name="$m['icon']" class="h-3.5 w-3.5 text-eo-muted" />
                    <span class="text-[12px] font-bold text-eo-text">{{ $m['value'] }}</span>
                    <span class="text-[11px] text-eo-muted">{{ $m['label'] }}</span>
                </div>
            @endforeach
        </div>
    @else
        <p class="mt-3 text-[11.5px] text-eo-muted">No dedicated metrics for this module yet — open it directly for the full picture.</p>
    @endif

    @if ($owner)
        <div class="mt-3">
            <p class="eo-label !text-[9.5px]">Ownership</p>
            <div class="mt-1.5 flex items-center gap-2">
                <x-user-avatar :user="$owner" size="h-8 w-8" text="text-[10px]" />
                <div class="min-w-0">
                    <p class="truncate text-[12px] font-bold text-eo-text">{{ $owner->name }}</p>
                    <p class="text-[10px] text-eo-muted">Event Owner</p>
                </div>
            </div>
        </div>
    @endif

    @if ($moduleNextAction)
        <div class="hubx-panel-next">
            <p class="text-[9.5px] font-bold uppercase tracking-wide text-white/60">Next Action</p>
            <p class="mt-1 text-[13px] font-bold">{{ $moduleNextAction['title'] }}</p>
            <p class="mt-0.5 text-[11px] text-white/60">{{ $moduleNextAction['due'] }} · {{ $moduleNextAction['level'] }} impact</p>
            <a href="{{ route('events.hub', [$event, 'tab' => $moduleNextAction['tab']]) }}" wire:navigate
               class="mt-2.5 inline-flex items-center justify-center rounded-lg bg-white px-3 py-1.5 text-[11px] font-bold text-eo-navy-deep">
                {{ $moduleNextAction['cta'] }}
            </a>
        </div>
    @endif

    @if ($recent->isNotEmpty())
        <div class="mt-3">
            <p class="eo-label !text-[9.5px]">Recent Activity</p>
            @foreach ($recent as $entry)
                <div class="hubx-panel-activity-row">
                    <x-user-avatar :user="$entry->user" size="h-6 w-6" text="text-[9px]" />
                    <div class="min-w-0">
                        <p class="truncate text-[11.5px] font-semibold text-eo-text">{{ $entry->summary() }}</p>
                        <p class="text-[10px] text-eo-muted">{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if (count($quickLinks) > 1)
        <div class="mt-3">
            <p class="eo-label !text-[9.5px]">Quick Links</p>
            <div class="mt-1.5 grid grid-cols-3 gap-1.5">
                @foreach ($quickLinks as $link)
                    <a href="{{ route('events.hub', [$event, 'tab' => $link['tab']]) }}" wire:navigate
                       class="flex flex-col items-center gap-1 rounded-xl border border-eo-line bg-white/70 px-2 py-2 text-center transition hover:border-eo-teal">
                        <x-icon :name="$link['icon']" class="h-3.5 w-3.5 text-eo-muted" />
                        <span class="text-[9.5px] font-bold text-eo-text">{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <a href="{{ route('events.hub', [$event, 'tab' => $inspectTab]) }}" wire:navigate class="hubx-stack-viewall mt-2" style="color: var(--color-eo-teal-ink); background: var(--color-eo-teal-soft);">
        Open {{ $moduleLabel }} →
    </a>
</div>
