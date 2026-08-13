@props([
    // The rich path — an EventMission::for() row (or anything shaped like one).
    'mission' => null,
    'variant' => 'hero', // hero | board | compact | list | hub
    'href' => null,

    // The hub path — EventCommandHeader::for($event), for variant="hub" only.
    // A different shape because it answers a different question: not "which
    // mission is this among many" but "what does this one need right now" —
    // critical() is already the next action, worst-first, with a level.
    'event' => null,
    'header' => null,

    // The legacy flat-prop path. Both of mission-card's two current callers
    // (dashboard.blade.php, events-index.blade.php) use this shape today —
    // kept working unchanged rather than migrated as part of building the
    // variant system. See the Phase B migration plan for when they move.
    'title' => null,
    'venue' => null,
    'dates' => null,
    'stage' => null,
    'type' => null,
    'readiness' => null,
    'health' => null,

    // variant="board" only: clicking the card selects it via wire:click,
    // naming the Livewire method to call with the mission's id, instead of
    // navigating away, so a detail panel elsewhere on the page can update
    // in place. Falls back to the plain navigating link when no select
    // action is given, so any other caller keeps today's behaviour
    // untouched.
    'selected' => false,
    'selectAction' => null,
])

@php
    // One normalized shape from here down, whichever door the caller came
    // through — every variant below reads $m and nothing else.
    if ($header) {
        $today = \Illuminate\Support\Carbon::today();
        $critical = $header['critical'] ?? null;
        $urgencyToneFromLevel = fn (?string $level) => match ($level) {
            'Critical' => 'risk', 'High' => 'warn', 'Medium' => 'warn', 'Low' => 'ok', default => null,
        };

        $m = [
            'name' => $event->name,
            'client' => $event->client?->name,
            'daysOut' => $event->starts_at ? (int) $today->diffInDays($event->starts_at, false) : null,
            'dates' => null,
            'where' => null,
            'type' => null,
            'statusLabel' => null,
            'healthScore' => $header['health']['score'] ?? null,
            'healthGroup' => $header['health']['group'] ?? null,
            'progress' => $header['readiness']['pct'] ?? null,
            'budgetLabel' => null,
            'budgetOf' => null,
            'budgetPct' => null,
            'milestone' => $critical ? [
                'title' => $critical['title'],
                'due' => $critical['due'],
                'owner' => $critical['owner'],
                'tone' => $urgencyToneFromLevel($critical['level'] ?? null),
            ] : null,
            // Hub-only field: the header's own "what's true right now" line —
            // the same one open/due/blocked triple operations-card used to
            // show, now one sentence instead of three raw numbers.
            'liveDesk' => $header['live']['label'] ?? null,
            'event' => $event,
        ];
    } else {
        $m = $mission ?? [
            'name' => $title,
            'client' => null,
            'daysOut' => null,
            'dates' => $dates,
            'where' => $venue,
            'type' => $type,
            'statusLabel' => $stage,
            'healthScore' => null,
            'healthGroup' => $health, // legacy already passed a tone, not a score
            'progress' => $readiness,
            'budgetLabel' => null,
            'budgetOf' => null,
            'budgetPct' => null,
            'milestone' => null,
            'event' => null,
        ];
    }

    $name = $m['name'] ?? $m['title'] ?? 'Untitled mission';
    $client = $m['client'] ?? null;
    $daysOut = $m['daysOut'] ?? null;
    $dateLabel = $m['shortDates'] ?? $m['dates'] ?? null;
    $where = $m['where'] ?? null;
    $type = $m['type'] ?? null;
    $stage = $m['statusLabel'] ?? $m['stage'] ?? null;
    $ready = is_null($m['progress'] ?? null) ? null : max(0, min(100, (int) $m['progress']));
    $healthScore = $m['healthScore'] ?? null;
    $healthGroup = $m['healthGroup'] ?? null; // risk | warn | ok | live | null
    $budgetLabel = $m['budgetLabel'] ?? null;
    $budgetOf = $m['budgetOf'] ?? null;
    $budgetPct = $m['budgetPct'] ?? null;
    $milestone = $m['milestone'] ?? null;
    $liveDesk = $m['liveDesk'] ?? null;

    // The hub variant lives on the event's own page already — a card that
    // links to the page it's on is a link to nowhere, dressed as one.
    $resolvedHref = $variant === 'hub' ? null : ($href ?? (($m['event'] ?? null) && \Illuminate\Support\Facades\Route::has('events.hub')
        ? route('events.hub', $m['event'])
        : null));

    $healthTone = match ($healthGroup) {
        'risk' => 'risk', 'warn' => 'warn', 'live' => 'live',
        'ok' => 'ok', default => $healthGroup ? 'live' : null,
    };
    $healthPillLabel = match ($healthTone) {
        'risk' => 'At risk', 'warn' => 'Watch', 'live' => 'Live', 'ok' => 'On track', default => null,
    };
    $ringHex = match ($healthTone) {
        'risk' => 'var(--color-eo-risk)', 'warn' => 'var(--color-eo-warn)',
        'ok' => 'var(--color-eo-ok)', default => 'var(--color-eo-teal)',
    };

    // Next Action urgency. `milestone.tone` is the real signal once
    // EventMission::milestone() is extended to compute it (see migration
    // plan — Due Today / Blocked need a due-date and a task/risk-blocked
    // check that don't exist yet); until then this falls back to the one
    // thing already computed, `overdue`.
    $urgency = $milestone['tone'] ?? (($milestone['overdue'] ?? false) ? 'overdue' : ($milestone ? 'ok' : null));
    [$urgencyLabel, $urgencyTone] = match ($urgency) {
        'overdue' => ['Overdue', 'risk'],
        'due-today' => ['Due today', 'warn'],
        'blocked' => ['Blocked', 'premium'],
        'ok' => ['On track', 'ok'],
        default => [null, null],
    };

    $ownerRaw = $milestone['owner'] ?? null;
    $ownerName = is_object($ownerRaw) ? ($ownerRaw->name ?? null) : $ownerRaw;
    $ownerInitials = $ownerName ? collect(explode(' ', trim($ownerName)))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') : null;

    $daysOutLabel = match (true) {
        $daysOut === null => null,
        $daysOut < 0 => 'In progress',
        $daysOut === 0 => 'Today',
        default => $daysOut.'d out',
    };

    $tag = $resolvedHref ? 'a' : 'div';
@endphp

@if ($variant === 'list')
    {{-- ═══ LIST — one row, every field, for List/Table view ═══ --}}
    <{{ $tag }} @if ($resolvedHref) href="{{ $resolvedHref }}" @endif
        {{ $attributes->class([
            'eo-mission-row grid grid-cols-[1.6fr_.9fr_.7fr_.9fr_.6fr_.7fr_.9fr_1.3fr] items-center gap-3 border-b border-eo-line px-4 py-3 text-[12.5px] transition last:border-b-0',
            'hover:bg-eo-workspace' => (bool) $resolvedHref,
        ]) }}
    >
        <div class="min-w-0">
            <p class="truncate font-semibold text-eo-text">{{ $name }}</p>
            @if ($client)<p class="truncate text-[11px] text-eo-muted">{{ $client }}</p>@endif
        </div>
        <span class="text-eo-muted">{{ $daysOutLabel ?? '—' }}</span>
        <span class="text-eo-muted">{{ $dateLabel ?? '—' }}</span>
        <span class="truncate text-eo-muted">{{ $where ?? '—' }}</span>
        <span>@if ($stage)<x-eo.status-pill tone="live">{{ $stage }}</x-eo.status-pill>@else — @endif</span>
        <span class="font-bold tabular-nums" @if ($healthTone === 'risk') style="color: var(--color-eo-risk)" @elseif ($healthTone === 'warn') style="color: var(--color-eo-warn)" @elseif ($healthTone === 'ok') style="color: var(--color-eo-ok)" @endif>
            {{ $healthScore ?? '—' }}
        </span>
        <span class="text-eo-muted">{{ $budgetLabel ?? '—' }}</span>
        <div class="flex min-w-0 items-center gap-2">
            @if ($milestone)
                <span class="truncate text-eo-muted">{{ $milestone['title'] ?? '—' }}</span>
                @if ($urgencyLabel)<x-eo.status-pill :tone="$urgencyTone">{{ $urgencyLabel }}</x-eo.status-pill>@endif
            @else
                <span class="text-eo-muted">—</span>
            @endif
        </div>
    </{{ $tag }}>

@elseif ($variant === 'compact')
    {{-- ═══ COMPACT — radar rail, notification density ═══ --}}
    <{{ $tag }} @if ($resolvedHref) href="{{ $resolvedHref }}" @endif
        {{ $attributes->class([
            'eo-domain-card flex items-center gap-3 !p-3 transition',
            'hover:-translate-y-0.5 hover:shadow-eo-float' => (bool) $resolvedHref,
        ]) }}
    >
        @if (! is_null($healthScore))
            <div class="eo-mission-card-ring !h-9 !w-9 shrink-0" style="--eo-ring: {{ $ringHex }}; --eo-ring-pct: {{ $healthScore }}%">
                <span class="eo-mission-card-ring-value !text-[10px]">{{ $healthScore }}</span>
            </div>
        @endif
        <div class="min-w-0 flex-1">
            <p class="truncate text-[12.5px] font-semibold text-eo-text">{{ $name }}</p>
            <p class="truncate text-[10.5px] text-eo-muted">{{ $milestone['title'] ?? $where ?? '—' }}</p>
        </div>
        @if ($urgencyLabel)
            <x-eo.status-pill :tone="$urgencyTone" class="shrink-0">{{ $urgencyLabel }}</x-eo.status-pill>
        @elseif ($healthPillLabel)
            <x-eo.status-pill :tone="$healthTone" class="shrink-0">{{ $healthPillLabel }}</x-eo.status-pill>
        @endif
    </{{ $tag }}>

@elseif ($variant === 'board')
    {{-- ═══ BOARD — Mission Board grid item ═══
         With a selectAction, clicking the card selects it (a detail panel
         elsewhere on the page updates) rather than navigating away — only
         the card's own "Open Event" link leaves the page, so scanning the
         board never costs you the board. Without one, it behaves exactly
         like any other navigating card. --}}
    @php
        $clickable = (bool) $selectAction && isset($m['id']);
        $cardTag = $clickable ? 'div' : $tag;
    @endphp
    <{{ $cardTag }}
        @if ($clickable) wire:click="{{ $selectAction }}({{ $m['id'] }})" role="button" tabindex="0" @endif
        @if (! $clickable && $resolvedHref) href="{{ $resolvedHref }}" @endif
        {{ $attributes->class([
            'eo-domain-card eo-mission-card block transition',
            'cursor-pointer' => $clickable,
            'hover:-translate-y-0.5 hover:shadow-eo-float' => (bool) $resolvedHref || $clickable,
            '!shadow-eo-teal ring-2 ring-eo-teal/50' => $selected,
        ]) }}
    >
        <div class="mb-3 flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
                <h3 class="truncate text-[14.5px] font-bold text-eo-text">{{ $name }}</h3>
                @if ($client)<p class="mt-0.5 truncate text-[11px] text-eo-muted">{{ $client }}</p>@endif
            </div>
            <div class="flex shrink-0 flex-col items-end gap-1">
                @if ($stage)<x-eo.status-pill tone="live" class="!text-[9.5px]">{{ $stage }}</x-eo.status-pill>@endif
                @if ($daysOutLabel)<span class="text-[10px] font-bold tabular-nums text-eo-muted">{{ $daysOutLabel }}</span>@endif
            </div>
        </div>

        <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-eo-muted">
            <span class="flex items-center gap-1"><x-icon name="calendar" class="h-3 w-3 shrink-0" />{{ $dateLabel ?? 'Date TBC' }}</span>
            <span class="flex min-w-0 items-center gap-1 truncate"><x-icon name="pin" class="h-3 w-3 shrink-0" />{{ $where ?? 'Venue TBC' }}</span>
        </div>

        <div class="mb-3 grid grid-cols-2 gap-2">
            <div class="rounded-xl bg-eo-workspace px-2.5 py-2">
                <p class="eo-label !text-[9px]">Health</p>
                <p class="mt-0.5 flex items-center gap-1.5">
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background: {{ $ringHex }}"></span>
                    <b class="text-[13px] tabular-nums text-eo-text">{{ $healthScore ?? '—' }}</b>
                </p>
            </div>
            <div class="rounded-xl bg-eo-workspace px-2.5 py-2">
                <p class="eo-label !text-[9px]">Readiness</p>
                <p class="mt-0.5 text-[13px] font-bold tabular-nums text-eo-text">{{ $ready !== null ? $ready.'%' : '—' }}</p>
            </div>
        </div>

        @if ($budgetLabel)
            <div class="mb-3">
                <div class="flex items-baseline justify-between gap-2 text-[11px]">
                    <span class="eo-label !text-[9px]">Budget</span>
                    <span class="truncate text-eo-text"><b class="font-bold">{{ $budgetLabel }}</b> <span class="text-eo-muted">{{ $budgetOf }}</span></span>
                </div>
                @if (! is_null($budgetPct))
                    <div class="mt-1 h-1 overflow-hidden rounded-full bg-eo-bg">
                        <div class="h-full rounded-full bg-eo-teal" style="width: {{ $budgetPct }}%"></div>
                    </div>
                @endif
            </div>
        @endif

        @if ($ownerName)
            <div class="mb-3 flex items-center gap-2">
                <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-eo-navy text-[8.5px] font-bold text-white">{{ $ownerInitials }}</span>
                <span class="eo-label !text-[9px]">Owner</span>
                <span class="min-w-0 flex-1 truncate text-[11px] font-semibold text-eo-text">{{ $ownerName }}</span>
            </div>
        @endif

        @if ($milestone)
            <div class="flex items-center gap-2 rounded-xl bg-eo-workspace px-2.5 py-2">
                <span class="min-w-0 flex-1 truncate text-[11px] text-eo-text">{{ $milestone['title'] }}</span>
                @if ($urgencyLabel)<x-eo.status-pill :tone="$urgencyTone" class="shrink-0 !text-[9.5px]">{{ $urgencyLabel }}</x-eo.status-pill>@endif
            </div>
        @endif

        {{-- Explicit, not just implied by the card being clickable — a board
             is scanned fast, and "clearly actionable" means the affordance
             is visible, not merely clickable. Stops its own click from also
             firing the select() the card around it carries. --}}
        @if ($resolvedHref)
            <a href="{{ $resolvedHref }}" wire:navigate @if ($clickable) onclick="event.stopPropagation()" @endif
               class="mt-3 flex items-center gap-1 border-t border-eo-line pt-2.5 text-[11px] font-bold text-eo-teal-ink transition hover:text-eo-teal-deep">
                Open Event <x-icon name="chevron" class="h-3 w-3 -rotate-90" />
            </a>
        @endif
    </{{ $cardTag }}>

@elseif ($variant === 'hub')
    {{-- ═══ HUB — Event Hub header's vitals card (Phase E) ═══
         Identity (crest/title/type/stage) stays in event-header.blade.php's
         own top block — this card is deliberately just the six figures that
         used to be three separate cards plus a banner: Health, Readiness,
         Days Out, Live Desk, Owner, Next Action. --}}
    <div {{ $attributes->class(['eo-domain-card eo-mission-card']) }}>
        <div class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
            <div>
                <span class="eo-label">Health</span>
                <p class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-[22px] font-bold tabular-nums text-eo-text" @if ($healthTone === 'risk') style="color: var(--color-eo-risk)" @elseif ($healthTone === 'warn') style="color: var(--color-eo-warn)" @elseif ($healthTone === 'ok') style="color: var(--color-eo-ok)" @endif>
                        {{ $healthScore ?? '—' }}
                    </span>
                    @if ($healthPillLabel)<x-eo.status-pill :tone="$healthTone" class="!text-[9.5px]">{{ $healthPillLabel }}</x-eo.status-pill>@endif
                </p>
            </div>
            <div>
                <span class="eo-label">Readiness</span>
                <p class="mt-1 text-[22px] font-bold tabular-nums text-eo-text">{{ $ready !== null ? $ready.'%' : '—' }}</p>
            </div>
            <div>
                <span class="eo-label">Days out</span>
                <p class="mt-1 text-[22px] font-bold tabular-nums text-eo-text">{{ $daysOutLabel ?? '—' }}</p>
            </div>
            <div class="col-span-2 sm:col-span-1">
                <span class="eo-label">Live desk</span>
                <p class="mt-1 text-[13px] font-semibold text-eo-text">{{ $liveDesk ?? 'Nothing tracked yet' }}</p>
            </div>
            <div>
                <span class="eo-label">Owner</span>
                <p class="mt-1 text-[13px] font-semibold text-eo-text">{{ $ownerName ?? '—' }}</p>
            </div>
            <div class="col-span-2 sm:col-span-1">
                <span class="eo-label">Next action</span>
                @if ($milestone)
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-[13px] font-semibold text-eo-text">
                        <a href="{{ route('events.hub', [$m['event'], 'tab' => $milestone['tab'] ?? 'tasks']) }}" class="min-w-0 truncate hover:text-eo-teal-ink">{{ $milestone['title'] }}</a>
                        @if ($urgencyLabel)<x-eo.status-pill :tone="$urgencyTone" class="shrink-0 !text-[9.5px]">{{ $urgencyLabel }}</x-eo.status-pill>@endif
                    </p>
                    @if ($milestone['due'] ?? null)
                        <p class="mt-0.5 text-[11px] text-eo-muted">{{ $milestone['due'] }}</p>
                    @endif
                @else
                    <p class="mt-1 text-[13px] font-semibold text-eo-text">Nothing pressing</p>
                @endif
            </div>
        </div>
    </div>

@else
    {{-- ═══ HERO — spotlight card: Command Center, Portfolio nearest-mission ═══
         Also the default when a legacy caller passes flat props with no
         $mission and no $variant — this is what the old mission-card looked
         like, unchanged, plus the new footer row when the richer fields
         (client/budget/owner/next action) happen to be present. --}}
    <{{ $tag }} @if ($resolvedHref) href="{{ $resolvedHref }}" @endif
        {{ $attributes->class([
            'eo-domain-card eo-mission-card block transition hover:-translate-y-0.5 hover:shadow-eo-float',
            'focus:outline-none focus-visible:ring-2 focus-visible:ring-eo-teal/40' => (bool) $resolvedHref,
        ]) }}
    >
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    @if ($type)<span class="eo-journey-chip">{{ $type }}</span>@endif
                    @if ($stage)<x-eo.status-pill tone="live">{{ $stage }}</x-eo.status-pill>@endif
                    @if ($healthPillLabel && ! $stage)<x-eo.status-pill :tone="$healthTone">{{ $healthPillLabel }}</x-eo.status-pill>@endif
                    @isset($badge){{ $badge }}@endisset
                </div>

                <h3 class="text-[16px] font-semibold text-eo-text">{{ $name }}</h3>

                @if ($where || $dateLabel || $client)
                    <p class="mt-1.5 text-[13px] text-eo-muted">
                        {{ collect([$client, $where, $dateLabel])->filter()->implode(' · ') }}
                    </p>
                @endif
            </div>

            @if (! is_null($ready))
                <div class="eo-mission-card-ring shrink-0" style="--eo-ring: {{ $ringHex }}; --eo-ring-pct: {{ $ready }}%" title="{{ $ready }}% readiness">
                    <span class="eo-mission-card-ring-value">{{ $ready }}</span>
                </div>
            @endif
        </div>

        @if (! is_null($ready))
            <div class="mt-4">
                <div class="mb-1.5 flex items-center justify-between">
                    <span class="eo-label">Mission readiness</span>
                    <span class="text-[12px] font-bold tabular-nums text-eo-teal-ink">{{ $ready }}%</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-eo-bg">
                    <div class="h-full rounded-full bg-gradient-to-r from-eo-teal-deep to-eo-teal-lit" style="width: {{ $ready }}%"></div>
                </div>
            </div>
        @endif

        {{-- The rich footer only appears when the caller passed a $mission
             with these fields — legacy flat-prop callers never see it, so
             their two screens render byte-for-byte what they render today. --}}
        @if ($budgetLabel || $ownerName || $milestone || $daysOutLabel)
            <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 border-t border-eo-line pt-3.5">
                @if ($daysOutLabel)
                    <div><span class="eo-label">Days out</span><p class="text-[12.5px] font-bold text-eo-text">{{ $daysOutLabel }}</p></div>
                @endif
                @if ($budgetLabel)
                    <div><span class="eo-label">Budget</span><p class="text-[12.5px] font-bold text-eo-text">{{ $budgetLabel }} <span class="font-normal text-eo-muted">{{ $budgetOf }}</span></p></div>
                @endif
                @if ($ownerName)
                    <div><span class="eo-label">Owner</span><p class="text-[12.5px] font-bold text-eo-text">{{ $ownerName }}</p></div>
                @endif
                @if ($milestone)
                    <div class="min-w-[160px] flex-1">
                        <span class="eo-label">Next action</span>
                        <p class="flex items-center gap-2 text-[12.5px] font-bold text-eo-text">
                            <span class="min-w-0 truncate">{{ $milestone['title'] }}</span>
                            @if ($urgencyLabel)<x-eo.status-pill :tone="$urgencyTone" class="shrink-0 !text-[9.5px]">{{ $urgencyLabel }}</x-eo.status-pill>@endif
                        </p>
                    </div>
                @endif
            </div>
        @endif

        {{ $slot }}
    </{{ $tag }}>
@endif
